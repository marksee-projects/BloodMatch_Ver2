<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use PDOException;
use RuntimeException;
use Throwable;

final class WorkflowLockService
{
    /** Request -> user IDs ascending -> match -> report. No SMTP retries here. */
    public static function transaction(callable $work): mixed
    {
        $pdo = Database::pdo();
        if ($pdo->inTransaction()) {
            // Donor reconciliation already owns the transaction and its rollback.
            return $work();
        }
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw new RuntimeException('This action conflicted with another update. Please try again.', 409, $e);
            }
            throw $e;
        }
    }

    public static function request(int $requestId): array
    {
        $request = (new BloodRequestRepository())->findByIdForUpdate($requestId);
        if ($request === null) {
            throw new RuntimeException('Request not found.', 404);
        }
        return $request;
    }

    /** @return array<int, array> */
    public static function users(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $users = [];
        $repo = new UserRepository();
        foreach ($ids as $id) {
            $user = $repo->findByIdForUpdate($id);
            if ($user === null) {
                throw new RuntimeException('An account in this workflow is no longer available.', 403);
            }
            $users[$id] = $user;
        }
        return $users;
    }

    public static function assertActiveActor(array $actor): void
    {
        if ((string) $actor['account_status'] !== 'active') {
            throw new RuntimeException('This account is no longer active.', 403);
        }
    }

    public static function assertOpen(array $request): void
    {
        if ((string) $request['status'] !== 'OPEN') {
            throw new RuntimeException('This request is no longer active.', 409);
        }
    }

    public static function assertOpenBeforeDeadline(array $request, ?string $nowUtc = null): void
    {
        self::assertOpen($request);
        if (RequestService::hasDeadlinePassed($request, $nowUtc)) {
            throw new RuntimeException("This request's needed-by time has passed.", 409);
        }
    }

    public static function assertReportReviewer(array $actor, array $request, int $donorId): void
    {
        self::assertActiveActor($actor);
        if (!in_array((string) $actor['role'], ['officer', 'admin'], true)) {
            throw new RuntimeException('Forbidden.', 403);
        }
        if ((int) $actor['id'] === $donorId) {
            throw new RuntimeException('You cannot decide on your own donation report.', 403);
        }
        if ((string) $actor['role'] === 'officer'
            && ($actor['chapter_id'] === null || $request['request_chapter_id'] === null
                || (int) $actor['chapter_id'] !== (int) $request['request_chapter_id'])) {
            throw new RuntimeException('Forbidden.', 403);
        }
    }
}

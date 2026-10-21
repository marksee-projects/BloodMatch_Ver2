<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Config\Database;
use PDO;
use RuntimeException;

final class MatchService
{
    private const DONOR_RECONCILE_LIMIT = 100;

    public function generateForRequest(
        int $requestId,
        bool $bumpGeneration,
        string $trigger,
        ?int $actorId = null,
        ?int $onlyDonorId = null
    ): array
    {
        return WorkflowLockService::transaction(fn (): array => $this->generateLockedForRequest(
            $requestId, $bumpGeneration, $trigger, $actorId, $onlyDonorId
        ));
    }

    private function generateLockedForRequest(
        int $requestId,
        bool $bumpGeneration,
        string $trigger,
        ?int $actorId,
        ?int $onlyDonorId
    ): array {
        $repo = new BloodRequestRepository();
        $request = $repo->findByIdForUpdate($requestId);

        if ($request === null) {
            throw new RuntimeException('Request not found.', 404);
        }
        if ((string) $request['status'] !== 'OPEN') {
            throw new RuntimeException("Matching runs only against OPEN requests (current: {$request['status']}).", 409);
        }
        WorkflowLockService::assertOpenBeforeDeadline($request);

        $compatibleTypes = BloodCompatibilityService::getCompatibleDonorTypes(
            (string) $request['required_blood_type']
        );

        $nowUtc = AuthService::nowUtc();

        $pdo = Database::pdo();
        $placeholders = implode(',', array_fill(0, count($compatibleTypes), '?'));

        // Persisted availability 'standby' may pass ONLY for genuine post-donation
        // donors (lvd set) whose windows have expired — the read-model path.
        // A stored 'standby' without donation history is never matchable.
        $donorScopeSql = $onlyDonorId !== null ? ' AND id = ?' : '';
        $stmt = $pdo->prepare(
            "SELECT id, full_name, role, chapter_id, account_status, verification_status,
                    email_verified_at, donor_enrolled_at, donor_availability,
                    last_verified_donation_at, blood_type, latitude, longitude, date_of_birth,
                    EXISTS (SELECT 1 FROM member_documents consent
                            WHERE consent.user_id = u.id AND consent.doc_type = 'parental_consent') AS has_parental_consent
             FROM users u
             WHERE blood_type IN ($placeholders){$donorScopeSql}"
        );
        $poolParams = $compatibleTypes;
        if ($onlyDonorId !== null) {
            $poolParams[] = $onlyDonorId;
        }
        $stmt->execute($poolParams);
        $pool = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reqChapter = $request['request_chapter_id'] !== null ? (int) $request['request_chapter_id'] : null;

        $scored = [];
        $holdOnlyDonors = [];
        foreach ($pool as $donor) {
            $donor['pending_report_hold']=\BloodMatch\Repositories\DonationWorkflowRepository::hasHold((int)$donor['id']);
            $eligibility = DonorEligibilityService::evaluateForRequest($donor, $request, $nowUtc);
            if (($eligibility['code']??null)==='pending_report_hold' && DonorEligibilityService::evaluateForRequest($donor,$request,$nowUtc,null,true)['eligible']) {
                $holdOnlyDonors[(int)$donor['id']]=true;
            }
            if (!$eligibility['eligible']) {
                continue;
            }

            $distance = Geo::distanceKm(
                $request['latitude'],
                $request['longitude'],
                $donor['latitude'],
                $donor['longitude']
            );

            $sameChapter = $reqChapter !== null
                && $donor['chapter_id'] !== null
                && (int) $donor['chapter_id'] === $reqChapter;

            $scored[(int) $donor['id']] = [
                'row' => $donor,
                'distance' => $distance,
                'same_chapter' => $sameChapter,
                // Retained for storage compatibility; ordering uses distance directly.
                'rank_score' => 0,
            ];
        }
        uasort($scored, [MatchProximity::class, 'compare']);

        $maxGenStmt = $pdo->prepare('SELECT COALESCE(MAX(generation), 0) FROM matches WHERE request_id = ?');
        $maxGenStmt->execute([$requestId]);
        $currentGen = (int) $maxGenStmt->fetchColumn();

        $generation = $bumpGeneration ? $currentGen + 1 : max(1, $currentGen);

        $existingSql = 'SELECT id, donor_id, status, generation FROM matches WHERE request_id = ?';
        $existingParams = [$requestId];
        if ($onlyDonorId !== null) {
            $existingSql .= ' AND donor_id = ?';
            $existingParams[] = $onlyDonorId;
        }
        $existingStmt = $pdo->prepare($existingSql);
        $existingStmt->execute($existingParams);
        $existingByDonor = [];
        foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $existingByDonor[(int) $m['donor_id']] = $m;
        }

        $inserted = 0;
        $updated = 0;
        $notificationCandidateIds = [];

        foreach ($scored as $donorId => $entry) {
            if (isset($existingByDonor[$donorId])) {
                $existing = $existingByDonor[$donorId];
                $status = (string) $existing['status'];
                if ($status === 'COMPLETED') { continue; }
                if ($onlyDonorId !== null && in_array($status, ['CLOSED', 'RESPONDED', 'COMPLETED'], true)) {
                    continue;
                }
                if ($onlyDonorId !== null) {
                    $upd = $pdo->prepare(
                        'UPDATE matches SET distance_km = ?, rank_score = ? WHERE id = ?'
                    );
                    $upd->execute([$entry['distance'], $entry['rank_score'], $existing['id']]);
                } else {
                    if ($status === 'CLOSED') {
                        $status = 'POTENTIAL';
                    }
                    $upd = $pdo->prepare(
                        'UPDATE matches SET generation = ?, distance_km = ?, rank_score = ?, status = ? WHERE id = ?'
                    );
                    $upd->execute([$generation, $entry['distance'], $entry['rank_score'], $status, $existing['id']]);
                }
                $updated++;
                if ($onlyDonorId === null && in_array($status, ['POTENTIAL','NOTIFIED'], true)) {
                    $notificationCandidateIds[] = $donorId;
                }
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO matches (request_id, donor_id, generation, status, distance_km, rank_score)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $requestId,
                    $donorId,
                    $generation,
                    'POTENTIAL',
                    $entry['distance'],
                    $entry['rank_score'],
                ]);
                $inserted++;
                $notificationCandidateIds[] = $donorId;
            }
        }

        $closed = 0;
        foreach ($existingByDonor as $donorId => $existing) {
            $status = (string) $existing['status'];
            if ($status==='RESPONDED' && isset($holdOnlyDonors[$donorId])) continue;
            if (!isset($scored[$donorId]) && $status==='RESPONDED' && $onlyDonorId===null && $trigger==='material_change') {
                $pending=(new \BloodMatch\Repositories\DonationReportRepository())->pendingExistsForMatchForUpdate((int)$existing['id']);
                if (!$pending) {
                    $pdo->prepare("UPDATE matches SET status='CLOSED' WHERE id=? AND status='RESPONDED'")->execute([$existing['id']]);
                    $closed++;
                }
                foreach (array_unique([$donorId,(int)$request['requester_id']]) as $recipient) {
                    NotificationService::notify((int)$recipient,'match.invalidated','Donor offer needs review',
                        NotificationService::displayName((int)$donorId) . "'s offer for " . NotificationService::requestDescription((int)$request['requester_id']) . " no longer meets the current compatibility or eligibility requirements." . ($pending?' Its pending report requires explicit review.':' The offer has been closed.'),[
                            'related_type'=>'blood_request','related_id'=>$requestId,
                            'dedup_key'=>"offer-invalid:{$existing['id']}:{$existing['generation']}:{$recipient}",
                        ]);
                }
                continue;
            }
            if (!isset($scored[$donorId]) && in_array($status, ['POTENTIAL', 'NOTIFIED'], true)) {
                $cls = $pdo->prepare("UPDATE matches SET status = 'CLOSED' WHERE id = ?");
                $cls->execute([$existing['id']]);
                $closed++;
                $pdo->prepare("UPDATE email_outbox e JOIN notifications n ON n.id=e.notification_id SET e.status='cancelled',e.cancel_reason='donor_ineligible',e.lease_token=NULL,e.lease_expires_at=NULL,e.updated_at=UTC_TIMESTAMP(6)
                    WHERE n.user_id=? AND n.related_type='blood_request' AND n.related_id=? AND e.status IN ('pending','leased')")->execute([$donorId,$requestId]);
            }
        }

        $notifiedDonorIds = NotificationService::notifyMatchGeneration(
            $request,
            $generation,
            $notificationCandidateIds,
            $onlyDonorId === null && $trigger === 'request_created'
        );

        if ($notifiedDonorIds !== []) {
            foreach (array_chunk($notifiedDonorIds,500) as $notificationBatch) {
            $ph = implode(',', array_fill(0, count($notificationBatch), '?'));
            $pdo->prepare(
                "UPDATE matches SET status = 'NOTIFIED'
                 WHERE request_id = ? AND donor_id IN ($ph) AND status = 'POTENTIAL'"
            )->execute(array_merge([$requestId], $notificationBatch));
            }
        }

        AuditLogger::log(
            $actorId,
            $trigger === 'manual_rematch' ? 'match.manual_rematch' : 'match.generation',
            'blood_request',
            (string) $requestId,
            [
                'trigger' => $trigger,
                'generation' => $generation,
                'bumped' => $bumpGeneration,
                'donor_scope' => $onlyDonorId,
                'pool_size' => count($scored),
                'inserted' => $inserted,
                'updated' => $updated,
                'closed' => $closed,
            ]
        );

        return [
            'generation' => $generation,
            'pool_size' => count($scored),
            'inserted' => $inserted,
            'updated' => $updated,
            'closed' => $closed,
            'notified' => count($notifiedDonorIds),
        ];
    }

    /**
     * Reconcile one donor across current OPEN requests in bounded batches of 100.
     * Each request is isolated in its own transaction. Generation is never
     * bumped, notification email is disabled, and only this donor's unanswered
     * matches may change. CLOSED, RESPONDED and COMPLETED rows are preserved.
     *
     * @return int[] request IDs that were refreshed
     */
    public function refreshMatchesForDonor(
        int $donorId,
        string $trigger,
        ?int $actorId = null
    ): array
    {
        $pdo = Database::pdo();
        $nowUtc = AuthService::nowUtc();
        $bound=$pdo->prepare("SELECT COALESCE(MAX(id),0) FROM blood_requests WHERE status='OPEN' AND needed_datetime>=?");
        $bound->execute([$nowUtc]);$lastId=(int)$bound->fetchColumn();$cursor=0;$refreshed=[];
        do {
            $stmt=$pdo->prepare("SELECT id FROM blood_requests WHERE status='OPEN' AND needed_datetime>=? AND id>? AND id<=? ORDER BY id LIMIT " . self::DONOR_RECONCILE_LIMIT);
            $stmt->execute([$nowUtc,$cursor,$lastId]);$requestIds=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
            foreach ($requestIds as $requestId) {
                $cursor=$requestId;
                try {
                    $pdo->beginTransaction();
                    $this->generateForRequest($requestId,false,$trigger,$actorId??$donorId,$donorId);
                    $pdo->commit();$refreshed[]=$requestId;
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    error_log('[matches] donor reconciliation failed; request can be retried');
                }
            }
        } while ($requestIds!==[]);

        return $refreshed;
    }

    public function privacySafeMatches(int $requestId, ?int $onlyDonorId = null, ?array $viewer = null): array
    {
        $sql = 'SELECT m.id AS match_id, m.donor_id, m.generation, m.status, m.distance_km, m.rank_score,
                    br.requester_id, br.status AS request_status, u.full_name, u.chapter_id, c.name AS chapter_name,
                    u.verification_status, u.account_status, u.role AS donor_role, u.donor_availability, u.profile_picture,
                    cr.status AS chat_status, cr.sender_id AS chat_sender_id, cr.created_at AS chat_created_at,
                    cr.updated_at AS chat_updated_at, cr.conversation_id AS chat_conversation_id,
                    EXISTS(SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id=cr.conversation_id AND cp.user_id=br.requester_id) AS chat_owner_participant,
                    EXISTS(SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id=cr.conversation_id AND cp.user_id=m.donor_id) AS chat_donor_participant
             FROM matches m
             JOIN blood_requests br ON br.id = m.request_id
             JOIN users u ON u.id = m.donor_id
             LEFT JOIN chapters c ON c.id = u.chapter_id
             LEFT JOIN chat_requests cr ON cr.user_low=LEAST(br.requester_id,m.donor_id) AND cr.user_high=GREATEST(br.requester_id,m.donor_id)
             WHERE m.request_id = ? AND m.status <> ?';
        $params = [$requestId, 'CLOSED'];
        if ($onlyDonorId !== null) {
            $sql .= ' AND m.donor_id = ?';
            $params[] = $onlyDonorId;
        }
        $sql .= ' ORDER BY ' . MatchProximity::orderSql('br.request_chapter_id');

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row) use ($viewer): array {
            $canViewProfile = $viewer !== null && (
                (int) $viewer['id'] === (int) $row['donor_id']
                || (string) $viewer['role'] === 'admin'
                || (string) $viewer['role'] === 'member'
                || ((int) $viewer['id'] === (int) $row['requester_id']
                    && (string) $row['request_status'] === 'OPEN'
                    && in_array((string) $row['status'], ['RESPONDED', 'COMPLETED'], true))
            );

            $chat = null;
            // Consent metadata is scoped to the actual request owner's own pair.
            if ($viewer !== null && (int)$viewer['id'] === (int)$row['requester_id']) {
                $peer=['id'=>$row['donor_id'],'role'=>$row['donor_role'],'account_status'=>$row['account_status'],'verification_status'=>$row['verification_status']];
                $chat=ChatConsentService::pairPresentation($viewer,$peer,[
                    'status'=>$row['chat_status'],'sender_id'=>$row['chat_sender_id'],'created_at'=>$row['chat_created_at'],
                    'updated_at'=>$row['chat_updated_at'],'conversation_id'=>$row['chat_owner_participant'] && $row['chat_donor_participant'] ? $row['chat_conversation_id'] : null,
                ]);
            }
            return [
                'match_id' => (int) $row['match_id'],
                'donor_reference' => 'donor-' . (int) $row['donor_id'],
                'is_current_user' => $viewer !== null && (int) $viewer['id'] === (int) $row['donor_id'],
                'display_name' => (string) $row['full_name'],
                'chapter_id' => $row['chapter_id'] !== null ? (int) $row['chapter_id'] : null,
                'chapter_name' => $row['chapter_name'] !== null ? (string) $row['chapter_name'] : null,
                'verification_status' => (string) $row['verification_status'],
                'availability' => $row['donor_availability'] !== null ? (string) $row['donor_availability'] : null,
                'approximate_distance_km' => $row['distance_km'] !== null ? round((float) $row['distance_km'], 1) : null,
                'generation' => (int) $row['generation'],
                'status' => (string) $row['status'],
                'profile_user_id' => $canViewProfile ? (int) $row['donor_id'] : null,
                'profile_picture_url' => $canViewProfile ? ProfilePictureStorageService::urlFor($row['profile_picture'],(int)$row['donor_id']) : null,
                'chat' => $chat,
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

<?php

declare(strict_types=1);

namespace BloodMatch\Repositories;

use BloodMatch\Config\Database;
use PDO;

final class BloodRequestRepository
{
    private const SAFE_COLUMNS =
        'br.id, br.requester_id, br.request_chapter_id, br.required_blood_type, br.quantity_units,
         br.facility_name, br.hospital_id, br.location_id, br.latitude, br.longitude, br.urgency, br.needed_datetime,
         br.status, br.review_status, br.created_at, br.updated_at, br.expired_at,
         loc.psgc_code AS loc_psgc, loc.name AS loc_name, loc.level AS loc_level,
         loc.municipality_code AS loc_municipality_code, loc.municipality_name AS loc_municipality_name';

    private const LOCATION_JOIN = ' LEFT JOIN bataan_locations loc ON loc.id = br.location_id';

    public function create(array $r): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO blood_requests
                (requester_id, request_chapter_id, required_blood_type, quantity_units,
                 facility_name, hospital_id, location_id, latitude, longitude, urgency, needed_datetime, status, review_status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $r['requester_id'],
            $r['request_chapter_id'],
            $r['required_blood_type'],
            $r['quantity_units'],
            $r['facility_name'],
            $r['hospital_id'] ?? null,
            $r['location_id'],
            $r['latitude'],
            $r['longitude'],
            $r['urgency'],
            $r['needed_datetime'],
            'OPEN',
            $r['review_status'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT ' . self::SAFE_COLUMNS . ' FROM blood_requests br' . self::LOCATION_JOIN . ' WHERE br.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listByRequester(int $requesterId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ',
                    (SELECT COUNT(*) FROM matches mx WHERE mx.request_id = br.id AND mx.status <> \'CLOSED\') AS match_count,
                    (SELECT COUNT(*) FROM matches rx WHERE rx.request_id = br.id AND rx.status IN (\'RESPONDED\', \'COMPLETED\')) AS response_count
             FROM blood_requests br' . self::LOCATION_JOIN . '
             WHERE br.requester_id = ? ORDER BY br.id DESC LIMIT 100'
        );
        $stmt->execute([$requesterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listCompatibleOpenForDonor(int $donorId, array $recipientTypes, int $limit = 50): array
    {
        if ($recipientTypes === []) {
            return [];
        }

        $safeLimit = max(1, min($limit, 100));
        $typePlaceholders = implode(',', array_fill(0, count($recipientTypes), '?'));
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ',
                    m.id AS match_id, m.status AS match_status,
                    requester.full_name AS requester_name,
                    requester.verification_status AS requester_verification_status,
                    requester.profile_picture AS requester_profile_picture,
                    requester_chapter.name AS requester_chapter_name
             FROM blood_requests br
             JOIN users requester ON requester.id = br.requester_id
             LEFT JOIN chapters requester_chapter ON requester_chapter.id = requester.chapter_id
             LEFT JOIN matches m ON m.request_id = br.id AND m.donor_id = ? AND m.status <> \'CLOSED\'' .
             self::LOCATION_JOIN . "
             WHERE br.requester_id <> ?
               AND br.status = 'OPEN'
               AND br.needed_datetime > UTC_TIMESTAMP()
               AND br.required_blood_type IN ($typePlaceholders)
               AND requester.role = 'member'
               AND requester.account_status = 'active'
             ORDER BY FIELD(br.urgency, 'critical', 'urgent', 'routine'), br.needed_datetime ASC, br.created_at DESC
             LIMIT {$safeLimit}"
        );
        $stmt->execute(array_merge([$donorId, $donorId], $recipientTypes));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRequestContext(int $requestId, int $viewerId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ',
                    m.id AS match_id, m.status AS match_status,
                    requester.full_name AS requester_name,
                    requester.verification_status AS requester_verification_status,
                    requester.profile_picture AS requester_profile_picture,
                    requester_chapter.name AS requester_chapter_name
             FROM blood_requests br
             JOIN users requester ON requester.id = br.requester_id
             LEFT JOIN chapters requester_chapter ON requester_chapter.id = requester.chapter_id
             LEFT JOIN matches m ON m.request_id = br.id AND m.donor_id = ? AND m.status <> \'CLOSED\'' .
             self::LOCATION_JOIN . '
             WHERE br.id = ? LIMIT 1'
        );
        $stmt->execute([$viewerId, $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listMatchedOpenForDonor(int $donorId, int $limit = 20): array
    {
        $safeLimit = max(1, min($limit, 100));
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::SAFE_COLUMNS . ',
                    m.status AS match_status,
                    requester.full_name AS requester_name,
                    requester.verification_status AS requester_verification_status,
                    requester.profile_picture AS requester_profile_picture,
                    requester_chapter.name AS requester_chapter_name
             FROM matches m
             JOIN blood_requests br ON br.id = m.request_id
             JOIN users requester ON requester.id = br.requester_id
             LEFT JOIN chapters requester_chapter ON requester_chapter.id = requester.chapter_id' .
             self::LOCATION_JOIN . "
             WHERE m.donor_id = ?
               AND br.requester_id <> ?
               AND br.status = 'OPEN'
               AND requester.role = 'member'
               AND requester.account_status = 'active'
               AND m.status IN ('POTENTIAL', 'NOTIFIED', 'RESPONDED', 'COMPLETED')
             ORDER BY FIELD(br.urgency, 'critical', 'urgent', 'routine'), br.created_at DESC
             LIMIT {$safeLimit}"
        );
        $stmt->execute([$donorId, $donorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateFields(int $id, array $fields): void
    {
        static $map = [
            'required_blood_type' => 'required_blood_type',
            'quantity_units' => 'quantity_units',
            'facility_name' => 'facility_name',
            'hospital_id' => 'hospital_id',
            'location_id' => 'location_id',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'urgency' => 'urgency',
            'needed_datetime' => 'needed_datetime',
        ];

        $sets = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (!isset($map[$key])) {
                continue;
            }
            $sets[] = $map[$key] . ' = ?';
            $params[] = $value;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;

        $stmt = Database::pdo()->prepare('UPDATE blood_requests SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function setStatus(int $id, string $status, ?string $expiredAtUtc = null): void
    {
        $allowed = ['OPEN', 'FULFILLED', 'CANCELLED', 'EXPIRED'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid request status.');
        }
        $extra = $status === 'EXPIRED' ? ', expired_at = COALESCE(expired_at, ?)' : '';
        $params = [$status];
        if ($status === 'EXPIRED') {
            $params[] = $expiredAtUtc ?? \BloodMatch\Services\AuthService::nowUtc();
        }
        $params[] = $id;

        $stmt = Database::pdo()->prepare("UPDATE blood_requests SET status = ?{$extra} WHERE id = ?");
        $stmt->execute($params);
    }

    public function expireDueBatch(string $nowUtc, int $limit = 500): array
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE blood_requests
             SET status = 'EXPIRED', expired_at = ?
             WHERE status = 'OPEN' AND needed_datetime < ?
             LIMIT " . (int) $limit
        );
        $stmt->execute([$nowUtc, $nowUtc]);

        if ($stmt->rowCount() === 0) {
            return [];
        }

        $idStmt = Database::pdo()->prepare(
            'SELECT id, requester_id FROM blood_requests WHERE status = ? AND expired_at = ?'
        );
        $idStmt->execute(['EXPIRED', $nowUtc]);
        return $idStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

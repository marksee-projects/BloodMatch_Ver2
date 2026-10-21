<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Services\Exceptions\ValidationException;
use PDO;

/** Directory addresses use canonical reference rows; never infer an address from a name. */
final class HospitalLocationService
{
    public static function directory(): array
    {
        return array_map([self::class, 'present'], Database::pdo()->query(
            'SELECT id, name, type, municipality_name, location_id FROM hospitals ORDER BY type DESC, name ASC'
        )->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function resolve(mixed $id, ?string $name): ?array
    {
        if ($id !== null && $id !== '') {
            if (!is_int($id) && !(is_string($id) && preg_match('/^[1-9]\d*$/', $id))) {
                throw new ValidationException(['hospital_id' => ['Selected hospital is invalid.']]);
            }
            $stmt = Database::pdo()->prepare('SELECT * FROM hospitals WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new ValidationException(['hospital_id' => ['Selected hospital is unavailable.']]);
            }
            return self::present($row);
        }
        // A caller cannot bypass the directory address by omitting hospital_id.
        if (trim((string) $name) === '') { return null; }
        $stmt = Database::pdo()->prepare('SELECT * FROM hospitals WHERE LOWER(TRIM(name)) = LOWER(?) ORDER BY id LIMIT 1');
        $stmt->execute([trim($name)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::present($row) : null;
    }

    private static function present(array $row): array
    {
        $location = $row['location_id'] !== null ? LocationService::findById((int) $row['location_id']) : null;
        // No verification flag exists. Require a coherent active reference and parent.
        $valid = $location !== null && $location['is_active']
            && $location['latitude'] !== null && $location['longitude'] !== null
            && LocationService::municipalityExists($location['municipality_code'])
            && strcasecmp(trim((string) $row['municipality_name']), $location['municipality_name']) === 0;
        return [
            'id' => (int) $row['id'], 'name' => (string) $row['name'], 'type' => (string) $row['type'],
            'municipality_name' => (string) $row['municipality_name'],
            'location_id' => $row['location_id'] !== null ? (int) $row['location_id'] : null,
            'address_complete' => $valid && $location['level'] === 'barangay',
            'location' => $valid ? array_intersect_key($location, array_flip([
                'location_id', 'municipality_code', 'municipality_name', 'barangay_code', 'barangay_name',
            ])) : null,
        ];
    }
}

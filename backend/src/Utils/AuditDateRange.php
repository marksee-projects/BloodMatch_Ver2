<?php
declare(strict_types=1);
namespace BloodMatch\Utils;

final class AuditDateRange
{
    /** Date-only end bounds include the entire UTC day, including fractional seconds. */
    public static function bound(string $value, bool $end): array
    {
        $value=trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { return [$end?' <= ?':' >= ?', $value]; }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d')!==$value) {
            throw new \BloodMatch\Services\Exceptions\ValidationException([$end?'date_to':'date_from'=>['Choose a valid date.']]);
        }
        return $end ? [' < ?', $date->modify('+1 day')->format('Y-m-d H:i:s')]
            : [' >= ?', $date->format('Y-m-d H:i:s')];
    }
}

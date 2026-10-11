<?php
declare(strict_types=1);
namespace BloodMatch\Utils;

use BloodMatch\Services\Exceptions\ValidationException;

final class ConfirmedDonationSummary
{
    public static function range(?string $from, ?string $to): array
    {
        $from = $from ?? gmdate('Y-m-d', strtotime('-29 days'));
        $to = $to ?? gmdate('Y-m-d');
        foreach (['date_from'=>$from, 'date_to'=>$to] as $field=>$value) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                throw new ValidationException([$field=>['Choose a valid UTC date (YYYY-MM-DD).']]);
            }
        }
        [, $start] = AuditDateRange::bound($from, false);
        [, $end] = AuditDateRange::bound($to, true);
        if ($from > $to) throw new ValidationException(['date_to'=>['End date must not precede start date.']]);
        return ['date_from'=>$from, 'date_to'=>$to, 'start'=>$start, 'end_exclusive'=>$end];
    }

    public static function csv(array $data): string
    {
        $stream = fopen('php://temp','w+');
        $write = static function (array $row) use ($stream): void {
            fputcsv($stream, array_map([AggregateCsv::class,'cell'],$row), ',', '"', '');
        };
        $write(['confirmation_date_utc','confirmed_reports','date_from_utc','date_to_utc','request_chapter_scope']);
        $metadata = [$data['date_from'], $data['date_to'], $data['chapter_id'] ?? 'all'];
        $write(array_merge(['TOTAL', (int)$data['total_confirmed_reports']], $metadata));
        foreach ($data['daily_confirmations'] as $row) {
            $write(array_merge([$row['confirmation_date_utc'], (int)$row['confirmed_reports']], $metadata));
        }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $csv;
    }
}

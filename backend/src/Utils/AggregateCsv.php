<?php
declare(strict_types=1);
namespace BloodMatch\Utils;
final class AggregateCsv
{
    public static function cell(mixed $value): string
    {
        $text=is_scalar($value)?(string)$value:'';
        if (preg_match('/^[\x00-\x20]*[=+@-]/',$text) || preg_match('/^[\t\r\n]/',$text)) { $text="'".$text; }
        return $text;
    }
    public static function render(array $summary): string
    {
        $stream=fopen('php://temp','w+');
        $write=static function(array $cells) use($stream): void { fputcsv($stream,array_map([self::class,'cell'],$cells),',','"',''); };
        $write(['section','metric','value','date_from','date_to','chapter_id']);
        $metadata=[$summary['date_from']??'',$summary['date_to']??'',$summary['chapter_id']??'all'];
        $sections=[
            'request_volume'=>['total_requests','open_requests','fulfilled_requests','cancelled_requests','expired_requests','resolved_requests','total_units_requested','fulfillment_rate_percent','cancellation_rate_percent','expiration_rate_percent'],
            'verification_activity'=>['approved','rejected'],
            'donation_engagement'=>['pending_reports','confirmed_donations','rejected_reports','completed_matches'],
            'demand_by_blood_type'=>['A+','A-','B+','B-','AB+','AB-','O+','O-'],
            'demand_by_urgency'=>['routine','urgent','emergency'],
            'donor_pool'=>['total_enrolled','available','standby','cooldown','unavailable'],
        ];
        foreach ($sections as $section=>$metrics) {
            foreach ($metrics as $metric) {
                $value=$summary[$section][$metric]??null;
                if (is_numeric($value)) { $write(array_merge([$section,$metric,$value],$metadata)); }
            }
        }
        foreach ($summary['daily_request_trend']??[] as $row) {
            foreach (['requests_created','fulfilled'] as $metric) {
                if (is_numeric($row[$metric]??null)) { $write(array_merge(['daily_request_trend',($row['date']??'').' '.$metric,$row[$metric]],$metadata)); }
            }
        }
        foreach ($sections['demand_by_blood_type'] as $bloodType) {
            $value=$summary['donor_pool']['by_blood_type'][$bloodType]??null;
            if (is_numeric($value)) { $write(array_merge(['donor_pool_by_blood_type',$bloodType,$value],$metadata)); }
        }
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
    }
}

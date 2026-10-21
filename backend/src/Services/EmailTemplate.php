<?php
declare(strict_types=1);
namespace BloodMatch\Services;

/** Shared, link-free email presentation. No queue, identity or delivery side effects. */
final class EmailTemplate
{
    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function layout(string $heading, string $content, string $text): array
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#eef0f5;color:#172033;font-family:Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #dce0e8;border-radius:12px">'
            . '<tr><td style="padding:24px;border-bottom:1px solid #dce0e8;color:#020066;font-size:20px;font-weight:bold">BloodMatch</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.6;overflow-wrap:anywhere">'
            . '<h1 style="margin:0 0 20px;font-size:24px;line-height:1.3">'.self::escape($heading).'</h1>'.$content
            . '</td></tr><tr><td style="padding:16px 24px;border-top:1px solid #dce0e8;color:#58657a;font-size:12px;line-height:1.5">BloodMatch automated notification</td></tr></table>'
            . '</td></tr></table></body></html>';
        return ['html'=>$html, 'text'=>"BloodMatch\n\n$heading\n\n$text\n\nBloodMatch automated notification"];
    }

    public static function otp(string $code, int $ttlSeconds, bool $resend = false): array
    {
        $minutes = (int)ceil($ttlSeconds / 60);
        $intro = $resend ? 'Use this new code to verify your email address. Your previous code has been replaced.' : 'Enter this code in BloodMatch to verify your email address.';
        $note = "This code expires in $minutes minutes. If you did not request it, ignore this email.";
        return self::layout('Verify your email address', '<p style="margin:0 0 20px">'.self::escape($intro).'</p>'
            . '<div style="padding:20px;background:#f1f3f8;border-radius:8px;text-align:center;font-size:32px;font-weight:bold;letter-spacing:6px;color:#020066">'.self::escape($code).'</div>'
            . '<p style="margin:20px 0 0;color:#58657a">'.self::escape($note).'</p>', "$intro\n\n$code\n\n$note");
    }

    public static function notifications(array $rows): array
    {
        if ($rows === []) throw new \InvalidArgumentException('Email content is required.');
        if ($rows[0]['type'] === 'verification.decision') {
            $rejected = str_contains(strtolower($rows[0]['title']), 'rejected');
            $heading = $rejected ? 'Your verification needs correction' : 'Your account is now verified';
            $body = $rejected ? (string)$rows[0]['body'] : 'An administrator has reviewed and verified your submitted documents. Your BloodMatch account is now verified.';
            $next = $rejected ? 'Open BloodMatch, go to Profile > Verification, and upload a clear, valid National ID for another review.' : 'You can now enroll as a donor and access features available to verified users. Open BloodMatch to complete your donor enrollment.';
            return self::layout($heading, '<p style="margin:0 0 20px">'.nl2br(self::escape($body)).'</p><p style="margin:0">'.self::escape($next).'</p>', "$body\n\n$next");
        }
        $count = count($rows);
        $heading = $count === 1 ? '1 emergency request matches you' : "$count emergency requests match you";
        $html = ''; $text = [];
        foreach ($rows as $i=>$row) {
            $title = 'Emergency request '.($i+1);
            $facts = ['Requester'=>trim((string)($row['requester_name'] ?? '')) ?: 'Name not available', 'Blood type'=>$row['required_blood_type'] ?? 'Not provided', 'Hospital'=>$row['facility_name'] ?? 'Not provided', 'Municipality'=>$row['municipality_name'] ?? 'Not provided'];
            $deadline = $row['needed_datetime'] ?? null;
            $facts['Needed by'] = $deadline ? (new \DateTimeImmutable($deadline, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Manila'))->format('M j, Y, g:i A').' PHT (UTC+08:00)' : 'Not provided';
            $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px;border:1px solid #dce0e8;border-radius:8px"><tr><td style="padding:16px">'
                . '<h2 style="margin:0 0 12px;font-size:18px">'.self::escape($title).'</h2>';
            $lines = [$title];
            foreach ($facts as $label=>$value) {
                $value = $value === '' ? 'Not provided' : $value;
                $html .= '<p style="margin:0 0 10px"><span style="color:#58657a;font-size:13px">'.self::escape($label).'</span><br><strong>'.self::escape($value).'</strong></p>';
                $lines[] = "$label: $value";
            }
            // Retain the approved notification text, including explicit test labels.
            $html .= '<p style="margin:12px 0 0;color:#58657a">'.nl2br(self::escape($row['body'])).'</p></td></tr></table>';
            $lines[] = $row['body']; $text[] = implode("\n", $lines);
        }
        $next = 'Open BloodMatch to check the current request status and offer to donate. The receiving facility determines medical eligibility.';
        return self::layout($heading, $html.'<p style="margin:20px 0 0">'.self::escape($next).'</p>', implode("\n\n",$text)."\n\n$next");
    }
}

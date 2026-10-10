<?php
declare(strict_types=1);
namespace BloodMatch\Services;
/** Captures protocol stage without retaining or logging SMTP replies/addresses. */
final class SmtpOutcomeTracker extends \PHPMailer\PHPMailer\SMTP
{
    private bool $bodyStarted=false;
    private bool $accepted=false;
    private ?int $endCode=null;
    protected function sendCommand($command,$commandstring,$expect)
    {
        $ok=parent::sendCommand($command,$commandstring,$expect);
        if ($command==='DATA' && $ok) { $this->bodyStarted=true; }
        if ($command==='DATA END') {
            if ($ok) { $this->accepted=true; }
            else { $code=(int)($this->getError()['smtp_code'] ?? 0);$this->endCode=$code?:null; }
        }
        return $ok;
    }
    public static function classify(bool $bodyStarted,bool $accepted,?int $endCode): string
    {
        if ($accepted) { return 'accepted'; }
        if (!$bodyStarted || ($endCode!==null && $endCode>=400 && $endCode<600)) { return 'rejected'; }
        return 'uncertain';
    }
    public function outcome(): string { return self::classify($this->bodyStarted,$this->accepted,$this->endCode); }
}

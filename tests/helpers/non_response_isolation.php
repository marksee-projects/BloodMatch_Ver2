<?php
declare(strict_types=1);

// Test-only bootstrap, shared by parent and race workers. No production storage or SMTP.
foreach (['BLOODMATCH_TEST_ISOLATION'=>'1','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306',
    'DB_NAME'=>'bloodmatch_test','MAIL_HOST'=>'mock','MAIL_PORT'=>'1','MAIL_USER'=>'','MAIL_PASS'=>'',
    'EMAIL_WORKER_ENABLED'=>'0','MEMBERSHIP_EMAIL_ENABLED'=>'0','SAFETY_AUTOMATION_ENABLED'=>'0',
    'DONATION_FOLLOWUP_ENABLED'=>'0','APP_SECURE_COOKIES'=>'false','DOCUMENT_ENCRYPTION_MODE'=>'legacy'] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key]=$value;
}
final class NonResponseTestConfig
{
    public static function basePath(): string { return (string)getenv('BM_NON_RESPONSE_STORAGE'); }
    public static function logPath(): string { return self::basePath().'/app.log'; }
    public static function env(): string { return 'test'; }
    public static function debug(): bool { return false; }
    public static function secureCookies(): bool { return false; }
    public static function corsOrigins(): array { return []; }
}
if (class_exists('BloodMatch\\Config\\AppConfig',false)) { throw new LogicException('Isolation must load before app configuration.'); }
class_alias(NonResponseTestConfig::class,'BloodMatch\\Config\\AppConfig');
require_once __DIR__.'/fake_mail_transport.php';

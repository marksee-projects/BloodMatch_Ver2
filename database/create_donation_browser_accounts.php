<?php
declare(strict_types=1);
// USER-RUN ONLY: persistent synthetic browser fixtures, fixed test target, no SMTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';

$p = null;
try {
    if (!array_key_exists('create', getopt('', ['create']))) {
        echo "Creates two synthetic members in bloodmatch_test:3306 only. Run with --create. No existing accounts are modified.\n";
        exit;
    }
    $p = operationalTarget('test');
    \BloodMatch\Repositories\DonationWorkflowRepository::requireReady();
    $chapter = $p->query('SELECT id FROM chapters ORDER BY id LIMIT 1')->fetchColumn();
    if (!$chapter) { throw new RuntimeException('A seeded test chapter is required.'); }
    $password = 'BloodMatchTest2026!';
    $accounts = [
        ['email'=>'bm-donation-requester@example.test', 'name'=>'Requester', 'enrolled'=>false],
        ['email'=>'bm-donation-donor@example.test', 'name'=>'Donor', 'enrolled'=>true],
    ];
    $p->beginTransaction();
    $find = $p->prepare('SELECT id FROM users WHERE email=? FOR UPDATE');
    foreach ($accounts as $account) {
        $find->execute([$account['email']]);
        if ($find->fetchColumn() !== false) {
            throw new RuntimeException('Test address already exists: '.$account['email'].'. Nothing changed; existing passwords/accounts will not be reset.');
        }
    }
    $insert = $p->prepare("INSERT INTO users(email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,donor_availability,email_verified_at)
        VALUES(?,?,'Browser Test',?,'member',?,'verified','active','1990-01-01','O+','self_reported',0,?,'available',UTC_TIMESTAMP())");
    foreach ($accounts as &$account) {
        $insert->execute([$account['email'],password_hash($password,PASSWORD_BCRYPT),$account['name'],(int)$chapter,$account['enrolled'] ? gmdate('Y-m-d H:i:s') : null]);
        $account['id'] = (int)$p->lastInsertId();
        \BloodMatch\Services\AuditLogger::log(null,'test.donation_browser_account_created','user',(string)$account['id'],['fixture'=>'donation_browser','test_target'=>true]);
    }
    unset($account);
    $p->commit();
    echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'accounts'=>$accounts,'password'=>$password,'blood_type'=>'O+','smtp'=>false],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    if ($p instanceof PDO && $p->inTransaction()) { $p->rollBack(); }
    fwrite(STDERR,'Browser accounts stopped: '.$e->getMessage().PHP_EOL);
    exit(1);
}

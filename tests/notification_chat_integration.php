<?php
declare(strict_types=1);
ob_start();
// Uses the configured local database; every fixture change is rolled back.
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Database,Env};
use BloodMatch\Services\{PresenceService,NotificationService};
Env::load(dirname(__DIR__).'/.env');
$p=Database::pdo();
if (!in_array($p->query('SELECT DATABASE()')->fetchColumn(),['bloodmatch','bloodmatch_test'],true)) throw new RuntimeException('Unexpected target.');
$checks=0;
function checkFix(bool $ok,string $label): void { global $checks;if (!$ok) throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL; }
$userId=(int)$p->query("SELECT id FROM users WHERE role='member' AND account_status='active' AND verification_status='verified' LIMIT 1")->fetchColumn();
if (!$userId) throw new RuntimeException('An eligible local fixture account is required.');
$p->beginTransaction();
try {
    $p->prepare('DELETE FROM user_presence WHERE user_id=?')->execute([$userId]);
    checkFix(!PresenceService::isOnline($userId),'No heartbeat means offline');
    session_id('presence-check-'.bin2hex(random_bytes(12)));
    PresenceService::heartbeat($userId);
    checkFix(PresenceService::isOnline($userId),'Heartbeat makes eligible account online');
    $p->prepare('UPDATE user_presence SET last_seen_at=UTC_TIMESTAMP()-INTERVAL 91 SECOND WHERE user_id=?')->execute([$userId]);
    checkFix(!PresenceService::isOnline($userId),'Stale heartbeat becomes offline');
    PresenceService::heartbeat($userId);
    $firstSession=session_id();
    session_id('presence-check-'.bin2hex(random_bytes(12)));
    PresenceService::heartbeat($userId);
    PresenceService::leave();
    checkFix(PresenceService::isOnline($userId),'Logout from one device preserves another device presence');
    session_id($firstSession);PresenceService::leave();
    checkFix(!PresenceService::isOnline($userId),'Logout from last device clears presence');
    PresenceService::heartbeat($userId);
    $p->prepare("UPDATE users SET account_status='deactivated',deactivated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$userId]);
    checkFix(!PresenceService::isOnline($userId),'Deactivated account never appears online');
    $p->prepare("UPDATE users SET account_status='active',deactivated_at=NULL WHERE id=?")->execute([$userId]);

    $docId=random_int(100000000,200000000);
    $recipients=NotificationService::notifyVerificationRequested($userId,'Regression Fixture',$docId);
    checkFix(count($recipients)>0,'ID submission creates admin review notification');
    checkFix(NotificationService::notifyVerificationRequested($userId,'Regression Fixture',$docId)===[],'Repeated ID submission event is deduplicated');
    $s=$p->prepare("SELECT COUNT(*) FROM notifications WHERE dedup_key LIKE ? AND read_at IS NULL");$s->execute(["verification-request:$docId:admin:%"]);
    checkFix((int)$s->fetchColumn()===count($recipients),'New admin review alerts start unread');

    $before=(int)$p->query('SELECT COUNT(*) FROM hospitals')->fetchColumn();
    foreach (explode(';',file_get_contents(dirname(__DIR__).'/database/seeds/005_bataan_hospitals.sql')) as $sql) if (trim($sql)!=='') $p->exec($sql);
    checkFix((int)$p->query('SELECT COUNT(*) FROM hospitals')->fetchColumn()===$before,'Repeating hospital seed adds no duplicates');
    checkFix((int)$p->query('SELECT COUNT(*) FROM blood_requests r LEFT JOIN hospitals h ON h.id=r.hospital_id WHERE r.hospital_id IS NOT NULL AND h.id IS NULL')->fetchColumn()===0,'All request hospital links resolve');
} finally { $p->rollBack(); }
echo "$checks integration checks passed; all fixture writes rolled back; no SMTP.\n";

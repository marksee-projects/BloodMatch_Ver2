<?php
// Read-only aggregate diagnostics: never output credentials or personal data.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
$p=BloodMatch\Config\Database::pdo();
$queries=[
 'hospital_duplicates'=>'SELECT COUNT(*) AS duplicate_groups FROM (SELECT name,municipality_name FROM hospitals GROUP BY name,municipality_name HAVING COUNT(*)>1) d',
 'review_notifications'=>"SELECT type,COUNT(*) AS total,SUM(read_at IS NULL) AS unread FROM notifications WHERE type IN ('verification.requested','verification.decision') GROUP BY type",
 'mail_queue'=>"SELECT e.status,e.cancel_reason,COUNT(*) AS total FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.type='verification.decision' GROUP BY e.status,e.cancel_reason",
 'pending_documents'=>"SELECT COUNT(DISTINCT u.id) AS members,COUNT(DISTINCT CASE WHEN n.id IS NOT NULL THEN u.id END) AS members_with_review_alert FROM users u JOIN member_documents d ON d.user_id=u.id AND d.doc_type='national_id' LEFT JOIN notifications n ON n.related_id=u.id AND n.type='verification.requested' WHERE u.verification_status='pending'",
];
foreach ($queries as $label=>$sql) { try { echo json_encode([$label=>$p->query($sql)->fetchAll()]).PHP_EOL; } catch (Throwable $e) { echo "$label: unavailable (schema differs)\n"; } }
echo json_encode(['worker_enabled'=>BloodMatch\Config\Env::get('EMAIL_WORKER_ENABLED','0')==='1','smtp_configured'=>BloodMatch\Services\Mailer::isConfigured()]).PHP_EOL;

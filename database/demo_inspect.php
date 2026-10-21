<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
use BloodMatch\Config\Database;
Env::load(dirname(__DIR__).'/.env');
$p=Database::pdo();
$result=['target'=>$p->query('SELECT DATABASE() db, @@hostname host, @@port port, VERSION() version, UTC_TIMESTAMP() utc_now')->fetch(),
 'accounts'=>$p->query('SELECT id,email,full_name,role,verification_status,account_status,chapter_id,blood_type,donor_availability,donor_enrolled_at,last_verified_donation_at FROM users ORDER BY id')->fetchAll(),
 'chapters'=>$p->query('SELECT * FROM chapters')->fetchAll(),
 'hospitals'=>$p->query('SELECT * FROM hospitals LIMIT 5')->fetchAll()];
foreach (['users','blood_requests','matches','donation_reports','donation_report_events','donation_chat_contexts','chat_requests','member_documents','notifications','audit_log'] as $t) $result['schema'][$t]=$p->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);

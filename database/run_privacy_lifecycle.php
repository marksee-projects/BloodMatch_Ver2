<?php
/** User-run periodic expiry/reminders. Review is SELECT-only. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Services\{AuthService,RequestLifecycleService,NotificationService};
Env::load(dirname(__DIR__).'/.env');$args=getopt('',['target:','apply']);$target=$args['target']??'bloodmatch_test';
try {
 if (!in_array($target,['bloodmatch','bloodmatch_test'],true)) throw new RuntimeException('Unsupported target.');
 $_ENV['DB_NAME']=$target;$_ENV['DB_PORT']=$target==='bloodmatch_test'?Env::get('TEST_DB_PORT','3306'):Env::get('DB_PORT','3306');
 $p=Database::pdo();if ((string)$p->query('SELECT @@port')->fetchColumn()!==(string)$_ENV['DB_PORT']) throw new RuntimeException('Port mismatch.');
 $due=(int)$p->query("SELECT COUNT(*) FROM blood_requests WHERE status='OPEN' AND needed_datetime<UTC_TIMESTAMP()")->fetchColumn();
 $rows=$p->query("SELECT m.id,m.donor_id,br.requester_id,br.id AS request_id FROM matches m JOIN blood_requests br ON br.id=m.request_id JOIN users u ON u.id=m.donor_id WHERE m.status='RESPONDED' AND m.updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) AND br.status='OPEN' AND br.needed_datetime>UTC_TIMESTAMP() AND u.account_status='active' AND NOT EXISTS(SELECT 1 FROM donation_reports d WHERE d.match_id=m.id) AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.dedup_key=CONCAT('offer-reminder:',m.id,':',m.donor_id,':',DATE_FORMAT(UTC_TIMESTAMP(),'%Y-%m-%d'))) ORDER BY m.id LIMIT 500")->fetchAll();
 if (isset($args['apply'])) {
  foreach((new RequestLifecycleService())->expireDueRequests(AuthService::nowUtc()) as $r) {}
  foreach($rows as $r) foreach(array_unique([(int)$r['donor_id'],(int)$r['requester_id']]) as $recipient) NotificationService::notify($recipient,'donation.reminder','Donation offer follow-up','If the donation happened, submit a report. Otherwise update the offer.',['related_type'=>'blood_request','related_id'=>(int)$r['request_id'],'dedup_key'=>'offer-reminder:'.$r['id'].':'.$recipient.':'.gmdate('Y-m-d')]);
 }
 echo json_encode(['target'=>$target,'port'=>$_ENV['DB_PORT'],'due_expirations'=>$due,'reminder_offers'=>count($rows),'mode'=>isset($args['apply'])?'applied':'review'],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}

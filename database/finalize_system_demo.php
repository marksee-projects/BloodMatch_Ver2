<?php
/** Inspect/finish only this isolated batch and export an exhaustive safe inventory. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Services\{AuthService,MatchService,DonorEligibilityService,DocumentStorageService,MatchProximity,EmailWorker,Mailer,EmailTemplate};
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch_demo_20261022_7c91';$_ENV['DB_PORT']='3306';$_ENV['MAIL_HOST']='127.0.0.1';$_ENV['MAIL_PORT']='1026';$_ENV['MAIL_USER']='';$_ENV['MAIL_PASS']='';$_ENV['MAIL_FROM']='demo@bloodmatch.local';
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';$m=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);$p=Database::pdo();
if($p->query('SELECT DATABASE()')->fetchColumn()!==$m['database']||(int)$p->query('SELECT @@port')->fetchColumn()!==3306)throw new RuntimeException('Wrong demo target');
$keys=json_decode(file_get_contents($dir.'/document-key-location.json'),true);$_ENV['DOCUMENT_KEY_DIRECTORY']=$keys['directory'];$_ENV['DOCUMENT_ACTIVE_KEY_ID']=$keys['id'];$_ENV['DOCUMENT_ENCRYPTION_MODE']='strict';
$q=function(string $sql,array $args=[])use($p){$s=$p->prepare($sql);$s->execute($args);return $s;};
$done=is_file($dir.'/final-inventory.json');
if(!$done){
 $p->beginTransaction();try{
  $q("UPDATE users SET donor_enrolled_at=NULL,donor_availability=NULL WHERE email=?",[strtolower($m['batch']).'.disposable@example.com']);
  $q('UPDATE users SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 DAY),donor_enrolled_at=IF(donor_enrolled_at IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 19 DAY)) WHERE email LIKE ?',[strtolower($m['batch']).'.%@example.com']);
  foreach($p->query('SELECT id FROM blood_requests')->fetchAll(PDO::FETCH_COLUMN) as $id)$q("UPDATE blood_requests SET description=CONCAT(?, ' API or fixture request #',id) WHERE id=? AND (description IS NULL OR description NOT LIKE ?)",[$m['batch'],$id,$m['batch'].'%']);
  $p->commit();
 }catch(Throwable $e){$p->rollBack();throw $e;}
 (new MatchService())->generateForRequest($m['requests']['main-live']['id'],false,$m['batch'],85);
 // Supported bounded worker, custom local-only transport. Production TLS transport
 // intentionally permits 465/587 only and is NOT replaced or claimed tested.
 $jobs=$p->query("SELECT e.id FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id=69 AND n.type='verification.decision'")->fetchAll(PDO::FETCH_COLUMN);
 $transport=static function(array $meta)use($p):array {
  $ids=array_map('intval',$meta['job_ids']);$rows=$p->query('SELECT n.title,n.body,n.type,u.email FROM email_outbox e JOIN notifications n ON n.id=e.notification_id JOIN users u ON u.id=n.user_id WHERE e.id IN ('.implode(',',$ids).')')->fetchAll();
  $message=EmailTemplate::notifications($rows);
  foreach(['Exception','SMTP','PHPMailer'] as $c)if(!class_exists('PHPMailer\\PHPMailer\\'.$c))require dirname(__DIR__).'/backend/lib/phpmailer/'.$c.'.php';
  $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host='127.0.0.1';$mail->Port=1026;$mail->SMTPAuth=false;$mail->SMTPAutoTLS=false;$mail->setFrom('demo@bloodmatch.local','BloodMatch DEMO');$mail->addAddress($rows[0]['email']);$mail->Subject='[DEMO] Captured membership decision';$mail->isHTML(true);$mail->Body=$message['html'];$mail->AltBody=$message['text'];return ['outcome'=>$mail->send()?'accepted':'rejected'];
 };
 $m['checks']['scoped_mail']=(new EmailWorker($transport,$m['database'],3306,'demo-7c91-mail',true))->run(array_map('intval',$jobs),true,10);
}
foreach($p->query('SELECT id FROM blood_requests WHERE description IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id)$q('UPDATE blood_requests SET description=? WHERE id=?',[$m['batch'].' API validation fixture',$id]);
$inventory=['batch'=>$m['batch'],'database'=>$m['database'],'tables'=>[],'accounts'=>$p->query('SELECT id,email,full_name,role,verification_status,account_status,chapter_id,blood_type,donor_availability,donor_enrolled_at,last_verified_donation_at FROM users ORDER BY id')->fetchAll(),'requests'=>$p->query('SELECT id,requester_id,request_chapter_id,urgency,status,needed_datetime,closure_reason,description FROM blood_requests ORDER BY id')->fetchAll(),'reports'=>$p->query('SELECT id,match_id,donor_id,status,workflow_state,content_version,requester_response,acknowledged_version,occurred_at,reported_at,credit_recorded_at,reminder_sent_at,escalated_at,hold_started_at,hold_released_at FROM donation_reports ORDER BY id')->fetchAll(),'files'=>[]];
foreach(['member_documents','donation_report_evidence'] as $table)foreach($p->query('SELECT id,stored_name FROM `'.$table.'`')->fetchAll() as $f)$inventory['files'][]=['table'=>$table,'id'=>$f['id'],'path'=>DocumentStorageService::pathFor($f['stored_name']),'sha256'=>hash_file('sha256',DocumentStorageService::pathFor($f['stored_name']))];
foreach($p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$t]) {
 $cols=$p->query('SHOW FULL COLUMNS FROM `'.$t.'`')->fetchAll();$pk=array_column(array_filter($cols,fn($c)=>$c['Key']==='PRI'),'Field');
 $inventory['tables'][$t]=['count'=>(int)$p->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(),'primary_keys'=>$pk?$p->query('SELECT `'.implode('`,`',$pk).'` FROM `'.$t.'` ORDER BY `'.implode('`,`',$pk).'`')->fetchAll():[]];
}
$raw=$p->query('SELECT m.donor_id,m.distance_km,u.chapter_id,u.donor_enrolled_at FROM matches m JOIN users u ON u.id=m.donor_id WHERE m.request_id='.(int)$m['requests']['main-live']['id'].' ORDER BY '.MatchProximity::orderSql('1'))->fetchAll();$m['checks']['final_ranking']=array_values(array_filter($raw,fn($r)=>$r['donor_enrolled_at']!==null));
$m['checks']['settings']=\BloodMatch\Services\SystemSettingsService::get();
$m['checks']['ties']=['same_chapter_first'=>MatchProximity::compare(['distance'=>1.0,'same_chapter'=>true,'row'=>['id'=>20]],['distance'=>1.0,'same_chapter'=>false,'row'=>['id'=>10]])<0,'lower_id_first'=>MatchProximity::compare(['distance'=>1.0,'same_chapter'=>true,'row'=>['id'=>10]],['distance'=>1.0,'same_chapter'=>true,'row'=>['id'=>20]])<0,'null_last'=>MatchProximity::compare(['distance'=>null,'same_chapter'=>true,'row'=>['id'=>1]],['distance'=>10.0,'same_chapter'=>false,'row'=>['id'=>2]])>0];
$m['checks']['active_hold_rejected']=\BloodMatch\Repositories\DonationWorkflowRepository::hasHold($m['cases']['hold-rejected']['donor_id']);
$m['checks']['post_donation_window']=DonorEligibilityService::evaluateWindows((string)$q('SELECT last_verified_donation_at FROM users WHERE id=?',[$m['cases']['mutual']['donor_id']])->fetchColumn());
$m['checks']['main_open']=$q('SELECT status FROM blood_requests WHERE id=?',[$m['requests']['main-live']['id']])->fetchColumn()==='OPEN';
$m['checks']['main_no_reports']=(int)$q('SELECT COUNT(*) FROM donation_reports d JOIN matches x ON x.id=d.match_id WHERE x.request_id=?',[$m['requests']['main-live']['id']])->fetchColumn()===0;
$m['checks']['pending_untouched']=$p->query('SELECT id,verification_status FROM users WHERE id IN (89,104) ORDER BY id')->fetchAll();
$m['checks']['foreign_keys_enabled']=(int)$p->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()===1;
$m['checks']['orphan_foreign_keys']=[];
$fks=$p->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
foreach($fks as $f){$n=(int)$p->query('SELECT COUNT(*) FROM `'.$f['TABLE_NAME'].'` c LEFT JOIN `'.$f['REFERENCED_TABLE_NAME'].'` r ON c.`'.$f['COLUMN_NAME'].'`=r.`'.$f['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$f['COLUMN_NAME'].'` IS NOT NULL AND r.`'.$f['REFERENCED_COLUMN_NAME'].'` IS NULL')->fetchColumn();if($n)$m['checks']['orphan_foreign_keys'][]=[$f,$n];}
$m['checks']['chronology_violations']=(int)$p->query('SELECT COUNT(*) FROM donation_reports d JOIN donation_chat_contexts c ON c.id=d.offer_context_id WHERE c.responded_at>c.accepted_at OR c.accepted_at>d.occurred_at OR d.occurred_at>d.reported_at')->fetchColumn();
foreach($p->query('SELECT * FROM member_documents')->fetchAll() as $doc)if(!getimagesizefromstring(DocumentStorageService::read($doc)))throw new RuntimeException('Dummy document did not decrypt to a valid raster image.');
$m['checks']['dummy_documents_decrypted']=(int)$p->query('SELECT COUNT(*) FROM member_documents')->fetchColumn();
$snapshot=['batch'=>$m['batch'],'database'=>$m['database'],'tables'=>[],'triggers'=>$p->query('SHOW TRIGGERS')->fetchAll()];
foreach(array_keys($inventory['tables']) as $t){$cols=$p->query('SHOW FULL COLUMNS FROM `'.$t.'`')->fetchAll();$names=array_column(array_filter($cols,fn($c)=>!str_contains($c['Extra'],'GENERATED')),'Field');$snapshot['tables'][$t]=['ddl'=>$p->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1],'columns'=>$names,'rows'=>$p->query('SELECT `'.implode('`,`',$names).'` FROM `'.$t.'` ORDER BY `'.implode('`,`',$names).'`')->fetchAll(PDO::FETCH_NUM)];}
file_put_contents($dir.'/prepared.json',json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
file_put_contents($dir.'/manifest.json',json_encode($m,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);file_put_contents($dir.'/final-inventory.json',json_encode($inventory,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
echo json_encode(['accounts'=>count($inventory['accounts']),'requests'=>count($inventory['requests']),'reports'=>count($inventory['reports']),'checks'=>$m['checks']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);

<?php
/** Reversible release of nine user-confirmed test addresses; preserves history. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
Env::load(dirname(__DIR__).'/.env');$p=Database::pdo();
$t=$p->query('SELECT DATABASE() db,@@port port')->fetch();if($t['db']!=='bloodmatch'||(int)$t['port']!==3306)throw new RuntimeException('Exact application target mismatch.');
$batch='DEMO_20261022_7C91';$dir=dirname(__DIR__).'/backend/storage/maintenance/'.$batch;$file=$dir.'/released-emails-backup.json';
$emails=[137=>'fireshorts395@gmail.com',138=>'emeraldfae16@gmail.com',139=>'feyestipular@gmail.com',140=>'deleon.ak1010@gmail.com',141=>'ninamaemanzano5@gmail.com',142=>'marsee24@bpsu.edu.ph',143=>'jamessrusssel33@gmail.com',144=>'jrbremodo24@bpsu.edu.ph',147=>'solaralor14@gmail.com'];
$mode=$argv[1]??'review';if(!in_array($mode,['review','apply','restore'],true))throw new RuntimeException('review, apply or restore required.');
if($mode!=='review'&&($argv[2]??'')!==$batch)throw new RuntimeException('Supply exact batch marker.');
$ids=implode(',',array_keys($emails));$users=$p->query('SELECT * FROM users WHERE id IN ('.$ids.') ORDER BY id')->fetchAll();
if($mode==='review'){echo json_encode(['target'=>$t,'accounts'=>array_map(fn($u)=>array_intersect_key($u,array_flip(['id','email','role','account_status'])),$users),'action'=>'Back up; replace only listed emails with reserved demo addresses; deactivate only these test accounts; cancel only their queued emails. Preserve passwords and all donation/request/chat records.'],JSON_PRETTY_PRINT);exit;}
$p->beginTransaction();try {
 $users=$p->query('SELECT * FROM users WHERE id IN ('.$ids.') ORDER BY id FOR UPDATE')->fetchAll();
 if($mode==='apply') {
  if(is_file($file))throw new RuntimeException('Already backed up/applied; review the manifest. Never overwrite backup.');
  if(count($users)!==9)throw new RuntimeException('Nine exact accounts required.');
  foreach($users as $u)if($u['email']!==$emails[(int)$u['id']]||$u['role']!=='member')throw new RuntimeException('Account allowlist mismatch.');
  $jobs=$p->query('SELECT e.* FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id IN ('.$ids.') ORDER BY e.id FOR UPDATE')->fetchAll();
  foreach($jobs as $job)if($job['status']==='leased')throw new RuntimeException('Leased mail job; stop its sender and retry after lease expiry.');
  $backup=['batch'=>$batch,'target'=>$t,'before_users'=>$users,'before_jobs'=>$jobs,'at_utc'=>gmdate('Y-m-d H:i:s')];
  if(file_put_contents($file,json_encode($backup,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Backup failed.');
  $q=$p->prepare("UPDATE users SET email=?,account_status='deactivated',deactivated_at=UTC_TIMESTAMP() WHERE id=? AND email=? AND role='member'");
  foreach($emails as $id=>$email)$q->execute(['released-'.$id.'-'.strtolower($batch).'@example.invalid',$id,$email]);
  $p->exec("UPDATE email_outbox e JOIN notifications n ON n.id=e.notification_id SET e.status='cancelled',e.cancel_reason='demo_email_released',e.lease_token=NULL,e.lease_expires_at=NULL,e.updated_at=UTC_TIMESTAMP(6) WHERE n.user_id IN ($ids) AND e.status IN ('pending','leased')");
  $backup['after_users']=$p->query('SELECT * FROM users WHERE id IN ('.$ids.') ORDER BY id')->fetchAll();
  $backup['after_jobs']=$p->query('SELECT e.* FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id IN ('.$ids.') ORDER BY e.id')->fetchAll();
  file_put_contents($file,json_encode($backup,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
 } else {
  $backup=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  if($users!==$backup['after_users'])throw new RuntimeException('Accounts changed since release; manual review needed.');
  $jobs=$p->query('SELECT e.* FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id IN ('.$ids.') ORDER BY e.id FOR UPDATE')->fetchAll();
  if($jobs!==$backup['after_jobs'])throw new RuntimeException('Mail state changed; refuse restore.');
  $q=$p->prepare('SELECT id FROM users WHERE email=? AND id<>?');foreach($emails as $id=>$email){$q->execute([$email,$id]);if($q->fetchColumn())throw new RuntimeException('Email has been reused; refuse to displace its new owner.');}
  $q=$p->prepare('UPDATE users SET email=?,account_status=?,deactivated_at=?,updated_at=? WHERE id=?');foreach($backup['before_users'] as $u)$q->execute([$u['email'],$u['account_status'],$u['deactivated_at'],$u['updated_at'],$u['id']]);
  $q=$p->prepare('UPDATE email_outbox SET status=?,cancel_reason=?,lease_token=?,lease_expires_at=?,updated_at=? WHERE id=?');foreach($backup['before_jobs'] as $j)$q->execute([$j['status'],$j['cancel_reason'],$j['lease_token'],$j['lease_expires_at'],$j['updated_at'],$j['id']]);
 }
 $log=$p->prepare('INSERT INTO audit_log(actor_id,action,target_type,target_id,context) VALUES(NULL,?,\'user\',?,?)');foreach($emails as $id=>$email)$log->execute(['demo.email.'.$mode,(string)$id,json_encode(['batch'=>$batch,'user_confirmed_test'=>true,'history_preserved'=>true])]);
 $p->commit();echo $mode.' completed for exactly nine confirmed example accounts; passwords and linked history preserved. No email sent. '.PHP_EOL;
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}

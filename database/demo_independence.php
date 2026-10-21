<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\MatchResponseService;
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch_demo_20261022_7c91';$_ENV['DB_PORT']='3306';$_ENV['MAIL_HOST']='';
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';$m=json_decode(file_get_contents($dir.'/manifest.json'),true);$p=Database::pdo();if($p->query('SELECT DATABASE()')->fetchColumn()!==$m['database'])throw new RuntimeException('Target mismatch');
$p->beginTransaction();try{
 foreach(['withdrawal'=>'withdraw-only','accept-live'=>'accept-only'] as $case=>$label){
  $email=strtolower($m['batch']).'.'.$label.'@example.com';$s=$p->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);$id=$s->fetchColumn();
  if(!$id){$password=bin2hex(random_bytes(12)).'A1';$s=$p->prepare("INSERT INTO users(email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at,latitude,longitude) VALUES(?,?,?,'DEMO','member',1,'verified','active','1990-01-01','O+','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP(),14.68,120.54)");$s->execute([$email,password_hash($password,PASSWORD_BCRYPT),$label]);$id=(int)$p->lastInsertId();file_put_contents($dir.'/synthetic-credentials.jsonl',json_encode(['id'=>$id,'email'=>$email,'password'=>$password])."\n",FILE_APPEND|LOCK_EX);$m['accounts'][]=['id'=>$id,'email'=>$email,'label'=>$label,'origin'=>'independent live response scenario'];}
  if((int)$m['cases'][$case]['donor_id']!==(int)$id){$response=new MatchResponseService();$response->withdraw((new UserRepository())->findById($m['cases'][$case]['donor_id']),$m['cases'][$case]['match_id']);$offered=$response->respond((new UserRepository())->findById((int)$id),$m['cases'][$case]['request_id'],$m['batch']);$m['cases'][$case]['prior_withdrawn_match_id']=$m['cases'][$case]['match_id'];$m['cases'][$case]['donor_id']=(int)$id;$m['cases'][$case]['match_id']=$offered['match_id'];}
 }
 $p->commit();file_put_contents($dir.'/manifest.json',json_encode($m,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);echo "Withdrawal and live acceptance now use separate donors; near/far remain independent.\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}

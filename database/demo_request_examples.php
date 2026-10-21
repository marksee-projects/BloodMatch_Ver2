<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\{RequestLifecycleService,AuthService};
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch_demo_20261022_7c91';$_ENV['DB_PORT']='3306';$_ENV['MAIL_HOST']='';
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';$m=json_decode(file_get_contents($dir.'/manifest.json'),true);$p=Database::pdo();if($p->query('SELECT DATABASE()')->fetchColumn()!==$m['database'])throw new RuntimeException('Target mismatch');
foreach(['expired-static','close-live'] as $label){if(isset($m['requests'][$label]))continue;$s=$p->query('SELECT * FROM blood_requests WHERE id='.(int)$m['requests']['main-live']['id'])->fetch();$s['needed_datetime']=gmdate('Y-m-d H:i:s',time()+($label==='expired-static'?-3600:7*86400));$id=(new BloodRequestRepository())->create($s);$p->prepare('UPDATE blood_requests SET description=? WHERE id=?')->execute([$m['batch'].' '.$label,$id]);if($label==='expired-static'){$expired=(new RequestLifecycleService())->expireOne($id,AuthService::nowUtc());$m['checks']['scoped_expiration']=$expired!==null;} $m['requests'][$label]=['id'=>$id,'status'=>$label==='expired-static'?'EXPIRED':'OPEN','urgency'=>'routine','chapter'=>1,'screen'=>'/requests/'.$id.'/matches'];}
$p->exec("UPDATE blood_requests SET created_at=DATE_SUB(needed_datetime,INTERVAL 1 DAY) WHERE status='EXPIRED' AND created_at>needed_datetime");
file_put_contents($dir.'/manifest.json',json_encode($m,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);echo "Separate expired and live-close requests prepared; scoped expiration service exercised.\n";

<?php
/** Bounded request fixture creation/edit with normal payload validation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Repositories\{UserRepository,BloodRequestRepository};
use BloodMatch\Services\{RequestService,WorkflowLockService,MatchService,AuditLogger};
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch';$_ENV['DB_PORT']='3306';$_ENV['MAIL_HOST']='';
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_MAIN_20261022_B42E';$m=json_decode(file_get_contents($dir.'/manifest.json'),true);$p=Database::pdo();$t=$p->query('SELECT DATABASE() db,@@port port')->fetch();if($t['db']!=='bloodmatch'||(int)$t['port']!==3306)throw new RuntimeException('Main target mismatch');
$mode=$argv[1]??'';$id=(int)($argv[2]??0);$path=realpath($argv[3]??'');$base=realpath($dir);if(!in_array($mode,['create','edit'],true)||!$path||!str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$base).'/')||!str_ends_with($path,'.json'))throw new RuntimeException('Use create 0 or edit REQUEST_ID plus a JSON payload path inside this batch private directory');
$body=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$owner=(new UserRepository())->findById(85);if($owner['email']!=='p5other189351979@test.local'||$owner['role']!=='member'||$owner['verification_status']!=='verified'||$owner['account_status']!=='active')throw new RuntimeException('Requester allowlist/prerequisite mismatch');
$result=WorkflowLockService::transaction(function()use($mode,$id,$body,$owner,$p,$m){$repo=new BloodRequestRepository();
 if($mode==='edit'){$old=WorkflowLockService::request($id);if((int)$old['requester_id']!==85||!str_starts_with($old['description']??'',$m['batch']))throw new RuntimeException('Only a marked main demo request may be edited');WorkflowLockService::assertOpenBeforeDeadline($old);$fields=RequestService::validatePayload($body,true,$old);$repo->updateFields($id,$fields);}
 else{$fields=RequestService::validatePayload($body);$id=$repo->create($fields+['requester_id'=>85,'request_chapter_id'=>RequestService::chapterIdOf($owner),'review_status'=>RequestService::reviewStatusFor($owner)]);$p->prepare('UPDATE blood_requests SET description=? WHERE id=?')->execute([$m['batch'].' scoped CLI request fixture',$id]);}
 foreach($m['maps']['users'] as $donor)if(!in_array((int)$donor,[3,85],true))(new MatchService())->generateForRequest($id,false,'demo_scoped_request',85,(int)$donor);
 AuditLogger::log(85,'demo.main.request.'.$mode,'blood_request',(string)$id,['demo_batch'=>$m['batch'],'only_example_donors'=>true]);return $id;
});
file_put_contents($dir.'/scoped-request-actions.jsonl',json_encode(['mode'=>$mode,'request_id'=>$result,'at_utc'=>gmdate('Y-m-d H:i:s')])."\n",FILE_APPEND|LOCK_EX);echo 'Main demo request #'.$result.' '.$mode." completed with example-only matching.\n";

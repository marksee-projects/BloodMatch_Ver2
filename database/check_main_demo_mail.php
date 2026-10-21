<?php
/** One loopback-only transport smoke test, never a global worker. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Services\{Mailer,EmailAttemptBudget};
Env::load(dirname(__DIR__).'/.env');foreach(['DB_NAME'=>'bloodmatch','DB_PORT'=>'3306','MAIL_HOST'=>'127.0.0.1','MAIL_PORT'=>'1026','MAIL_USER'=>'','MAIL_PASS'=>'','MAIL_FROM'=>'demo@bloodmatch.local'] as $k=>$v)$_ENV[$k]=$v;
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_MAIN_20261022_B42E';$file=$dir.'/mail-check.json';if(is_file($file)){echo "Main local-mail smoke check already recorded.\n";exit;}
$p=Database::pdo();$t=$p->query('SELECT DATABASE() db,@@port port')->fetch();if($t['db']!=='bloodmatch'||(int)$t['port']!==3306)throw new RuntimeException('Main target mismatch');
$m=json_decode(file_get_contents($dir.'/manifest.json'),true);$near=array_values(array_filter($m['accounts'],fn($a)=>$a['label']==='near'))[0];$q=$p->prepare('SELECT email FROM users WHERE id=?');$q->execute([$near['id']]);if($q->fetchColumn()!==$near['email'])throw new RuntimeException('Example recipient mismatch');
$ok=Mailer::send($near['email'],'[DEMO] Main database local email capture','<p>DEMO_MAIN_20261022_B42E transport smoke test only.</p><p>The prepared main workflow is request #'.(int)$m['requests']['main-live']['id'].'. No real donation or external delivery is claimed.</p>',EmailAttemptBudget::ACCOUNT);
if(!$ok)throw new RuntimeException('Local capture send failed; inspect without running global workers.');
$files=glob($dir.'/mail/*.eml');$found=[];foreach($files as $f)if(str_contains(file_get_contents($f),'DEMO_MAIN_20261022_B42E transport smoke test'))$found[]=basename($f);if(!$found)throw new RuntimeException('SMTP returned accepted but capture file not found');
file_put_contents($file,json_encode(['database'=>'bloodmatch','recipient_id'=>$near['id'],'captured_files'=>$found,'scope'=>'transport smoke only; no automated workflow claim','external_delivery'=>false],JSON_PRETTY_PRINT),LOCK_EX);echo "Main database email captured on loopback; no external delivery.\n";

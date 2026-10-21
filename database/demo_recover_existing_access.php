<?php
// Reuse already-saved local demo credentials only when hashes still match.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch_demo_20261022_7c91';$_ENV['DB_PORT']='3306';$p=Database::pdo();
if($p->query('SELECT DATABASE()')->fetchColumn()!=='bloodmatch_demo_20261022_7c91')throw new RuntimeException('Target mismatch');
$out=[];foreach(glob(dirname(__DIR__).'/backend/storage/maintenance/bloodmatch-*-credentials.json') as $file){$s=json_decode(file_get_contents($file),true);if(($s['status']??'')!=='COMMITTED')continue;foreach($s['accounts']??[] as $a){$q=$p->prepare('SELECT password_hash FROM users WHERE id=? AND email=?');$q->execute([$a['id'],$a['email']]);$hash=$q->fetchColumn();if($hash&&password_verify($a['password'],$hash))$out[$a['id']]=$a;}}
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';file_put_contents($dir.'/existing-access.json',json_encode(array_values($out),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
echo json_encode(['verified_existing_account_ids'=>array_keys($out),'password_changes'=>0,'secret_output'=>false]);

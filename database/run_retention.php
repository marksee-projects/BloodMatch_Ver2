<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
Env::load(dirname(__DIR__).'/.env');
$args=getopt('',['target:','apply','offline-ack']);$target=$args['target']??'bloodmatch_test';
if (!in_array($target,['bloodmatch','bloodmatch_test'],true)) {fwrite(STDERR,"Unsupported target.\n");exit(1);}
$_ENV['DB_NAME']=$target;$_ENV['DB_PORT']=$target==='bloodmatch_test'?Env::get('TEST_DB_PORT','3306'):Env::get('DB_PORT','3306');
try {
 $p=Database::pdo();$resolved=$p->query('SELECT DATABASE() AS db,@@port AS port')->fetch();
 if ($resolved['db']!==$target || (string)$resolved['port']!==(string)$_ENV['DB_PORT']) throw new RuntimeException('Target/port mismatch.');
 if (isset($args['apply']) && !isset($args['offline-ack'])) throw new RuntimeException('Stop servers/workers and supply --offline-ack for apply.');
 if (isset($args['apply'])) echo 'Backup: '.\BloodMatch\Services\MaintenanceBackupService::create($target,(int)$resolved['port']).PHP_EOL;
 echo json_encode(['target'=>$resolved,'result'=>\BloodMatch\Services\RetentionService::run(isset($args['apply']))],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}

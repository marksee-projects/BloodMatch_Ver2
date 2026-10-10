<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require __DIR__.'/email_checkpoint_support.php';
require __DIR__.'/non_response_checkpoint_support.php';
try {
    if (count($argv)>2 || !in_array($argv[1]??'--verify',['--verify','--run'],true)) { throw new LogicException('Use --verify or --run.'); }
    $pdo=emailCheckpointTarget();$shape=nonResponseShape($pdo);
    if (in_array('missing',$shape,true)) { throw new LogicException('022 is not ready.'); }
    if (($argv[1]??'--verify')==='--verify') { echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'shape'=>$shape,'writes'=>false]).PHP_EOL;exit; }
    $lock=$pdo->query("SELECT GET_LOCK('bm-test-non-response',0)")->fetchColumn();
    if ((int)$lock!==1) { echo "overlap_skipped\n";exit; }
    try { echo json_encode(['standby_transitions'=>\BloodMatch\Services\NonResponseStandbyService::processDue()]).PHP_EOL; }
    finally { $pdo->query("SELECT RELEASE_LOCK('bm-test-non-response')"); }
} catch (Throwable $e) { fwrite(STDERR,"Non-response test worker refused or failed.\n");exit(1); }

<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/email_checkpoint_support.php';

// USER-RUN ONLY. No transport adapter, SMTP callback, scheduler or real mode.
try {
    $run=false; $verify=false; $mode=null; $id=null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg==='--run') { $run=true; }
        elseif ($arg==='--verify') { $verify=true; }
        elseif (preg_match('/^--fake-mode=(accept|fail|unconfigured)$/D',$arg,$m)) { $mode=$m[1]; }
        elseif (preg_match('/^--job-id=([1-9]\d*)$/D',$arg,$m) && filter_var($m[1],FILTER_VALIDATE_INT)!==false) { $id=(int)$m[1]; }
        else { throw new LogicException('Use --verify, or --run --fake-mode=accept|fail|unconfigured --job-id=ID. Real delivery is unavailable.'); }
    }
    if (($run && $verify) || ($run && ($mode===null || $id===null)) || (!$run && ($mode!==null || $id!==null))) { throw new LogicException('Invalid command combination.'); }
    $_ENV['MAIL_HOST']=''; putenv('MAIL_HOST=');
    $pdo=emailCheckpointTarget();
    $shape=emailCheckpointShape($pdo);
    if ($shape!==['email_outbox'=>'ready','email_attempts'=>'ready']) { throw new LogicException('Migration 021 schema is not ready.'); }
    $ledger=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');$ledger->execute(['021_email_outbox_accounting.sql']);
    if((int)$ledger->fetchColumn()!==1){throw new LogicException('Migration 021 must be recorded before worker validation.');}
    if (!$run) {
        echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'mode'=>'verify','shape'=>$shape,
            'pending'=>(int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status='pending'")->fetchColumn(),
            'leased'=>(int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status='leased'")->fetchColumn(),
            'transport'=>'fake_only']) . PHP_EOL; exit(0);
    }
    $worker=new \BloodMatch\Services\TestEmailWorker(static fn(array $metadata): bool=>$mode==='accept');
    $result=$worker->run([$id],$mode!=='unconfigured',1);
    echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'job_id'=>$id,'fake_mode'=>$mode]+$result).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,($e instanceof LogicException?$e->getMessage():'Test worker failed; raw database errors withheld.').PHP_EOL); exit(1);
}

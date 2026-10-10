<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require __DIR__.'/email_checkpoint_support.php';
require __DIR__.'/non_response_checkpoint_support.php';
try {
    if (array_diff(array_slice($argv,1),['--apply']) || count($argv)>2) { throw new LogicException('Use no arguments for review or --apply for user-controlled test-only DDL.'); }
    $pdo=emailCheckpointTarget(); // actual bloodmatch_test:3306, no fallback allowed
    $name='022_non_response_opportunities.sql';$shape=nonResponseShape($pdo);
    $s=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');$s->execute([$name]);$recorded=(int)$s->fetchColumn()===1;
    if ($recorded && in_array('missing',$shape,true)) { throw new LogicException('Ledger/schema mismatch; inspect before changing data.'); }
    $sql=file_get_contents(__DIR__.'/migrations/'.$name);
    if ($sql===false) { throw new LogicException('Migration unavailable.'); }
    echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'shape'=>$shape,'recorded'=>$recorded]).PHP_EOL;
    if (!in_array('--apply',$argv,true)) { echo $sql."\nReview only; no writes.\n";exit(0); }
    foreach (explode(';',$sql) as $statement) {
        if (!preg_match('/CREATE TABLE (donor_response_state|donor_opportunities)\b/',$statement,$m)) { continue; }
        if ($shape[$m[1]]==='missing') { $pdo->exec($statement); }
    }
    $shape=nonResponseShape($pdo);
    if (in_array('missing',$shape,true)) { throw new LogicException('Incomplete additive schema; no ledger write.'); }
    if (!$recorded) { $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$name]); }
    echo "022 ready/recorded on bloodmatch_test:3306; no historical rows changed or backfilled.\n";
} catch (Throwable $e) { fwrite(STDERR,"Non-response checkpoint refused or failed; inspect schema. No reset or repair attempted.\n");exit(1); }

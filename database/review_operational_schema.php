<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
$options=getopt('',['target:','migration:','apply','sha256:','acknowledge-application-020']);
try {
    $target=$options['target']??'application';$number=$options['migration']??'023';
    $file=match($number) {'020'=>'020_donation_report_rejection_reason.sql','022'=>'022_non_response_opportunities.sql','023'=>'023_email_donor_reservations.sql',default=>throw new LogicException('Only 020/022/023 may be reviewed/applied.')};
    $pdo=operationalTarget($target);$path=__DIR__.'/migrations/'.$file;$hash=hash_file('sha256',$path);
    $shape=match($number) {'020'=>['donation_report_rejection_reason'=>rejectionReasonShape($pdo)],'022'=>nonResponseShape($pdo),'023'=>['email_donor_reservations'=>donorReservationShape($pdo)]};
    $s=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');$s->execute([$file]);$recorded=(bool)$s->fetchColumn();
    $before=$number==='020' ? rejectionReportSnapshot($pdo,current($shape)==='ready') : null;
    $preserved=null;
    if (isset($options['apply'])) {
        if ($number==='020' && $target==='application' && !$recorded && !isset($options['acknowledge-application-020'])) { throw new LogicException('Application 020 needs its explicit target approval/acknowledgment.'); }
        if (!hash_equals($hash,(string)($options['sha256']??''))) { throw new LogicException('Reviewed SHA-256 required.'); }
        if ($recorded) { if (in_array('missing',$shape,true)) { throw new LogicException('Recorded but missing schema.'); } }
        else {
            if (count(array_unique($shape))!==1 || current($shape)!=='missing') { throw new LogicException('Existing/partial objects require review; no automatic repair.'); }
            if ($number==='023' && in_array('missing',emailCheckpointShape($pdo),true)) { throw new LogicException('021 prerequisite missing.'); }
            $sql=preg_replace('/^\s*--.*$/m','',file_get_contents($path));
            foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') { $pdo->exec($statement); } }
            $shape=match($number) {'020'=>['donation_report_rejection_reason'=>rejectionReasonShape($pdo)],'022'=>nonResponseShape($pdo),'023'=>['email_donor_reservations'=>donorReservationShape($pdo)]};
            if (in_array('missing',$shape,true)) { throw new LogicException('Incomplete DDL; stop without dropping anything.'); }
            if ($number==='020') {
                $after=rejectionReportSnapshot($pdo,true);
                $preserved=$before['existing_fields_sha256']===$after['existing_fields_sha256']
                    && $before['report_count']===$after['report_count'] && $after['reports_with_rejection_reason']===0;
                if (!$preserved) { throw new LogicException('Report preservation check failed after DDL; stop and inspect. No automatic repair/backfill.'); }
            }
            $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$file]);$recorded=true;
        }
    }
    $result=['database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),'host'=>'127.0.0.1','port'=>3306,'mode'=>isset($options['apply'])?'apply':'review','migration'=>$file,'shape'=>$shape,'recorded'=>$recorded,'sql_sha256'=>$hash,'historical_backfill'=>false];
    if ($number==='020') { $result['reports']=rejectionReportSnapshot($pdo,current($shape)==='ready');$result['reports_preserved_during_apply']=$preserved; }
    echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,$e instanceof LogicException ? $e->getMessage().PHP_EOL : 'Schema review/application failed; inspect partial objects before retrying.'.PHP_EOL);exit(1); }

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';require dirname(__DIR__).'/database/operational_checkpoint_support.php';require dirname(__DIR__).'/database/donation_consolidation_support.php';
use BloodMatch\Services\BloodCompatibilityService;
$p=operationalTarget('test');if(\BloodMatch\Config\Env::get('BLOODMATCH_TEST_ISOLATION')!=='1')throw new LogicException('Isolation required.');
$checks=0;function extra032(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function reject032(callable $fn,int $code,string $label):void{global $p;$p->exec('SAVEPOINT extra_constraint');try{$fn();}catch(PDOException $e){$p->exec('ROLLBACK TO SAVEPOINT extra_constraint');$p->exec('RELEASE SAVEPOINT extra_constraint');extra032((int)($e->errorInfo[1]??0)===$code,$label);return;}throw new RuntimeException('FAIL '.$label);}
$old=$p->query('SELECT recipient_type,allowed_donor_types FROM compatibility_matrix ORDER BY recipient_type')->fetchAll(PDO::FETCH_KEY_PAIR);
foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $donor){$expected=[];foreach($old as $recipient=>$list)if(in_array($donor,array_map('trim',explode(',',$list)),true))$expected[]=$recipient;extra032(BloodCompatibilityService::getCompatibleRecipientTypesForDonor($donor)===$expected,'Inverse compatibility and ordering preserved for '.$donor);}
$p->beginTransaction();try{
    $state=json_decode(file_get_contents(dirname(__DIR__).'/backend/storage/maintenance/consolidation032-fixtures.json'),true,512,JSON_THROW_ON_ERROR);[$owner,$donor]=$state['users'];
    $p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,needed_datetime) VALUES(?,'O+',1,'Synthetic constraints',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([$owner]);$r=(int)$p->lastInsertId();
    $p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at) VALUES(?,?,'RESPONDED',UTC_TIMESTAMP())")->execute([$r,$donor]);$m=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO donation_chat_contexts(match_id,offer_cycle,accepted_at,responded_at) VALUES(?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$m]);$c=(int)$p->lastInsertId();
    $otherMatch=(int)$p->query('SELECT match_id FROM donation_reports WHERE id='.(int)$state['reports']['active'])->fetchColumn();
    reject032(fn()=>$p->prepare('UPDATE donation_chat_contexts SET match_id=? WHERE id=?')->execute([$otherMatch,$c]),1644,'Accepted context identity is immutable even before a report exists');
    $p->prepare('INSERT INTO donation_reports(match_id,donor_id,offer_context_id,reported_at,hold_started_at) VALUES(?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$m,$donor,$c]);$report=(int)$p->lastInsertId();
    $otherMatch=(int)$p->query('SELECT match_id FROM donation_reports WHERE id='.(int)$state['reports']['active'])->fetchColumn();
    reject032(fn()=>$p->prepare('UPDATE donation_chat_contexts SET match_id=? WHERE id=?')->execute([$otherMatch,$c]),1644,'Referenced context cannot move to a different match');
    $p->prepare('DELETE FROM donation_chat_contexts WHERE id=?')->execute([$c]);$s=$p->prepare('SELECT offer_context_id,hold_started_at FROM donation_reports WHERE id=?');$s->execute([$report]);$row=$s->fetch();extra032($row!==false&&$row['offer_context_id']===null&&$row['hold_started_at']!==null,'Context deletion preserves report and independent hold through SET NULL');
    reject032(fn()=>$p->prepare('UPDATE donation_reports SET credit_recorded_at=UTC_TIMESTAMP() WHERE id=?')->execute([$state['reports']['legacy']]),4025,'Legacy report cannot acquire credit without actual occurrence and valid basis');
    reject032(fn()=>$p->prepare("UPDATE donation_reports SET requester_response='invalid' WHERE id=?")->execute([$report]),4025,'Requester response domain enforced');
    $p->prepare("INSERT INTO chat_reports(reporter_id,reason) VALUES(?,'Synthetic uniqueness')")->execute([$owner]);$chat=(int)$p->lastInsertId();$insert=$p->prepare("INSERT INTO chat_report_evidence(report_id,message_id,body,message_created_at,is_selected) VALUES(?,1,'Synthetic snapshot',UTC_TIMESTAMP(),1)");$insert->execute([$chat]);
    reject032(fn()=>$insert->execute([$chat]),1062,'Same message cannot be duplicated within one chat evidence case');
    $before=$p->query('SELECT recipient_type,donor_type,preference_order FROM compatibility_pairs ORDER BY recipient_type,preference_order')->fetchAll();$p->exec(file_get_contents(dirname(__DIR__).'/database/seeds/006_compatibility_pairs.sql'));$after=$p->query('SELECT recipient_type,donor_type,preference_order FROM compatibility_pairs ORDER BY recipient_type,preference_order')->fetchAll();extra032($before===$after,'Normalized reference seed is idempotent and equivalent');
    $p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,needed_datetime) VALUES(?,'O+',1,'Synthetic overdue request',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([$owner]);$lateRequest=(int)$p->lastInsertId();
    $p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY))")->execute([$lateRequest,$donor]);$lateMatch=(int)$p->lastInsertId();
    $users=new \BloodMatch\Repositories\UserRepository();$service=new \BloodMatch\Services\DonationCompletionService();
    $lateReport=(int)$service->submit($users->findById($donor),$lateMatch,gmdate('Y-m-d\TH:i:s\Z',time()-300),'Synthetic overdue claim')['id'];
    $p->prepare('UPDATE blood_requests SET needed_datetime=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?')->execute([$lateRequest]);
    $completed=$service->acknowledge($users->findById($owner),$lateReport,1,'received',null);
    $s=$p->prepare('SELECT status FROM blood_requests WHERE id=?');$s->execute([$lateRequest]);extra032($s->fetchColumn()==='EXPIRED'&&!$completed['request_fulfilled_now'],'Effectively overdue OPEN request expires instead of fulfilling on late review');
    $row=(new \BloodMatch\Repositories\DonationReportRepository())->findById($lateReport);extra032($row['credit_recorded_at']!==null&&$row['hold_released_at']!==null,'Late genuine donation still records credit and resolves its own hold');
    extra032($users->findById($donor)['last_verified_donation_at']===$row['occurred_at'],'Late genuine donation still establishes the occurrence restriction anchor');
}finally{$p->rollBack();}
try{consolidationTarget('application');throw new LogicException('Application connection accepted');}catch(LogicException $e){extra032(str_contains($e->getMessage(),'cannot select'),'Isolated tools reject application target before connection');}
// Fresh isolated child cannot use the application name or alternate port, even if reachable.
foreach([['DB_NAME','bloodmatch'],['DB_PORT','3307']] as [$key,$value]){
    $code='require "backend/src/autoload.php"; putenv("'.$key.'='.$value.'"); $_ENV["'.$key.'"]="'.$value.'"; try { BloodMatch\\Config\\Database::pdo(); exit(1); } catch(RuntimeException $e) { exit(str_contains($e->getMessage(),"Isolated tests require")?0:2); }';
    $pipes=[];$proc=proc_open([PHP_BINARY,'-d','auto_prepend_file='.__DIR__.'/helpers/consolidation_isolation.php','-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);extra032(proc_close($proc)===0,'Isolated connector refuses '.$key.' override without fallback');
}
echo "$checks additional compatibility, constraint and isolation checks passed; fixture writes rolled back.\n";

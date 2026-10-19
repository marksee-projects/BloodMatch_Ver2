<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';
require dirname(__DIR__).'/database/operational_checkpoint_support.php';
require dirname(__DIR__).'/database/donation_consolidation_support.php';
use BloodMatch\Repositories\{DonationStorageRepository as Storage,DonationWorkflowRepository as Workflow,DonationReportRepository,UserRepository};
use BloodMatch\Services\{DonationCompletionService as Completion,BloodCompatibilityService,RetentionService,Mailer};
$p=operationalTarget('test');
if(\BloodMatch\Config\Env::get('BLOODMATCH_TEST_ISOLATION')!=='1')throw new LogicException('Isolation bootstrap required.');
$stateFile=dirname(__DIR__).'/backend/storage/maintenance/consolidation032-fixtures.json';
$checks=0;
function cc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function sqlDenied(string $sql,array $args,string $label):void{global $p;$p->exec('SAVEPOINT constraint_probe');try{$p->prepare($sql)->execute($args);}catch(PDOException $e){$p->exec('ROLLBACK TO SAVEPOINT constraint_probe');$p->exec('RELEASE SAVEPOINT constraint_probe');cc(in_array((int)($e->errorInfo[1]??0),[1062,1452,4025,1644],true),$label);return;}throw new RuntimeException('FAIL '.$label);}
function cu(string $tag):int{global $p;$s=$p->prepare("INSERT INTO users(email,first_name,last_name,role,verification_status,account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at) VALUES(?,'Synthetic','Consolidation','member','verified','active','1990-01-01','O+','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())");$s->execute(['consolidation032-'.bin2hex(random_bytes(8)).'-'.$tag.'@example.test']);return(int)$p->lastInsertId();}
function cr(int $owner):int{global $p;$p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,needed_datetime) VALUES(?,'O+',1,'Synthetic consolidation',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([$owner]);return(int)$p->lastInsertId();}
function cm(int $request,int $donor):int{global $p;$p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at,offer_cycle) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY),1)")->execute([$request,$donor]);return(int)$p->lastInsertId();}
if(in_array('--prepare',$argv,true)){
    if(Storage::consolidated()||is_file($stateFile))throw new LogicException('Prepare requires unapplied migration and no existing fixture checkpoint.');
    $p->beginTransaction();try{
        $owner=cu('owner');$donor=cu('donor');$ids=[];$requests=[];
        foreach(['legacy','credited','active','released'] as $i=>$kind){
            $r=cr($owner);$requests[]=$r;$m=cm($r,$donor);$status=in_array($kind,['legacy','credited'],true)?'CONFIRMED':($kind==='released'?'REJECTED':'PENDING');
            $s=$p->prepare('INSERT INTO donation_reports(match_id,donor_id,status,reported_at,occurred_at,workflow_state,completion_basis,confirmed_at) VALUES(?,?,?,UTC_TIMESTAMP(),?,?,?,?)');
            $occurred=$kind==='legacy'?null:gmdate('Y-m-d H:i:s',time()-7200-$i);
            $s->execute([$m,$donor,$status,$occurred,$status==='CONFIRMED'?'completed':($status==='REJECTED'?'rejected':'needs_review'),$kind==='legacy'?'legacy_admin':($kind==='credited'?'mutual':null),$status==='CONFIRMED'?gmdate('Y-m-d H:i:s'):null]);$id=(int)$p->lastInsertId();$ids[$kind]=$id;
            if($status==='CONFIRMED')$p->prepare("UPDATE matches SET status='COMPLETED' WHERE id=?")->execute([$m]);
            if($kind==='credited')$p->prepare("INSERT INTO donation_credits(report_id,match_id,donor_id,occurred_at,recorded_at,completion_basis) VALUES(?,?,?,?,UTC_TIMESTAMP(),'mutual')")->execute([$id,$m,$donor,$occurred]);
            if($kind!=='legacy')$p->prepare('INSERT INTO donation_report_holds(report_id,donor_id,started_at,released_at,release_type,release_basis) VALUES(?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?,?,?)')->execute([$id,$donor,$kind==='active'?null:gmdate('Y-m-d H:i:s'),$kind==='active'?null:($kind==='credited'?'donation_recorded':'no_donation'),$kind==='active'?null:'Synthetic preserved release']);
        }
        $matrix=$p->query('SELECT recipient_type,allowed_donor_types FROM compatibility_matrix')->fetchAll(PDO::FETCH_KEY_PAIR);
        $p->commit();file_put_contents($stateFile,json_encode(['users'=>[$owner,$donor],'requests'=>$requests,'reports'=>$ids,'matrix'=>$matrix],JSON_THROW_ON_ERROR));
        echo "Prepared four synthetic preservation cases on bloodmatch_test only.\n";
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}exit;
}
if(!Storage::consolidated())throw new LogicException('Migration 032 must be recorded.');
$state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
$p->beginTransaction();try{
    $repo=new DonationReportRepository();$users=new UserRepository();$service=new Completion();
    $legacy=$repo->findById($state['reports']['legacy']);cc($legacy['status']==='CONFIRMED'&&$legacy['occurred_at']===null&&$legacy['credit_recorded_at']===null,'Legacy confirmed report receives no invented date or credit');
    cc($repo->findById($state['reports']['credited'])['credit_recorded_at']!==null,'Existing credit timestamp copied');
    cc($repo->findById($state['reports']['active'])['hold_started_at']!==null&&$repo->findById($state['reports']['active'])['hold_released_at']===null,'Independent active hold copied');
    $released=$repo->findById($state['reports']['released']);cc($released['hold_released_at']!==null&&$released['hold_release_type']==='no_donation'&&$released['hold_release_basis']==='Synthetic preserved release','Released hold reason and state copied');
    $expected=[];foreach($state['matrix'] as $recipient=>$list)$expected[$recipient]=array_map('trim',explode(',',$list));$actual=BloodCompatibilityService::fullMatrix();ksort($actual);ksort($expected);cc($actual===$expected,'All compatibility pairs and preference ordering exactly preserved');
    cc(Mailer::send('synthetic@example.org','Synthetic','Synthetic'),'Fake transport accepts without external delivery');
    $owner=$users->findById($state['users'][0]);$donor=$users->findById($state['users'][1]);$admin=$users->findById((int)$p->query("SELECT id FROM users WHERE role='admin' AND account_status='active' LIMIT 1")->fetchColumn());
    $id=$released['id'];$service->review($admin,(int)$id,(int)$released['content_version'],'clarify','Synthetic reopening');$reopened=$repo->findById((int)$id);cc($reopened['hold_released_at']===null&&$reopened['content_version']==2,'Reopening restores only the original report hold and advances version');
    cc(count($service->events($owner,(int)$id))>=2,'Reopening retains append-only event history');
    $r=cr((int)$owner['id']);$m=cm($r,(int)$donor['id']);$duplicateTime=$repo->findById($state['reports']['credited'])['occurred_at'];$claim=$service->submit($donor,$m,str_replace(' ','T',$duplicateTime).'Z','Synthetic duplicate claim');
    try{$service->acknowledge($owner,(int)$claim['id'],1,'received',null);throw new LogicException('Duplicate occurrence accepted');}catch(RuntimeException $e){cc((int)$e->getCode()===409,'Duplicate donor occurrence is refused by confirmation');}
    sqlDenied("UPDATE donation_reports SET status='CONFIRMED',workflow_state='completed',completion_basis='mutual',credit_recorded_at=UTC_TIMESTAMP() WHERE id=?",[$claim['id']],'Conditional credited donor/occurrence uniqueness rejects direct duplicate');
    sqlDenied('INSERT INTO donation_reports(match_id,donor_id,reported_at) VALUES(?,?,UTC_TIMESTAMP())',[$m,$donor['id']],'Unique match rejects second report');
    $r2=cr((int)$owner['id']);$m2=cm($r2,(int)$donor['id']);
    sqlDenied('INSERT INTO donation_reports(match_id,donor_id,reported_at) VALUES(?,?,UTC_TIMESTAMP())',[$m2,$owner['id']],'Composite FK rejects mismatched report donor');
    sqlDenied('UPDATE donation_reports SET content_version=0 WHERE id=?',[$claim['id']],'Positive report version enforced');
    sqlDenied("UPDATE donation_reports SET workflow_state='invalid' WHERE id=?",[$claim['id']],'Workflow state domain enforced');
    sqlDenied('UPDATE blood_requests SET quantity_units=0 WHERE id=?',[$r2],'Positive request quantity enforced');
    sqlDenied('UPDATE blood_requests SET renewed_from_id=? WHERE id=?',[1844674407,$r2],'Renewal parent FK enforced');
    $p->prepare('UPDATE blood_requests SET renewed_from_id=? WHERE id=?')->execute([$r,$r2]);$r3=cr((int)$owner['id']);
    sqlDenied('UPDATE blood_requests SET renewed_from_id=? WHERE id=?',[$r,$r3],'One successor per renewed request enforced');
    $p->prepare('INSERT INTO donation_chat_contexts(match_id,offer_cycle,accepted_at,responded_at) VALUES(?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$m2]);$context=(int)$p->lastInsertId();
    sqlDenied('UPDATE donation_reports SET offer_context_id=? WHERE id=?',[$context,$claim['id']],'Report context must belong to same match');
    sqlDenied("INSERT INTO compatibility_pairs VALUES('O+','O+',8)",[],'Duplicate compatibility pair refused');
    // Exercise the actual queue/proof/retention read SQL, without running retention mutations.
    cc(count(Workflow::listFor($owner))>=4,'Consolidated participant queue executes');
    cc(is_array(RetentionService::run(false)),'Consolidated retention dry-run SQL executes');
    echo "$checks consolidation database checks passed; transient assertions rolled back.\n";
}finally{$p->rollBack();}

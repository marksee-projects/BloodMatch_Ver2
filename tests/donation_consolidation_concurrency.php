<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';
require dirname(__DIR__).'/database/operational_checkpoint_support.php';
use BloodMatch\Services\DonationCompletionService as Completion;
use BloodMatch\Repositories\{DonationReportRepository,UserRepository,DonationStorageRepository};
$p=operationalTarget('test');
if(\BloodMatch\Config\Env::get('BLOODMATCH_TEST_ISOLATION')!=='1'||!DonationStorageRepository::consolidated())throw new LogicException('Isolated migrated test target required.');
$p->exec('SET SESSION innodb_lock_wait_timeout=5');
if(($argv[1]??'')==='--worker'){
    [$script,$flag,$actor,$record,$action,$barrier,$occurred]=$argv;
    $deadline=microtime(true)+15;while(!is_file($barrier)){if(microtime(true)>$deadline)exit(2);usleep(10000);}
    try{
        $a=(new UserRepository())->findById((int)$actor);$service=new Completion();
        $result=match($action){
            'ack'=>$service->acknowledge($a,(int)$record,1,'received',null),
            'reject'=>$service->review($a,(int)$record,1,'reject','Synthetic concurrency rejection'),
            'submit'=>$service->submit($a,(int)$record,$occurred,'Synthetic concurrent claim'),
        };
        echo json_encode(['ok'=>true,'changed'=>$result['changed']??false]);
    }catch(Throwable $e){echo json_encode(['ok'=>false,'code'=>(int)$e->getCode()]);}
    exit;
}
$checks=0;$fixtureUsers=[];$fixtureRequests=[];
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function user032(string $tag):array{global $p,$fixtureUsers;$s=$p->prepare("INSERT INTO users(email,first_name,last_name,role,verification_status,account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at) VALUES(?,'Synthetic','Race','member','verified','active','1990-01-01','O+','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())");$s->execute(['race032-'.bin2hex(random_bytes(8)).'-'.$tag.'@example.test']);$id=(int)$p->lastInsertId();$fixtureUsers[]=$id;return(new UserRepository())->findById($id);}
function request032(int $owner,int $donor):array{global $p,$fixtureRequests;$p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,needed_datetime) VALUES(?,'O+',1,'Synthetic concurrency',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([$owner]);$r=(int)$p->lastInsertId();$fixtureRequests[]=$r;$p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY))")->execute([$r,$donor]);return[$r,(int)$p->lastInsertId()];}
function race032(array $jobs):array{
    $barrier=tempnam(sys_get_temp_dir(),'bm032-');unlink($barrier);$children=[];
    try{
        foreach($jobs as [$actor,$record,$action,$occurred]){
            $pipes=[];$process=proc_open([PHP_BINARY,'-d','auto_prepend_file='. __DIR__.'/helpers/consolidation_isolation.php',__FILE__,'--worker',(string)$actor,(string)$record,$action,$barrier,$occurred],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
            if(!is_resource($process))throw new RuntimeException('Cannot start isolated race worker.');fclose($pipes[0]);$children[]=[$process,$pipes];
        }
        file_put_contents($barrier,'go');$results=[];
        foreach($children as [$process,$pipes]){
            $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
            if($exit!==0||$stderr!=='')throw new RuntimeException('Race worker failed (output suppressed; no secrets emitted).');
            $results[]=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
        }
        return $results;
    }finally{if(is_file($barrier))unlink($barrier);}
}
function row032(int $id):array{return(new DonationReportRepository())->findById($id);}
function credited032(array $ids):int{global $p;$s=$p->prepare('SELECT COUNT(*) FROM donation_reports WHERE credit_recorded_at IS NOT NULL AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$s->execute($ids);return(int)$s->fetchColumn();}
$service=new Completion();$owner=user032('owner');$donor=user032('donor');$admin=(int)$p->query("SELECT id FROM users WHERE role='admin' AND account_status='active' LIMIT 1")->fetchColumn();if(!$admin)throw new LogicException('Test administrator required.');
$occurrence=gmdate('Y-m-d\TH:i:s\Z',time()-3600);
[$r,$m]=request032((int)$owner['id'],(int)$donor['id']);
$submitted=race032([[$donor['id'],$m,'submit',$occurrence],[$donor['id'],$m,'submit',$occurrence]]);
ck(count(array_filter($submitted,fn($x)=>$x['ok']))===2&&array_sum(array_column($submitted,'changed'))===1,'Concurrent identical submissions produce one change and one silent retry');
$s=$p->prepare('SELECT id FROM donation_reports WHERE match_id=?');$s->execute([$m]);$ids=$s->fetchAll(PDO::FETCH_COLUMN);ck(count($ids)===1&&row032((int)$ids[0])['hold_started_at']!==null,'Concurrent submit creates exactly one report and hold');$id=(int)$ids[0];
$acks=race032([[$owner['id'],$id,'ack','-'],[$owner['id'],$id,'ack','-']]);
ck(count(array_filter($acks,fn($x)=>$x['ok']))===2&&array_sum(array_column($acks,'changed'))===1,'Concurrent acknowledgments produce one completion and one idempotent retry');
ck(credited032([$id])===1&&row032($id)['hold_released_at']!==null,'Concurrent confirmation records one credit and releases its hold');
$s=$p->prepare("SELECT COUNT(*) FROM donation_report_events WHERE report_id=? AND event_type='mutually_acknowledged'");$s->execute([$id]);ck((int)$s->fetchColumn()===1,'Concurrent completion records exactly one completion event');
$donor2=user032('conflict');[$r2,$m2]=request032((int)$owner['id'],(int)$donor2['id']);$id2=(int)$service->submit($donor2,$m2,$occurrence,'Synthetic decision race')['id'];
$decisions=race032([[$owner['id'],$id2,'ack','-'],[$admin,$id2,'reject','-']]);
ck(count(array_filter($decisions,fn($x)=>$x['ok']&&$x['changed']))===1,'Confirmation versus rejection has exactly one final winner');
$row=row032($id2);ck(($row['status']==='CONFIRMED'&&credited032([$id2])===1&&$row['hold_released_at']!==null)||($row['status']==='REJECTED'&&credited032([$id2])===0&&$row['hold_released_at']===null),'Winning decision keeps credit and independent hold consistent');
$donor3=user032('duplicate');$reports=[];foreach([1,2] as $i){[$rq,$mq]=request032((int)$owner['id'],(int)$donor3['id']);$reports[]=(int)$service->submit($donor3,$mq,$occurrence,'Synthetic duplicate race')['id'];}
$duplicates=race032([[$owner['id'],$reports[0],'ack','-'],[$owner['id'],$reports[1],'ack','-']]);
ck(count(array_filter($duplicates,fn($x)=>$x['ok']))===1&&credited032($reports)===1,'Concurrent different claims at same donor occurrence award exactly one credit');
$loser=array_values(array_filter($reports,fn($rid)=>row032($rid)['credit_recorded_at']===null))[0];ck(row032($loser)['requester_response']===null&&row032($loser)['hold_released_at']===null,'Losing duplicate transaction rolls acknowledgment back and retains hold');
$donor4=user032('rollback');[$rf,$mf]=request032((int)$owner['id'],(int)$donor4['id']);$idf=(int)$service->submit($donor4,$mf,$occurrence,'Synthetic atomic rollback')['id'];
$trigger='consolidation032_atomic_failure';
try{
    $p->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON blood_requests FOR EACH ROW BEGIN IF NEW.id=$rf AND NEW.status='FULFILLED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fulfillment failure'; END IF; END");
    $failure=race032([[$owner['id'],$idf,'ack','-']]);ck(!$failure[0]['ok'],'Injected fulfillment failure reaches the transaction boundary');
    $row=row032($idf);ck($row['status']==='PENDING'&&$row['requester_response']===null&&$row['credit_recorded_at']===null&&$row['hold_released_at']===null,'Fulfillment failure rolls back report, acknowledgment, credit and hold release');
    $s=$p->prepare('SELECT status FROM matches WHERE id=?');$s->execute([$mf]);ck($s->fetchColumn()==='RESPONDED','Fulfillment failure rolls back match completion');
    ck((new UserRepository())->findById((int)$donor4['id'])['last_verified_donation_at']===null,'Fulfillment failure rolls back restriction anchor');
}finally{$p->exec("DROP TRIGGER IF EXISTS $trigger");}
$retry=$service->acknowledge($owner,$idf,1,'received',null);ck($retry['changed']&&credited032([$idf])===1,'Retry after rolled-back failure completes once');
file_put_contents(dirname(__DIR__).'/backend/storage/maintenance/consolidation032-race-fixtures.json',json_encode(['users'=>$fixtureUsers,'requests'=>$fixtureRequests,'checks'=>$checks],JSON_THROW_ON_ERROR));
echo "$checks multi-process concurrency/atomicity checks passed; synthetic committed history retained on disposable test target only.\n";

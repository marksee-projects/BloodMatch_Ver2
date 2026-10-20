<?php
declare(strict_types=1);
// Execute the real service/evaluator over an in-memory PDO adapter and synthetic notifications.
// This proves orchestration, not MySQL SQL semantics or cross-process locking.
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Services\{NonResponseStandbyService as Standby,BloodCompatibilityService,SystemSettingsService};

final class NrMemoryPdo extends PDO
{
    public array $timers=[],$users=[],$requests=[],$matches=[],$states=[],$notifications=[],$audits=[];
    public string $now; public bool $failAudit=false; private ?array $snapshot=null;
    public function __construct() { $this->now=gmdate('Y-m-d H:i:s').'.000000'; }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        $s=new NrMemoryStatement($this,$query);$s->execute();return $s;
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new NrMemoryStatement($this,$query); }
    public function inTransaction(): bool { return $this->snapshot!==null; }
    public function beginTransaction(): bool {
        $this->snapshot=[$this->timers,$this->states,$this->notifications,$this->audits]; return true;
    }
    public function commit(): bool { $this->snapshot=null;return true; }
    public function rollBack(): bool {
        [$this->timers,$this->states,$this->notifications,$this->audits]=$this->snapshot;$this->snapshot=null;return true;
    }
}
final class NrMemoryStatement extends PDOStatement
{
    private array $rows=[];private int $pos=0;
    public function __construct(private NrMemoryPdo $db,private string $sql) {}
    public function execute(?array $params=null): bool {
        $p=$params??[];$d=$this->db;$sql=preg_replace('/\s+/',' ',trim($this->sql));$this->rows=[];$this->pos=0;
        if(str_contains($sql,'information_schema.TABLES'))$this->rows=[[2]];
        elseif(str_starts_with($sql,'SELECT COUNT(*) FROM schema_migrations'))$this->rows=[[1]];
        elseif($sql==='SELECT UTC_TIMESTAMP(6)')$this->rows=[[$d->now]];
        elseif(str_starts_with($sql,'SELECT standby_started_at,consumed_through'))$this->rows=isset($d->states[$p[0]])?[$d->states[$p[0]]]:[];
        elseif(str_starts_with($sql,'SELECT id,request_id,donor_id FROM donor_opportunities')) {
            foreach($d->timers as $t)if($t['status']==='pending'&&$t['due_at']<=$p[0]&&$t['id']>$p[1]&&(!str_contains($sql,'id IN')||in_array($t['id'],array_slice($p,2),true)))$this->rows[]=$t;
            usort($this->rows,fn($a,$b)=>$a['id']<=>$b['id']);$this->rows=array_slice($this->rows,0,100);
        } elseif(str_starts_with($sql,'SELECT * FROM donor_opportunities'))$this->rows=isset($d->timers[$p[0]])?[$d->timers[$p[0]]]:[];
        elseif(str_starts_with($sql,'SELECT generation FROM donor_opportunities')) {
            $rows=array_filter($d->timers,fn($t)=>$t['request_id']===$p[0]&&$t['donor_id']===$p[1]);
            if($rows)$this->rows=[[max(array_column($rows,'generation'))]];
        } elseif(str_starts_with($sql,'INSERT INTO donor_opportunities')) {
            foreach($d->timers as $t)if($t['request_id']===$p[0]&&$t['donor_id']===$p[1]&&$t['generation']===$p[2])return true;
            preg_match('/INTERVAL (\d+) HOUR/',$sql,$hours);if(!$hours)throw new LogicException('Duration missing');
            $id=count($d->timers)+1;$d->timers[$id]=['id'=>$id,'request_id'=>$p[0],'donor_id'=>$p[1],'generation'=>$p[2],'notification_id'=>$p[3],
                'notified_at'=>$d->now,'due_at'=>(new DateTimeImmutable($d->now,new DateTimeZone('UTC')))->modify('+'.$hours[1].' hours')->format('Y-m-d H:i:s.u'),'status'=>'pending'];
        } elseif(str_starts_with($sql,"UPDATE donor_opportunities SET status='superseded'")) {
            foreach($d->timers as &$t)if($t['request_id']===$p[0]&&$t['donor_id']===$p[1]&&$t['generation']<$p[2]&&$t['status']==='pending')$t['status']='superseded';unset($t);
        } elseif(str_starts_with($sql,"UPDATE donor_opportunities SET status='invalidated'"))$d->timers[$p[0]]['status']='invalidated';
        elseif(str_starts_with($sql,'INSERT INTO donor_response_state'))$d->states[$p[0]]=['standby_started_at'=>$p[1],'consumed_through'=>$d->states[$p[0]]['consumed_through']??null];
        elseif(str_starts_with($sql,"UPDATE donor_opportunities SET status='acknowledged'")) {
            foreach($d->timers as &$t)if($t['donor_id']===$p[0]&&$t['status']==='pending'&&$t['due_at']<=$p[1])$t['status']='acknowledged';unset($t);
        } elseif(str_starts_with($sql,'UPDATE donor_response_state SET standby_started_at=NULL'))$d->states[$p[1]]=['standby_started_at'=>null,'consumed_through'=>$p[0]];
        else throw new LogicException('Unsupported memory SQL: '.$sql);
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->rows[$this->pos++]??false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column=0): mixed { $r=$this->rows[$this->pos++]??false;return $r===false?false:array_values($r)[$column]; }
}
final class NrMemoryUsers { public function findByIdForUpdate(int $id): ?array { global $db;return $db->users[$id]??null; } }
final class NrMemoryRequests { public function findByIdForUpdate(int $id): ?array { global $db;return $db->requests[$id]??null; } }
final class NrMemoryMatches { public function findByRequestAndDonorForUpdate(int $r,int $d): ?array { global $db;return $db->matches["$r:$d"]??null; } }
final class NrMemoryNotifications {
    public static function notify(int $id,string $type,string $title,string $body,array $options=[]): int { global $db;$db->notifications[]=compact('id','type','title','body','options');return count($db->notifications); }
}
final class NrMemoryAudit {
    public static function log(?int $actor,string $action,?string $target,?string $id,array $context=[]): ?int {
        global $db;if($db->failAudit)return null;$db->audits[]=compact('action','id','context');return count($db->audits);
    }
}
class_alias(NrMemoryUsers::class,'BloodMatch\\Repositories\\UserRepository');
class_alias(NrMemoryRequests::class,'BloodMatch\\Repositories\\BloodRequestRepository');
class_alias(NrMemoryMatches::class,'BloodMatch\\Repositories\\MatchRepository');
class_alias(NrMemoryNotifications::class,'BloodMatch\\Services\\NotificationService');
class_alias(NrMemoryAudit::class,'BloodMatch\\Services\\AuditLogger');
$db=new NrMemoryPdo();(new ReflectionProperty(Database::class,'pdo'))->setValue(null,$db);
(new ReflectionProperty(BloodCompatibilityService::class,'cache'))->setValue(null,['O+'=>['O+','O-']]);
(new ReflectionProperty(SystemSettingsService::class,'cache'))->setValue(null,['standby_hours'=>42,'cooldown_days'=>90]);
$checks=0;
function nrAssert(bool $ok,string $label): void { global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n"; }
function nrMemoryFixture(): int {
    global $db;$id=count($db->timers)+1;$db->users[$id]=['id'=>$id,'role'=>'member','account_status'=>'active','verification_status'=>'verified','email_verified_at'=>'2026-01-01 00:00:00',
        'donor_enrolled_at'=>'2026-01-01 00:00:00','donor_availability'=>'available','date_of_birth'=>'1990-01-01','has_parental_consent'=>false,'pending_report_hold'=>false,'non_response_standby'=>null,'last_verified_donation_at'=>null,'blood_type'=>'O+'];
    $db->requests[$id]=['id'=>$id,'requester_id'=>99999,'status'=>'OPEN','needed_datetime'=>gmdate('Y-m-d H:i:s',time()+86400),'required_blood_type'=>'O+'];
    $db->matches["$id:$id"]=['status'=>'NOTIFIED','generation'=>1];
    \BloodMatch\Services\WorkflowLockService::transaction(fn()=>Standby::opportunity($id,$id,1,$id));return $id;
}
$id=nrMemoryFixture();nrAssert(strtotime($db->timers[$id]['due_at'])-strtotime($db->timers[$id]['notified_at'])===172800,'real service issues a48h deadline through SQL adapter');
nrAssert(Standby::processDue([$id])===0,'future48h obligation not processed');
$db->timers[$id]['due_at']=substr($db->now,0,19).'.000001';
nrAssert(Standby::processDue([$id])===0,'one microsecond before deadline is not due');
$db->timers[$id]['due_at']=$db->now;
$db->failAudit=true;try{Standby::processDue([$id]);throw new LogicException('Expected audit failure');}catch(RuntimeException $e){nrAssert($e->getCode()===503&&$db->notifications===[]&&$db->states===[],'audit failure rolls transition and synthetic notification back');}$db->failAudit=false;
nrAssert(Standby::processDue([$id])===1&&Standby::processDue([$id])===0,'exact stored boundary triggers once and retry is silent');
$state=$db->states[$id];$snapshot=$db->timers[$id];
\BloodMatch\Services\WorkflowLockService::transaction(fn()=>Standby::opportunity($id,$id,1,999));
nrAssert($db->timers[$id]===$snapshot&&$db->states[$id]===$state,'same-generation retry preserves timer and active standby');
nrAssert(Standby::acknowledge($id)&&!Standby::acknowledge($id)&&$db->timers[$id]['status']==='acknowledged','explicit acknowledgment remains idempotent');
foreach(['CANCELLED','EXPIRED','FULFILLED','overdue','own_request','answered_match'] as $kind){
    $i=nrMemoryFixture();$db->timers[$i]['due_at']=$db->now;
    if($kind==='overdue')$db->requests[$i]['needed_datetime']=gmdate('Y-m-d H:i:s',time()-3600);
    elseif($kind==='own_request')$db->requests[$i]['requester_id']=$i;
    elseif($kind==='answered_match')$db->matches["$i:$i"]['status']='RESPONDED';
    else $db->requests[$i]['status']=$kind;
    $request=$db->requests[$i];$match=$db->matches["$i:$i"];
    nrAssert(Standby::processDue([$i])===0&&$db->timers[$i]['status']==='invalidated',$kind.' retires only the obsolete timer');
    nrAssert($db->requests[$i]===$request&&$db->matches["$i:$i"]===$match,$kind.' does not mutate request or answered match');
}
foreach([
    'staff'=>['role'=>'admin'],'inactive'=>['account_status'=>'deactivated'],'pending'=>['verification_status'=>'pending'],'rejected'=>['verification_status'=>'rejected'],
    'email'=>['email_verified_at'=>null],'enrollment'=>['donor_enrolled_at'=>null],'hold'=>['pending_report_hold'=>true],
    'age'=>['date_of_birth'=>gmdate('Y-m-d',strtotime('-14 years'))],'consent'=>['date_of_birth'=>gmdate('Y-m-d',strtotime('-17 years'))],
    'availability'=>['donor_availability'=>'unavailable'],'unanchored standby'=>['donor_availability'=>'standby'],
    'blood missing'=>['blood_type'=>null],'blood incompatible'=>['blood_type'=>'AB+'],
    'post-donation standby'=>['last_verified_donation_at'=>gmdate('Y-m-d H:i:s',time()-3600)],
    'cooldown'=>['last_verified_donation_at'=>gmdate('Y-m-d H:i:s',time()-5*86400)],
] as $label=>$changes){
    $i=nrMemoryFixture();$db->timers[$i]['due_at']=$db->now;$original=$db->users[$i];$match=$db->matches["$i:$i"];$db->users[$i]=array_replace($original,$changes);
    nrAssert(Standby::processDue([$i])===0&&$db->timers[$i]['status']==='invalidated',$label.' excluded by actual shared evaluator');
    nrAssert($db->matches["$i:$i"]===$match,$label.' invalidation never alters match');
    $db->users[$i]=$original;\BloodMatch\Services\WorkflowLockService::transaction(fn()=>Standby::opportunity($i,$i,1,$i));
    nrAssert(Standby::processDue([$i])===0&&$db->timers[$i]['status']==='invalidated',$label.' recovery/retry cannot revive timer');
}
$legacy=nrMemoryFixture();$db->timers[$legacy]['notified_at']=gmdate('Y-m-d H:i:s',strtotime($db->now.' UTC')-42*3600).'.000000';$db->timers[$legacy]['due_at']=$db->now;$snapshot=$db->timers[$legacy];
\BloodMatch\Services\WorkflowLockService::transaction(fn()=>Standby::opportunity($legacy,$legacy,1,$legacy));
nrAssert($db->timers[$legacy]===$snapshot&&Standby::processDue([$legacy])===1,'legacy42h deadline retained and honored');
$g=nrMemoryFixture();$db->timers[$g]['due_at']=$db->now;\BloodMatch\Services\WorkflowLockService::transaction(fn()=>Standby::opportunity($g,$g,2,$g));$newId=count($db->timers);
nrAssert($db->timers[$g]['status']==='superseded'&&Standby::processDue([$g])===0,'new generation supersedes pending legacy timer');
nrAssert($db->timers[$newId]['generation']===2&&strtotime($db->timers[$newId]['due_at'])-strtotime($db->now)===172800,'genuine new issuance gets fresh48h obligation');
$db->matches["$g:$g"]['generation']=2;
$db->timers[$newId]['due_at']=$db->now;
nrAssert(Standby::processDue([$newId])===1,'genuine new generation can eventually create a response obligation');
$unrelated=nrMemoryFixture();$db->timers[$unrelated]['due_at']=$db->now;$before=$db->timers[$unrelated];
nrAssert(Standby::processDue([$g])===0&&$db->timers[$unrelated]===$before,'scoped processing does not touch unrelated due opportunity');
$batch=[];for($j=0;$j<105;$j++){$next=nrMemoryFixture();$db->timers[$next]['due_at']=$db->now;$batch[]=$next;}
nrAssert(Standby::processDue($batch)===105&&Standby::processDue($batch)===0,'keyset processing drains multiple100-row batches once');
nrAssert(Standby::processDue([])===0,'empty internal scope processes nothing');
try{Standby::processDue(['1']);throw new LogicException('Expected invalid scope');}catch(InvalidArgumentException){nrAssert(true,'noninteger scope rejected before mutation');}
echo "$checks in-memory service checks passed; no SQL server, files, SMTP or real locks exercised.\n";

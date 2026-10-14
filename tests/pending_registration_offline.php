<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Services\PendingRegistrationService;
final class PendingStatement extends PDOStatement {
    private array $params=[];
    public function __construct(private PendingPDO $db,private string $sql) {}
    public function execute(?array $params=null): bool {
        $this->params=$params??[];
        if (str_contains($this->sql,'INSERT INTO users')) {
            if ($this->db->failCreate) throw new RuntimeException('Simulated insert failure.');
            $this->db->users[]=$this->params;
        }
        if (str_contains($this->sql,'DELETE FROM pending_registrations')) $this->db->pending=null;
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed {return str_contains($this->sql,'FROM pending_registrations')?($this->db->pending??false):false;}
}
final class PendingPDO extends PDO {
    public ?array $pending=null;
    public array $users=[];
    public bool $failCreate=false;
    private bool $transaction=false;
    private array $snapshot=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false {return new PendingStatement($this,$query);}
    public function inTransaction(): bool {return $this->transaction;}
    public function beginTransaction(): bool {$this->snapshot=[$this->pending,$this->users];$this->transaction=true;return true;}
    public function commit(): bool {$this->transaction=false;return true;}
    public function rollBack(): bool {[$this->pending,$this->users]=$this->snapshot;$this->transaction=false;return true;}
    public function lastInsertId(?string $name=null): string|false {return '101';}
}
$db=new PendingPDO();(new ReflectionProperty(Database::class,'pdo'))->setValue(null,$db);
$payload=['email'=>'fixture@example.test','password_hash'=>password_hash('Fixture123',PASSWORD_BCRYPT),'first_name'=>'Test','middle_name'=>null,'last_name'=>'Member','phone'=>null,'role'=>'member','chapter_id'=>1,'verification_status'=>'pending','account_status'=>'active','date_of_birth'=>'2000-01-01','blood_type'=>'O+','blood_type_source'=>'self_reported','blood_type_verified'=>0,'location_id'=>42,'latitude'=>14.0,'longitude'=>120.0];
$row=['id'=>1,'email'=>$payload['email'],'payload_json'=>json_encode($payload),'document_json'=>null,'code_hash'=>password_hash('123456',PASSWORD_BCRYPT),'code_expires_at'=>gmdate('Y-m-d H:i:s',time()+600),'expires_at'=>gmdate('Y-m-d H:i:s',time()+86400)];
$count=0;
function checkPending(bool $ok,string $label): void {global $count;if (!$ok) throw new LogicException('FAIL '.$label);$count++;echo 'PASS '.$label.PHP_EOL;}
foreach(['','654321','12345'] as $code) {
    $db->pending=$row;try {PendingRegistrationService::verify($payload['email'],$code);throw new LogicException('Expected denial.');}catch(RuntimeException){}
    checkPending(count($db->users)===0 && $db->pending!==null,'Missing/invalid OTP does not create an account');
}
$db->pending=array_replace($row,['code_expires_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
try {PendingRegistrationService::verify($payload['email'],'123456');throw new LogicException('Expected expired denial.');}catch(RuntimeException){}
checkPending(count($db->users)===0,'Expired code creates no account');
$db->pending=$row;$db->failCreate=true;
try {PendingRegistrationService::verify($payload['email'],'123456');throw new LogicException('Expected failed insert.');}catch(RuntimeException){}
checkPending($db->pending!==null && !$db->inTransaction(),'Insert failure rolls back and preserves pending signup');
$db->failCreate=false;
checkPending(PendingRegistrationService::verify($payload['email'],'123456'),'Valid code creates account');
checkPending(count($db->users)===1 && $db->pending===null,'Pending OTP is consumed atomically');
checkPending($db->users[0][8]==='pending','Email verification does not grant membership verification');
checkPending($db->users[0][14]===42,'Canonical registration location reaches the account insert');
checkPending(password_verify('Fixture123',$db->users[0][1]),'Only a password hash is carried into the account');
checkPending(!PendingRegistrationService::verify($payload['email'],'123456') && count($db->users)===1,'Reused code creates no duplicate');
echo "PASS $count pending-registration checks; connectionless PDO, no database/mail.\n";

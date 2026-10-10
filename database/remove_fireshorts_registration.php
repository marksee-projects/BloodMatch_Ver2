<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Services\DocumentStorageService;
use BloodMatch\Services\ProfilePictureStorageService;

// One explicitly user-approved fresh registration; never a general account-delete API.
const REMOVAL_ID=136;
const REMOVAL_EMAIL='fireshorts395@gmail.com';

function registrationRemovalPath(array $file): ?string
{
    if (!in_array($file['kind']??null,['document','picture'],true)||!preg_match('/^[0-9a-f]{64}$/D',$file['name']??'')
        ||!preg_match('/^[0-9a-f]{64}$/D',$file['sha256']??'')) { throw new LogicException('Invalid owned-file reference.'); }
    $base=$file['kind']==='document'?DocumentStorageService::baseDir():ProfilePictureStorageService::baseDir();
    $path=$file['kind']==='document'?DocumentStorageService::pathFor($file['name']):ProfilePictureStorageService::pathFor($file['name']);
    if (is_link($path)) { throw new LogicException('File symlink refused.'); }
    if (!file_exists($path)) { return null; }
    $root=realpath($base);$resolved=realpath($path);
    if ($root===false||$resolved===false||!is_file($resolved)
        ||strtolower(str_replace('\\','/',dirname($resolved)))!==strtolower(str_replace('\\','/',$root))) { throw new LogicException('File outside owned storage directory.'); }
    if (!hash_equals($file['sha256'],(string)hash_file('sha256',$resolved))) { throw new LogicException('Owned file changed; stop.'); }
    return $resolved;
}

function registrationRemovalDigest(PDO $pdo,string $table,int $through): string
{
    $columns=match($table) {
        'audit_log'=>'id,action,target_type,target_id,context,created_at', // permits existing actor FK -> NULL
        'email_attempts'=>'id,mail_class,attempted_at',
        default=>throw new LogicException('Invalid protected table.'),
    };
    $s=$pdo->prepare("SELECT $columns FROM $table WHERE id<=? ORDER BY id");$s->execute([$through]);$h=hash_init('sha256');
    while ($row=$s->fetch(PDO::FETCH_NUM)) { hash_update($h,serialize($row)); }
    return hash_final($h);
}

$pdo=null;$locked=false;
try {
    if (count($argv)>2||(isset($argv[1])&&!in_array($argv[1],['--review','--delete'],true))) { throw new LogicException('Use --review or --delete.'); }
    $delete=($argv[1]??'--review')==='--delete';
    foreach (['DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_NAME'=>'bloodmatch'] as $key=>$value) { putenv($key.'='.$value);$_ENV[$key]=$value; }
    Env::load(dirname(__DIR__).'/.env');$pdo=Database::pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='bloodmatch'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3306) { throw new LogicException('Wrong target; no action.'); }
    $q=static function(string $sql,array $args=[]) use($pdo):PDOStatement { $s=$pdo->prepare($sql);$s->execute($args);return $s; };
    $journalPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'bloodmatch-remove-registration-136.json';$journal=null;
    if (is_link($journalPath)) { throw new LogicException('Journal symlink refused.'); }
    if (is_file($journalPath)) {
        $journal=json_decode((string)file_get_contents($journalPath),true,512,JSON_THROW_ON_ERROR);
        if (($journal['user_id']??null)!==REMOVAL_ID||($journal['email_sha256']??null)!==hash('sha256',REMOVAL_EMAIL)||!is_array($journal['files']??null)) { throw new LogicException('Unexpected recovery journal.'); }
    }
    if ($delete) {
        $locked=(int)$q("SELECT GET_LOCK('bm-remove-registration-136',0)")->fetchColumn()===1;
        if (!$locked) { throw new LogicException('Removal is already running.'); }
        $pdo->beginTransaction();
    }
    $user=$q('SELECT id,email,role,account_status,verification_status,email_verified_at,donor_enrolled_at,last_verified_donation_at,profile_picture FROM users WHERE id=?'.($delete?' FOR UPDATE':''),[REMOVAL_ID])->fetch(PDO::FETCH_ASSOC);
    if ($user!==false) {
        if ($user['email']!==REMOVAL_EMAIL||$user['role']!=='member'||$user['verification_status']!=='pending'||!empty($user['email_verified_at'])
            ||!empty($user['donor_enrolled_at'])||!empty($user['last_verified_donation_at'])) { throw new LogicException('Registration is no longer the reviewed fresh pending account; stop.'); }
        $checks=[
            'requests'=>'SELECT COUNT(*) FROM blood_requests WHERE requester_id=?',
            'offers'=>'SELECT COUNT(*) FROM matches WHERE donor_id=?',
            'donation_history'=>'SELECT COUNT(*) FROM donation_reports WHERE donor_id=? OR confirmed_by=?',
            'verification_history'=>'SELECT COUNT(*) FROM verification_decisions WHERE target_user_id=? OR officer_id=?',
        ];
        foreach ($checks as $label=>$sql) {
            if ((int)$q($sql,str_contains($sql,' OR ')?[REMOVAL_ID,REMOVAL_ID]:[REMOVAL_ID])->fetchColumn()!==0) { throw new LogicException('Protected linked '.$label.' requires separate review; no removal.'); }
        }
        // Unknown references cannot silently expand the authorized cascade scope.
        $allowed=['audit_log.actor_id','member_documents.user_id','password_resets.user_id','notifications.user_id',
            'blood_requests.requester_id','matches.donor_id','donation_reports.donor_id','donation_reports.confirmed_by',
            'verification_decisions.target_user_id','verification_decisions.officer_id','donor_opportunities.donor_id','donor_response_state.user_id'];
        foreach ($q("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='users'")->fetchAll(PDO::FETCH_ASSOC) as $fk) {
            if (!in_array($fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME'],$allowed,true)) { throw new LogicException('Unknown user reference; no removal.'); }
        }
        foreach (['donor_response_state'=>'user_id','donor_opportunities'=>'donor_id'] as $table=>$column) {
            if ((int)$q('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->fetchColumn()===1
                &&(int)$q("SELECT COUNT(*) FROM $table WHERE $column=?",[REMOVAL_ID])->fetchColumn()!==0) { throw new LogicException('Donor timer/recovery history requires review; no removal.'); }
        }
    }
    $docs=$q('SELECT id,stored_name FROM member_documents WHERE user_id=? ORDER BY id'.($delete?' FOR UPDATE':''),[REMOVAL_ID])->fetchAll(PDO::FETCH_ASSOC);
    $files=[];
    foreach ($docs as $doc) {
        $path=DocumentStorageService::pathFor($doc['stored_name']);
        $files[]=['kind'=>'document','name'=>$doc['stored_name'],'sha256'=>is_file($path)?hash_file('sha256',$path):str_repeat('0',64)];
    }
    if ($user!==false&&!empty($user['profile_picture'])) {
        $path=ProfilePictureStorageService::pathFor($user['profile_picture']);
        $files[]=['kind'=>'picture','name'=>$user['profile_picture'],'sha256'=>is_file($path)?hash_file('sha256',$path):str_repeat('0',64)];
    }
    if ($journal!==null&&$user!==false&&$journal['files']!==$files) { throw new LogicException('Owned files changed after capture; stop.'); }
    if ($journal!==null) { $files=$journal['files']; }
    foreach ($files as $file) {
        registrationRemovalPath($file);
        $sql=$file['kind']==='document'?'SELECT COUNT(*) FROM member_documents WHERE stored_name=? AND user_id<>?':'SELECT COUNT(*) FROM users WHERE profile_picture=? AND id<>?';
        if ((int)$q($sql,[$file['name'],REMOVAL_ID])->fetchColumn()!==0) { throw new LogicException('File is shared by another account; no removal.'); }
    }
    if (!$delete) { echo json_encode(['database'=>'bloodmatch','port'=>3306,'user_id'=>REMOVAL_ID,'email'=>REMOVAL_EMAIL,'present'=>$user!==false,'documents'=>count($docs),'owned_files'=>count($files),'writes'=>false,'status'=>'review_only']).PHP_EOL;exit; }
    if ($user!==false) {
        if ($journal===null) {
            $journal=['user_id'=>REMOVAL_ID,'email_sha256'=>hash('sha256',REMOVAL_EMAIL),'files'=>$files];
            $h=fopen($journalPath,'x');if ($h===false) { throw new LogicException('Cannot create recovery journal.'); }
            try { $payload=json_encode($journal,JSON_THROW_ON_ERROR);if(fwrite($h,$payload)!==strlen($payload)||!fflush($h)){throw new LogicException('Cannot persist recovery journal.');} } finally { fclose($h); }
        }
        $protected=[];
        foreach (['audit_log','email_attempts'] as $table) { $max=(int)$q("SELECT COALESCE(MAX(id),0) FROM $table")->fetchColumn();$protected[$table]=[$max,registrationRemovalDigest($pdo,$table,$max)]; }
        $relevant="SELECT id FROM notifications WHERE user_id=? OR (related_type IN ('account','verification') AND related_id=?)";
        if ((int)$q("SELECT COUNT(*) FROM email_outbox WHERE status='leased' AND notification_id IN ($relevant)",[REMOVAL_ID,REMOVAL_ID])->fetchColumn()!==0) { throw new LogicException('Active email lease; stop before removal.'); }
        $q("DELETE FROM notifications WHERE user_id=? OR (related_type IN ('account','verification') AND related_id=?)",[REMOVAL_ID,REMOVAL_ID]);
        $keys=array_map(fn($prefix)=>$prefix.REMOVAL_EMAIL,['login:','reset:','verify:acct:','resend:acct:','resend:cd:']);
        $q('DELETE FROM auth_throttle WHERE identifier IN ('.implode(',',array_fill(0,count($keys),'?')).')',$keys);
        $q('INSERT INTO audit_log(actor_id,action,target_type,target_id,context) VALUES (NULL,?,?,?,?)',
            ['account.removed_user_requested','user',(string)REMOVAL_ID,json_encode(['source'=>'explicit user-approved local maintenance','reason'=>'remove fresh pending registration to register again','email_sha256'=>hash('sha256',REMOVAL_EMAIL),'documents_removed'=>count($docs)],JSON_THROW_ON_ERROR)]);
        $removed=$q('DELETE FROM users WHERE id=? AND CAST(email AS BINARY)=CAST(? AS BINARY)',[REMOVAL_ID,REMOVAL_EMAIL]);
        if ($removed->rowCount()!==1) { throw new LogicException('Removal ownership changed; rollback.'); }
        foreach ($protected as $table=>[$max,$digest]) { if (!hash_equals($digest,registrationRemovalDigest($pdo,$table,$max))) { throw new LogicException('Protected audit/accounting changed; rollback.'); } }
    }
    $pdo->commit();
    foreach ($files as $file) {
        $sql=$file['kind']==='document'?'SELECT COUNT(*) FROM member_documents WHERE stored_name=?':'SELECT COUNT(*) FROM users WHERE profile_picture=?';
        if ((int)$q($sql,[$file['name']])->fetchColumn()!==0) { throw new LogicException('File still referenced; journal retained.'); }
        $path=registrationRemovalPath($file);if ($path!==null&&!unlink($path)) { throw new LogicException('File deletion failed; journal retained for recovery.'); }
    }
    if (is_file($journalPath)&&!unlink($journalPath)) { throw new LogicException('Journal removal failed; retry for this original ID only.'); }
    $remaining=(int)$q('SELECT COUNT(*) FROM users WHERE CAST(LOWER(email) AS BINARY)=CAST(? AS BINARY)',[REMOVAL_EMAIL])->fetchColumn();
    echo json_encode(['status'=>$user===false?'original_account_already_absent':'removed','database'=>'bloodmatch','port'=>3306,'user_id'=>REMOVAL_ID,
        'remaining_email_matches'=>$remaining,'owned_file_cleanup'=>'complete','audit_history'=>'retained','attempt_accounting'=>'retained','journal_removed'=>true]).PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO&&$pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,$e instanceof LogicException?$e->getMessage().PHP_EOL:"Scoped removal failed; raw error withheld. Keep any recovery journal.\n");exit(1);
} finally { if ($locked&&$pdo instanceof PDO) { $pdo->query("SELECT RELEASE_LOCK('bm-remove-registration-136')"); } }

<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';
use BloodMatch\Services\DocumentStorageService;

// USER-RUN ONLY: exact latest run supplied in the 2026-10-10 inventory.
function latestApiFixtureUsers(): array
{
    $groups=[
        [1240,'p4','1843164037',['admin','offa','offb','member','nochapter','prom']],
        [1246,'p5','2011903800',['off','adm','pend','rej','other','prov','off2','offb','rejflow','age15False','age16False','age16True','age17False','age17True','age18False']],
        [1261,'p12','439947979',['adm','off1','off2','mem1','mem2','pend','don1','don2']],
    ];
    $result=[];
    foreach ($groups as [$start,$prefix,$suffix,$labels]) {
        foreach ($labels as $offset=>$label) { $result[$start+$offset]=$prefix.$label.$suffix.'@test.local'; }
    }
    return $result;
}

/** Single validated file, never a recursive/path-built shell deletion. */
function latestApiFixtureFile(array $entry): ?string
{
    if (!isset($entry['user_id'],$entry['stored_name'],$entry['sha256']) || $entry['user_id']<1246 || $entry['user_id']>1260
        || !preg_match('/^[0-9a-f]{64}$/D',$entry['stored_name']) || !preg_match('/^[0-9a-f]{64}$/D',$entry['sha256'])) {
        throw new LogicException('Invalid owned document manifest.');
    }
    $path=DocumentStorageService::pathFor($entry['stored_name']);
    if (is_link($path)) { throw new LogicException('Document symlink refused.'); }
    if (!file_exists($path)) { return null; }
    $base=realpath(DocumentStorageService::baseDir());$resolved=realpath($path);
    if ($base===false || $resolved===false || !is_file($resolved)
        || strtolower(str_replace('\\','/',dirname($resolved)))!==strtolower(str_replace('\\','/',$base))) {
        throw new LogicException('Document path is outside the approved storage directory.');
    }
    if (!hash_equals($entry['sha256'],(string)hash_file('sha256',$resolved))) { throw new LogicException('Fixture document changed; no file removal.'); }
    return $resolved;
}

function latestApiDigest(PDO $pdo,string $table,int $through): string
{
    $columns=match($table) {
        'audit_log'=>'id,action,target_type,target_id,context,created_at', // actor SET NULL is permitted FK cleanup
        'email_attempts'=>'id,mail_class,attempted_at',
        'users'=>'id,email,created_at',
        default=>throw new LogicException('Invalid verification table.'),
    };
    $exclude=$table==='users'?' AND id NOT IN ('.implode(',',array_keys(latestApiFixtureUsers())).')':'';
    $s=$pdo->prepare("SELECT $columns FROM $table WHERE id<=?$exclude ORDER BY id");$s->execute([$through]);
    $hash=hash_init('sha256');
    while ($row=$s->fetch(PDO::FETCH_NUM)) { hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n"); }
    return hash_final($hash);
}

$pdo=null;$locked=false;
try {
    if (count($argv)>2 || (isset($argv[1]) && !in_array($argv[1],['--review','--cleanup'],true))) { throw new LogicException('Use --review (default) or --cleanup.'); }
    $cleanup=($argv[1]??'--review')==='--cleanup';
    $pdo=emailCheckpointTarget();
    $q=static function(string $sql,array $args=[]) use ($pdo):PDOStatement { $s=$pdo->prepare($sql);$s->execute($args);return $s; };
    $users=latestApiFixtureUsers();$ids=array_keys($users);$marks=implode(',',array_fill(0,count($ids),'?'));
    $journalPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'bloodmatch-api-fixtures-1240-1268.json';
    if (is_link($journalPath)) { throw new LogicException('Cleanup journal symlink refused.'); }
    $journal=null;
    if (is_file($journalPath)) {
        $journal=json_decode((string)file_get_contents($journalPath),true,512,JSON_THROW_ON_ERROR);
        if (($journal['scope']??null)!=='latest-api-1240-1268-v1' || ($journal['users']??null)!==$users
            || !is_array($journal['documents']??null) || !is_array($journal['protected']??null)) { throw new LogicException('Unexpected cleanup journal; no removal.'); }
    }
    if ($cleanup) {
        $locked=(int)$q("SELECT GET_LOCK('bm-latest-api-fixture-cleanup',0)")->fetchColumn()===1;
        if (!$locked) { throw new LogicException('Another cleanup is running.'); }
        $pdo->beginTransaction();
    }
    $rows=$q("SELECT id,email,created_at,profile_picture FROM users WHERE id IN ($marks) ORDER BY id".($cleanup?' FOR UPDATE':''),$ids)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (($users[(int)$row['id']]??null)!==$row['email']) { throw new LogicException('User ID/email ownership mismatch.'); }
        if (!empty($row['profile_picture'])) { throw new LogicException('Unexpected fixture profile picture requires review.'); }
    }
    if ($rows!==[] && count($rows)!==29) { throw new LogicException('Unexpected partial group; inspect before cleanup.'); }
    $requests=$q("SELECT id,requester_id,facility_name FROM blood_requests WHERE requester_id IN ($marks) ORDER BY id".($cleanup?' FOR UPDATE':''),$ids)->fetchAll(PDO::FETCH_ASSOC);
    $facilities=[1264=>['Orani District Hospital','Orani Clinic','Orani Center','Orani Health'],1265=>['Mariveles Emergency Hospital']];
    foreach ($requests as $request) {
        if (!in_array($request['facility_name'],$facilities[(int)$request['requester_id']]??[],true)) { throw new LogicException('Unexpected fixture request; no removal.'); }
    }
    if (count($requests)>5) { throw new LogicException('Unexpected additional requests; no removal.'); }
    $requestIds=array_map('intval',array_column($requests,'id'));$requestMarks=implode(',',array_fill(0,count($requestIds),'?'));
    $reportCount=(int)$q("SELECT COUNT(*) FROM donation_reports WHERE donor_id IN ($marks)",$ids)->fetchColumn();
    if ($requestIds!==[]) { $reportCount+=(int)$q("SELECT COUNT(*) FROM donation_reports dr JOIN matches m ON m.id=dr.match_id WHERE m.request_id IN ($requestMarks)",$requestIds)->fetchColumn(); }
    if ($reportCount!==0) { throw new LogicException('Donation reports require separate review; no removal.'); }
    if ((int)$q("SELECT COUNT(*) FROM verification_decisions WHERE officer_id IN ($marks) AND target_user_id NOT IN ($marks)",array_merge($ids,$ids))->fetchColumn()!==0) { throw new LogicException('Reviewer referenced outside this run; no removal.'); }
    $outsideRequests=$requestIds===[]?'':" AND request_id NOT IN ($requestMarks)";
    if ((int)$q("SELECT COUNT(*) FROM matches WHERE donor_id IN ($marks)$outsideRequests",array_merge($ids,$requestIds))->fetchColumn()!==0) { throw new LogicException('Offers outside this run require review; no removal.'); }
    $documents=$journal['documents']??[];
    if ($journal!==null && $rows!==[]) {
        $currentDocs=$q("SELECT id,user_id,stored_name FROM member_documents WHERE user_id IN ($marks) ORDER BY id",$ids)->fetchAll(PDO::FETCH_ASSOC);
        $savedDocs=array_map(static fn($doc)=>['id'=>$doc['id'],'user_id'=>$doc['user_id'],'stored_name'=>$doc['stored_name']],$documents);
        if ($currentDocs!=$savedDocs || $requestIds!==$journal['request_ids']) { throw new LogicException('Fixture document/request set changed since journal capture; no removal.'); }
    }
    if ($journal===null) {
        foreach ($q("SELECT id,user_id,stored_name FROM member_documents WHERE user_id IN ($marks) ORDER BY id",$ids)->fetchAll(PDO::FETCH_ASSOC) as $doc) {
            if ($doc['user_id']<1246 || $doc['user_id']>1260 || !preg_match('/^[0-9a-f]{64}$/D',$doc['stored_name'])) { throw new LogicException('Unexpected document owner/reference.'); }
            $path=DocumentStorageService::pathFor($doc['stored_name']);
            if (is_link($path)) { throw new LogicException('Document symlink refused.'); }
            // A previously absent fixture file has no file-delete work to recover.
            $doc['sha256']=is_file($path)?hash_file('sha256',$path):str_repeat('0',64);
            $documents[]=$doc;
        }
    }
    $presentFiles=0;
    foreach ($documents as $doc) { if (latestApiFixtureFile($doc)!==null) { $presentFiles++; } }
    $summary=['database'=>'bloodmatch_test','port'=>3306,'scope'=>'latest-api-1240-1268-v1','users'=>count($rows),
        'user_ids'=>array_map('intval',array_column($rows,'id')),'request_ids'=>$requestIds,'documents'=>count($documents),
        'files_present'=>$presentFiles,'journal'=>$journal===null?null:$journalPath];
    if (!$cleanup) { echo json_encode($summary+['writes'=>false,'status'=>$rows===[]&&$presentFiles===0?'already_absent':'review_only']).PHP_EOL;exit; }
    if ($rows===[] && $journal===null) { $pdo->commit();echo json_encode($summary+['status'=>'already_absent','files'=>'untracked; no file deletion']).PHP_EOL;exit; }
    if ($journal===null) {
        $protected=[];
        foreach (['audit_log','email_attempts','users'] as $table) {
            $through=(int)$q("SELECT COALESCE(MAX(id),0) FROM $table")->fetchColumn();
            $protected[$table]=['through'=>$through,'digest'=>latestApiDigest($pdo,$table,$through)];
        }
        $journal=['scope'=>'latest-api-1240-1268-v1','users'=>$users,'request_ids'=>$requestIds,'documents'=>$documents,'protected'=>$protected];
        $handle=fopen($journalPath,'x');
        if ($handle===false) { throw new LogicException('Cannot create recovery journal.'); }
        try {
            $data=json_encode($journal,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
            if (fwrite($handle,$data)!==strlen($data)||!fflush($handle)) { throw new LogicException('Cannot persist recovery journal.'); }
        } finally { fclose($handle); }
    }
    foreach ($documents as $doc) {
        if ((int)$q('SELECT COUNT(*) FROM member_documents WHERE stored_name=? AND user_id<>?',[$doc['stored_name'],$doc['user_id']])->fetchColumn()!==0) { throw new LogicException('File reference has another owner; no removal.'); }
    }
    foreach ($requestIds as $requestId) {
        $q("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?",[$requestId]);
        $q('DELETE FROM blood_requests WHERE id=?',[$requestId]);
    }
    $q("DELETE FROM notifications WHERE related_type IN ('verification','account') AND related_id IN ($marks)",$ids);
    foreach ($ids as $id) { $q('DELETE FROM users WHERE id=?',[$id]); }
    if ((int)$q("SELECT COUNT(*) FROM users WHERE id IN ($marks)",$ids)->fetchColumn()!==0) { throw new LogicException('User removal verification failed.'); }
    foreach ($journal['protected'] as $table=>$guard) {
        if (!hash_equals($guard['digest'],latestApiDigest($pdo,$table,(int)$guard['through']))) { throw new LogicException('Protected history/other users changed; transaction rolled back.'); }
    }
    $pdo->commit(); // File deletion follows commit; journal enables retry after interruption.
    foreach ($documents as $doc) {
        if ((int)$q('SELECT COUNT(*) FROM member_documents WHERE stored_name=?',[$doc['stored_name']])->fetchColumn()!==0) { throw new LogicException('Document is still referenced; journal retained.'); }
        $path=latestApiFixtureFile($doc);
        if ($path!==null && !unlink($path)) { throw new LogicException('Fixture file removal failed; journal retained for retry.'); }
    }
    if (!unlink($journalPath)) { throw new LogicException('Cleanup complete but journal removal failed; retry --cleanup.'); }
    echo json_encode($summary+['status'=>'cleaned','remaining_users'=>0,'remaining_owned_files'=>0,'audit_history'=>'retained',
        'email_attempts'=>'unchanged','other_user_identities'=>'unchanged','journal_removed'=>true]).PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,$e instanceof LogicException?$e->getMessage().PHP_EOL:"Latest API fixture cleanup failed; raw error withheld. Keep any recovery journal.\n");exit(1);
} finally {
    if ($locked && $pdo instanceof PDO) { $pdo->query("SELECT RELEASE_LOCK('bm-latest-api-fixture-cleanup')"); }
}

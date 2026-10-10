<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';

// USER-RUN ONLY. Default SELECT-only; one exact test nonce required for deletion.
$pdo=null;
try {
    $token=null;$cleanup=false;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg==='--cleanup' && !$cleanup) { $cleanup=true; }
        elseif (preg_match('/^--token=([0-9a-f]{16})$/D',$arg,$m) && $token===null) { $token=$m[1]; }
        else { throw new LogicException('Use no arguments, --token=TOKEN, or --cleanup --token=TOKEN.'); }
    }
    if ($cleanup && $token===null) { throw new LogicException('Cleanup requires one reviewed exact fixture token.'); }
    $pdo=emailCheckpointTarget();
    $query=static function(string $sql,array $args=[]) use ($pdo):PDOStatement {
        $s=$pdo->prepare($sql);$s->execute($args);return $s;
    };
    if ($token===null) {
        $groups=$query("SELECT SUBSTRING(email,9,16) AS fixture_token,COUNT(*) AS users,MIN(created_at) AS created_at
            FROM users WHERE email REGEXP '^enqueue-[0-9a-f]{16}-(officer|member|concurrent|review|failed-review|approved|cross-chapter|ineligible|wrong-officer)@example[.]test$'
            AND first_name='Enqueue' AND last_name='Fixture' GROUP BY SUBSTRING(email,9,16) ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'writes'=>false,'fixture_groups'=>$groups]).PHP_EOL;exit;
    }
    $labels=['officer','member','concurrent','review','failed-review','approved','cross-chapter','ineligible','wrong-officer'];
    $emails=array_map(fn($label)=>"enqueue-{$token}-{$label}@example.test",$labels);
    $marks=implode(',',array_fill(0,count($emails),'?'));
    if ($cleanup) { $pdo->beginTransaction(); }
    $rows=$query("SELECT id,first_name,last_name FROM users WHERE email IN ($marks) ORDER BY id".($cleanup?' FOR UPDATE':''),$emails)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) { if ($row['first_name']!=='Enqueue'||$row['last_name']!=='Fixture') { throw new LogicException('Fixture identity mismatch; no cleanup.'); } }
    $ids=array_map('intval',array_column($rows,'id'));
    if ($ids===[]) {
        if ($cleanup) { $pdo->commit(); }
        echo json_encode(['fixture_token'=>$token,'users'=>0,'requests'=>0,'status'=>'already_absent','writes'=>false]).PHP_EOL;exit;
    }
    $marks=implode(',',array_fill(0,count($ids),'?'));
    if ((int)$query("SELECT COUNT(*) FROM users WHERE email LIKE ? AND id NOT IN ($marks)",array_merge(["enqueue-{$token}-%"],$ids))->fetchColumn()!==0) { throw new LogicException('Unexpected fixture label; no cleanup.'); }
    $requests=$query("SELECT id,facility_name FROM blood_requests WHERE requester_id IN ($marks) ORDER BY id".($cleanup?' FOR UPDATE':''),$ids)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requests as $request) { if ($request['facility_name']!=="Enqueue {$token}") { throw new LogicException('Unexpected request ownership; no cleanup.'); } }
    if ((int)$query("SELECT COUNT(*) FROM donation_reports WHERE donor_id IN ($marks)",$ids)->fetchColumn()!==0) { throw new LogicException('Unexpected donation history; no cleanup.'); }
    if ((int)$query("SELECT COUNT(*) FROM verification_decisions WHERE officer_id IN ($marks) AND target_user_id NOT IN ($marks)",array_merge($ids,$ids))->fetchColumn()!==0) { throw new LogicException('Reviewer referenced outside this fixture group; no cleanup.'); }
    $requestIds=array_map('intval',array_column($requests,'id'));
    $ownedRequestMarks=implode(',',array_fill(0,count($requestIds),'?'));
    $outside=$requestIds===[]?'':" AND request_id NOT IN ($ownedRequestMarks)";
    if ((int)$query("SELECT COUNT(*) FROM matches WHERE donor_id IN ($marks)".$outside,array_merge($ids,$requestIds))->fetchColumn()!==0) { throw new LogicException('Unowned offer history; no cleanup.'); }
    if ($requestIds!==[] && (int)$query("SELECT COUNT(*) FROM donation_reports dr JOIN matches m ON m.id=dr.match_id WHERE m.request_id IN ($ownedRequestMarks)",$requestIds)->fetchColumn()!==0) { throw new LogicException('Unexpected request donation history; no cleanup.'); }
    $summary=['database'=>'bloodmatch_test','port'=>3306,'fixture_token'=>$token,'user_ids'=>$ids,'request_ids'=>$requestIds];
    if (!$cleanup) { echo json_encode($summary+['writes'=>false,'status'=>'review_only']).PHP_EOL;exit; }
    foreach ($requestIds as $id) {
        $query("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?",[$id]);
        $query('DELETE FROM blood_requests WHERE id=?',[$id]);
    }
    foreach ($ids as $id) { $query('DELETE FROM users WHERE id=?',[$id]); }
    if ((int)$query("SELECT COUNT(*) FROM users WHERE id IN ($marks)",$ids)->fetchColumn()!==0) { throw new LogicException('Fixture cleanup verification failed.'); }
    $pdo->commit();
    echo json_encode($summary+['status'=>'cleaned','audit_history'=>'retained','email_attempts'=>'untouched']).PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,$e instanceof LogicException?$e->getMessage().PHP_EOL:"Scoped fixture review/cleanup failed; raw database error withheld.\n");exit(1);
}

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';
use BloodMatch\Services\AuthService;
use BloodMatch\Services\LocationService;
use BloodMatch\Services\NotificationService;
use BloodMatch\Services\MatchResponseService;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\Exceptions\ValidationException;
function checkpointCheck(bool $ok,string $label): void {
    if (!$ok) { throw new LogicException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
function checkpointQuery(PDO $pdo,string $sql,array $values=[]): PDOStatement {
    $s=$pdo->prepare($sql);$s->execute($values);return $s;
}
$pdo=null;$ids=[];$token=bin2hex(random_bytes(8));$exit=0;$requestId=null;
try {
    if (count($argv)>2 || (isset($argv[1]) && $argv[1]!=='--review')) { throw new LogicException('Use no arguments or --review.'); }
    // Override before .env loading. Registration cannot reach SMTP or reserve an attempt.
    putenv('MAIL_HOST=');$_ENV['MAIL_HOST']='';
    $pdo=emailCheckpointTarget();
    $chapter=(int)$pdo->query('SELECT id FROM chapters ORDER BY id LIMIT 1')->fetchColumn();
    $municipalities=LocationService::listMunicipalities();
    if ($chapter===0 || count($municipalities)<2) { throw new LogicException('Existing chapters and canonical location seed are prerequisites.'); }
    $parent=null;$barangay=null;
    foreach ($municipalities as $municipality) {
        $children=LocationService::listBarangays($municipality['psgc_code'])['barangays'];
        if ($children!==[]) { $parent=$municipality;$barangay=$children[0];break; }
    }
    if ($barangay===null) { throw new LogicException('Active canonical barangay required.'); }
    $pdo->query('SELECT location_id FROM users LIMIT 1');
    $pdo->query('SELECT status,notification_id FROM email_outbox LIMIT 1');
    $pdo->query('SELECT attempted_at FROM email_attempts LIMIT 1');
    $pdo->query('SELECT request_id,donor_id,generation,notified_at,due_at FROM donor_opportunities LIMIT 1');
    echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'writes'=>!isset($argv[1]),'smtp'=>false,'municipality'=>$parent['name'],'barangay'=>$barangay['name']],JSON_UNESCAPED_SLASHES).PHP_EOL;
    if (isset($argv[1])) { exit(0); }
    $attempts=$pdo->query('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $pdo->beginTransaction();
    $base=['first_name'=>'Location','last_name'=>'Fixture','password'=>'FixturePass123','chapter_id'=>$chapter,'date_of_birth'=>'1990-01-02','blood_type'=>'A+','privacy_acknowledged'=>true];
    $selection=['location_id'=>$barangay['location_id'],'municipality_code'=>$parent['psgc_code'],'barangay_code'=>$barangay['psgc_code']];
    $service=new AuthService();$repo=new UserRepository();
    // JSON input and string fields produced by multipart form submission use the same controller/service.
    foreach (['json','multipart'] as $mode) {
        $input=$base+$selection+['email'=>"location-$token-$mode@example.test"];
        if ($mode==='multipart') { $input['location_id']=(string)$input['location_id'];$input['chapter_id']=(string)$chapter;$input['privacy_acknowledged']='true'; }
        $created=$service->register($input);$ids[]=(int)$created['id'];
        $user=$repo->findById((int)$created['id']);$canonical=LocationService::resolveSelection($selection);
        checkpointCheck((int)$user['location_id']===$barangay['location_id'],$mode.' registration saves canonical barangay location_id');
        checkpointCheck((float)$user['latitude']===$canonical['latitude'] && (float)$user['longitude']===$canonical['longitude'],$mode.' coordinates come from reference row');
        $profile=\BloodMatch\Controllers\ProfileController::compose($user);
        checkpointCheck($profile['location']['municipality_code']===$parent['psgc_code'] && $profile['location']['barangay_code']===$barangay['psgc_code'],$mode.' own profile projection preserves municipality and barangay');
    }
    $municipalityInput=$base+['location_id'=>$parent['location_id'],'municipality_code'=>$parent['psgc_code'],'barangay_code'=>null,'email'=>"location-$token-municipality@example.test"];
    $created=$service->register($municipalityInput);$ids[]=(int)$created['id'];
    checkpointCheck((int)$repo->findById((int)$created['id'])['location_id']===$parent['location_id'],'municipality-only location remains allowed');
    $created=$service->register($base+['email'=>"location-$token-empty@example.test"]);$ids[]=(int)$created['id'];
    checkpointCheck($repo->findById((int)$created['id'])['location_id']===null,'omitted optional location remains empty');
    $other=array_values(array_filter($municipalities,fn($m)=>$m['psgc_code']!==$parent['psgc_code']))[0];
    foreach ([['municipality_code'=>$other['psgc_code']],['barangay_code'=>$other['psgc_code']],['latitude'=>1,'longitude'=>2],['location_id'=>0]] as $index=>$override) {
        $email="location-$token-invalid-$index@example.test";
        try { $service->register(array_replace($base+$selection+['email'=>$email],$override));throw new LogicException('Invalid registration accepted.'); }
        catch (ValidationException $e) { checkpointCheck(!$repo->emailExists($email),'invalid selection '.$index.' rejected before account creation'); }
    }
    $requestId=(new BloodRequestRepository())->create(['requester_id'=>$ids[0],'request_chapter_id'=>$chapter,'required_blood_type'=>'A+','quantity_units'=>1,'facility_name'=>'Location fixture','location_id'=>$parent['location_id'],'latitude'=>null,'longitude'=>null,'urgency'=>'emergency','needed_datetime'=>gmdate('Y-m-d H:i:s',time()+86400),'review_status'=>'not_required']);
    checkpointQuery($pdo,"UPDATE users SET verification_status='verified',email_verified_at=UTC_TIMESTAMP(),donor_enrolled_at=UTC_TIMESTAMP(),donor_availability='available' WHERE id=?",[$ids[1]]);
    $request=(new BloodRequestRepository())->findById($requestId);
    NotificationService::notifyMatchGeneration($request,1,[$ids[1]],false);
    $notice=checkpointQuery($pdo,"SELECT * FROM notifications WHERE user_id=? AND type='match.new' AND related_id=?",[$ids[1],$requestId])->fetch(PDO::FETCH_ASSOC);
    checkpointCheck(str_contains($notice['body'],'Location Fixture') && !preg_match('/#\d+/',$notice['title'].$notice['body']),'opportunity wording uses requester name without request number');
    checkpointQuery($pdo,'UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE id=?',[$notice['id']]);
    $before=checkpointQuery($pdo,'SELECT created_at,read_at FROM notifications WHERE id=?',[$notice['id']])->fetch(PDO::FETCH_NUM);
    NotificationService::notifyMatchGeneration($request,1,[$ids[1]],false);
    checkpointCheck($before===checkpointQuery($pdo,'SELECT created_at,read_at FROM notifications WHERE id=?',[$notice['id']])->fetch(PDO::FETCH_NUM),'wording refresh preserves read/created timestamps');
    (new MatchResponseService())->respond($repo->findById($ids[1]),$requestId,'location_checkpoint');
    $response=checkpointQuery($pdo,"SELECT title,body FROM notifications WHERE user_id=? AND type='match.responded' AND related_id=?",[$ids[0],$requestId])->fetch(PDO::FETCH_ASSOC);
    checkpointCheck(str_contains($response['body'],'Location Fixture') && !preg_match('/#\d+/',implode(' ',$response)),'response wording uses donor name without request number');
    $pdo->rollBack();
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    checkpointCheck((int)checkpointQuery($pdo,"SELECT COUNT(*) FROM users WHERE id IN ($placeholders)",$ids)->fetchColumn()===0
        && (new BloodRequestRepository())->findById($requestId)===null,'all transaction users and request absent after rollback');
    checkpointCheck($attempts===$pdo->query('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM),'shared attempt ledger unchanged');
    echo 'Registration/location/notification checkpoint passed; disposable transaction rolled back; no files or SMTP.'.PHP_EOL;
} catch (Throwable $e) { $exit=1;fwrite(STDERR,$e->getMessage().PHP_EOL); }
finally { if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack();echo 'Disposable transaction rolled back.'.PHP_EOL; } }
exit($exit);

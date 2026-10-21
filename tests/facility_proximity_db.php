<?php
declare(strict_types=1);
// Explicit disposable target, fake mail, one outer rollback; never migrate or bulk rematch.
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__.'/helpers/consolidation_isolation.php';
require __DIR__.'/helpers/fake_mail_transport.php';
require dirname(__DIR__).'/backend/src/autoload.php';

use BloodMatch\Config\{Database, Env};
use BloodMatch\Repositories\{BloodRequestRepository, UserRepository};
use BloodMatch\Services\{HospitalLocationService, MatchService, RequestService, RequestLifecycleService, OfferChatService};

Env::load(dirname(__DIR__).'/.env');
// Bound the greeting before connecting; refuse fallback or a non-test schema.
$socket = @stream_socket_client('tcp://127.0.0.1:3306', $errno, $error, 3);
if (!$socket) { throw new RuntimeException('Test database unavailable.'); }
stream_set_timeout($socket, 3); $greeting = fread($socket, 4); fclose($socket);
if (strlen((string)$greeting) !== 4) { throw new RuntimeException('Test database greeting unavailable.'); }
$p = Database::pdo();
if ($p->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test' || (int)$p->query('SELECT @@port')->fetchColumn() !== 3306) {
    throw new RuntimeException('Refused non-disposable database.');
}
$p->exec('SET SESSION innodb_lock_wait_timeout=5');
$checks = 0; $token = bin2hex(random_bytes(6));
function q(string $sql, array $args = []): PDOStatement { global $p; $s=$p->prepare($sql); $s->execute($args); return $s; }
function ok(bool $value, string $label): void { global $checks; if (!$value) throw new RuntimeException('FAIL '.$label); $checks++; echo "PASS $label\n"; }
function denied(callable $action, int $code = 0): bool { try { $action(); } catch (Throwable $e) { return $code === 0 || $e->getCode() === $code; } return false; }
function person(string $label, ?array $loc, int $chapter = 1, string $blood = 'O+', bool $available = true): array {
    global $p, $token;
    q("INSERT INTO users(email,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at,location_id,latitude,longitude)
        VALUES (?,'Proximity','Fixture','member',?,'verified','active','1990-01-01',?,'self_reported',UTC_TIMESTAMP(),?,UTC_TIMESTAMP(),?,?,?)",
        ["proximity-$token-$label@example.test", $chapter, $blood, $available ? 'available' : 'unavailable', $loc['id'] ?? null, $loc['latitude'] ?? null, $loc['longitude'] ?? null]);
    return (new UserRepository())->findById((int)$p->lastInsertId());
}
function fixtureRequest(array $owner, array $loc): int {
    $fields=RequestService::validatePayload(['facility_name'=>'Synthetic manual clinic','required_blood_type'=>'O+', 'needed_datetime'=>gmdate('Y-m-d H:i:s',time()+86400),'location_id'=>(int)$loc['id']]);
    return (new BloodRequestRepository())->create($fields+['requester_id'=>(int)$owner['id'],'request_chapter_id'=>1,'review_status'=>'not_required']);
}
function fixtureOrder(int $request, array $people): array {
    $refs=array_map(fn($u)=>'donor-'.$u['id'],$people);
    return array_values(array_filter(array_column((new MatchService())->privacySafeMatches($request),'donor_reference'),fn($ref)=>in_array($ref,$refs,true)));
}

$p->beginTransaction();
try {
    $balanga=q("SELECT * FROM bataan_locations WHERE municipality_name='City of Balanga' AND level='barangay' AND is_active=1 LIMIT 1")->fetch();
    $mariveles=q("SELECT * FROM bataan_locations WHERE municipality_name='Mariveles' AND level='barangay' AND is_active=1 LIMIT 1")->fetch();
    $municipal=q("SELECT * FROM bataan_locations WHERE municipality_name='City of Balanga' AND level='municipality' AND is_active=1 LIMIT 1")->fetch();
    ok($balanga && $mariveles && $municipal,'verified disposable test database and existing reference rows');
    $owner=person('owner',$mariveles);
    $near=person('near',$balanga,2); $far=person('far',$mariveles,1);
    $equalOther=person('equal-other',$balanga,2); $equalChapter=person('equal-chapter',$balanga,1);
    $missing=person('missing',null,1); $wrongBlood=person('wrong',$balanga,1,'AB+');
    $unavailable=person('unavailable',$balanga,1,'O+',false);
    $cooldown=person('cooldown',$balanga); q('UPDATE users SET last_verified_donation_at=UTC_TIMESTAMP() WHERE id=?',[$cooldown['id']]);
    $unverified=person('unverified',$balanga); q("UPDATE users SET verification_status='pending' WHERE id=?",[$unverified['id']]);
    $minor=person('minor',$balanga); q('UPDATE users SET date_of_birth=? WHERE id=?',[gmdate('Y-m-d',time()-10*365*86400),$minor['id']]);
    $r=fixtureRequest($owner,$balanga); $engine=new MatchService();
    $engine->generateForRequest($r,true,'request_created',(int)$owner['id']);
    $people=[$near,$far,$equalOther,$equalChapter,$missing];
    $expected=array_map(fn($u)=>'donor-'.$u['id'],[$equalChapter,$near,$equalOther,$far,$missing]);
    ok(fixtureOrder($r,$people)===$expected,'nearest first, equal-distance chapter preference, stable donor ID, missing last');
    ok((int)q('SELECT COUNT(*) FROM matches WHERE request_id=? AND donor_id IN (?,?)',[$r,$wrongBlood['id'],$unavailable['id']])->fetchColumn()===0,'compatibility and availability remain prerequisites');
    ok((int)q('SELECT COUNT(*) FROM matches WHERE request_id=? AND donor_id IN (?,?,?,?)',[$r,$owner['id'],$cooldown['id'],$unverified['id'],$minor['id']])->fetchColumn()===0,'own request, cooldown, verification and age safety gates preserved');
    // Deliberately corrupt obsolete sort keys to prove reads do not use them.
    q('UPDATE matches SET generation=99,rank_score=999999 WHERE request_id=? AND donor_id=?',[$r,$far['id']]);
    ok(fixtureOrder($r,$people)===$expected,'generation and legacy scores cannot override proximity');
    $before=q('SELECT donor_id,distance_km FROM matches WHERE request_id=? ORDER BY donor_id',[$r])->fetchAll();
    q('UPDATE users SET location_id=?,latitude=?,longitude=? WHERE id=?',[$balanga['id'],$balanga['latitude'],$balanga['longitude'],$owner['id']]);
    $engine->generateForRequest($r,false,'manual_rematch',(int)$owner['id']);
    ok(q('SELECT donor_id,distance_km FROM matches WHERE request_id=? ORDER BY donor_id',[$r])->fetchAll()===$before,'requester home does not affect proximity');

    // Synthetic directory entries only; no real hospital addresses are invented or altered.
    q("INSERT INTO hospitals(name,type,municipality_name,location_id) VALUES (?,'public','City of Balanga',?)",['Synthetic complete '.$token,$balanga['id']]); $complete=(int)$p->lastInsertId();
    q("INSERT INTO hospitals(name,type,municipality_name,location_id) VALUES (?,'public','City of Balanga',?)",['Synthetic incomplete '.$token,$municipal['id']]); $incomplete=(int)$p->lastInsertId();
    q("INSERT INTO hospitals(name,type,municipality_name,location_id) VALUES (?,'private','Unknown',NULL)",['Synthetic unmapped '.$token]); $unmapped=(int)$p->lastInsertId();
    $base=['facility_name'=>'Synthetic complete '.$token,'hospital_id'=>$complete,'required_blood_type'=>'O+','needed_datetime'=>gmdate('Y-m-d H:i:s',time()+86400),'location_id'=>(int)$mariveles['id']];
    $resolved=RequestService::validatePayload($base);
    ok($resolved['location_id']===(int)$balanga['id'] && $resolved['latitude']===(float)$balanga['latitude'],'listed full address overrides conflicting API location');
    unset($base['hospital_id']);
    ok(RequestService::validatePayload($base)['hospital_id']===$complete,'hospital name cannot bypass authoritative address');
    ok(denied(fn()=>RequestService::validatePayload(['hospital_id'=>'invalid']+$base)),'invalid hospital IDs rejected');
    $partial=RequestService::validatePayload(['location_id'=>(int)$mariveles['id']],true,$resolved);
    ok($partial['location_id']===(int)$balanga['id'],'location-only API edit still enforces saved hospital address');
    $manual=RequestService::validatePayload(['hospital_id'=>$incomplete,'facility_name'=>'Synthetic incomplete '.$token,'location_id'=>(int)$balanga['id']]+$base);
    ok($manual['location_id']===(int)$balanga['id'],'incomplete listed hospital permits manual barangay');
    $municipalityOnly=RequestService::validatePayload(['hospital_id'=>$incomplete,'location_id'=>(int)$municipal['id']]+$base);
    ok($municipalityOnly['location_id']===(int)$municipal['id'],'hospital municipality-only selection works without barangay');
    $otherFields=RequestService::validatePayload(['facility_name'=>'Other location','hospital_id'=>null,'location_id'=>(int)$municipal['id']]+$base);
    $otherId=(new BloodRequestRepository())->create($otherFields+['requester_id'=>(int)$owner['id'],'request_chapter_id'=>1,'review_status'=>'not_required']);
    $otherSaved=(new BloodRequestRepository())->findById($otherId);
    ok($otherSaved['facility_name']==='Other location' && $otherSaved['hospital_id']===null && (int)$otherSaved['location_id']===(int)$municipal['id'],'other location saves using existing request fields without a meeting-place textbox or schema addition');
    ok(denied(fn()=>RequestService::validatePayload(['hospital_id'=>$incomplete,'location_id'=>(int)$mariveles['id']]+$base)),'known hospital municipality rejects conflicting manual address');
    ok(RequestService::validatePayload(['hospital_id'=>$unmapped,'location_id'=>(int)$mariveles['id']]+$base)['location_id']===(int)$mariveles['id'],'unmapped listed hospital permits manual address without guessing');
    $switched=RequestService::validatePayload(['facility_name'=>'Unlisted new clinic','hospital_id'=>null],true,$resolved);
    ok($switched['hospital_id']===null && $switched['location_id']===null && $switched['latitude']===null,'facility-only switch clears stale hospital and address');
    ok(RequestService::validatePayload(['quantity_units'=>3],true,$resolved)===['quantity_units'=>3],'unrelated edits leave existing facility records alone');
    $directory=HospitalLocationService::directory();
    $completeRow=array_values(array_filter($directory,fn($h)=>$h['id']===$complete))[0];
    ok($completeRow['address_complete'] && !isset($completeRow['location']['latitude']),'directory supplies read-only address without coordinates');

    // Protect a completed historical row; preserve an existing offer with its pending report.
    q("UPDATE matches SET status='COMPLETED' WHERE request_id=? AND donor_id=?",[$r,$equalOther['id']]);
    $historical=q('SELECT * FROM matches WHERE request_id=? AND donor_id=?',[$r,$equalOther['id']])->fetch();
    q("UPDATE matches SET status='RESPONDED',responded_at=UTC_TIMESTAMP() WHERE request_id=? AND donor_id=?",[$r,$near['id']]);
    $offer=q('SELECT * FROM matches WHERE request_id=? AND donor_id=?',[$r,$near['id']])->fetch();
    q("INSERT INTO donation_reports(match_id,donor_id,reported_at,status,report_note,hold_started_at) VALUES (?,?,UTC_TIMESTAMP(),'PENDING','Synthetic proximity preservation',UTC_TIMESTAMP())",[$offer['id'],$near['id']]);
    $reportId=(int)$p->lastInsertId(); $report=q('SELECT * FROM donation_reports WHERE id=?',[$reportId])->fetch();
    q("UPDATE matches SET status='RESPONDED',responded_at=UTC_TIMESTAMP() WHERE request_id=? AND donor_id=?",[$r,$equalChapter['id']]);
    $eligibleOffer=q('SELECT * FROM matches WHERE request_id=? AND donor_id=?',[$r,$equalChapter['id']])->fetch();
    $fresh=(new RequestLifecycleService())->update($owner,$r,['location_id'=>(int)$mariveles['id']]);
    ok($fresh['matching_status']==='completed' && (float)q('SELECT distance_km FROM matches WHERE request_id=? AND donor_id=?',[$r,$far['id']])->fetchColumn()===0.0,'facility edit recalculates eligible unfinished matches');
    ok(q('SELECT * FROM matches WHERE id=?',[$historical['id']])->fetch()===$historical,'completed-match history unchanged');
    $updatedOffer=q('SELECT * FROM matches WHERE id=?',[$eligibleOffer['id']])->fetch();
    ok($updatedOffer['status']==='RESPONDED' && $updatedOffer['offer_cycle']===$eligibleOffer['offer_cycle'] && (float)$updatedOffer['distance_km']>0,'eligible unfinished offer recalculates distance without replacing offer history');
    ok(q('SELECT status FROM matches WHERE id=?',[$offer['id']])->fetchColumn()==='RESPONDED' && q('SELECT * FROM donation_reports WHERE id=?',[$reportId])->fetch()===$report,'pending offer/report retained during facility edit');
    ok(denied(fn()=>(new RequestLifecycleService())->update($far,$r,['location_id'=>(int)$balanga['id']]),403),'non-owner cannot edit facility');
    q("UPDATE matches SET status='RESPONDED' WHERE request_id=? AND donor_id=?",[$r,$far['id']]);
    $offers=OfferChatService::offers($owner,$r)['offers'];
    $offerIds=array_column($offers,'match_id');
    ok(array_search((int)q('SELECT id FROM matches WHERE request_id=? AND donor_id=?',[$r,$far['id']])->fetchColumn(),array_map('intval',$offerIds),true)
        < array_search((int)$offer['id'],array_map('intval',$offerIds),true),'offer list also uses nearest-first ordering');
    ok(\PHPMailer\PHPMailer\PHPMailer::$calls===0,'no email transport or workers invoked');
    echo "$checks facility/proximity DB checks passed; all fixture data rolled back.\n";
} finally { if ($p->inTransaction()) $p->rollBack(); }

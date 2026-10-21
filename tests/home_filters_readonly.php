<?php
declare(strict_types=1);
// SELECT-only checks of the current database. No fixtures, lifecycle jobs or HTTP actions.
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Database,Env};
use BloodMatch\Services\{RequestService,BloodCompatibilityService};
use BloodMatch\Services\Exceptions\ValidationException;
use BloodMatch\Repositories\BloodRequestRepository;
Env::load(dirname(__DIR__).'/.env');
$pdo=Database::pdo();
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
$checks=0; $nonempty=0; $maxMs=0;
function checkHome(bool $condition,string $label): void { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
try {
    $now=(string)$pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
    $viewer=(int)$pdo->query("SELECT id FROM users WHERE role='member' AND account_status='active' AND verification_status='verified' ORDER BY id LIMIT 1")->fetchColumn();
    checkHome($viewer>0,'No viewer available for read-only checks');
    $raw=$pdo->query("SELECT br.id,br.requester_id,br.required_blood_type,br.facility_name,br.urgency,br.status,br.needed_datetime,
        requester.role,requester.account_status,loc.municipality_code,loc.municipality_name
        FROM blood_requests br JOIN users requester ON requester.id=br.requester_id
        LEFT JOIN bataan_locations loc ON loc.id=br.location_id ORDER BY br.created_at DESC,br.id DESC")->fetchAll();
    $municipality=(string)$pdo->query("SELECT psgc_code FROM bataan_locations WHERE level='municipality' AND is_active=1 ORDER BY psgc_code LIMIT 1")->fetchColumn();
    $repo=new BloodRequestRepository();
    foreach(RequestService::BLOOD_TYPES as $donorType) {
        $types=BloodCompatibilityService::getCompatibleRecipientTypesForDonor($donorType);
        $base=array_values(array_filter($raw,fn($r)=>(int)$r['requester_id']!==$viewer && $r['status']==='OPEN'
            && $r['needed_datetime']>=$now && $r['role']==='member' && $r['account_status']==='active'
            && in_array($r['required_blood_type'],$types,true)));
        $cases=[[],['municipality_code'=>$municipality],['blood'=>'A+,O+'],['urgency'=>'urgent,emergency'],
            ['municipality_code'=>$municipality,'blood'=>'O+','urgency'=>'emergency'],['q'=>'%'],['q'=>'_'],['q'=>"' OR 1=1 --"]];
        foreach(RequestService::BLOOD_TYPES as $type) $cases[]=['blood'=>$type];
        foreach(RequestService::URGENCIES as $urgency) $cases[]=['urgency'=>$urgency];
        foreach($base as $r) {
            if ($r['municipality_code']) $cases[]=['municipality_code'=>$r['municipality_code']];
            $cases[]=['blood'=>$r['required_blood_type'],'urgency'=>$r['urgency']];
        }
        foreach($cases as $input) {
            $filters=RequestService::validateHomeFeedQuery($input);
            $expected=array_values(array_filter($base,static function($r) use($filters):bool {
                if($filters['blood'] && !in_array($r['required_blood_type'],$filters['blood'],true)) return false;
                if($filters['urgency'] && !in_array($r['urgency'],$filters['urgency'],true)) return false;
                if($filters['municipality_code']!==null && $r['municipality_code']!==$filters['municipality_code']) return false;
                return true;
            }));
            $started=microtime(true);
            $actual=$repo->pageCompatibleOpenForViewer($viewer,$types,$filters,1,100,$now);
            $maxMs=max($maxMs,(microtime(true)-$started)*1000);
            checkHome(array_map('intval',array_column($actual['rows'],'id'))===array_map('intval',array_column($expected,'id')),'Filtered rows/order differ from independent evaluation');
            checkHome($actual['total_matching']===count($expected) && $actual['total_unfiltered']===count($base),'Counts do not match filters');
            if($expected) $nonempty++;
        }
        $filters=RequestService::validateHomeFeedQuery([]);
        $retired=$repo->pageCompatibleOpenForViewer($viewer,$types,array_merge($filters,['q'=>'Hospital']),1,100,$now);
        checkHome(array_map('intval',array_column($retired['rows'],'id'))===array_map('intval',array_column($base,'id')),'Repository still applies removed text search');
        $pages=[];
        for($page=1;$page<=count($base)+1;$page++) {
            $result=$repo->pageCompatibleOpenForViewer($viewer,$types,$filters,$page,1,$now);
            checkHome($result['has_more']===($page<count($base)),'Pagination has_more is incorrect');
            array_push($pages,...array_map('intval',array_column($result['rows'],'id')));
        }
        checkHome($pages===array_map('intval',array_column($base,'id')),'Pagination misses or duplicates records');
    }
    foreach([['blood'=>'X'],['blood'=>['O+']],['urgency'=>'critical'],['urgency'=>'urgent,'],['municipality_code'=>'999999999'],['page'=>'0'],['page'=>'2147483648']] as $invalid) {
        try { RequestService::validateHomeFeedQuery($invalid); throw new RuntimeException('Invalid input accepted'); }
        catch(ValidationException $e) { $checks++; }
    }
    foreach(['Hospital','%',str_repeat('x',81),['ignored']] as $removed) {
        checkHome(RequestService::validateHomeFeedQuery(['q'=>$removed])===RequestService::validateHomeFeedQuery([]),'Removed text-search parameter must not affect supported filters');
    }
    checkHome(RequestService::validateHomeFeedQuery(['blood'=>'O+,O+'])['blood']===['O+'],'Blood duplicates not normalized');
    echo "PASS $checks SELECT-only filter/count/compatibility/pagination/validation checks; $nonempty nonempty cases; max repository call ".round($maxMs,1)." ms.\n";
    echo "Eight simulated donor blood types evaluated; no account blood types or records changed.\n";
} finally { $pdo->rollBack(); }

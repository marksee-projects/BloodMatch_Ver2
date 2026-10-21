<?php
/** Read-only diagnosis for the account shown in the user's screenshot. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
use BloodMatch\Repositories\{UserRepository,BloodRequestRepository};
use BloodMatch\Services\{BloodCompatibilityService,DonorEligibilityService,RequestService,AuthService};
Env::load(dirname(__DIR__).'/.env');$p=Database::pdo();$target=$p->query('SELECT DATABASE() db,@@port port')->fetch();if($target['db']!=='bloodmatch'||(int)$target['port']!==3306)throw new RuntimeException('Unexpected database');
$id=$p->query("SELECT id FROM users WHERE full_name='Regular Ito User'")->fetchColumn();if(!$id)throw new RuntimeException('Screenshot account not found');$u=(new UserRepository())->findById((int)$id);
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_MAIN_20261022_B42E';$m=json_decode(file_get_contents($dir.'/manifest.json'),true);$main=(new BloodRequestRepository())->findById($m['requests']['main-live']['id']);$types=BloodCompatibilityService::getCompatibleRecipientTypesForDonor($u['blood_type']);$feed=(new BloodRequestRepository())->pageCompatibleOpenForViewer((int)$id,$types,RequestService::validateHomeFeedQuery([]),1,20,AuthService::nowUtc());
$result=['target'=>$target,'account'=>array_intersect_key($u,array_flip(['id','email','full_name','blood_type','verification_status','account_status'])),'main_request_id'=>$main['id'],'main_request_blood'=>$main['required_blood_type'],'main_eligibility'=>DonorEligibilityService::evaluateForRequest($u,$main),'compatible_recipient_types'=>$types,'total_feed'=>$feed['total_matching'],'feed_request_ids'=>array_column($feed['rows'],'id')];
foreach(glob(dirname(__DIR__).'/backend/storage/maintenance/bloodmatch-*-credentials.json') as $file){$saved=json_decode(file_get_contents($file),true);if(($saved['status']??'')!=='COMMITTED')continue;foreach($saved['accounts']??[] as $a)if((int)$a['id']===(int)$id){$hash=$p->prepare('SELECT password_hash FROM users WHERE id=?');$hash->execute([$id]);if(password_verify($a['password'],$hash->fetchColumn()))file_put_contents($dir.'/regular-account-access.json',json_encode($a,JSON_PRETTY_PRINT),LOCK_EX);}}
file_put_contents($dir.'/visibility-check.json',json_encode($result,JSON_PRETTY_PRINT),LOCK_EX);echo json_encode($result,JSON_PRETTY_PRINT);

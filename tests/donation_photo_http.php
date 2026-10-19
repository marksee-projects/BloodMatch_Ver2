<?php
declare(strict_types=1);
// Fixed isolated HTTP target; disposable test users/notifications only. Never SMTP.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';
require dirname(__DIR__).'/database/operational_checkpoint_support.php';
use BloodMatch\Config\{Database,Env};
use BloodMatch\Services\{DocumentCipher,DocumentStorageService,OfferChatService};
use BloodMatch\Repositories\{DocumentRepository,UserRepository};
$base=$argv[1]??'';
if($base!=='http://127.0.0.1:8001' || getenv('BLOODMATCH_TEST_ISOLATION')!=='1' || getenv('MAIL_HOST')!=='127.0.0.1' || getenv('MAIL_PORT')!=='1')throw new RuntimeException('Use the isolated runner on port 8001 with SMTP disabled.');
$p=operationalTarget('test');
if(!extension_loaded('curl')||!extension_loaded('gd')||DocumentCipher::mode()!=='dual')throw new RuntimeException('Prepared dual storage, GD and cURL required.');
DocumentCipher::key(DocumentCipher::activeId());
$checks=0;$ids=[];$requests=[];$cookies=[];$temps=[];$owned=[];$nonce=bin2hex(random_bytes(10));$poisonId=null;$legacyDoc=null;$failed=false;
function photoCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function photoHttp(array &$client,string $method,string $path,?array $body=null,array $files=[]):array{
 global $base;
 $ch=curl_init($base.$path);$headers=['Accept: application/json'];$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$client['jar'],CURLOPT_COOKIEJAR=>$client['jar'],CURLOPT_TIMEOUT=>20,CURLOPT_HEADER=>true];
 if($method!=='GET'){
  $headers[]='X-CSRF-Token: '.$client['csrf'];$options[CURLOPT_POST]=true;
  if($files){$payload=$body??[];foreach($files as $i=>$file)$payload['proofs['.$i.']']=new CURLFile($file[0],$file[1],$file[2]);$options[CURLOPT_POSTFIELDS]=$payload;}
  else{$headers[]='Content-Type: application/json';$options[CURLOPT_POSTFIELDS]=json_encode($body??[]);}
 }
 $options[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($ch,$options);$raw=curl_exec($ch);
 if($raw===false)throw new RuntimeException('Isolated HTTP transport failed.');
 $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$len=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 return ['status'=>$status,'headers'=>substr($raw,0,$len),'raw'=>substr($raw,$len),'json'=>json_decode(substr($raw,$len),true)];
}
function photoClient(string $email,string $password):array{
 global $cookies;
 $jar=tempnam(sys_get_temp_dir(),'bm-photo-cookie-');$cookies[]=$jar;$client=['jar'=>$jar,'csrf'=>''];
 $r=photoHttp($client,'GET','/api/csrf');$client['csrf']=$r['json']['data']['csrf_token']??'';
 $r=photoHttp($client,'POST','/api/login',['email'=>$email,'password'=>$password]);
 photoCheck($r['status']===200,'Disposable verified member can log in');
 $r=photoHttp($client,'GET','/api/csrf');$client['csrf']=$r['json']['data']['csrf_token']??'';return $client;
}
function photoCount(string $sql,array $args=[]):int{global $p;$s=$p->prepare($sql);$s->execute($args);return(int)$s->fetchColumn();}
function photoFiles():array{$f=glob(DocumentStorageService::baseDir().'/*')?:[];sort($f);return $f;}
try{
 $repo=new UserRepository();$accounts=[];
 foreach(['owner','donor','other'] as $tag){
  $email="photo-http-$nonce-$tag@example.test";$password='Fixture-'.bin2hex(random_bytes(12));
  $s=$p->prepare("INSERT INTO users(email,password_hash,first_name,last_name,role,verification_status,account_status,date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,donor_availability,email_verified_at) VALUES(?,?,'Photo HTTP',?,'member','verified','active','1990-01-01','O+','self_reported',0,UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())");
  $s->execute([$email,password_hash($password,PASSWORD_BCRYPT),$tag]);$id=(int)$p->lastInsertId();$ids[]=$id;$accounts[$tag]=$repo->findById($id);$accounts[$tag]['test_password']=$password;
 }
 foreach($accounts as $tag=>$a)$clients[$tag]=photoClient($a['email'],$a['test_password']);
 $matches=[];
 for($i=0;$i<4;$i++){
  $s=$p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status) VALUES(?,'O+',1,'Synthetic private photo test','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'OPEN','not_required')");$s->execute([$accounts['owner']['id']]);$request=(int)$p->lastInsertId();$requests[]=$request;
  $s=$p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at,offer_cycle) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),1)");$s->execute([$request,$accounts['donor']['id']]);$match=(int)$p->lastInsertId();
  $context=OfferChatService::accept($accounts['owner'],$match,1);$matches[]=['match_id'=>$match,'context_id'=>$context['context_id']??null];
 }
 $baseline=photoFiles();
 $image=imagecreatetruecolor(4,4);imagesetpixel($image,1,1,imagecolorallocate($image,255,0,0));
 $photo=tempnam(sys_get_temp_dir(),'bm-photo-');$temps[]=$photo;imagepng($image,$photo);imagedestroy($image);
 $different=tempnam(sys_get_temp_dir(),'bm-photo-');$temps[]=$different;
 $image=imagecreatetruecolor(4,4);imagepng($image,$different);imagedestroy($image);
 $invalid=tempnam(sys_get_temp_dir(),'bm-photo-');$temps[]=$invalid;file_put_contents($invalid,'This is not an image.');
 $proof=[[$photo,'image/png','proof.png']];$occurred=gmdate('Y-m-d\TH:i:s\Z',time()-3600);
 $body=['match_id'=>(string)$matches[0]['match_id'],'occurred_at'=>$occurred];
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body,$proof);
 photoCheck($r['status']===200,'Browser-style multipart submission persists report and photo');
 $report=(int)($r['json']['data']['report']['id']??0);
 $s=$p->prepare('SELECT * FROM donation_report_evidence WHERE report_id=?');$s->execute([$report]);$evidence=$s->fetch();
 photoCheck($evidence!==false,'Photo is attached to the exact report/version');
 $path=DocumentStorageService::pathFor($evidence['stored_name']);$owned[]=$path;
 $stored=file_get_contents($path);
 photoCheck(str_starts_with($stored,DocumentCipher::MAGIC)&&!str_contains($stored,file_get_contents($photo)),'Photo is encrypted at rest with the existing cipher');
 photoCheck(photoCount("SELECT COUNT(*) FROM donation_reports WHERE id=? AND status='PENDING' AND workflow_state='awaiting_requester' AND confirmed_at IS NULL",[$report])===1,'Success awaits requester acknowledgment rather than confirmation');
 photoCheck(photoCount("SELECT COUNT(*) FROM notifications WHERE user_id=? AND related_type='donation_report' AND related_id=? AND type='donation.awaiting_acknowledgment'",[$accounts['owner']['id'],$report])===1,'Exactly one fake requester acknowledgment notification is persisted');
 $r=photoHttp($clients['owner'],'GET','/api/notifications');
 photoCheck($r['status']===200 && str_contains($r['raw'],'donation.awaiting_acknowledgment'),'Requester notification is visible through the real notification API');
 $r=photoHttp($clients['owner'],'GET','/api/donation-proof/'.$evidence['id'].'/file');
 photoCheck($r['status']===200 && hash('sha256',$r['raw'])===hash_file('sha256',$photo) && str_contains(strtolower($r['headers']),'no-store'),'Requester can retrieve only private decoded proof with no-store');
 $r=photoHttp($clients['other'],'GET','/api/donation-proof/'.$evidence['id'].'/file');photoCheck($r['status']===403||$r['status']===404,'Unrelated member cannot access proof');
 $beforeRetry=photoFiles();$r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body,$proof);
 photoCheck($r['status']===200 && ($r['json']['data']['report']['changed']??true)===false && (int)$r['json']['data']['report']['id']===$report,'Identical photo retry returns the original report');
 photoCheck(photoFiles()===$beforeRetry && photoCount('SELECT COUNT(*) FROM donation_report_evidence WHERE report_id=?',[$report])===1,'Retry removes uncommitted new uploads without deleting committed evidence');
 photoCheck(photoCount("SELECT COUNT(*) FROM notifications WHERE user_id=? AND related_id=? AND type='donation.awaiting_acknowledgment'",[$accounts['owner']['id'],$report])===1,'Retry emits no duplicate requester alert');
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body,[[$different,'image/png','different.png']]);
 photoCheck($r['status']===409 && photoFiles()===$beforeRetry,'Different proof cannot silently replace the original report and leaves no orphan');
 $body2=['match_id'=>(string)$matches[1]['match_id'],'occurred_at'=>gmdate('Y-m-d\TH:i:s\Z',time()-3500)];
 $r=photoHttp($clients['donor'],'POST','/api/donation-completion',$body2);
 photoCheck($r['status']===200 && ($r['json']['data']['report']['changed']??false),'Optional-photo-free submission still works through the shared controller');
 $beforeFail=photoFiles();$body3=['match_id'=>(string)$matches[2]['match_id'],'occurred_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)];
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body3,$proof);
 photoCheck($r['status']===400 && photoFiles()===$beforeFail && photoCount('SELECT COUNT(*) FROM donation_reports WHERE match_id=?',[$matches[2]['match_id']])===0,'Future donation with a photo is rejected and staged files are removed');
 $body3['occurred_at']=gmdate('Y-m-d\TH:i:s\Z',time()-3400);
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body3,[[$invalid,'image/png','fake.png']]);
 photoCheck($r['status']===400 && photoFiles()===$beforeFail,'Spoofed image is rejected without abandoned uploads');
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body3,[$proof[0],[$invalid,'image/png','fake.png']]);
 photoCheck($r['status']===400 && photoFiles()===$beforeFail,'Partial multi-photo failure removes earlier staged photos');
 $r=photoHttp($clients['other'],'POST','/api/donation-reports',$body3,$proof);
 photoCheck($r['status']===403 && photoFiles()===$beforeFail,'Unauthorized donor submission discards staged proof');
 // Induce a notification persistence conflict on the next owned report ID.
 $next=(int)$p->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports'")->fetchColumn();
 $s=$p->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id,dedup_key,generation) VALUES(?,'test.photo.failure','Synthetic failure','Owned fixture','donation_report',? ,?,0)");$s->execute([$accounts['owner']['id'],$next,'donation-workflow:'.$next.':submitted:'.$accounts['owner']['id']]);$poisonId=(int)$p->lastInsertId();
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body3,$proof);
 photoCheck($r['status']===503 && photoFiles()===$beforeFail && photoCount('SELECT COUNT(*) FROM donation_reports WHERE match_id=?',[$matches[2]['match_id']])===0,'Notification persistence failure rolls back report/hold/evidence and removes staged photo');
 $p->prepare('DELETE FROM notifications WHERE id=?')->execute([$poisonId]);$poisonId=null;
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body3,$proof);
 photoCheck($r['status']===200,'Retry after transaction failure creates the report successfully');
 $beforeLimits=photoFiles();
 $r=photoHttp($clients['other'],'POST','/api/donation-reports',$body3,[$proof[0],$proof[0],$proof[0],$proof[0]]);
 photoCheck($r['status']===400 && photoFiles()===$beforeLimits,'More than three photos are rejected without orphan files');
 $large=tempnam(sys_get_temp_dir(),'bm-photo-');$temps[]=$large;file_put_contents($large,str_repeat('X',5*1024*1024+1));
 $r=photoHttp($clients['other'],'POST','/api/donation-reports',$body3,[[$large,'image/png','large.png']]);
 photoCheck($r['status']===400 && photoFiles()===$beforeLimits,'A photo exceeding 5 MB is rejected without orphan files');
 $jpg=tempnam(sys_get_temp_dir(),'bm-photo-');$webp=tempnam(sys_get_temp_dir(),'bm-photo-');$temps[]=$jpg;$temps[]=$webp;
 $image=imagecreatetruecolor(4,4);imagejpeg($image,$jpg);imagewebp($image,$webp);imagedestroy($image);
 $body4=['match_id'=>(string)$matches[3]['match_id'],'occurred_at'=>gmdate('Y-m-d\TH:i:s\Z',time()-3300)];
 $r=photoHttp($clients['donor'],'POST','/api/donation-reports',$body4,[$proof[0],[$jpg,'image/jpeg','proof.jpg'],[$webp,'image/webp','proof.webp']]);
 $threeId=(int)($r['json']['data']['report']['id']??0);
 photoCheck($r['status']===200 && photoCount('SELECT COUNT(*) FROM donation_report_evidence WHERE report_id=?',[$threeId])===3,'Three optional PNG/JPG/WEBP photos are normalized, encrypted and attached');
 // Synthetic legacy document only; historical application uploads are never read or changed.
 $name=bin2hex(random_bytes(32));$legacyPath=DocumentStorageService::pathFor($name);$owned[]=$legacyPath;
 $legacy="%PDF-1.4\nSynthetic legacy fixture\n%%EOF\n";file_put_contents($legacyPath,$legacy);
 $legacyDoc=(new DocumentRepository())->create((int)$accounts['owner']['id'],'national_id',['stored_name'=>$name,'mime_type'=>'application/pdf','original_ext'=>'pdf','size_bytes'=>strlen($legacy)],gmdate('Y-m-d H:i:s'));
 $r=photoHttp($clients['owner'],'GET','/api/profile/documents/'.$legacyDoc.'/file');
 photoCheck($r['status']===200 && $r['raw']===$legacy && file_get_contents($legacyPath)===$legacy,'Dual mode reads an authorized legacy file without conversion');
 $r=photoHttp($clients['other'],'GET','/api/profile/documents/'.$legacyDoc.'/file');photoCheck($r['status']===404,'Legacy-file access remains owner-restricted');
 $ph=implode(',',array_fill(0,count($ids),'?'));
 photoCheck(photoCount("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id IN ($ph)",$ids)===0,'Donation workflow uses only fake in-app notifications and enqueues no emails');
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage().PHP_EOL);}
finally{
 try{
  // Capture only paths attached to owned fixture donors, never directory-wide deletion.
  if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$s=$p->prepare("SELECT e.stored_name FROM donation_report_evidence e JOIN donation_reports d ON d.id=e.report_id WHERE d.donor_id IN ($ph)");$s->execute($ids);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $name)$owned[]=DocumentStorageService::pathFor($name);
   $s=$p->prepare("SELECT COUNT(*) FROM users WHERE id IN ($ph) AND email LIKE ?");$s->execute([...$ids,"photo-http-$nonce-%"]);if((int)$s->fetchColumn()!==count($ids))throw new RuntimeException('Fixture ownership changed; stop cleanup.');
   $p->beginTransaction();
   $p->prepare("DELETE e FROM donation_report_evidence e JOIN donation_reports d ON d.id=e.report_id WHERE d.donor_id IN ($ph)")->execute($ids);
   $p->prepare("DELETE FROM donation_reports WHERE donor_id IN ($ph)")->execute($ids);
   $p->prepare("DELETE FROM notifications WHERE user_id IN ($ph)")->execute($ids);
   $p->prepare("DELETE FROM member_documents WHERE user_id IN ($ph)")->execute($ids);
   foreach($requests as $id)$p->prepare('DELETE FROM blood_requests WHERE id=? AND requester_id=?')->execute([$id,$accounts['owner']['id']]);
   // Context acceptance creates a pair conversation; remove only this owned pair.
   $s=$p->prepare("SELECT DISTINCT conversation_id FROM conversation_participants WHERE user_id IN ($ph)");$s->execute($ids);$conversations=$s->fetchAll(PDO::FETCH_COLUMN);
   foreach($conversations as $id){if(photoCount("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id=? AND user_id NOT IN ($ph)",[(int)$id,...$ids])!==0)throw new RuntimeException('Unexpected fixture conversation participant.');$p->prepare('DELETE FROM conversations WHERE id=?')->execute([$id]);}
   $p->prepare("DELETE FROM users WHERE id IN ($ph)")->execute($ids);$p->commit();
  }
  foreach(array_unique($owned) as $path){if(is_link($path))throw new RuntimeException('Unsafe owned fixture path.');if(is_file($path)&&!unlink($path))throw new RuntimeException('Owned fixture file cleanup failed.');}
  foreach([...$cookies,...$temps] as $path)if(is_file($path))unlink($path);
  echo "Owned photo fixtures cleaned; historical uploads unchanged; SMTP disabled.\n";
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();$failed=true;fwrite(STDERR,'CLEANUP STOPPED: '.$e->getMessage().PHP_EOL);}
}
if($failed)exit(1);
echo "== donation_photo_http: $checks passed, 0 failed ==\n";

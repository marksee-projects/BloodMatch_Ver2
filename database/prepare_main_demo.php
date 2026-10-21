<?php
/** Reviewed transactional transfer into the user's explicitly selected MAIN DB.
 * No schema changes, no FK disabling, no matching/notification/email workers.
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
const MAIN_DEMO_BATCH='DEMO_MAIN_20261022_B42E';
Env::load(dirname(__DIR__).'/.env');
if(Env::get('DB_HOST')!=='127.0.0.1'||Env::get('DB_NAME')!=='bloodmatch')throw new RuntimeException('Unexpected main configuration.');
$p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch;charset=utf8mb4',Env::get('DB_USER'),Env::get('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$p->exec("SET time_zone='+00:00'");
$target=$p->query('SELECT DATABASE() db,@@port port,@@hostname host')->fetch();if($target['db']!=='bloodmatch'||(int)$target['port']!==3306)throw new RuntimeException('Target mismatch');
$root=dirname(__DIR__);$prior=$root.'/backend/storage/maintenance/DEMO_20261022_7C91';$dir=$root.'/backend/storage/maintenance/'.MAIN_DEMO_BATCH;if(!is_dir($dir))mkdir($dir,0700,true);
function mq(string $s):string{return '`'.str_replace('`','``',$s).'`';}
function mainSnap(PDO $p):array{$out=[];foreach($p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$t]){$c=$p->query('SHOW FULL COLUMNS FROM '.mq($t))->fetchAll();$cols=array_column(array_filter($c,fn($r)=>!str_contains($r['Extra'],'GENERATED')),'Field');$pk=array_column(array_filter($c,fn($r)=>$r['Key']==='PRI'),'Field');$out[$t]=['ddl'=>$p->query('SHOW CREATE TABLE '.mq($t))->fetch(PDO::FETCH_NUM)[1],'columns'=>$cols,'pk'=>$pk,'rows'=>$p->query('SELECT '.implode(',',array_map('mq',$cols)).' FROM '.mq($t).' ORDER BY '.implode(',',array_map('mq',$cols)))->fetchAll()];}return $out;}
function ms(string $name,mixed $v):void{global $dir;if(file_put_contents($dir.'/'.$name,json_encode($v,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Private backup write failed');}
$mode=$argv[1]??'review';$source=json_decode(file_get_contents($prior.'/prepared.json'),true,512,JSON_THROW_ON_ERROR);$oldManifest=json_decode(file_get_contents($prior.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
if($source['database']!=='bloodmatch_demo_20261022_7c91')throw new RuntimeException('Unexpected source snapshot');
$existing=[3=>'markseejr@gmail.com',69=>'p5rej346578869@test.local',85=>'p5other189351979@test.local',89=>'p5rejflow189351979@test.local',104=>'p5rejflow918167087@test.local'];
foreach($existing as $id=>$email){$q=$p->prepare('SELECT email,role FROM users WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r||$r['email']!==$email||$r['role']!==($id===3?'admin':'member'))throw new RuntimeException('Existing account allowlist mismatch');}
$order=['users','member_documents','verification_decisions','blood_requests','matches','conversations','messages','conversation_participants','chat_requests','donation_chat_contexts','donation_reports','donation_report_evidence','donation_report_events','donation_correction_requests','donation_coordination_events','chat_reports','chat_report_evidence','notifications','audit_log'];
if($mode==='review'){echo json_encode(['target'=>$target,'batch'=>MAIN_DEMO_BATCH,'existing_example_ids'=>[69,85,89,104],'admin_user_untouched'=>3,'source_accounts'=>count($source['tables']['users']['rows']),'source_requests'=>count($source['tables']['blood_requests']['rows']),'source_reports'=>count($source['tables']['donation_reports']['rows']),'tables'=>$order,'excluded'=>'All source reference/schema, sessions, authentication/accounting tables and email jobs. All notifications to Admin/genuine accounts excluded. All primary/foreign and logical IDs remapped.'],JSON_PRETTY_PRINT);exit;}
if(!in_array($mode,['apply','cleanup','restore-examples'],true)||($argv[2]??'')!==MAIN_DEMO_BATCH)throw new RuntimeException('Use review, or apply/cleanup/restore-examples plus exact batch');
if(in_array($mode,['cleanup','restore-examples'],true)){
 $m=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);$after=json_decode(file_get_contents($dir.'/created-records.json'),true,512,JSON_THROW_ON_ERROR);$back=json_decode(file_get_contents($dir.'/before.json'),true,512,JSON_THROW_ON_ERROR);
 // Refuse intervening edits to the original example profiles. No password writes.
 $p->beginTransaction();try{
 foreach($m['changed_examples'] as $id=>$fields){$q=$p->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$cur=$q->fetch();foreach($fields as $f=>$values)if($cur[$f]!==$values['after'])throw new RuntimeException('Example profile changed; refuse automatic restore.');}
 if($mode==='cleanup')foreach(array_reverse($order) as $t){if($t==='audit_log')continue;foreach(array_reverse($after[$t]??[]) as $r){$id=$r['id']??null;if(!$id)throw new RuntimeException('Unexpected missing record ID');$q=$p->prepare('SELECT * FROM '.mq($t).' WHERE id=? FOR UPDATE');$q->execute([$id]);$cur=$q->fetch();if(!$cur)continue;foreach($r as $c=>$v)if(($cur[$c]??null)!==$v)throw new RuntimeException('Demo record changed: '.$t.' #'.$id.'; review required.');$p->prepare('DELETE FROM '.mq($t).' WHERE id=?')->execute([$id]);}}
 foreach($m['changed_examples'] as $id=>$fields){$names=array_keys($fields);$q=$p->prepare('UPDATE users SET '.implode(',',array_map(fn($c)=>mq($c).'=?',$names)).' WHERE id=?');$q->execute([...array_map(fn($f)=>$f['before'],$fields),$id]);}
 $p->prepare("INSERT INTO audit_log(action,target_type,target_id,context) VALUES('demo.main.restore','demo_batch',?,?)")->execute([MAIN_DEMO_BATCH,json_encode(['mode'=>$mode,'append_only_audit_retained'=>true])]);$p->commit();echo $mode." completed within the recorded scope. Audit history and image files retained.\n";
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}exit;
}
if(is_file($dir.'/manifest.json')){echo "Main demo batch already applied; no duplicates created.\n";exit;}
$before=mainSnap($p);
if(is_file($dir.'/before.json')){
 if(!is_file($dir.'/failure.json'))throw new RuntimeException('A previous attempt has a backup; inspect it before retrying.');
 $saved=json_decode(file_get_contents($dir.'/before.json'),true,512,JSON_THROW_ON_ERROR)['tables'];foreach($saved as $t=>$v)if(!in_array($t,['audit_log','user_presence'],true)&&$v['rows']!==$before[$t]['rows'])throw new RuntimeException('Original business data changed after failed transfer: '.$t);$before=$saved;
}else ms('before.json',['batch'=>MAIN_DEMO_BATCH,'target'=>$target,'tables'=>$before,'triggers'=>$p->query('SHOW TRIGGERS')->fetchAll()]);
$norm=fn($ddl)=>preg_replace('/ AUTO_INCREMENT=\d+/','',$ddl);
foreach($order as $t)if($norm($before[$t]['ddl'])!==$norm($source['tables'][$t]['ddl']))throw new RuntimeException('Schema changed for '.$t.'; review transfer.');
$fks=[];foreach($p->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME='id'")->fetchAll() as $f)$fks[$f['TABLE_NAME']][$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];
$maps=['users'=>array_combine(array_keys($existing),array_keys($existing))];$made=[];$changed=[];
$logical=['blood_request'=>'blood_requests','request'=>'blood_requests','match'=>'matches','donation_report'=>'donation_reports','chat_request'=>'chat_requests','conversation'=>'conversations','chat_report'=>'chat_reports','user'=>'users','verification'=>'users','account'=>'users'];
$jsonKeys=['match_id'=>'matches','request_id'=>'blood_requests','report_id'=>'donation_reports','context_id'=>'donation_chat_contexts','offer_context_id'=>'donation_chat_contexts','conversation_id'=>'conversations','recipient_id'=>'users','donor_id'=>'users','user_id'=>'users','actor_id'=>'users','actor_reference'=>'users','target_user_id'=>'users','document_id'=>'member_documents','evidence_id'=>'donation_report_evidence'];
$remapJson=function($v,$key='')use(&$remapJson,&$maps,$jsonKeys){if($key==='proof_ids'&&is_array($v))return array_map(fn($id)=>$maps['donation_report_evidence'][$id]??$id,$v);if(is_array($v)){foreach($v as $k=>$x)$v[$k]=$remapJson($x,(string)$k);return $v;}if(isset($jsonKeys[$key])&&is_numeric($v))return $maps[$jsonKeys[$key]][$v]??$v;return is_string($v)?str_replace('DEMO_20261022_7C91',MAIN_DEMO_BATCH,$v):$v;};
$p->beginTransaction();try{
 foreach($existing as $id=>$email){$q=$p->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$cur=$q->fetch();if($cur['email']!==$email)throw new RuntimeException('Account changed while preparing');}
 foreach($order as $t){$cols=$source['tables'][$t]['columns'];foreach($source['tables'][$t]['rows'] as $values){$row=array_combine($cols,$values);$old=(int)$row['id'];
  if($t==='users'&&isset($existing[$old])){
   if($old===85){$fields=['date_of_birth','blood_type','blood_type_source','blood_type_verified','donor_enrolled_at','donor_availability','latitude','longitude'];$cur=array_values(array_filter($before['users']['rows'],fn($u)=>(int)$u['id']===$old))[0];$updates=[];foreach($fields as $c)if($cur[$c]!==$row[$c]){$changed[$old][$c]=['before'=>$cur[$c],'after'=>$row[$c]];$updates[$c]=$row[$c];}if($updates){$q=$p->prepare('UPDATE users SET '.implode(',',array_map(fn($c)=>mq($c).'=?',array_keys($updates))).',updated_at=updated_at WHERE id=?');$q->execute([...array_values($updates),$old]);}}
   continue;
  }
  if($t==='notifications'&&!in_array((int)$row['user_id'],[69,85,89,104],true)&&!isset($maps['users'][$row['user_id']]))throw new RuntimeException('Unknown notification recipient');
  if($t==='notifications'&&(int)$row['user_id']===3)continue;
  if($t==='users'){$row['email']=str_replace('demo_20261022_7c91.',strtolower(MAIN_DEMO_BATCH).'.',$row['email']);$q=$p->prepare('SELECT id FROM users WHERE email=?');$q->execute([$row['email']]);if($q->fetchColumn())throw new RuntimeException('Synthetic main email already exists');}
  foreach($fks[$t]??[] as $c=>$parent)if($row[$c]!==null&&isset($source['tables'][$parent])&&!in_array($parent,['chapters','bataan_locations','hospitals'],true)){
   if(!isset($maps[$parent][$row[$c]]))throw new RuntimeException('Unmapped FK '.$t.'.'.$c.' -> '.$parent.' #'.$row[$c]);$row[$c]=$maps[$parent][$row[$c]];
  }
  if($t==='donation_report_events'&&$row['actor_reference']!==null)$row['actor_reference']=$maps['users'][$row['actor_reference']]??throw new RuntimeException('Unmapped actor reference');
  if(isset($row['related_type'],$row['related_id'])&&isset($logical[$row['related_type']]))$row['related_id']=$maps[$logical[$row['related_type']]][$row['related_id']]??throw new RuntimeException('Unmapped related ID');
  if(isset($row['target_type'],$row['target_id'])&&isset($logical[$row['target_type']]))$row['target_id']=(string)($maps[$logical[$row['target_type']]][$row['target_id']]??throw new RuntimeException('Unmapped audit target'));
  foreach($row as $c=>&$v){if(is_string($v))$v=str_replace('DEMO_20261022_7C91',MAIN_DEMO_BATCH,$v);if(in_array($c,['context','snapshot_json'],true)&&$v!==null){$data=json_decode($v,true,512,JSON_THROW_ON_ERROR);$data=$remapJson($data);if(is_array($data))$data['demo_batch']=MAIN_DEMO_BATCH;$v=json_encode($data,JSON_THROW_ON_ERROR);}}unset($v);
  if($t==='notifications'&&$row['dedup_key']!==null)$row['dedup_key']=MAIN_DEMO_BATCH.':'.substr(hash('sha256',$row['dedup_key'].':'.$old),0,64);
  unset($row['id']);$q=$p->prepare('INSERT INTO '.mq($t).' ('.implode(',',array_map('mq',array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));$new=(int)$p->lastInsertId();$maps[$t][$old]=$new;$made[$t][]=['id'=>$new]+$row;
 }}
 $q=$p->prepare("INSERT INTO audit_log(action,target_type,target_id,context) VALUES('demo.main.prepared','demo_batch',?,?)");$q->execute([MAIN_DEMO_BATCH,json_encode(['source_batch'=>$source['batch'],'user_authorized_main_database'=>true,'no_smtp'=>true])]);$made['audit_log'][]=['id'=>(int)$p->lastInsertId(),'action'=>'demo.main.prepared'];
 // Verify protected business rows byte-for-byte; only source test 85 is adjusted.
 foreach($before as $t=>$meta){if(in_array($t,['audit_log','user_presence'],true))continue;foreach($meta['rows'] as $r){if($t==='users'&&(int)$r['id']===85)continue;if(!$meta['pk'])continue;$q=$p->prepare('SELECT '.implode(',',array_map('mq',$meta['columns'])).' FROM '.mq($t).' WHERE '.implode(' AND ',array_map(fn($c)=>mq($c).'=?',$meta['pk'])));$q->execute(array_map(fn($c)=>$r[$c],$meta['pk']));if($q->fetch()!==$r)throw new RuntimeException('Protected original record changed: '.$t);}}
 $m=$oldManifest;$m['batch']=MAIN_DEMO_BATCH;$m['database']='bloodmatch';$m['source_isolated_batch']=$source['batch'];$m['target']=$target;$m['maps']=$maps;$m['changed_examples']=$changed;$m['status']='COMMITTED';$m['checks']=['protected_original_business_rows_unchanged'=>true,'no_main_admin_mutation'=>true,'no_notifications_to_genuine_accounts'=>true,'foreign_keys_enabled'=>(int)$p->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()===1,'schema_unchanged'=>true,'browser_tested'=>false,'source_api_checks_are_historical_only'=>true];
 foreach($m['accounts'] as &$a){$a['id']=$maps['users'][$a['id']];$a['email']=str_replace('demo_20261022_7c91.',strtolower(MAIN_DEMO_BATCH).'.',$a['email']);}unset($a);
 foreach($m['requests'] as &$r){$r['id']=$maps['blood_requests'][$r['id']];$r['screen']='/requests/'.$r['id'].'/matches';}unset($r);
 foreach($m['cases'] as $case=>&$c){foreach($c as $k=>&$v)if(isset($jsonKeys[$k])&&is_numeric($v))$v=$maps[$jsonKeys[$k]][$v]??$v;unset($v);if($case==='chat-pending')$c['id']=$maps['chat_requests'][$c['id']];if(isset($c['prior_withdrawn_match_id']))$c['prior_withdrawn_match_id']=$maps['matches'][$c['prior_withdrawn_match_id']];}unset($c);
 $m['created_counts']=array_map('count',$made);ms('created-records.json',$made);ms('pending-manifest.json',$m);
 $p->commit();ms('manifest.json',$m);echo json_encode(['target'=>$target,'batch'=>MAIN_DEMO_BATCH,'created_counts'=>$m['created_counts'],'main_request_id'=>$m['requests']['main-live']['id'],'checks'=>$m['checks']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();ms('failure.json',['error'=>$e->getMessage(),'rolled_back'=>true]);throw $e;}

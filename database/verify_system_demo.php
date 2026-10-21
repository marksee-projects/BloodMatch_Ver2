<?php
/** Read-only safety verification, including normalization of the authorized release. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
Env::load(dirname(__DIR__).'/.env');$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';
$p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch;charset=utf8mb4',Env::get('DB_USER'),Env::get('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$p->exec("SET time_zone='+00:00'");
$before=json_decode(file_get_contents($dir.'/source-fingerprint.json'),true);$release=json_decode(file_get_contents($dir.'/released-emails-backup.json'),true);$results=[];
foreach($before as $t=>$expected){$cols=$p->query('SHOW FULL COLUMNS FROM `'.$t.'`')->fetchAll();$names=array_column(array_filter($cols,fn($c)=>!str_contains($c['Extra'],'GENERATED')),'Field');$rows=$p->query('SELECT `'.implode('`,`',$names).'` FROM `'.$t.'` ORDER BY `'.implode('`,`',$names).'`')->fetchAll(PDO::FETCH_NUM);
 if(in_array($t,['users','email_outbox'],true)){
  $saved=$release[$t==='users'?'before_users':'before_jobs'];$map=[];foreach($saved as $r)$map[$r['id']]=$r;$idx=array_search('id',$names,true);
  foreach($rows as &$row)if(isset($map[$row[$idx]])){$r=$map[$row[$idx]];$row=array_map(fn($n)=>$r[$n],$names);}unset($row);
 }
 if($t==='audit_log'){$action=array_search('action',$names,true);$ctx=array_search('context',$names,true);$rows=array_values(array_filter($rows,fn($r)=>!($r[$action]==='demo.email.apply'&&str_contains($r[$ctx]??'','DEMO_20261022_7C91'))));}
 $results[$t]=count($rows)===$expected['count']&&hash('sha256',json_encode($rows))===$expected['sha256'];
}
$passwords=true;$free=true;$pendingFree=true;
foreach($release['before_users'] as $u){$s=$p->prepare('SELECT email,password_hash,account_status FROM users WHERE id=?');$s->execute([$u['id']]);$current=$s->fetch();$passwords=$passwords&&hash_equals($u['password_hash'],$current['password_hash']);$s=$p->prepare('SELECT COUNT(*) FROM users WHERE email=?');$s->execute([$u['email']]);$free=$free&&(int)$s->fetchColumn()===0;$s=$p->prepare('SELECT COUNT(*) FROM pending_registrations WHERE email=?');$s->execute([$u['email']]);$pendingFree=$pendingFree&&(int)$s->fetchColumn()===0;}
$demo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch_demo_20261022_7c91;charset=utf8mb4',Env::get('DB_USER'),Env::get('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$baseline=json_decode(file_get_contents($dir.'/baseline.json'),true);$schemas=[];foreach($baseline['tables'] as $t=>$v){$current=$demo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];$norm=fn($sql)=>preg_replace('/ AUTO_INCREMENT=\d+/','',$sql);$schemas[$t]=$norm($v['ddl'])===$norm($current);}
$report=['application_tables_unchanged_except_authorized_release'=>!in_array(false,$results,true),'table_checks'=>$results,'nine_passwords_unchanged'=>$passwords,'nine_working_emails_free_in_users'=>$free,'nine_working_emails_free_in_pending_registrations'=>$pendingFree,'demo_schema_matches_current_copy'=>!in_array(false,$schemas,true),'demo_schema_table_checks'=>$schemas,'browser_tested'=>false];
$report['concurrent_application_audit']=$p->query("SELECT id,actor_id,action,created_at FROM audit_log WHERE id>2732 AND action<>'demo.email.apply' ORDER BY id")->fetchAll();
$business=$results;unset($business['audit_log'],$business['user_presence']);$report['business_tables_unchanged_except_authorized_release']=!in_array(false,$business,true);
$report['runtime_drift_note']='The original application remains open. Its audit/presence tables changed during normal polling after account 147 was deactivated. No attempt was made to overwrite that concurrent activity.';
$report['release_audit_ids']=$p->query("SELECT id FROM audit_log WHERE action='demo.email.apply' AND JSON_UNQUOTE(JSON_EXTRACT(context,'$.batch'))='DEMO_20261022_7C91' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
file_put_contents($dir.'/safety-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);if(!$report['nine_passwords_unchanged']||!$report['nine_working_emails_free_in_users']||!$report['demo_schema_matches_current_copy'])exit(1);

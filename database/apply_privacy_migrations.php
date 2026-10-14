<?php
/** User-run, additive migration runner. Defaults to read-only review. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\MaintenanceBackupService;
Env::load(dirname(__DIR__).'/.env');
$args=getopt('',['target:','migration:','apply','offline-ack','sha256:']);
$target=$args['target']??'bloodmatch_test';$number=$args['migration']??'025';
try {
 if (!in_array($target,['bloodmatch','bloodmatch_test'],true) || !in_array($number,['025','026','027'],true)) throw new RuntimeException('Unsupported target/migration.');
 $port=(int)($target==='bloodmatch_test'?Env::get('TEST_DB_PORT','3306'):Env::get('DB_PORT','3306'));
 $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port='.$port.';dbname='.$target.';charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $p->exec("SET time_zone = '+00:00'");
 if ((int)$p->query('SELECT @@port')->fetchColumn()!==$port) throw new RuntimeException('Resolved port mismatch.');
 $name=['025'=>'025_chat_privacy_lifecycle.sql','026'=>'026_audit_retention.sql','027'=>'027_single_administrator.sql'][$number];$sql=file_get_contents(__DIR__.'/migrations/'.$name);$hash=hash('sha256',$sql);
 foreach(['users','conversations','conversation_participants','messages'] as $table) {
  $s=$p->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'");$s->execute([$table]);
  if (!$s->fetchColumn()) throw new RuntimeException('Rehearse and apply the reviewed import repair first.');
 }
 $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$name]);
 if ($s->fetchColumn()) {echo "Already applied: $name\n";exit;}
 if ($number==='025') {
  $s=$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('pending_registrations','chat_requests','chat_reports','chat_report_evidence','account_closures')");
  if ((int)$s->fetchColumn()!==0) throw new RuntimeException('Partial migration detected: restore/inspect before retrying.');
 } else { $p->query('SELECT id FROM chat_reports LIMIT 0'); }
 if ($number==='027' && ((int)$p->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()!==1 || (int)$p->query("SELECT COUNT(*) FROM users WHERE role='admin' AND email='markseejr@gmail.com' AND account_status='active'")->fetchColumn()!==1)) throw new RuntimeException('Reviewed cleanup must first leave exactly the designated active administrator.');
 echo json_encode(['target'=>$target,'port'=>$port,'migration'=>$name,'sha256'=>$hash,'users'=>(int)$p->query('SELECT COUNT(*) FROM users')->fetchColumn(),'existing_conversations_receive_consent'=>false],JSON_PRETTY_PRINT),PHP_EOL;
 if (!isset($args['apply'])) exit;
 if (!isset($args['offline-ack']) || !hash_equals($hash,(string)($args['sha256']??''))) throw new RuntimeException('Apply requires stopped servers/workers, --offline-ack and reviewed --sha256.');
 echo 'Backup: '.MaintenanceBackupService::create($target,$port).PHP_EOL;
 // 026 contains a compound trigger; its two statements use an explicit boundary.
 $statements=$number!=='025'?explode('-- next statement',$sql):explode(';',$sql);
 foreach($statements as $statement) if (trim($statement)!=='') $p->exec($statement);
 $s=$p->prepare('INSERT INTO schema_migrations(name) VALUES(?)');$s->execute([$name]);
 echo "Applied $name. DDL is not transactional; retain the backup.\n";
} catch(Throwable $e) {fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL);exit(1);}

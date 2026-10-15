<?php
/** Fixtures for user-run, small functional tests only. Never application data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
Env::load(dirname(__DIR__).'/.env');
try {
    if(Env::get('TEST_DB_NAME')!=='bloodmatch_test' || Env::get('TEST_DB_PORT')!=='3306') throw new LogicException('Use tests/run_isolated.ps1 with bloodmatch_test:3306.');
    $p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch_test;charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $p->exec("SET time_zone = '+00:00'");
    if($p->query('SELECT DATABASE()')->fetchColumn()!=='bloodmatch_test') throw new LogicException('Wrong database.');
    $args=getopt('',['create','cleanup:','revoke:','otp:','signup-status:','expire-request:']);
    $dir=dirname(__DIR__).'/backend/storage/maintenance';if(!is_dir($dir))mkdir($dir,0700,true);
    if(isset($args['create'])){
        foreach(['conversations','conversation_participants','messages'] as $table)if((int)$p->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND INDEX_NAME='PRIMARY'")->fetchColumn()!==1)throw new LogicException('Repair the imported test chat schema first.');
        foreach(['pending_registrations','chat_requests','chat_reports','chat_report_evidence','account_closures'] as $table) if((int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'")->fetchColumn()!==1) throw new LogicException('Apply migration 025 to the isolated database before creating fixtures.');
        $nonce=bin2hex(random_bytes(10));$accounts=[];
        $path=$dir.'/chat-api-'.$nonce.'.json';
        $p->beginTransaction();
        try {
            $s=$p->prepare("INSERT INTO users(email,password_hash,first_name,last_name,role,verification_status,account_status,date_of_birth,email_verified_at,deactivated_at) VALUES (?,?,'Chat','Fixture',?,?,?,'1990-01-01',UTC_TIMESTAMP(),?)");
            foreach(['a'=>'verified','b'=>'verified','pending'=>'pending','rejected'=>'rejected','outsider'=>'verified','inactive'=>'verified'] as $name=>$status){
                $email="chat-api-$nonce-$name@example.test";$password='Fixture-'.bin2hex(random_bytes(10));$active=$name!=='inactive';
                $s->execute([$email,password_hash($password,PASSWORD_BCRYPT),'member',$status,$active?'active':'deactivated',$active?null:gmdate('Y-m-d H:i:s')]);
                $accounts[$name]=['id'=>(int)$p->lastInsertId(),'email'=>$email,'password'=>$password];
            }
            $p->exec("INSERT INTO conversations(type) VALUES('direct')");$legacyId=(int)$p->lastInsertId();
            $s=$p->prepare('INSERT INTO conversation_participants(conversation_id,user_id) VALUES(?,?),(?,?)');$s->execute([$legacyId,$accounts['a']['id'],$legacyId,$accounts['outsider']['id']]);
            $s=$p->prepare('INSERT INTO messages(conversation_id,sender_id,body) VALUES(?,?,?)');$s->execute([$legacyId,$accounts['outsider']['id'],'Owned imported-history fixture']);
            if(file_put_contents($path,json_encode(['nonce'=>$nonce,'accounts'=>$accounts,'legacy_conversation_id'=>$legacyId,'registration'=>['email'=>"chat-api-$nonce-signup@example.test",'password'=>'Fixture-'.bin2hex(random_bytes(10))]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot save fixture manifest.');
            $p->commit();
        }catch(Throwable $e){$p->rollBack();throw $e;}
        echo $path.PHP_EOL;exit;
    }
    $path=realpath($args['cleanup']??$args['revoke']??$args['otp']??$args['signup-status']??$args['expire-request']??'');$root=realpath($dir);
    if(!$path || !str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$root).'/chat-api-'))throw new LogicException('Unexpected fixture path.');
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach($fixture['accounts'] as $name=>$account){
        $s=$p->prepare('SELECT email FROM users WHERE id=?');$s->execute([$account['id']]);$email=$s->fetchColumn();
        if($email!==false && ($email!==$account['email'] || $email!=="chat-api-{$fixture['nonce']}-$name@example.test"))throw new LogicException('Fixture ownership mismatch.');
    }
    $signup=$fixture['registration']??null;
    if ($signup && $signup['email']!=="chat-api-{$fixture['nonce']}-signup@example.test") throw new LogicException('Signup ownership mismatch.');
    if(isset($args['otp'])) {
        $s=$p->prepare('UPDATE pending_registrations SET code_hash=?,code_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE email=?');$s->execute([password_hash('123456',PASSWORD_BCRYPT),$signup['email']]);
        if ($s->rowCount()!==1) throw new LogicException('Expected one owned pending registration.');echo 'fixture OTP set'.PHP_EOL;exit;
    }
    if(isset($args['signup-status'])) {
        $s=$p->prepare('SELECT id,location_id,verification_status FROM users WHERE email=?');$s->execute([$signup['email']]);$user=$s->fetch();
        if ($user) { $fixture['accounts']['signup']=['id'=>(int)$user['id'],'email'=>$signup['email'],'password'=>$signup['password']];file_put_contents($path,json_encode($fixture,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX); }
        $s=$p->prepare('SELECT COUNT(*) FROM pending_registrations WHERE email=?');$s->execute([$signup['email']]);echo json_encode(['user_count'=>$user?1:0,'pending_count'=>(int)$s->fetchColumn(),'location_id'=>$user['location_id']??null,'verification_status'=>$user['verification_status']??null]),PHP_EOL;exit;
    }
    if(isset($args['revoke'])){$p->prepare("UPDATE users SET verification_status='pending' WHERE id=?")->execute([$fixture['accounts']['b']['id']]);echo 'revoked'.PHP_EOL;exit;}
    if(isset($args['expire-request'])) {
        $s=$p->prepare("UPDATE chat_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY),updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY) WHERE sender_id=? AND recipient_id=? AND status='pending'");
        $s->execute([$fixture['accounts']['a']['id'],$fixture['accounts']['b']['id']]);
        if ($s->rowCount()!==1) throw new LogicException('Expected one owned pending chat request.');
        echo 'owned request expired'.PHP_EOL;exit;
    }
    if ($signup) {
        $s=$p->prepare('SELECT id FROM users WHERE email=?');$s->execute([$signup['email']]);$signupId=$s->fetchColumn();
        if ($signupId) $fixture['accounts']['signup']=['id'=>(int)$signupId,'email'=>$signup['email'],'password'=>$signup['password']];
    }
    $p->beginTransaction();
    try{
        $ids=implode(',',array_map(fn($account)=>(int)$account['id'],$fixture['accounts']));
        $p->exec("DELETE FROM notifications WHERE related_type='chat_report' AND related_id IN (SELECT id FROM chat_reports WHERE reporter_id IN ($ids))");
        $p->exec("DELETE FROM chat_reports WHERE reporter_id IN ($ids)");
        if ($signup) { $s=$p->prepare('DELETE FROM pending_registrations WHERE email=?');$s->execute([$signup['email']]); }
        $p->exec("DELETE c FROM conversations c WHERE EXISTS (SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id=c.id AND cp.user_id IN ($ids)) AND NOT EXISTS (SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id=c.id AND cp.user_id NOT IN ($ids))");
        foreach($fixture['accounts'] as $account){
            $p->prepare('DELETE FROM users WHERE id=? AND email=?')->execute([$account['id'],$account['email']]);
            $p->prepare('DELETE FROM auth_throttle WHERE identifier IN (?,?)')->execute(['login:'.$account['email'],'chat_send:'.$account['id']]);
        }
        $p->commit();
    }catch(Throwable $e){$p->rollBack();throw $e;}
    unlink($path);echo 'fixture accounts removed; append-only test audit history retained'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL);exit(1);}

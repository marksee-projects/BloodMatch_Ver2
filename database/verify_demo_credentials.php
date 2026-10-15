<?php
/** CLI-only, SELECT-only verification; never prints passwords or hashes. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BLOODMATCH_MAINTENANCE_FUNCTIONS_ONLY',true);
require __DIR__.'/demo_maintenance.php';
use BloodMatch\Config\Env;

try {
    $args=getopt('',['target:','file:']);$target=$args['target']??'bloodmatch_test';
    if (!in_array($target,['bloodmatch','bloodmatch_test'],true)) throw new LogicException('Unsupported target.');
    $root=realpath(dirname(__DIR__).'/backend/storage/maintenance');$path=realpath($args['file']??'');
    if (!$root || !$path || !str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$root).'/') || !str_starts_with(basename($path),$target.'-') || !str_ends_with($path,'-credentials.json')) throw new LogicException('Unexpected private credential path.');
    $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if (($data['status']??'')!=='COMMITTED' || count($data['accounts']??[])!==15) throw new LogicException('Expected a committed fifteen-account manifest.');
    $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port=3306;dbname='.$target.';charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $actual=$p->query('SELECT DATABASE() db,@@port port')->fetch();
    if ($actual['db']!==$target || (int)$actual['port']!==3306 || scalar($p,'SELECT COUNT(*) FROM users')!==15) throw new LogicException('Unexpected database target/count.');
    $ids=[];$s=$p->prepare('SELECT email,role,password_hash FROM users WHERE id=?');
    foreach ($data['accounts'] as $account) {
        $s->execute([$account['id']]);$row=$s->fetch();
        if (!$row || $row['email']!==$account['email'] || $row['role']!==$account['role'] || !password_verify($account['password'],$row['password_hash'])) throw new LogicException('A retained credential does not match; do not publish the manifest.');
        $ids[]=(int)$account['id'];
    }
    if (count(array_unique($ids))!==15) throw new LogicException('Duplicate retained credentials.');
    echo json_encode(['target'=>$target,'verified_credentials'=>15,'passwords_exposed'=>false],JSON_PRETTY_PRINT),PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL);exit(1); }

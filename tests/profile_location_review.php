<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
try {
    if (count($argv)!==2 || !preg_match('/^--user-id=([1-9][0-9]*)$/D',$argv[1],$match)) throw new LogicException('Use --user-id=NUMERIC_ID. Read-only application target bloodmatch:3306.');
    Env::load(dirname(__DIR__).'/.env');
    $pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch;charset=utf8mb4',Env::get('DB_USER',''),Env::get('DB_PASS',''),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='bloodmatch' || (int)$pdo->query('SELECT @@port')->fetchColumn()!==3306) throw new LogicException('Unexpected target; stop.');
    $query=$pdo->prepare('SELECT u.id,u.location_id,l.id AS reference_id,l.municipality_name,l.is_active FROM users u LEFT JOIN bataan_locations l ON l.id=u.location_id WHERE u.id=?');
    $query->execute([(int)$match[1]]); $row=$query->fetch();
    if(!$row) throw new LogicException('Account not found on the application target.');
    $status=$row['location_id']===null?'no_saved_location':($row['reference_id']===null?'missing_reference':(trim((string)$row['municipality_name'])===''?'missing_reference_label':'display_value_available'));
    echo json_encode(['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'writes'=>false,'user_id'=>(int)$row['id'],
        'saved_location_id'=>$row['location_id']===null?null:(int)$row['location_id'],'municipality_name'=>$row['municipality_name'],
        'reference_active'=>$row['is_active']===null?null:(bool)$row['is_active'],'status'=>$status],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) { fwrite(STDERR,'Location review failed: '.$error->getMessage().PHP_EOL); exit(1); }

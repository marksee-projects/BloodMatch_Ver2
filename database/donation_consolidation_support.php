<?php
declare(strict_types=1);

/** Fixed direct connection: never the application's port-fallback connector. */
function consolidationTarget(string $target): PDO
{
    if ((getenv('BLOODMATCH_TEST_ISOLATION') === '1' || ($_ENV['BLOODMATCH_TEST_ISOLATION']??'') === '1') && $target !== 'test') {
        throw new LogicException('Isolated test process cannot select an application target.');
    }
    $db = match($target) {'test'=>'bloodmatch_test','application'=>'bloodmatch',default=>throw new LogicException('Invalid target.')};
    \BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
    $p = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$db.';charset=utf8mb4',
        \BloodMatch\Config\Env::get('DB_USER',''),\BloodMatch\Config\Env::get('DB_PASS',''),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actual=$p->query('SELECT DATABASE() AS db,@@port AS port')->fetch();
    if ($actual['db']!==$db || (int)$actual['port']!==3306) throw new LogicException('Exact target mismatch; no fallback allowed.');
    $p->exec("SET time_zone='+00:00'");
    return $p;
}

function consolidationBackup(string $path,string $sha,string $database): void
{
    if(!is_file($path)||filesize($path)<1000||!hash_equals(hash_file('sha256',$path),$sha))throw new LogicException('Verified full backup SHA required.');
    $header=file_get_contents($path,false,null,0,4096);
    if(!preg_match('/Database:\s+'.preg_quote($database,'/').'[ \t]*(?:\r?\n|$)/',$header))throw new LogicException('Backup target header mismatch.');
    $f=fopen($path,'rb');fseek($f,max(0,filesize($path)-4096));$tail=stream_get_contents($f);fclose($f);
    if(!str_contains($tail,'-- Dump completed on'))throw new LogicException('Backup completion marker missing.');
}

function consolidationPreflight(PDO $p): array
{
    $queries=[
        'duplicate_report_match'=>'SELECT COUNT(*) FROM (SELECT match_id FROM donation_reports GROUP BY match_id HAVING COUNT(*)>1) x',
        'report_donor_mismatch'=>'SELECT COUNT(*) FROM donation_reports d JOIN matches m ON m.id=d.match_id WHERE d.donor_id<>m.donor_id',
        'context_mismatch'=>'SELECT COUNT(*) FROM donation_reports d JOIN donation_chat_contexts c ON c.id=d.offer_context_id WHERE d.match_id<>c.match_id',
        'credit_mismatch'=>"SELECT COUNT(*) FROM donation_credits c LEFT JOIN donation_reports d ON d.id=c.report_id WHERE d.id IS NULL OR c.match_id<>d.match_id OR c.donor_id<>d.donor_id OR NOT(c.occurred_at<=>d.occurred_at) OR NOT(c.completion_basis<=>d.completion_basis) OR d.status<>'CONFIRMED'",
        'hold_mismatch'=>'SELECT COUNT(*) FROM donation_report_holds h LEFT JOIN donation_reports d ON d.id=h.report_id WHERE d.id IS NULL OR h.donor_id<>d.donor_id OR (h.released_at IS NOT NULL AND h.released_at<h.started_at)',
        'renewal_orphan'=>'SELECT COUNT(*) FROM blood_requests b LEFT JOIN blood_requests old ON old.id=b.renewed_from_id WHERE b.renewed_from_id IS NOT NULL AND old.id IS NULL',
        'renewal_duplicates'=>'SELECT COUNT(*) FROM (SELECT renewed_from_id FROM blood_requests WHERE renewed_from_id IS NOT NULL GROUP BY renewed_from_id HAVING COUNT(*)>1) x',
        'chat_evidence_duplicates'=>'SELECT COUNT(*) FROM (SELECT report_id,message_id FROM chat_report_evidence GROUP BY report_id,message_id HAVING COUNT(*)>1) x',
        'invalid_quantity'=>'SELECT COUNT(*) FROM blood_requests WHERE quantity_units=0',
        'invalid_report_state'=>"SELECT COUNT(*) FROM donation_reports WHERE content_version<1 OR workflow_state NOT IN ('awaiting_requester','needs_review','clarification','completed','rejected') OR (requester_response IS NOT NULL AND requester_response NOT IN ('received','did_not_donate','not_sure'))"
    ];
    $result=[];foreach($queries as $label=>$sql)$result[$label]=(int)$p->query($sql)->fetchColumn();
    $types=['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $matrix=$p->query('SELECT recipient_type,allowed_donor_types FROM compatibility_matrix')->fetchAll(PDO::FETCH_KEY_PAIR);
    $invalid=count(array_diff($types,array_keys($matrix)))+count(array_diff(array_keys($matrix),$types));
    foreach($matrix as $list){$tokens=array_map('trim',explode(',',$list));$invalid+=count(array_diff($tokens,$types))+(count($tokens)-count(array_unique($tokens)))+(count($tokens)>8?1:0);}
    $result['invalid_compatibility']=$invalid;
    return $result;
}

/** Aggregate table digest; never emits row contents. */
function consolidationDigest(PDO $p,string $table,?array $columns=null): array
{
    if(!preg_match('/^[a-z_]+$/D',$table))throw new LogicException('Invalid table identifier.');
    $projection='*';
    if($columns!==null){foreach($columns as $c)if(!preg_match('/^[a-z_]+$/D',$c))throw new LogicException('Invalid column identifier.');$projection=implode(',',array_map(fn($c)=>'`'.$c.'`',$columns));}
    $order=match($table){'compatibility_matrix'=>'recipient_type','donation_credits','donation_report_holds'=>'report_id',default=>'id'};
    $rows=$p->query('SELECT '.$projection.' FROM `'.$table.'` ORDER BY '.$order);
    $hash=hash_init('sha256');$n=0;while($row=$rows->fetch(PDO::FETCH_ASSOC)){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$n++;}
    return ['rows'=>$n,'sha256'=>hash_final($hash)];
}

function consolidationReconcile(PDO $p): array
{
    return [
        'credit_copy_mismatches'=>(int)$p->query('SELECT COUNT(*) FROM donation_reports d LEFT JOIN donation_credits c ON c.report_id=d.id WHERE NOT(d.credit_recorded_at<=>c.recorded_at)')->fetchColumn(),
        'hold_copy_mismatches'=>(int)$p->query('SELECT COUNT(*) FROM donation_reports d LEFT JOIN donation_report_holds h ON h.report_id=d.id WHERE NOT(d.hold_started_at<=>h.started_at) OR NOT(d.hold_released_at<=>h.released_at) OR NOT(d.hold_released_by<=>h.released_by) OR NOT(d.hold_release_basis<=>h.release_basis) OR NOT(d.hold_release_type<=>h.release_type)')->fetchColumn(),
        'compatibility_mismatches'=>(int)$p->query("SELECT COUNT(*) FROM compatibility_matrix m LEFT JOIN (SELECT recipient_type,GROUP_CONCAT(donor_type ORDER BY preference_order SEPARATOR ',') AS donor_list FROM compatibility_pairs GROUP BY recipient_type) n ON n.recipient_type=m.recipient_type WHERE REPLACE(m.allowed_donor_types,' ','')<>n.donor_list OR n.recipient_type IS NULL")->fetchColumn(),
    ];
}

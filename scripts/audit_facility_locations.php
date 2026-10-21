<?php
declare(strict_types=1);
// Read-only application audit: explicit configured port, no fallback, no PII output.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
Env::load(dirname(__DIR__).'/.env');
$name=Env::get('DB_NAME',''); $host=Env::get('DB_HOST','127.0.0.1'); $port=Env::get('DB_PORT','3306');
if ($name!=='bloodmatch' || !in_array($host,['127.0.0.1','localhost'],true)) { throw new RuntimeException('Expected configured local application database.'); }
$socket=@stream_socket_client("tcp://{$host}:{$port}",$errno,$error,3);
if (!$socket) { throw new RuntimeException('Configured application database unavailable.'); }
stream_set_timeout($socket,3);$greeting=fread($socket,4);fclose($socket);
if (strlen((string)$greeting)!==4) { throw new RuntimeException('Application database greeting unavailable.'); }
$p=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",Env::get('DB_USER',''),Env::get('DB_PASS',''),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
$target=$p->query('SELECT DATABASE() AS db,@@port AS port')->fetch();
if ($target['db']!=='bloodmatch' || (int)$target['port']!==(int)$port) { throw new RuntimeException('Target mismatch.'); }
$p->exec('SET TRANSACTION READ ONLY'); $p->beginTransaction();
try {
    $directory=$p->query("SELECT COUNT(*) AS hospitals,SUM(l.id IS NULL OR l.is_active<>1 OR l.latitude IS NULL OR l.longitude IS NULL) AS missing_reference,
        SUM(l.level='municipality') AS municipality_only,SUM(l.level='barangay') AS barangay_reference
        FROM hospitals h LEFT JOIN bataan_locations l ON l.id=h.location_id")->fetch();
    $rows=$p->query("SELECT br.id AS request_id,br.hospital_id,br.location_id AS request_location_id,h.location_id AS hospital_location_id,
        CASE WHEN h.id IS NULL THEN 'hospital_link_missing'
             WHEN hl.id IS NULL OR hl.is_active<>1 THEN 'hospital_reference_missing_or_inactive'
             WHEN rl.id IS NULL THEN 'request_reference_missing'
             WHEN rl.municipality_code<>hl.municipality_code THEN 'municipality_mismatch'
             WHEN hl.level='barangay' AND br.location_id<>h.location_id THEN 'barangay_mismatch'
             WHEN NOT(br.latitude<=>rl.latitude) OR NOT(br.longitude<=>rl.longitude) THEN 'copied_coordinates_differ'
             WHEN hl.level='municipality' AND rl.level<>'barangay' THEN 'manual_barangay_missing'
             ELSE 'directory_municipality_name_mismatch' END AS review_reason
        FROM blood_requests br LEFT JOIN hospitals h ON h.id=br.hospital_id
        LEFT JOIN bataan_locations hl ON hl.id=h.location_id LEFT JOIN bataan_locations rl ON rl.id=br.location_id
        WHERE br.status='OPEN' AND br.needed_datetime>UTC_TIMESTAMP() AND br.hospital_id IS NOT NULL
        AND (h.id IS NULL OR hl.id IS NULL OR hl.is_active<>1 OR rl.id IS NULL OR rl.municipality_code<>hl.municipality_code
            OR (hl.level='barangay' AND br.location_id<>h.location_id)
            OR NOT(br.latitude<=>rl.latitude) OR NOT(br.longitude<=>rl.longitude)
            OR (hl.level='municipality' AND rl.level<>'barangay') OR TRIM(h.municipality_name)<>hl.municipality_name)
        ORDER BY br.id")->fetchAll();
    $meetingColumns=$p->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
        AND (COLUMN_NAME LIKE '%meeting%' OR COLUMN_NAME='destination_type') ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll();
    echo json_encode(['target'=>$target,'read_only'=>true,'meeting_place_columns'=>$meetingColumns,'directory'=>$directory,'active_records_for_targeted_review'=>$rows],JSON_PRETTY_PRINT).PHP_EOL;
} finally { $p->rollBack(); }

<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require BASE_PATH.'/database/operational_checkpoint_support.php';
use BloodMatch\Services\DocumentStorageService;
use BloodMatch\Services\DocumentCipher;
use BloodMatch\Repositories\DocumentRepository;
$pdo=null;$path=null;$directory=null;$keyFile=null;$upload=null;$saved=$_ENV;$passed=0;$exit=0;
function documentCheck(bool $valid,string $label): void { global $passed;if (!$valid) { throw new LogicException('FAIL '.$label); }$passed++;echo 'PASS '.$label.PHP_EOL; }
try {
    if (count($argv)!==1) { throw new LogicException('No arguments allowed.'); }
    $pdo=operationalTarget('test');$chapter=(int)$pdo->query('SELECT id FROM chapters ORDER BY id LIMIT 1')->fetchColumn();
    if ($chapter<1) { throw new LogicException('Existing reference chapter required.'); }
    $directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'bm-document-fixture-'.bin2hex(random_bytes(8));mkdir($directory,0700);
    $id=bin2hex(random_bytes(16));$keyFile=$directory.DIRECTORY_SEPARATOR.$id.'.key';file_put_contents($keyFile,random_bytes(32));
    $_ENV['DOCUMENT_KEY_DIRECTORY']=$directory;$_ENV['DOCUMENT_ACTIVE_KEY_ID']=$id;$_ENV['DOCUMENT_ENCRYPTION_MODE']='dual';$_ENV['MAIL_HOST']='';
    $upload=$directory.DIRECTORY_SEPARATOR.'synthetic.pdf';$plain="%PDF-1.4\nSynthetic fixture only\n%%EOF\n";file_put_contents($upload,$plain);
    $pdo->beginTransaction();$email='crypto-'.bin2hex(random_bytes(8)).'@example.test';
    $s=$pdo->prepare("INSERT INTO users(email,first_name,last_name,role,chapter_id) VALUES (?,'Crypto','Fixture','member',?)");$s->execute([$email,$chapter]);$user=(int)$pdo->lastInsertId();
    $stored=DocumentStorageService::store($upload);$path=DocumentStorageService::pathFor($stored['stored_name']);
    documentCheck(str_starts_with(file_get_contents($path),DocumentCipher::MAGIC),'new staged document is encrypted at rest');
    documentCheck(!str_contains(file_get_contents($path),$plain),'original bytes absent from stored envelope');
    $docId=(new DocumentRepository())->create($user,'national_id',$stored,gmdate('Y-m-d H:i:s'));
    $s=$pdo->prepare('SELECT * FROM member_documents WHERE id=?');$s->execute([$docId]);$doc=$s->fetch(PDO::FETCH_ASSOC);
    documentCheck($doc['mime_type']==='application/pdf' && (int)$doc['size_bytes']===strlen($plain),'database retains original MIME and plaintext size');
    documentCheck(DocumentStorageService::read($doc)===$plain,'authenticated storage read returns original bytes');
    $_ENV['DOCUMENT_ENCRYPTION_MODE']='strict';documentCheck(DocumentStorageService::read($doc)===$plain,'strict mode reads encrypted document');
    $tampered=$doc;$tampered['size_bytes']++;
    try { DocumentStorageService::read($tampered);throw new LogicException('Tampered metadata accepted.'); }
    catch (RuntimeException $e) { documentCheck(true,'metadata tampering refused'); }
    $_ENV['DOCUMENT_KEY_DIRECTORY']=$directory.'/missing';
    try { DocumentStorageService::read($doc);throw new LogicException('Missing key accepted.'); }
    catch (RuntimeException $e) { documentCheck(true,'missing key fails closed'); }
    $pdo->rollBack();$s=$pdo->prepare('SELECT COUNT(*) FROM users WHERE id=?');$s->execute([$user]);documentCheck((int)$s->fetchColumn()===0,'disposable database fixture rolled back');
} catch (Throwable $e) { $exit=1;fwrite(STDERR,$e instanceof LogicException ? $e->getMessage().PHP_EOL : 'Synthetic document checkpoint failed; raw errors withheld.'.PHP_EOL); }
finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    $_ENV=$saved;
    $cleanupFailed=[];
    foreach ([$path,$keyFile,$upload] as $file) { if ($file!==null && is_file($file) && !is_link($file)) { if (!unlink($file)) { $exit=1;$cleanupFailed[]=$file; } } }
    if ($directory!==null && is_dir($directory) && !rmdir($directory)) { $exit=1; }
    echo json_encode(['database'=>'bloodmatch_test','port'=>3306,'checks'=>$passed,'exit_code'=>$exit,'remaining_owned_files'=>$cleanupFailed,'smtp'=>false,'historical_conversion'=>false],JSON_UNESCAPED_SLASHES).PHP_EOL;
}
exit($exit);

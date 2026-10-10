<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Services\DocumentCipher;
use BloodMatch\Services\DocumentStorageService;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Config\Env;
$options=getopt('',['id:','convert','recover','sha256:','backup-directory:','maintenance-confirmed']);$pdo=null;$staging=null;$lock=null;
try {
    if (isset($options['convert']) && isset($options['recover'])) { throw new LogicException('Choose conversion or recovery, not both.'); }
    if (!ctype_digit((string)($options['id']??'')) || (int)$options['id']<1) { throw new LogicException('Review one explicit document ID with --id=N.'); }
    $pdo=operationalTarget('application');$id=(int)$options['id'];
    $s=$pdo->prepare('SELECT id,stored_name,mime_type,size_bytes FROM member_documents WHERE id=?');$s->execute([$id]);$doc=$s->fetch(PDO::FETCH_ASSOC);
    if (!$doc) { throw new LogicException('Document not found.'); }
    $s=$pdo->prepare('SELECT COUNT(*) FROM member_documents WHERE stored_name=?');$s->execute([$doc['stored_name']]);
    if ((int)$s->fetchColumn()!==1) { throw new LogicException('Shared file reference requires separate review.'); }
    $path=DocumentStorageService::pathFor($doc['stored_name']);
    if (is_link($path) || !is_file($path) || filesize($path)>DocumentStorageService::maxBytes()+68) { throw new LogicException('Document file unavailable.'); }
    $bytes=file_get_contents($path);$encrypted=str_starts_with($bytes,DocumentCipher::MAGIC);$hash=hash('sha256',$bytes);
    if (isset($options['recover'])) {
        if (!$encrypted || !isset($options['maintenance-confirmed'])) { throw new LogicException('Recovery requires an encrypted file and confirmed maintenance.'); }
        $backup=realpath((string)($options['backup-directory']??''));if (!$backup) { throw new LogicException('External recovery directory required.'); }
        $saved=Env::get('DOCUMENT_KEY_DIRECTORY','');$_ENV['DOCUMENT_KEY_DIRECTORY']=$backup;DocumentCipher::keyDirectory();$_ENV['DOCUMENT_KEY_DIRECTORY']=$saved;
        $keyId=substr($bytes,8,32);if (!preg_match('/^[a-f0-9]{32}$/D',$keyId)) { throw new LogicException('Invalid encrypted key identifier.'); }
        $journalPath=$backup.DIRECTORY_SEPARATOR.$doc['stored_name'].'.'.$keyId.'.encrypted-backup.json';
        if (is_link($journalPath) || !is_file($journalPath)) { throw new LogicException('Owned recovery journal unavailable.'); }
        $journal=json_decode(file_get_contents($journalPath),true,512,JSON_THROW_ON_ERROR);
        $plain=DocumentStorageService::read($doc);
        if (($journal['database']??null)!=='bloodmatch' || ($journal['port']??null)!==3306 || ($journal['document_id']??null)!==$id
            || ($journal['stored_name']??null)!==$doc['stored_name'] || ($journal['key_id']??null)!==$keyId
            || !hash_equals((string)($journal['encrypted_sha256']??''),$hash) || !hash_equals((string)($journal['original_sha256']??''),hash('sha256',$plain))) { throw new LogicException('Recovery identity/hash mismatch.'); }
        $lock=fopen(DocumentStorageService::baseDir().'/conversion.lock','c');if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new LogicException('Another conversion is active.'); }
        $pdo->beginTransaction();$s=$pdo->prepare('SELECT stored_name FROM member_documents WHERE id=? FOR UPDATE');$s->execute([$id]);
        if ($s->fetchColumn()!==$doc['stored_name'] || !hash_equals($hash,hash_file('sha256',$path))) { throw new LogicException('Document changed during recovery.'); }
        $s=$pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE action='document.encrypted' AND target_type='member_document' AND target_id=? AND context LIKE ?");$s->execute([(string)$id,'%"key_id":"'.$keyId.'"%']);
        if ((int)$s->fetchColumn()===0 && AuditLogger::log(null,'document.encrypted','member_document',(string)$id,['key_id'=>$keyId,'recovered'=>true])===null) { throw new LogicException('Recovery audit unavailable; retain journal.'); }
        $pdo->commit();$journal['phase']='complete';
        if (file_put_contents($journalPath,json_encode($journal,JSON_THROW_ON_ERROR),LOCK_EX)===false) { throw new LogicException('Recovery journal update failed.'); }
    }
    if (isset($options['convert'])) {
        if (!isset($options['maintenance-confirmed']) || Env::get('DOCUMENT_ENCRYPTION_MODE','legacy')!=='dual') { throw new LogicException('Stop document writers and explicitly confirm maintenance; dual mode required.'); }
        if ($encrypted) { throw new LogicException('Already encrypted; verify authenticated retrieval instead of reconverting.'); }
        if (!hash_equals($hash,(string)($options['sha256']??''))) { throw new LogicException('Reviewed file SHA-256 required.'); }
        $backup=realpath((string)($options['backup-directory']??''));
        if (!$backup) { throw new LogicException('Existing external encrypted-backup directory required.'); }
        $saved=Env::get('DOCUMENT_KEY_DIRECTORY','');$_ENV['DOCUMENT_KEY_DIRECTORY']=$backup;DocumentCipher::keyDirectory();$_ENV['DOCUMENT_KEY_DIRECTORY']=$saved;
        $lock=fopen(DocumentStorageService::baseDir().'/conversion.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new LogicException('Another conversion is active.'); }
        $pdo->beginTransaction();$s=$pdo->prepare('SELECT id,stored_name,mime_type,size_bytes FROM member_documents WHERE id=? FOR UPDATE');$s->execute([$id]);
        if ($s->fetch(PDO::FETCH_ASSOC)!==$doc || !hash_equals($hash,hash_file('sha256',$path)) || is_link($path)) { throw new LogicException('Document changed since review.'); }
        if (strlen($bytes)!==(int)$doc['size_bytes'] || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!==$doc['mime_type']) { throw new LogicException('Original document metadata mismatch.'); }
        $keyId=DocumentCipher::activeId();$key=DocumentCipher::key($keyId);$context=DocumentCipher::context($doc['stored_name'],$doc['mime_type'],(int)$doc['size_bytes']);
        $sealed=DocumentCipher::seal($bytes,$context,$key,$keyId);
        if (!hash_equals($bytes,DocumentCipher::open($sealed,$context,$key))) { throw new LogicException('Encryption roundtrip failed.'); }
        $backupPath=$backup.DIRECTORY_SEPARATOR.$doc['stored_name'].'.'.$keyId.'.encrypted-backup';
        if (file_exists($backupPath)) {
            if (is_link($backupPath) || !hash_equals($bytes,DocumentCipher::open(file_get_contents($backupPath),$context,$key))) { throw new LogicException('Existing encrypted backup mismatch.'); }
        } else {
            $file=fopen($backupPath,'xb');if (!$file) { throw new LogicException('Encrypted backup unavailable.'); }
            $written=fwrite($file,$sealed);fflush($file);fclose($file);chmod($backupPath,0600);
            if ($written!==strlen($sealed)) { throw new LogicException('Incomplete backup; preserve and inspect it.'); }
        }
        $journalPath=$backupPath.'.json';$journal=['database'=>'bloodmatch','port'=>3306,'document_id'=>$id,'stored_name'=>$doc['stored_name'],'original_sha256'=>$hash,'encrypted_sha256'=>hash('sha256',$sealed),'key_id'=>$keyId,'phase'=>'backup_verified'];
        if (is_link($journalPath)) { throw new LogicException('Recovery journal cannot be a link.'); }
        if (file_put_contents($journalPath,json_encode($journal,JSON_THROW_ON_ERROR),LOCK_EX)===false) { throw new LogicException('Recovery journal unavailable.'); }
        $staging=$path.'.encrypted-'.bin2hex(random_bytes(8));$file=fopen($staging,'xb');
        if (!$file) { throw new LogicException('Encrypted staging unavailable.'); }
        $written=fwrite($file,$sealed);fflush($file);fclose($file);chmod($staging,0640);
        if ($written!==strlen($sealed) || !hash_equals($bytes,DocumentCipher::open(file_get_contents($staging),$context,$key))) { throw new LogicException('Staging verification failed.'); }
        if (!rename($staging,$path)) { throw new LogicException('Atomic file replacement failed; original preserved.'); }$staging=null;
        $journal['phase']='file_replaced';if (file_put_contents($journalPath,json_encode($journal),LOCK_EX)===false) { throw new LogicException('Converted file retained; recovery journal update failed.'); }
        if (AuditLogger::log(null,'document.encrypted','member_document',(string)$id,['key_id'=>$keyId])===null) { throw new LogicException('File converted but audit failed; retain journal and review before continuing.'); }
        $pdo->commit();$journal['phase']='complete';if (file_put_contents($journalPath,json_encode($journal),LOCK_EX)===false) { throw new LogicException('Conversion committed; retain journal for verified recovery.'); }$encrypted=true;
    }
    if ($encrypted) { DocumentStorageService::read($doc); }
    echo json_encode(['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'document_id'=>$id,'encrypted'=>$encrypted,'file_sha256'=>hash_file('sha256',$path),'writes'=>isset($options['convert'])||isset($options['recover']),'metadata_changed'=>false],JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) { if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }fwrite(STDERR,$e instanceof LogicException ? $e->getMessage().PHP_EOL : 'Document conversion/review failed; retain encrypted backup and journal.'.PHP_EOL);exit(1); }
finally {
    if ($staging!==null && is_file($staging) && !is_link($staging)) { unlink($staging); }
    if (is_resource($lock)) { flock($lock,LOCK_UN);fclose($lock); }
}

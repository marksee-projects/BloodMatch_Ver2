<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\DocumentCipher;
$options=getopt('',['directory:','review-directory','create','verify-backup:']);
try {
    Env::load(BASE_PATH.'/.env');
    if (isset($options['directory'])) { $_ENV['DOCUMENT_KEY_DIRECTORY']=$options['directory']; }
    $directory=DocumentCipher::keyDirectory();$id=DocumentCipher::activeId();
    if (isset($options['review-directory'])) {
        if (isset($options['create'])) { throw new LogicException('Choose directory review or key creation.'); }
        echo json_encode(['directory'=>$directory,'outside_repository'=>true,'writes'=>false],JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);
    }
    if (isset($options['create'])) {
        $id=bin2hex(random_bytes(16));$path=$directory.DIRECTORY_SEPARATOR.$id.'.key';$file=fopen($path,'xb');
        if (!$file) { throw new LogicException('Cannot create a new external key.'); }
        $key=random_bytes(32);$written=fwrite($file,$key);fflush($file);fclose($file);chmod($path,0600);
        if ($written!==32) { unlink($path);throw new LogicException('Key write failed.'); }
    }
    $result=['writes'=>isset($options['create']),'key_directory'=>$directory,'active_key_id'=>$id,'key_ready'=>strlen(DocumentCipher::key($id))===32,'aes_256_gcm'=>extension_loaded('openssl') && in_array('aes-256-gcm',openssl_get_cipher_methods(),true)];
    if (isset($options['verify-backup'])) {
        $active=DocumentCipher::key($id);$_ENV['DOCUMENT_KEY_DIRECTORY']=$options['verify-backup'];
        $result['backup_matches']=hash_equals($active,DocumentCipher::key($id));
        if (!$result['backup_matches']) { throw new LogicException('Backup key mismatch.'); }
    }
    echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,'Key readiness/creation failed; no key contents emitted. Check directory, active ID, ACLs and backup.'.PHP_EOL);exit(1); }

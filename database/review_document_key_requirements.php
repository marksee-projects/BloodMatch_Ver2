<?php
declare(strict_types=1);
// Header-only local storage review. No database, document plaintext or key output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\{DocumentCipher,DocumentStorageService};
try {
    Env::load(BASE_PATH.'/.env');
    $counts=['legacy'=>0,'encrypted'=>0]; $required=[];
    $dir=DocumentStorageService::baseDir();
    if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Document storage cannot be reviewed safely.');
    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isDot()) continue;
        if ($entry->isLink() || !$entry->isFile()) throw new RuntimeException('Unexpected storage entry; review before preparing keys.');
        $f=fopen($entry->getPathname(),'rb');
        if ($f===false) throw new RuntimeException('Unreadable document; stop before preparing keys.');
        try { $header=fread($f,40); } finally { fclose($f); }
        if ($header===false) throw new RuntimeException('Unreadable document header.');
        if (str_starts_with($header,DocumentCipher::MAGIC)) {
            $id=substr($header,8,32);
            if ($entry->getSize()<68 || !preg_match('/^[a-f0-9]{32}$/D',$id)) throw new RuntimeException('Invalid encrypted envelope; retain files and stop.');
            $counts['encrypted']++; $required[$id]=true;
        } elseif (str_starts_with($header,'BMDOC')) {
            throw new RuntimeException('Unknown document envelope; retain files and stop.');
        } else { $counts['legacy']++; }
    }
    $missing=[];
    foreach (array_keys($required) as $id) {
        try { DocumentCipher::key($id); } catch (Throwable) { $missing[]=$id; }
    }
    $active=DocumentCipher::activeId(); $activeReady=false;
    if ($active!=='') { try { DocumentCipher::key($active); $activeReady=true; } catch(Throwable) {} }
    $blocked=$missing!==[] || ($active!=='' && !$activeReady);
    echo json_encode(['writes'=>false,'database'=>false,'mode'=>DocumentCipher::mode(),
        'stored_files'=>$counts,'required_key_ids'=>array_keys($required),'missing_key_ids'=>$missing,
        'key_directory_configured'=>Env::get('DOCUMENT_KEY_DIRECTORY','')!=='',
        'active_key_id_configured'=>$active!=='','active_key_ready'=>$activeReady,
        'gd'=>extension_loaded('gd'),'status'=>$blocked?'stop_missing_existing_key':'reviewed'],JSON_THROW_ON_ERROR).PHP_EOL;
    exit($blocked?1:0);
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }

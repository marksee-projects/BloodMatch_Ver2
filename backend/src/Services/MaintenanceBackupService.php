<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Env;
use RuntimeException;
final class MaintenanceBackupService
{
    public static function create(string $db,int $port): string
    {
        if (PHP_SAPI!=='cli' || !in_array($db,['bloodmatch','bloodmatch_test'],true)) throw new RuntimeException('Unsupported backup target.');
        $dir=dirname(__DIR__,2).'/storage/maintenance';
        if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create private backup folder.');
        $escape=fn($s)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$s).'"';
        $nonce=bin2hex(random_bytes(8));$config=$dir.'/connection-'.$nonce.'.cnf';
        $path=$dir.'/'.$db.'-'.gmdate('Ymd-His').'-'.$nonce.'.sql';
        $content="[client]\nhost=".$escape(Env::get('DB_HOST','127.0.0.1'))."\nport=$port\nuser=".$escape(Env::get('DB_USER','root'))."\npassword=".$escape(Env::get('DB_PASS',''))."\n";
        if (file_put_contents($config,$content,LOCK_EX)===false) throw new RuntimeException('Cannot prepare backup connection.');
        chmod($config,0600);
        try {
            $proc=proc_open(['C:/xampp/mysql/bin/mysqldump.exe','--defaults-extra-file='.$config,'--single-transaction','--routines','--events','--triggers','--hex-blob','--result-file='.$path,$db],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($proc)) throw new RuntimeException('Cannot start backup.');
            fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
            if (proc_close($proc)!==0 || !is_file($path) || filesize($path)===0) throw new RuntimeException('Backup failed. No changes applied.');
        } finally {unlink($config);}
        chmod($path,0600);return $path;
    }
}

<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Env;
use RuntimeException;
/** OpenSSL AES-256-GCM envelope. Keys never enter a DB row or document header. */
final class DocumentCipher
{
    public const MAGIC="BMDOC1\0\0";
    public static function mode(): string
    {
        $mode=(string)Env::get('DOCUMENT_ENCRYPTION_MODE','legacy');
        if (!in_array($mode,['legacy','dual','strict'],true)) { throw new RuntimeException('Invalid document encryption mode.'); }
        return $mode;
    }
    public static function keyDirectory(): string
    {
        $directory=realpath((string)Env::get('DOCUMENT_KEY_DIRECTORY',''));
        $root=realpath(\BloodMatch\Config\AppConfig::basePath());
        if ($directory===false || $root===false || !is_dir($directory)) { throw new RuntimeException('Document key directory unavailable.'); }
        $normalized=strtolower(str_replace('\\','/',$directory));$workspace=strtolower(str_replace('\\','/',$root));
        if ($normalized===$workspace || str_starts_with($normalized,$workspace.'/')) { throw new RuntimeException('Document keys must be outside the repository/public directory.'); }
        if (preg_match('~/(public|htdocs|www|wwwroot)(/|$)~',$normalized)) { throw new RuntimeException('Document keys must be outside web-root directories.'); }
        return $directory;
    }
    public static function key(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { throw new RuntimeException('Invalid document key identifier.'); }
        $directory=self::keyDirectory();$file=$directory.DIRECTORY_SEPARATOR.$id.'.key';
        if (is_link($file) || !is_file($file) || !is_readable($file)) { throw new RuntimeException('Document key unavailable.'); }
        $key=file_get_contents($file);
        if (!is_string($key) || strlen($key)!==32) { throw new RuntimeException('Invalid document key.'); }
        return $key;
    }
    public static function activeId(): string { return (string)Env::get('DOCUMENT_ACTIVE_KEY_ID',''); }
    public static function context(string $name,string $mime,int $size): string { return $name.'|'.$mime.'|'.$size; }
    public static function seal(string $plain,string $context,string $key,string $keyId): string
    {
        if (!extension_loaded('openssl') || !in_array('aes-256-gcm',openssl_get_cipher_methods(),true)) { throw new RuntimeException('AES-256-GCM unavailable.'); }
        if (strlen($key)!==32 || !preg_match('/^[a-f0-9]{32}$/D',$keyId)) { throw new RuntimeException('Invalid encryption key.'); }
        $nonce=random_bytes(12);$header=self::MAGIC.$keyId;
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$header.$context,16);
        if ($cipher===false || strlen($tag)!==16) { throw new RuntimeException('Document encryption failed.'); }
        return $header.$nonce.$tag.$cipher;
    }
    public static function open(string $envelope,string $context,string $key): string
    {
        if (strlen($key)!==32 || strlen($envelope)<68 || !str_starts_with($envelope,self::MAGIC)) { throw new RuntimeException('Invalid encrypted document.'); }
        $plain=openssl_decrypt(substr($envelope,68),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($envelope,40,12),substr($envelope,52,16),substr($envelope,0,40).$context);
        if ($plain===false) { throw new RuntimeException('Document authentication failed.'); }
        return $plain;
    }
    public static function decode(string $bytes,string $context): string
    {
        if (str_starts_with($bytes,self::MAGIC)) { return self::open($bytes,$context,self::key(substr($bytes,8,32))); }
        if (self::mode()==='strict') { throw new RuntimeException('Legacy document requires approved conversion.'); }
        return $bytes;
    }
}

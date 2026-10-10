<?php
declare(strict_types=1);
namespace PHPMailer\PHPMailer;

// Database checkpoint tests ONLY. There is no SMTP/socket implementation.
final class PHPMailer
{
    public static bool $fail = false;
    public static int $calls = 0;
    public function __construct(bool $exceptions = true) {}
    public function __set(string $name, mixed $value): void {}
    public function isSMTP(): void {}
    public function setFrom(string $address, string $name): void {}
    public function addAddress(string $address): void {}
    public function isHTML(bool $enabled): void {}
    public function send(): bool {
        self::$calls++;
        if (self::$fail) { throw new \RuntimeException('Synthetic transport failure'); }
        return true;
    }
}

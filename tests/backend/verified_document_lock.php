<?php
// Offline controller check: mocked auth/response; no database, storage, or network.
declare(strict_types=1);

namespace BloodMatch\Middleware {
    final class AuthMiddleware {
        public static string $status = 'verified';
        public static function requireActiveUser(string $endpoint): array {
            return ['id' => 1, 'verification_status' => self::$status];
        }
    }
}
namespace BloodMatch\Repositories {
    final class AuthThrottleRepository {
        public function hitAndCheckRateLimit(...$args): bool { return true; }
    }
}
namespace BloodMatch\Services {
    final class AuthService {
        public static function nowUtc(): string { return '2026-10-08 00:00:00'; }
        public static function isPrivacyAcknowledged($value): bool { return $value === '1'; }
    }
}
namespace BloodMatch\Utils {
    final class Response {
        public static array $last = [];
        public static function error(string $message, int $status, array $details = []): void {
            self::$last = ['status' => $status, 'message' => $message];
        }
    }
}
namespace {
    require __DIR__ . '/../../backend/src/Controllers/DocumentController.php';
    $cases = [
        ['verified', 'national_id', 403, 'National ID uploads are locked after verification.'],
        ['pending', 'national_id', 400, 'No file was uploaded.'],
        ['rejected', 'national_id', 400, 'No file was uploaded.'],
        ['verified', 'donor_card', 400, 'No file was uploaded.'],
        ['verified', 'parental_consent', 400, 'No file was uploaded.'],
    ];
    foreach ($cases as [$verification, $type, $status, $message]) {
        \BloodMatch\Middleware\AuthMiddleware::$status = $verification;
        \BloodMatch\Utils\Response::$last = [];
        $_POST = ['doc_type' => $type, 'privacy_acknowledged' => '1'];
        $_FILES = [];
        (new \BloodMatch\Controllers\DocumentController())->upload();
        if (\BloodMatch\Utils\Response::$last !== ['status' => $status, 'message' => $message]) {
            fwrite(STDERR, "FAIL: $verification / $type\n");
            exit(1);
        }
    }
    echo "PASS: verified National ID upload denied before file processing; pending/rejected and supplementary-document validation preserved.\n";
}

<?php
declare(strict_types=1);
namespace BloodMatch\Services;

final class DemoCleanupPolicy
{
    public static function assertLegacySchema(array $tables): void
    {
        foreach (['pending_registrations','chat_requests','chat_reports','chat_report_evidence','account_closures'] as $table) {
            if (in_array($table, $tables, true)) {
                throw new \LogicException('Demo cleanup is blocked after privacy migration 025. Preserve signup, consent, moderation and closure data; a new cleanup impact review is required.');
            }
        }
    }
}

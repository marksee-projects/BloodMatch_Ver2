<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use BloodMatch\Services\Exceptions\WindowBlockedException;

final class DonorEligibilityService
{
    public static function nowUtc(): string
    {
        return AuthService::nowUtc();
    }

    public static function cutoffsUtc(?int $standbyHours, ?int $cooldownDays, string $nowUtc): array
    {
        $ts = strtotime($nowUtc . ' UTC');
        return [
            'standby' => gmdate('Y-m-d H:i:s', $ts - ($standbyHours ?? 0) * 3600),
            'cooldown' => gmdate('Y-m-d H:i:s', $ts - ($cooldownDays ?? 0) * 86400),
        ];
    }

    public static function evaluateWindows(?string $lastVerifiedDonationAt, string $nowUtc = null): array
    {
        $nowUtc = $nowUtc ?? self::nowUtc();

        if ($lastVerifiedDonationAt === null || $lastVerifiedDonationAt === '') {
            return [
                'blocked' => false,
                'which' => null,
                'ends_at_utc' => null,
                'eligible_again_at_utc' => null,
                'remaining_seconds' => 0,
            ];
        }

        $settings = SystemSettingsService::get();
        $anchorTs = strtotime($lastVerifiedDonationAt . ' UTC');
        if ($anchorTs === false) {
            throw new RuntimeException('Invalid last_verified_donation_at value.');
        }
        $nowTs = strtotime($nowUtc . ' UTC');

        $standbyEndTs = $anchorTs + $settings['standby_hours'] * 3600;
        $cooldownEndTs = $anchorTs + $settings['cooldown_days'] * 86400;
        $eligibleAgainTs = max($standbyEndTs, $cooldownEndTs);

        if ($nowTs < $standbyEndTs) {
            return [
                'blocked' => true,
                'which' => 'standby',
                'ends_at_utc' => gmdate('Y-m-d H:i:s', $standbyEndTs),
                'eligible_again_at_utc' => gmdate('Y-m-d H:i:s', $eligibleAgainTs),
                'remaining_seconds' => max(0, $standbyEndTs - $nowTs),
            ];
        }

        if ($nowTs < $cooldownEndTs) {
            return [
                'blocked' => true,
                'which' => 'cooldown',
                'ends_at_utc' => gmdate('Y-m-d H:i:s', $cooldownEndTs),
                'eligible_again_at_utc' => gmdate('Y-m-d H:i:s', $eligibleAgainTs),
                'remaining_seconds' => max(0, $cooldownEndTs - $nowTs),
            ];
        }

        return [
            'blocked' => false,
            'which' => null,
            'ends_at_utc' => null,
            'eligible_again_at_utc' => null,
            'remaining_seconds' => 0,
        ];
    }

    /**
     * Authoritative live eligibility decision for one donor and one request.
     * A precomputed window lets feed callers evaluate donor timing once.
     *
     * @return array{eligible: bool, code: ?string, reason: ?string, eligible_again_at: ?string}
     */
    public static function evaluateForRequest(
        array $donor,
        array $request,
        ?string $nowUtc = null,
        ?array $window = null
    ): array {
        $nowUtc = $nowUtc ?? self::nowUtc();

        if ((string) ($donor['role'] ?? '') !== 'member') {
            return self::blocked('staff_account', "Staff accounts can't donate");
        }
        if ((string) ($donor['account_status'] ?? '') !== 'active') {
            return self::blocked('inactive_account', 'This account is not active.');
        }
        if ((string) ($donor['verification_status'] ?? '') !== 'verified') {
            return self::blocked('membership_unverified', 'Complete account verification before donating.');
        }
        if (empty($donor['email_verified_at'])) {
            return self::blocked('email_unverified', 'Verify your email before donating.');
        }
        if (empty($donor['donor_enrolled_at'])) {
            return self::blocked('not_enrolled', 'Enroll as a donor before responding.');
        }

        // Enrollment is historical participation, not proof of current age/consent.
        // has_parental_consent is projected from member_documents by the repository.
        $age = AgeEligibilityService::evaluate(
            isset($donor['date_of_birth']) ? (string) $donor['date_of_birth'] : null,
            !empty($donor['has_parental_consent'])
        );
        if (!$age['donor_path_allowed']) {
            return self::blocked(
                $age['requires_parental_consent'] ? 'parental_consent_required' : 'age_ineligible',
                $age['reason']
            );
        }

        $lastDonation = isset($donor['last_verified_donation_at'])
            ? (string) $donor['last_verified_donation_at']
            : null;
        $window = $window ?? self::evaluateWindows($lastDonation, $nowUtc);
        if (!empty($window['blocked'])) {
            $which = (string) ($window['which'] ?? 'cooldown');
            return self::blocked(
                $which,
                $which === 'standby'
                    ? 'The post-donation standby period is still active.'
                    : 'The post-donation cooldown period is still active.',
                isset($window['eligible_again_at_utc'])
                    ? (string) $window['eligible_again_at_utc']
                    : (isset($window['ends_at_utc']) ? (string) $window['ends_at_utc'] : null)
            );
        }

        $availability = (string) ($donor['donor_availability'] ?? '');
        $expiredStoredStandby = $availability === 'standby'
            && $lastDonation !== null
            && $lastDonation !== '';
        if ($availability !== 'available' && !$expiredStoredStandby) {
            return self::blocked(
                $availability === 'standby' ? 'standby' : 'unavailable',
                $availability === 'standby'
                    ? 'This donor account is on standby.'
                    : 'Set donor availability to available before responding.'
            );
        }

        if ((int) ($request['requester_id'] ?? 0) === (int) ($donor['id'] ?? 0)) {
            return self::blocked('own_request', 'You cannot respond to your own request.');
        }
        if ((string) ($request['status'] ?? '') !== 'OPEN') {
            return self::blocked('request_not_open', 'This request is no longer open.');
        }

        $neededAt = strtotime((string) ($request['needed_datetime'] ?? '') . ' UTC');
        $now = strtotime($nowUtc . ' UTC');
        if ($neededAt === false || $now === false || $neededAt < $now) {
            return self::blocked('request_expired', "This request's needed-by time has passed.");
        }

        $donorBloodType = (string) ($donor['blood_type'] ?? '');
        $requiredBloodType = (string) ($request['required_blood_type'] ?? '');
        if ($donorBloodType === '' || $requiredBloodType === '') {
            return self::blocked('blood_type_missing', 'A blood type is required to evaluate compatibility.');
        }
        if (!in_array(
            $donorBloodType,
            BloodCompatibilityService::getCompatibleDonorTypes($requiredBloodType),
            true
        )) {
            return self::blocked('blood_incompatible', 'Your blood type is not compatible with this request.');
        }

        return [
            'eligible' => true,
            'code' => null,
            'reason' => null,
            'eligible_again_at' => null,
        ];
    }

    /**
     * @return array{eligible: false, code: string, reason: string, eligible_again_at: ?string}
     */
    private static function blocked(string $code, string $reason, ?string $eligibleAgainAt = null): array
    {
        return [
            'eligible' => false,
            'code' => $code,
            'reason' => $reason,
            'eligible_again_at' => $eligibleAgainAt,
        ];
    }

    public static function assertAvailabilityChangeAllowed(array $user, ?string $nowUtc = null): void
    {
        if ($user['donor_enrolled_at'] === null) {
            throw new RuntimeException('Enroll as a donor before changing availability.', 409);
        }
        if ((string) $user['account_status'] !== 'active') {
            throw new RuntimeException('Deactivated accounts cannot change availability.', 403);
        }

        $window = self::evaluateWindows(
            $user['last_verified_donation_at'] !== null ? (string) $user['last_verified_donation_at'] : null,
            $nowUtc
        );

        if ($window['blocked']) {
            throw new WindowBlockedException(
                sprintf(
                    'Post-donation %s window is active until %s UTC. This is a BloodMatch administrative interval; actual donation eligibility is determined by the authorized blood-donation facility.',
                    $window['which'],
                    $window['ends_at_utc']
                ),
                $window
            );
        }
    }
}

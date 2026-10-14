<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Repositories\ChatRepository;

final class ChatPolicy
{
    public static function isEligible(array $user): bool
    {
        return (int) ($user['id'] ?? 0) > 0
            && ($user['account_status'] ?? '') === 'active'
            && in_array($user['role'] ?? '', ['member', 'admin'], true)
            && ($user['verification_status'] ?? '') === 'verified';
    }
    private ChatRepository $chatRepo;

    public function __construct(
        ?ChatRepository $chatRepo = null
    ) {
        $this->chatRepo = $chatRepo ?? new ChatRepository();
    }

    public function canMessage(array $sender, array $recipient): bool
    {
        $senderId = (int) ($sender['id'] ?? 0);
        $recipientId = (int) ($recipient['id'] ?? 0);

        // Cannot message oneself
        if ($senderId <= 0 || $recipientId <= 0 || $senderId === $recipientId) {
            return false;
        }

        // Both accounts must be active
        if (($sender['account_status'] ?? '') !== 'active' || ($recipient['account_status'] ?? '') !== 'active') {
            return false;
        }

        $senderRole = (string) ($sender['role'] ?? '');
        $recipientRole = (string) ($recipient['role'] ?? '');

        if (!in_array($senderRole, ['member', 'admin'], true)) {
            return false;
        }

        if (!in_array($recipientRole, ['member', 'admin'], true)) {
            return false;
        }

        // Verification is required for both ends, including existing chats.
        if (!self::isEligible($sender) || !self::isEligible($recipient)) {
            return false;
        }

        return true;
    }

    public function canAccessConversation(array $user, int $conversationId): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if (!self::isEligible($user)) {
            return false;
        }

        if (!$this->chatRepo->isParticipant($conversationId,$userId) || !ChatConsentService::accepted($conversationId,$userId)) return false;
        $other=$this->chatRepo->getOtherParticipant($conversationId,$userId);
        return $other!==null && $this->canMessage($user,$other);
    }
}

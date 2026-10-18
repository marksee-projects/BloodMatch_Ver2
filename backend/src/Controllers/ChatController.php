<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AuthThrottleRepository;
use BloodMatch\Services\AuthService;
use BloodMatch\Services\ChatService;
use BloodMatch\Services\ChatPolicy;
use BloodMatch\Utils\Request;
use BloodMatch\Utils\Response;
use Throwable;

final class ChatController
{
    private const ENDPOINT = 'chat';
    private ChatService $chatService;

    public function __construct(?ChatService $chatService = null)
    {
        $this->chatService = $chatService ?? new ChatService();
    }

    private static function requireChatUser(string $endpoint): array
    {
        $actor = AuthMiddleware::requireActiveUser($endpoint);
        if (!ChatPolicy::isEligible($actor)) {
            Response::error('Complete account verification before using chat.', 403);
            exit;
        }
        return $actor;
    }

    public function heartbeat(): void
    {
        $actor = self::requireChatUser('chat.presence');
        \BloodMatch\Services\PresenceService::heartbeat((int)$actor['id']);
        Response::success(['status'=>'ok']);
    }

    public function unreadCount(): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.unread');
        try { $count = $this->chatService->getUnreadCount((int)$actor['id']);Response::success(['unread_count'=>$count]); } catch (\Throwable $e) { Response::error('Chat is temporarily unavailable.',503); }
    }

    public function conversations(): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.conversations');
        try { $list = $this->chatService->getConversations((int)$actor['id']);Response::success(['conversations'=>$list]); } catch (\Throwable $e) { Response::error('Chat is temporarily unavailable.',503); }
    }

    public function startConversation(): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.start');
        $recipientId = Request::int('recipient_id');

        if ($recipientId === null || $recipientId <= 0) {
            Response::error('Invalid or missing recipient_id.', 422);
            return;
        }

        try {
            $conversation = $this->chatService->getOrCreateDirectConversation($actor, $recipientId);
            Response::success(['conversation' => $conversation]);
        } catch (Throwable $e) {
            Response::error($e instanceof \PDOException ? 'Chat is temporarily unavailable.' : $e->getMessage(), in_array((int)$e->getCode(),[400,403,409,429],true)?(int)$e->getCode():400);
        }
    }

    public function conversation(array $params): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.detail');
        $conversationId = (int) ($params['id'] ?? 0);

        if ($conversationId <= 0) {
            Response::error('Invalid conversation ID.', 404);
            return;
        }

        try {
            $conv = $this->chatService->getConversationDetails($actor, $conversationId);
            Response::success(['conversation' => $conv]);
        } catch (Throwable $e) {
            Response::error($e instanceof \PDOException ? 'Chat requires the reviewed database migration.' : $e->getMessage(), 403);
        }
    }

    public function messages(array $params): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.messages');
        $conversationId = (int) ($params['id'] ?? 0);

        if ($conversationId <= 0) {
            Response::error('Invalid conversation ID.', 404);
            return;
        }

        $afterId = Request::int('after_id');
        $limit = Request::int('limit') ?? 50;

        try {
            $messages = $this->chatService->getMessages($actor, $conversationId, $afterId, $limit, Request::int('before_id'));
            $meta = $this->chatService->getConversationMetadata($actor, $conversationId);
            Response::success([
                'messages' => $messages,
                'is_online' => $meta['is_online'],
                'has_messages' => $meta['has_messages'],
                'match_role' => $meta['match_role'],
                'suggested_chats' => $meta['suggested_chats'],
            ]);
        } catch (Throwable $e) {
            Response::error($e instanceof \PDOException ? 'Chat requires the reviewed database migration.' : $e->getMessage(), 403);
        }
    }

    public function sendMessage(array $params): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.send');
        $expectedSenderId = Request::int('expected_sender_id');
        if ($expectedSenderId !== null && $expectedSenderId !== (int) $actor['id']) {
            Response::error('Your signed-in account changed. Refresh the page before sending.', 409);
            return;
        }
        $conversationId = (int) ($params['id'] ?? 0);

        if ($conversationId <= 0) {
            Response::error('Invalid conversation ID.', 404);
            return;
        }

        // Rate limit: max 15 messages per 30 seconds per user
        $throttleKey = 'chat_send:' . (int) $actor['id'];
        $throttle = new AuthThrottleRepository();
        $nowUtc = AuthService::nowUtc();

        if (!$throttle->hitAndCheckRateLimitSeconds($throttleKey, 15, 30, $nowUtc)) {
            Response::error('Too many messages sent. Please slow down and try again shortly.', 429);
            return;
        }

        $body = Request::str('body');
        if ($body === null || trim($body) === '') {
            Response::error('Message body is required.', 422);
            return;
        }

        try {
            $message = $this->chatService->sendMessage($actor, $conversationId, $body);
            Response::success(['message' => $message]);
        } catch (Throwable $e) {
            Response::error($e instanceof \PDOException ? 'Chat requires the reviewed database migration.' : $e->getMessage(), 400);
        }
    }

    public function markRead(array $params): void
    {
        $actor = self::requireChatUser(self::ENDPOINT . '.read');
        $conversationId = (int) ($params['id'] ?? 0);

        if ($conversationId <= 0) {
            Response::error('Invalid conversation ID.', 404);
            return;
        }

        $lastMessageId = Request::int('last_message_id');
        if ($lastMessageId === null || $lastMessageId < 0) {
            Response::error('Invalid or missing last_message_id.', 422);
            return;
        }

        try {
            $this->chatService->markRead($actor, $conversationId, $lastMessageId);
            Response::success(['status' => 'ok']);
        } catch (Throwable $e) {
            Response::error($e instanceof \PDOException ? 'Chat requires the reviewed database migration.' : $e->getMessage(), 400);
        }
    }
}

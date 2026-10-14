<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Repositories\ChatRepository;
use BloodMatch\Services\ChatPolicy;
use BloodMatch\Services\ChatService;

final class ChatOfflineStatement extends PDOStatement {
    public function __construct(private ChatOfflinePDO $db, private string $query) {}
    public function execute(?array $params = null): bool { $this->db->calls[] = [$this->query, $params]; return true; }
    public function fetchColumn(int $column = 0): mixed {
        if (str_contains($this->query, 'conversation_participants WHERE')) return $this->db->participant;
        return false;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed {
        if (str_contains($this->query, 'cp.last_read_at')) return $this->db->recipient;
        return ['id'=>101, 'conversation_id'=>7, 'sender_id'=>1, 'sender_name'=>'Donor', 'sender_picture'=>null, 'body'=>'Hello', 'created_at'=>'2026-10-13 01:00:00.123'];
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        $rows = $this->db->messages;
        usort($rows, fn($a, $b) => str_contains($this->query, 'ORDER BY m.id DESC') ? $b['id'] <=> $a['id'] : $a['id'] <=> $b['id']);
        return $rows;
    }
}
final class ChatOfflinePDO extends PDO {
    public array $calls = [];
    public bool $participant = true;
    public array $recipient = ['id'=>2, 'full_name'=>'Requester', 'profile_picture'=>null, 'role'=>'member', 'account_status'=>'active', 'verification_status'=>'verified', 'last_read_at'=>null];
    public array $messages = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ChatOfflineStatement($this, $query); }
    public function lastInsertId(?string $name = null): string|false { return '101'; }
}
$pdo = new ChatOfflinePDO();
(new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
$checks = 0;
function chatCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new LogicException('FAIL '.$label); $checks++; echo 'PASS '.$label.PHP_EOL; }
function chatDenied(callable $fn, string $label): void {
    try { $fn(); } catch (RuntimeException) { chatCheck(true, $label); return; }
    throw new LogicException('FAIL '.$label);
}
$member = ['id'=>1, 'role'=>'member', 'account_status'=>'active', 'verification_status'=>'verified'];
$other = array_replace($member, ['id'=>2]);
$policy = new ChatPolicy();
chatCheck($policy->canMessage($member, $other), 'Verified active members can chat');
chatCheck(!$policy->canMessage($member, $member), 'Self-chat is denied');
chatCheck(!$policy->canMessage($member, array_replace($other, ['account_status'=>'deactivated'])), 'Inactive recipients are denied');
chatCheck(!$policy->canMessage(array_replace($member, ['account_status'=>'deactivated']), $other), 'Inactive senders are denied');
chatCheck(!$policy->canMessage(array_replace($member, ['role'=>'officer']), $other), 'Legacy officer role is not elevated');
chatCheck($policy->canMessage(array_replace($member, ['role'=>'admin']), $other), 'Canonical staff can coordinate with members');
chatCheck(!$policy->canMessage(array_replace($member, ['verification_status'=>'pending']), array_replace($other, ['role'=>'admin'])), 'Pending members cannot contact staff');
foreach (['pending', 'unverified', 'rejected'] as $status) {
    $blocked = array_replace($member, ['verification_status'=>$status]);
    chatCheck(!$policy->canMessage($blocked, $other), "$status senders cannot initiate chat");
    chatCheck(!$policy->canMessage($other, $blocked), "$status recipients cannot receive chat");
    chatCheck(!$policy->canAccessConversation($blocked, 7), "$status participants cannot read existing chats");
}
$service = new ChatService();
$pdo->participant = false;
chatDenied(fn()=> $service->getMessages($member, 7), 'Nonparticipants cannot read a conversation');
chatDenied(fn()=> $service->sendMessage($member, 7, 'Hello'), 'Nonparticipants cannot send');
chatDenied(fn()=> $service->markRead($member, 7, 100), 'Nonparticipants cannot mark read');
$pdo->participant = true;
$pdo->recipient['account_status'] = 'deactivated';
chatDenied(fn()=> $service->sendMessage($member, 7, 'Hello'), 'Existing conversations cannot bypass recipient deactivation');
$pdo->recipient['account_status'] = 'active';
chatDenied(fn()=> $service->sendMessage($member, 7, '   '), 'Empty messages are rejected');
chatDenied(fn()=> $service->sendMessage($member, 7, str_repeat('a', 2001)), 'Oversize messages are rejected');
chatDenied(fn()=> $service->sendMessage($member, 7, "\xFF"), 'Invalid UTF-8 is rejected');
$pdo->calls = [];
$service->sendMessage($member, 7, "  Hel\x00lo  ");
$inserts = array_values(array_filter($pdo->calls, fn($call)=>str_contains($call[0], 'INSERT INTO messages')));
chatCheck($inserts[0][1] === [7, 1, 'Hello'], 'Sending removes controls and uses the authenticated sender');
chatCheck(count(array_filter($pdo->calls, fn($call)=>str_contains($call[0], 'SET last_read_at'))) === 0, 'Sending does not mark unseen incoming messages read');
$pdo->messages = [
    ['id'=>99, 'conversation_id'=>7, 'sender_id'=>2, 'sender_name'=>'Requester', 'sender_picture'=>null, 'body'=>'Earlier', 'created_at'=>'2026-10-13 01:00:00.001'],
    ['id'=>100, 'conversation_id'=>7, 'sender_id'=>2, 'sender_name'=>'Requester', 'sender_picture'=>null, 'body'=>'Latest', 'created_at'=>'2026-10-13 01:00:00.002'],
];
$loaded = $service->getMessages($member, 7);
chatCheck(array_column($loaded, 'id') === [99, 100], 'Recent messages are presented chronologically');
$pdo->calls = [];
$service->markRead($member, 7, 99);
chatCheck($pdo->calls[1][1] === [7, 99, 7, 1], 'Read marker is bounded by the fetched message and authenticated participant');
echo "PASS $checks chat checks; connectionless PDO only, no live SQL or network.".PHP_EOL;

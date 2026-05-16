<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = (new Database())->getConnection();

$chat_id = $_GET['chat_id'] ?? null;
if (!$chat_id) {
    echo json_encode(['status' => 'error', 'message' => 'chat_id wajib']);
    exit;
}
$chat_id = (int)$chat_id;

$stmt = $pdo->prepare('
    SELECT id, sender_type, pesan, tipe_pesan, is_wa_sent, created_at
    FROM messages
    WHERE chat_id = :chat_id
    ORDER BY created_at ASC
    LIMIT 2000
');
$stmt->execute(['chat_id' => $chat_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark customer pesan sebagai terbaca
$pdo->prepare('UPDATE messages SET is_wa_sent = 1 WHERE chat_id = :id AND sender_type = "customer" AND is_wa_sent = 0')
    ->execute(['id' => $chat_id]);

$chatStmt = $pdo->prepare("
    SELECT c.*, cs.nomor_wa, cs.nama, cs.last_seen
    FROM chats c
    JOIN customers cs ON cs.id = c.customer_id
    WHERE c.id = :id LIMIT 1
");
$chatStmt->execute(['id' => $chat_id]);
$chatInfo = $chatStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status'   => 'success',
    'messages' => $messages,
    'chat'     => $chatInfo,
]);
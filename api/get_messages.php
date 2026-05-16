<?php
header('Content-Type: application/json');
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Catch any unexpected errors in the main logic
try {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = (new Database())->getConnection();
        if ($pdo === null) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }
    } catch (Exception $e) {
        error_log("Database connection error in get_messages.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    $chat_id = $_GET['chat_id'] ?? null;
    if (!$chat_id) {
        echo json_encode(['status' => 'error', 'message' => 'chat_id wajib']);
        exit;
    }
    $chat_id = (int)$chat_id;

    try {
        $stmt = $pdo->prepare('
            SELECT id, sender_type, pesan, tipe_pesan, is_wa_sent, created_at
            FROM messages
            WHERE chat_id = :chat_id
            ORDER BY created_at ASC
            LIMIT 2000
        ');
        $stmt->execute(['chat_id' => $chat_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database query error in get_messages.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch messages: ' . $e->getMessage()]);
        exit;
    }

    // Mark customer pesan sebagai terbaca
    try {
        $pdo->prepare('UPDATE messages SET is_wa_sent = 1 WHERE chat_id = :id AND sender_type = "customer" AND is_wa_sent = 0')
            ->execute(['id' => $chat_id]);
    } catch (Exception $e) {
        error_log("Failed to mark messages as read in get_messages.php: " . $e->getMessage());
        // Continue anyway, this is not critical
    }

    try {
        $chatStmt = $pdo->prepare("
            SELECT c.*, cs.nomor_wa, cs.nama, cs.last_seen
            FROM chats c
            JOIN customers cs ON cs.id = c.customer_id
            WHERE c.id = :id LIMIT 1
        ");
        $chatStmt->execute(['id' => $chat_id]);
        $chatInfo = $chatStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch chat info in get_messages.php: " . $e->getMessage());
        $chatInfo = null;
    }

    echo json_encode([
        'status'   => 'success',
        'messages' => $messages,
        'chat'     => $chatInfo,
    ]);
} catch (Exception $e) {
    error_log("Unexpected error in get_messages.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan tidak terduga: ' . $e->getMessage()
    ]);
    exit;
}
<?php
header('Content-Type: application/json');
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = (new Database())->getConnection();
    if ($pdo === null) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
} catch (Exception $e) {
    error_log("Database connection error in get_chats.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$agent_id = $_SESSION['agent_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.customer_id, c.agent_id, c.status,
            cs.nomor_wa, cs.nama, cs.last_seen,
            (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id AND m.sender_type = 'customer' AND m.is_wa_sent = 0) AS unread_count,
            (SELECT m2.pesan FROM messages m2 WHERE m2.chat_id = c.id ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
            c.assigned_at
        FROM chats c
        JOIN customers cs ON cs.id = c.customer_id
        WHERE c.status = 'open'
        ORDER BY c.assigned_at DESC
    ");
    $stmt->execute();
    $open = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch open chats in get_chats.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch open chats: ' . $e->getMessage()]);
    exit;
}

try {
    $stmt2 = $pdo->prepare("
        SELECT
            c.id, c.customer_id, c.agent_id, c.status,
            cs.nomor_wa, cs.nama, cs.last_seen,
            0 AS unread_count, '' AS last_message, NULL AS last_message_at,
            c.assigned_at, c.closed_at
        FROM chats c
        JOIN customers cs ON cs.id = c.customer_id
        WHERE c.status = 'closed' AND c.agent_id = :agent_id
        ORDER BY c.closed_at DESC
        LIMIT 50
    ");
    $stmt2->execute(['agent_id' => $agent_id]);
    $closed = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch closed chats in get_chats.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch closed chats: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'chats'  => array_merge($open, $closed),
]);

// Catch any other unexpected errors
} catch (Exception $e) {
    error_log("Unexpected error in get_chats.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan tidak terduga: ' . $e->getMessage()
    ]);
    exit;
}
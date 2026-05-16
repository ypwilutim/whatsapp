<?php
/**
 * DAFTAR SEMUA CHAT AKTIF & TERTUTUP UNTUK AGENT
 * GET (no params)
 * Return semua chat open + closed untuk CS agent yang login
 */
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../database.php';
$pdo = (new Database())->getConnection();

$agent_id = $_SESSION['agent_id'];

/* ===== CHATS TERBUKA (open) ===== */
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.customer_id,
        c.agent_id,
        c.status,
        cs.nomor_wa,
        cs.nama,
        cs.last_seen,
        (
            SELECT COUNT(*)
            FROM messages m
            WHERE m.chat_id = c.id
              AND m.sender_type = 'customer'
              AND m.is_wa_sent = 0
        ) AS unread_count,
        (
            SELECT m2.pesan
            FROM messages m2
            WHERE m2.chat_id = c.id
            ORDER BY m2.created_at DESC
            LIMIT 1
        ) AS last_message,
        (
            SELECT m3.created_at
            FROM messages m3
            WHERE m3.chat_id = c.id
            ORDER BY m3.created_at DESC
            LIMIT 1
        ) AS last_message_at,
        c.assigned_at
    FROM chats c
    JOIN customers cs ON cs.id = c.customer_id
    WHERE c.status = 'open'
    ORDER BY
        IFNULL((
            SELECT m4.created_at
            FROM messages m4
            WHERE m4.chat_id = c.id
            ORDER BY m4.created_at DESC
            LIMIT 1
        ), c.assigned_at) DESC
");
$stmt->execute();
$open = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== CHATS TERTUTUP (closed) — 50 terbaru untuk agent ini ===== */
$stmt2 = $pdo->prepare("
    SELECT
        c.id,
        c.customer_id,
        c.agent_id,
        c.status,
        cs.nomor_wa,
        cs.nama,
        cs.last_seen,
        0 AS unread_count,
        '' AS last_message,
        NULL AS last_message_at,
        c.assigned_at,
        c.closed_at
    FROM chats c
    JOIN customers cs ON cs.id = c.customer_id
    WHERE c.status = 'closed' AND c.agent_id = :agent_id
    ORDER BY c.closed_at DESC
    LIMIT 50
");
$stmt2->execute(['agent_id' => $agent_id]);
$closed = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'chats'  => array_merge($open, $closed),
]);

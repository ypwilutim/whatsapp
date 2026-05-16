<?php
/**
 * CS agent kirim pesan balik ke WA via WHACenter API
 * Selalu mengembalikan JSON (tidak pernah 500 tanpa json)
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache');
session_start();

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: silakan login CS']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
    exit;
}

// Menyelaraskan parameter agar bisa membaca 'number' atau 'customer_number' dari app.js
$chat_id  = isset($_POST['chat_id'])          ? trim($_POST['chat_id'])          : '';
$number   = isset($_POST['customer_number'])  ? trim($_POST['customer_number'])  : (isset($_POST['number']) ? trim($_POST['number']) : '');
$message  = isset($_POST['message'])         ? trim($_POST['message'])          : '';
$file_url = isset($_POST['file_url'])        ? trim($_POST['file_url'])         : '';

if ($chat_id === '' || $number === '' || $message === '') {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Chat ID: '.$chat_id.', Number: '.$number]);
    exit;
}

$dbFile = __DIR__ . '/../config/database.php';
if (!is_file($dbFile)) {
    echo json_encode(['status' => 'error', 'message' => 'File database.php tidak ditemukan: ' . $dbFile]);
    exit;
}
require_once $dbFile;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal koneksi DB: ' . $e->getMessage()]);
    exit;
}

$apiFile = __DIR__ . '/../config/api_whacenter.php';
if (!is_file($apiFile)) {
    $apiFile2 = __DIR__ . '/../api_whacenter.php';
    if (is_file($apiFile2)) { $apiFile = $apiFile2; }
}
if (!is_file($apiFile)) {
    echo json_encode(['status' => 'error', 'message' => 'File config/api_whacenter.php tidak ditemukan di: ' . $apiFile]);
    exit;
}
require_once $apiFile;
try {
    $api = new WhacenterAPI();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal load WHACenter API: ' . $e->getMessage()]);
    exit;
}

$number = preg_replace('/[^0-9]/', '', $number);
if (substr($number, 0, 1) === '0') { $number = '62' . substr($number, 1); }
if (substr($number, 0, 1) === '+') { $number = substr($number, 1); }
if (substr($number, 0, 1) !== '6') { $number = '62' . $number; }

if ($file_url !== '') {
    $resFile = $api->sendMessage($number, '', $file_url);
    if (!$resFile['success']) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal kirim file: ' . $resFile['error']]);
        exit;
    }
}

try {
    $result = $api->sendMessage($number, $message);
    if (!$result['success']) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal kirim ke WA: ' . ($result['error'] ?? 'Unknown')]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error WHACenter API: ' . $e->getMessage()]);
    exit;
}

$whacenter_id = isset($result['data']['id']) ? $result['data']['id'] : null;

try {
    // Membungkus Query SQL dengan try-catch agar jika struktur tabel MySQL Anda berbeda, ia memunculkan pesan error JSON yang jelas
    $stmt = $pdo->prepare("
        INSERT INTO messages (chat_id, sender_type, pesan, tipe_pesan, is_wa_sent, whacenter_msg_id)
        VALUES (:chat_id, 'agent', :pesan, :tipe, 1, :wc_id)
    ");
    $stmt->execute([
        'chat_id' => $chat_id,
        'pesan'   => $message,
        'tipe'    => $file_url ? 'file' : 'text',
        'wc_id'   => $whacenter_id,
    ]);
    
    $pdo->prepare('UPDATE chats SET assigned_at = NOW() WHERE id = :id')->execute(['id' => $chat_id]);

    echo json_encode([
        'status'     => 'success',
        'msg_id'     => $pdo->lastInsertId(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Query database gagal dijalankan. Ada kemungkinan kolom tabel messages tidak cocok: ' . $e->getMessage()
    ]);
    exit;
}
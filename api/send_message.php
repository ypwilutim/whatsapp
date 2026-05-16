<?php
/**
 * CS agent kirim pesan balik ke WA via WHACenter API
 * Selalu mengembalikan JSON (tidak pernah 500 tanpa json)
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache');
session_start();

// Helper: cetak JSON + exit
function json_out($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ===== 1) Auth CS =====
if (!isset($_SESSION['agent_id'])) {
    json_out(['status' => 'error', 'message' => 'Unauthorized: silakan login CS']);
}

// ===== 2) Method =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['status' => 'error', 'message' => 'Method tidak diizinkan, gunakan POST']);
}

// ===== 3) Ambil & validasi input =====
$chat_id  = isset($_POST['chat_id']) ? trim($_POST['chat_id']) : '';
$number   = isset($_POST['number'])  ? trim($_POST['number'])  : '';
$message  = isset($_POST['message']) ? trim($_POST['message']) : '';
$file_url = isset($_POST['file_url']) ? trim($_POST['file_url']) : '';

if ($chat_id === '' || $number === '' || $message === '') {
    json_out(['status' => 'error', 'message' => 'Data tidak lengkap']);
}

// ===== 4) Koneksi DB =====
$dbFile = __DIR__ . '/../database.php';
if (!is_file($dbFile)) {
    json_out(['status' => 'error', 'message' => 'File database.php tidak ditemukan: ' . $dbFile]);
}
require_once $dbFile;

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    json_out(['status' => 'error', 'message' => 'Koneksi DB gagal: ' . $e->getMessage()]);
}

// ===== 5) Load API WHACenter =====
$apiFile = __DIR__ . '/../config/api_whacenter.php';
if (!is_file($apiFile)) {
    $apiFile2 = __DIR__ . '/../api_whacenter.php';
    if (is_file($apiFile2)) { $apiFile = $apiFile2; }
}
if (!is_file($apiFile)) {
    json_out(['status' => 'error', 'message' => 'File config/api_whacenter.php tidak ditemukan']);
}
require_once $apiFile;

try {
    $api = new WhacenterAPI();
} catch (Exception $e) {
    json_out(['status' => 'error', 'message' => 'Gagal inisialisasi WHACenter API: ' . $e->getMessage()]);
}

// ===== 6) Normalisasi nomor (hilangkan +/0/dash/spasi) =====
$number = preg_replace('/[^0-9]/', '', $number);
if (substr($number, 0, 1) === '0')   { $number = '62' . substr($number, 1); }
if (substr($number, 0, 1) === '+')   { $number = substr($number, 1); }
if (substr($number, 0, 1) !== '6')   { $number = '62' . $number; }

// ===== 7) Kirim file jika ada =====
if ($file_url !== '') {
    try {
        $resFile = $api->sendMessage($number, '', $file_url);
        if (!$resFile['success']) {
            json_out(['status' => 'error', 'message' => 'Gagal kirim file: ' . $resFile['error']]);
        }
    } catch (Exception $e) {
        json_out(['status' => 'error', 'message' => 'Error kirim file: ' . $e->getMessage()]);
    }
}

// ===== 8) Kirim pesan teks via WHACenter =====
try {
    $result = $api->sendMessage($number, $message);
} catch (Exception $e) {
    json_out(['status' => 'error', 'message' => 'Error call WHACenter API: ' . $e->getMessage()]);
}
if (!$result['success']) {
    json_out(['status' => 'error', 'message' => 'Gagal kirim ke WHACenter: ' . ($result['error'] ?? 'Unknown')]);
}

// ===== 9) Simpan ke DB (agent) =====
try {
    $whacenter_id = isset($result['data']['id']) ? $result['data']['id'] : '';

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
    $msg_id = $pdo->lastInsertId();

    // Update waktu percakapan
    $pdo->prepare('UPDATE chats SET assigned_at = NOW() WHERE id = :id')->execute(['id' => $chat_id]);

} catch (Exception $e) {
    json_out(['status' => 'error', 'message' => 'Gagal simpan ke DB: ' . $e->getMessage()]);
}

// ===== 10) OK =====
json_out([
    'status'     => 'success',
    'message'    => 'Pesan terkirim ke WhatsApp',
    'msg_id'     => $msg_id,
    'created_at' => date('Y-m-d H:i:s'),
    'is_wa_sent' => 1,
]);

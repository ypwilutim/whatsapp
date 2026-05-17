<?php
/**
 * WEBHOOK WHACENTER
 * Format dari WHACenter (POST JSON):
 *   { "from": "6285603051722", "to": "6285156108635", "message": "...", "media": "url", "timestamp": "..." }
 * Set di WHACenter Dashboard → Webhook:
 *   https://wa.ypwilutim.com/webhook/receive_message.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Log the request
error_log("RECEIVE_MESSAGE_REQUEST: from_ip=" . $_SERVER['REMOTE_ADDR'] . " method=" . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("RECEIVE_MESSAGE_INFO: OPTIONS request handled");
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = (new Database())->getConnection();

if ($pdo === null) {
    error_log("RECEIVE_MESSAGE_ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
error_log("RECEIVE_MESSAGE_INFO: Database connection established");

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input) { $input = $_POST; }

error_log("RECEIVE_MESSAGE_REQUEST_DATA: " . print_r($input, true));

$from      = $input['from']      ?? '';
$to        = $input['to']        ?? '';
$message   = $input['message']   ?? '';
$media     = $input['media']     ?? '';
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

error_log("RECEIVE_MESSAGE_REQUEST_PARSED: from='$from', to='$to', message_length=" . strlen($message) . ", media='$media'");

if (empty($from) || empty($message)) {
    error_log("RECEIVE_MESSAGE_ERROR: Missing required parameters. from='$from', message='$message'");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'from dan message wajib diisi']);
    exit;
}

$from = preg_replace('/[^0-9]/', '', $from);
if (substr($from, 0, 1) === '0') { $from = '62' . substr($from, 1); }
if (substr($from, 0, 1) !== '6') { $from = '62' . $from; }

$to = preg_replace('/[^0-9]/', '', $to);
if (substr($to, 0, 1) === '0') { $to = '62' . substr($to, 1); }
if (substr($to, 0, 1) !== '6') { $to = '62' . $to; }

error_log("RECEIVE_MESSAGE_REQUEST_NORMALIZED: from='$from', to='$to'");

// Cari atau buat customer
$stmt = $pdo->prepare('SELECT * FROM customers WHERE nomor_wa = :nomor LIMIT 1');
$stmt->execute(['nomor' => $from]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    error_log("RECEIVE_MESSAGE_INFO: Creating new customer for number: $from");
    $pdo->prepare('INSERT INTO customers (nomor_wa) VALUES (:nomor)')->execute(['nomor' => $from]);
    $customer_id = $pdo->lastInsertId();
    error_log("RECEIVE_MESSAGE_SUCCESS: Created customer with ID: $customer_id");
} else {
    $customer_id = $customer['id'];
    error_log("RECEIVE_MESSAGE_INFO: Found existing customer with ID: $customer_id");
    $pdo->prepare('UPDATE customers SET last_seen = NOW() WHERE id = :id')->execute(['id' => $customer_id]);
    error_log("RECEIVE_MESSAGE_SUCCESS: Updated last_seen for customer ID: $customer_id");
}

// Cari atau buat chat
$stmt = $pdo->prepare('SELECT * FROM chats WHERE customer_id = :cid AND status = "open" LIMIT 1');
$stmt->execute(['cid' => $customer_id]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    error_log("RECEIVE_MESSAGE_INFO: Creating new open chat for customer_id: $customer_id");
    $pdo->prepare('INSERT INTO chats (customer_id, status) VALUES (:cid, "open")')->execute(['cid' => $customer_id]);
    $chat_id = $pdo->lastInsertId();
    error_log("RECEIVE_MESSAGE_SUCCESS: Created chat with ID: $chat_id");
} else {
    $chat_id = $chat['id'];
    error_log("RECEIVE_MESSAGE_INFO: Found existing open chat with ID: $chat_id");
}

// Simpan pesan customer
$tipe  = $media ? 'image' : 'text';
$pesan = $media ? trim($message ?: '[Media]') : $message;

error_log("RECEIVE_MESSAGE_INFO: Saving message - type: $tipe, length: " . strlen($pesan));

$stmt = $pdo->prepare("
    INSERT INTO messages (chat_id, sender_type, pesan, tipe_pesan, is_wa_sent)
    VALUES (:chat_id, 'customer', :pesan, :tipe, 0)
");
$stmt->execute([
    'chat_id' => $chat_id,
    'pesan'   => $pesan,
    'tipe'    => $tipe,
]);
$msg_id = $pdo->lastInsertId();
error_log("RECEIVE_MESSAGE_SUCCESS: Message saved with ID: $msg_id");

echo json_encode([
    'status'      => 'success',
    'msg_id'      => (int)$msg_id,
    'chat_id'     => (int)$chat_id,
    'customer_id' => (int)$customer_id,
    'received'    => true,
]);
error_log("RECEIVE_MESSAGE_REQUEST_COMPLETED: Returning success response");
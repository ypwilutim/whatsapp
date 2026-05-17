<?php
/**
 * CS agent kirim pesan balik ke WA via WHACenter API
 * Selalu mengembalikan JSON (tidak pernah 500 tanpa json)
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache');
session_start();

// Enable error logging to catch any issues
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log the request
error_log("SEND_MESSAGE_REQUEST: agent_id=" . ($_SESSION['agent_id'] ?? 'none') . " from=" . $_SERVER['REMOTE_ADDR'] . " method=" . $_SERVER['REQUEST_METHOD']);

// Catch any unexpected errors in the main logic
try {
    if (!isset($_SESSION['agent_id'])) {
        error_log("SEND_MESSAGE_ERROR: Unauthorized access attempt");
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: silakan login CS']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("SEND_MESSAGE_ERROR: Invalid request method: " . $_SERVER['REQUEST_METHOD']);
        echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
        exit;
    }

    // Menyelaraskan parameter agar bisa membaca 'number' atau 'customer_number' dari app.js
    $chat_id  = isset($_POST['chat_id'])          ? trim($_POST['chat_id'])          : '';
    $number   = isset($_POST['customer_number'])  ? trim($_POST['customer_number'])  : (isset($_POST['number']) ? trim($_POST['number']) : '');
    $message  = isset($_POST['message'])         ? trim($_POST['message'])          : '';
    $file_url = isset($_POST['file_url'])        ? trim($_POST['file_url'])         : '';

    error_log("SEND_MESSAGE_REQUEST_DATA: chat_id=$chat_id, number=$number, message_length=" . strlen($message) . ", file_url=" . ($file_url ?: 'none'));

    if ($chat_id === '' || $number === '' || $message === '') {
        error_log("SEND_MESSAGE_ERROR: Missing required parameters. chat_id='$chat_id', number='$number', message='$message'");
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Chat ID: '.$chat_id.', Number: '.$number]);
        exit;
    }

    $dbFile = __DIR__ . '/../config/database.php';
    if (!is_file($dbFile)) {
        error_log("SEND_MESSAGE_ERROR: Database config file not found: " . $dbFile);
        echo json_encode(['status' => 'error', 'message' => 'File database.php tidak ditemukan: ' . $dbFile]);
        exit;
    }
    require_once $dbFile;
    try {
        $pdo = (new Database())->getConnection();
        if ($pdo === null) {
            error_log("SEND_MESSAGE_ERROR: Database connection failed");
            echo json_encode(['status' => 'error', 'message' => 'Gagal koneksi ke database']);
            exit;
        }
        error_log("SEND_MESSAGE_SUCCESS: Database connection established");
    } catch (Exception $e) {
        error_log("SEND_MESSAGE_ERROR: Database connection error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal koneksi DB: ' . $e->getMessage()]);
        exit;
    }

    $apiFile = __DIR__ . '/../config/api_whacenter.php';
    if (!is_file($apiFile)) {
        $apiFile2 = __DIR__ . '/../api_whacenter.php';
        if (is_file($apiFile2)) { $apiFile = $apiFile2; }
    }
    if (!is_file($apiFile)) {
        error_log("SEND_MESSAGE_ERROR: WhatsApp API config file not found: " . $apiFile);
        echo json_encode(['status' => 'error', 'message' => 'File config/api_whacenter.php tidak ditemukan di: ' . $apiFile]);
        exit;
    }
    require_once $apiFile;
    try {
        $api = new WhacenterAPI();
        error_log("SEND_MESSAGE_SUCCESS: WhatsApp API initialized");
    } catch (Exception $e) {
        error_log("SEND_MESSAGE_ERROR: WhatsApp API load error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal load WHACenter API: ' . $e->getMessage()]);
        exit;
    }

    $number = preg_replace('/[^0-9]/', '', $number);
    if (substr($number, 0, 1) === '0') { $number = '62' . substr($number, 1); }
    if (substr($number, 0, 1) === '+') { $number = substr($number, 1); }
    if (substr($number, 0, 1) !== '6') { $number = '62' . $number; }

    error_log("SEND_MESSAGE_REQUEST_PROCESSED_NUMBER: $number");

    if ($file_url !== '') {
        error_log("SEND_MESSAGE_ATTEMPT: Sending file to $number");
        $resFile = $api->sendMessage($number, '', $file_url);
        if (!$resFile['success']) {
            error_log("SEND_MESSAGE_ERROR: Failed to send file: " . $resFile['error']);
            echo json_encode(['status' => 'error', 'message' => 'Gagal kirim file: ' . $resFile['error']]);
            exit;
        }
        error_log("SEND_MESSAGE_SUCCESS: File sent successfully");
    }

    try {
        error_log("SEND_MESSAGE_ATTEMPT: Sending message to $number");
        $result = $api->sendMessage($number, $message);
        if (!$result['success']) {
            error_log("SEND_MESSAGE_ERROR: Failed to send message: " . ($result['error'] ?? 'Unknown'));
            echo json_encode(['status' => 'error', 'message' => 'Gagal kirim ke WA: ' . ($result['error'] ?? 'Unknown')]);
            exit;
        }
        error_log("SEND_MESSAGE_SUCCESS: Message sent successfully, WA ID: " . ($result['data']['id'] ?? 'none'));
    } catch (Exception $e) {
        error_log("SEND_MESSAGE_ERROR: WhatsApp API send error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error WHACenter API: ' . $e->getMessage()]);
        exit;
    }

    $whacenter_id = isset($result['data']['id']) ? $result['data']['id'] : null;
    error_log("SEND_MESSAGE_INFO: WhatsApp message ID: " . $whacenter_id);

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
        
        $msgId = $pdo->lastInsertId();
        error_log("SEND_MESSAGE_SUCCESS: Message saved to database with ID: $msgId");

        echo json_encode([
            'status'     => 'success',
            'msg_id'     => $msgId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        exit;

    } catch (Exception $e) {
        error_log("SEND_MESSAGE_ERROR: Database query error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error', 
            'message' => 'Query database gagal dijalankan. Ada kemungkinan kolom tabel messages tidak cocok: ' . $e->getMessage()
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("SEND_MESSAGE_ERROR: Unexpected error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan tidak terduga: ' . $e->getMessage()
    ]);
    exit;
}
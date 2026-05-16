<?php
session_start();

if (!isset($_SESSION['agent_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$pdo = (new Database())->getConnection();

$stmt = $pdo->query('SELECT * FROM agents LIMIT 1');
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "
    SELECT c.id, c.status, c.assigned_at,
           cs.id AS customer_id, cs.nomor_wa, cs.nama,
           (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id AND m.sender_type = 'customer' AND m.is_wa_sent = 0) AS unread_count,
           (SELECT pesan FROM messages m2 WHERE m2.chat_id = c.id ORDER BY m2.created_at DESC LIMIT 1) AS last_message
    FROM chats c
    JOIN customers cs ON cs.id = c.customer_id
    WHERE c.agent_id = :agent_id
    ORDER BY c.assigned_at DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['agent_id' => $_SESSION['agent_id']]);
$activeChats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUnread = array_sum(array_column($activeChats, 'unread_count'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard WhatsApp CS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="agent-info">
                    <div class="agent-avatar">
                        <?= strtoupper(substr($agent['nama'], 0, 2)) ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($agent['nama']) ?></strong>
                        <span class="status-badge status-online">Online</span>
                    </div>
                </div>
                <div class="sidebar-actions">
                    <a href="logout.php" class="btn btn-logout" title="Logout">&#128682;</a>
                </div>
            </div>

            <div class="search-box">
                <input type="text" id="searchCustomer" placeholder="Cari customer...">
            </div>

            <div class="customer-list" id="customerList">
                <?php if (empty($activeChats)): ?>
                    <div class="empty-state">
                        <p>Belum ada chat aktif</p>
                        <small>Chat akan muncul otomatis saat ada pesan masuk</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeChats as $chat): ?>
                        <div class="customer-item" data-chat-id="<?= $chat['id'] ?>" data-customer-id="<?= $chat['customer_id'] ?>" data-customer-number="<?= htmlspecialchars($chat['nomor_wa']) ?>">
                            <div class="customer-avatar">
                                <?= strtoupper(substr($chat['nama'] ?: $chat['nomor_wa'], 0, 2)) ?>
                            </div>
                            <div class="customer-info">
                                <div class="customer-name">
                                    <?= htmlspecialchars($chat['nama'] ?: 'Tanpa Nama') ?>
                                    <span class="customer-number"><?= htmlspecialchars($chat['nomor_wa']) ?></span>
                                </div>
                                <div class="customer-last-msg">
                                    <?= htmlspecialchars(mb_substr($chat['last_message'] ?: 'Belum ada pesan', 0, 40)) ?>
                                </div>
                            </div>
                            <?php if ($chat['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= (int)$chat['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <div class="chat-header" id="chatHeader">
                <h2>Pilih chat untuk memulai percakapan</h2>
            </div>
            <div class="messages-container" id="messagesContainer">
                <div class="empty-chat">
                    <div class="empty-chat-icon">&#128172;</div>
                    <p>Silakan pilih customer dari daftar untuk mulai mengobrol</p>
                </div>
            </div>
            <div class="input-area" id="inputArea" style="display:none;">
                <form id="sendMessageForm">
                    <div class="input-wrapper">
                        <input type="hidden" id="currentChatId" name="chat_id">
                        <input type="hidden" id="currentCustomerNumber" name="customer_number">
                        <textarea
                            id="messageInput"
                            placeholder="Ketik pesan... (Enter untuk kirim, Shift+Enter untuk baris baru)"
                            rows="1"
                        ></textarea>
                        <button type="submit" class="btn btn-send">Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>

<?php
/* ============ API HELPER ENDPOINTS (called by dashboard JS via GET) ============ */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    require_once __DIR__ . '/config/database.php';
    $pdo = (new Database())->getConnection();

    if ($action === 'get_agents') {
        $stmt = $pdo->query('SELECT id, nama, is_online FROM agents ORDER BY is_online DESC, nama ASC');
        echo json_encode(['status' => 'success', 'agents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'close_chat') {
        $chat_id = $_GET['chat_id'] ?? null;
        if ($chat_id) {
            $pdo->prepare('UPDATE chats SET status = "closed", closed_at = NOW() WHERE id = :id')
                ->execute(['id' => $chat_id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'chat_id kosong']);
        }
        exit;
    }

    if ($action === 'assign_chat') {
        $chat_id   = $_GET['chat_id'] ?? $_POST['chat_id'] ?? null;
        $agent_id  = $_GET['agent_id'] ?? $_POST['agent_id'] ?? null;
        if ($chat_id && $agent_id) {
            $pdo->prepare('UPDATE chats SET agent_id = :aid, status = "open", assigned_at = NOW() WHERE id = :id')
                ->execute(['aid' => $agent_id, 'id' => $chat_id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'chat_id atau agent_id kosong']);
        }
        exit;
    }

    if ($action === 'get_customer_history') {
        $customer_id = $_GET['customer_id'] ?? null;
        if ($customer_id) {
            $stmt = $pdo->prepare('
                SELECT c.id, c.status, c.assigned_at, c.closed_at,
                       a.nama AS agent_nama,
                       (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id AND m.sender_type = "customer" AND m.is_wa_sent = 0) AS unread_count
                FROM chats c
                LEFT JOIN agents a ON a.id = c.agent_id
                WHERE c.customer_id = :cid
                ORDER BY c.assigned_at DESC
            ');
            $stmt->execute(['cid' => $customer_id]);
            $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($histories as &$h) {
                $ms = $pdo->prepare('
                    SELECT id, sender_type, pesan, tipe_pesan, created_at
                    FROM messages WHERE chat_id = :cid ORDER BY created_at ASC
                ');
                $ms->execute(['cid' => $h['id']]);
                $h['messages'] = $ms->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['status' => 'success', 'histories' => $histories]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'customer_id kosong']);
        }
        exit;
    }

    if ($action === 'get_messages') {
        $chat_id = $_GET['chat_id'] ?? null;
        if (!$chat_id) {
            echo json_encode(['status' => 'error', 'message' => 'chat_id wajib']);
            exit;
        }
        $chat_id = (int)$chat_id;
        $ms = $pdo->prepare('SELECT id, sender_type, pesan, tipe_pesan, created_at FROM messages WHERE chat_id = :chat_id ORDER BY created_at ASC');
        $ms->execute(['chat_id' => $chat_id]);
        $messages = $ms->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'messages' => $messages]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
    exit;
}
?>

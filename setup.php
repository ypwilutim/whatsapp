<?php
// SETUP SCRIPT -- run ONCE in browser to create admin CS
// Visit: http://wa.ypwilutim.com/setup.php
// Default CS pass: admin123
// Script auto-deletes after running!

require_once __DIR__ . '/config/database.php';

$pdo = (new Database())->getConnection();

try {
    // Check if agents table exists
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() AND table_name = 'agents'
    ");
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        die("Tabel 'agents' belum ada. Import schema.sql lewat phpMyAdmin terlebih dahulu.\n");
    }

    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "OK - Admin 'admin' sudah ada (ID: " . (int)$existing['id'] . ").<br>";
        echo "Setup done. Deleting script...";
        @unlink(__FILE__);
        exit;
    }

    // Create admin with bcrypt hash
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO agents (nama, username, password, is_online)
        VALUES (:nama, :uname, :pass, 1)
    ");
    $stmt->execute([
        'nama'  => 'Admin CS',
        'uname' => 'admin',
        'pass'  => $hash,
    ]);

    echo "<h1>Setup Berhasil!</h1>";
    echo "<p>Username : <strong>admin</strong></p>";
    echo "<p>Password : <strong>admin123</strong></p>";
    echo "<p><a href='login.php'>Klik di sini untuk login</a>.</p>";
    echo "<p style='color:red;'><strong>SCRIPT INI SUDAH DIHAPUS OTOMATIS!</strong></p>";

    @unlink(__FILE__);

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

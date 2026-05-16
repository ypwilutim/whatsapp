<?php
session_start();

if (isset($_SESSION['agent_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare('SELECT * FROM agents WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $_POST['username']]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent && password_verify($_POST['password'], $agent['password'])) {
        $_SESSION['agent_id']   = $agent['id'];
        $_SESSION['agent_nama'] = $agent['nama'];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Username atau password salah!';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WhatsApp CS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <h1>&#128172; WhatsApp CS</h1>
                <p>Login sebagai Customer Service</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </form>

            <div class="login-footer">
                <p>Default: username <strong>admin</strong> | password <strong>admin123</strong></p>
            </div>
        </div>
    </div>
</body>
</html>

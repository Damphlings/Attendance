<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in.
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, password_hash
                 FROM admins
                 WHERE username = :username
                 LIMIT 1'
            );

            $stmt->execute([
                ':username' => $username
            ]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['last_activity'] = time();

                setcookie(
                    session_name(),
                    session_id(),
                    time() + (60 * 60 * 8),
                    APP_URL === '' ? '/' : APP_URL . '/',
                    '',
                    !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    true
                );

                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            }

            $error = 'Invalid username or password.';

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to process your login. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Attendance System</title>

    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-container">

    <div class="login-card">

        <div class="login-logo">
            <div class="logo-icon">A</div>
            <h1>Attendance System</h1>
            <p>Committee Attendance Management</p>
        </div>

        <div class="login-heading">
            <h2>Welcome Back</h2>
            <p>Sign in to access the administration dashboard.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>" method="POST">

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="login-button">
                Sign In
            </button>

        </form>

        <div class="login-footer">
            <p>Authorized administrators only.</p>
        </div>

    </div>

</div>

</body>
</html>
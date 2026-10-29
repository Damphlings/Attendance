<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/*
|--------------------------------------------------------------------------
| Authentication Guard
|--------------------------------------------------------------------------
| Include this file at the top of every page that requires authentication.
*/

if (empty($_SESSION['admin_id'])) {
    set_flash('error', 'Please log in to access the system.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate the Session Identity
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin_username'])
    || !is_string($_SESSION['admin_username'])
) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();

    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Current Administrator
|--------------------------------------------------------------------------
*/

$currentAdminId = (int) $_SESSION['admin_id'];
$currentAdminUsername = (string) $_SESSION['admin_username'];
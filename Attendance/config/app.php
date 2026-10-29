<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Committee Attendance System');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Manila');
}

if (!defined('APP_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $scriptDirectory = str_replace('\\', '/', (string) dirname($scriptName));
    $basePath = rtrim($scriptDirectory, '/');

    // API scripts live one directory below the application root.
    if (strtolower(basename($basePath)) === 'api') {
        $basePath = rtrim(dirname($basePath), '/');
    }

    define('APP_URL', $basePath === '' ? '' : $basePath);
}

// Set the application timezone.
date_default_timezone_set(APP_TIMEZONE);

// Start the session only if one has not already been started.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 8,
        'path' => APP_URL === '' ? '/' : APP_URL . '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

if (isset($_SESSION['admin_id'])) {
    $_SESSION['last_activity'] = time();
}

/*
|--------------------------------------------------------------------------
| Escape HTML Output
|--------------------------------------------------------------------------
| Usage: <?= e($member['full_name']) ?>
*/

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
| Usage: redirect('dashboard.php');
*/

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        // Only allow local relative paths.
        if (
            preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $path)
            || str_starts_with($path, '//')
        ) {
            throw new InvalidArgumentException(
                'Only local relative redirect paths are allowed.'
            );
        }

        $basePath = APP_URL === '' ? '' : APP_URL;
        header('Location: ' . $basePath . '/' . ltrim($path, '/'));
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
| Generate a token for forms and verify it on POST requests.
*/

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (
            empty($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . e(csrf_token())
            . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(): bool
    {
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return is_string($submittedToken)
            && is_string($sessionToken)
            && $submittedToken !== ''
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }
}

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
| Usage:
| set_flash('success', 'Member added successfully.');
| $message = get_flash();
*/

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $allowedTypes = ['success', 'error', 'warning', 'info'];

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        if (!isset($_SESSION['flash_message'])) {
            return null;
        }

        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);

        return is_array($message) ? $message : null;
    }
}
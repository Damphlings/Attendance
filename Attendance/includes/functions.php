<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !$pdo instanceof PDO) {
    require_once __DIR__ . '/../config/db.php';
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

if (!function_exists('format_date')) {
    function format_date(?string $date): string
    {
        if (empty($date)) {
            return '—';
        }

        try {
            return (new DateTime($date))->format('M d, Y');
        } catch (Throwable $e) {
            return '—';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Format Time
|--------------------------------------------------------------------------
*/

if (!function_exists('format_time')) {
    function format_time(?string $time): string
    {
        if (empty($time)) {
            return '—';
        }

        try {
            return (new DateTime($time))->format('h:i A');
        } catch (Throwable $e) {
            return '—';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Format Date and Time
|--------------------------------------------------------------------------
*/

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string
    {
        if (empty($datetime)) {
            return '—';
        }

        try {
            return (new DateTime($datetime))->format('M d, Y h:i A');
        } catch (Throwable $e) {
            return '—';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Generate Member Code
|--------------------------------------------------------------------------
| Example: MEM-2026-0001
|--------------------------------------------------------------------------
*/

if (!function_exists('generate_member_code')) {
    function generate_member_code(PDO $pdo): string
    {
        $year = date('Y');
        $prefix = 'MEM-' . $year . '-';

        $stmt = $pdo->prepare(
            'SELECT member_code
             FROM members
             WHERE member_code LIKE :prefix
             ORDER BY member_code DESC
             LIMIT 1'
        );

        $stmt->execute([
            ':prefix' => $prefix . '%'
        ]);

        $lastCode = $stmt->fetchColumn();

        $nextNumber = 1;

        if ($lastCode && preg_match('/(\d+)$/', (string) $lastCode, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

/*
|--------------------------------------------------------------------------
| Generate a Secure QR Token
|--------------------------------------------------------------------------
*/

if (!function_exists('generate_qr_token')) {
    function generate_qr_token(): string
    {
        return bin2hex(random_bytes(32));
    }
}

/*
|--------------------------------------------------------------------------
| Hash a QR Token
|--------------------------------------------------------------------------
*/

if (!function_exists('hash_qr_token')) {
    function hash_qr_token(string $token): string
    {
        return hash('sha256', $token);
    }
}

/*
|--------------------------------------------------------------------------
| Validate CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('require_valid_csrf')) {
    function require_valid_csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        if (!verify_csrf_token()) {
            http_response_code(403);
            exit('Invalid security token. Reload the page and try again.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Display Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('display_flash_message')) {
    function display_flash_message(): void
    {
        $flash = get_flash();

        if (!$flash) {
            return;
        }

        $type = e($flash['type']);
        $message = e($flash['message']);

        echo '<div class="alert alert-' . $type . '" role="alert">';
        echo $message;
        echo '</div>';
    }
}

/*
|--------------------------------------------------------------------------
| Active Navigation Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('is_active_page')) {
    function is_active_page(string $page): bool
    {
        $currentPage = basename(
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''
        );

        return $currentPage === $page;
    }
}

/*
|--------------------------------------------------------------------------
| Verify an ID
|--------------------------------------------------------------------------
*/

if (!function_exists('valid_id')) {
    function valid_id(mixed $id): bool
    {
        return filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) !== false;
    }
}
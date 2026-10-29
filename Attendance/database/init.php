<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Committee Attendance System - Database Initialization
|--------------------------------------------------------------------------
| Creates the database tables and indexes required by the application.
| Existing tables and records are preserved.
|
| Open through:
| http://localhost/attendance/database/init.php
|
| IMPORTANT:
| Delete or restrict access to this file after initialization.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

/*
|--------------------------------------------------------------------------
| Initialization Access
|--------------------------------------------------------------------------
| Allow initialization from localhost only.
|--------------------------------------------------------------------------
*/

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$isLocalhost = in_array(
    $remoteAddress,
    ['127.0.0.1', '::1'],
    true
);

if (PHP_SAPI !== 'cli' && !$isLocalhost) {
    http_response_code(403);
    exit('Database initialization is only permitted from localhost.');
}

$messages = [];
$errors = [];

if (!function_exists('ensure_index')) {
    function ensure_index(PDO $pdo, string $indexName, string $tableName, string $columns): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name'
        );

        $stmt->execute([
            ':table_name' => $tableName,
            ':index_name' => $indexName,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                sprintf(
                    'CREATE INDEX %s ON %s (%s)',
                    $indexName,
                    $tableName,
                    $columns
                )
            );
        }
    }
}

try {
    /*
    |--------------------------------------------------------------------------
    | Administrators
    |--------------------------------------------------------------------------
    */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id INT NOT NULL AUTO_INCREMENT,
            username VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /*
    |--------------------------------------------------------------------------
    | Members
    |--------------------------------------------------------------------------
    */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS members (
            id INT NOT NULL AUTO_INCREMENT,
            member_code VARCHAR(100) NOT NULL UNIQUE,
            full_name VARCHAR(255) NOT NULL,
            position VARCHAR(255) NOT NULL DEFAULT 'Member',
            email VARCHAR(255) NULL,
            status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
            qr_token_hash VARCHAR(255) NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /*
    |--------------------------------------------------------------------------
    | Attendance Sessions
    |--------------------------------------------------------------------------
    */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            session_name VARCHAR(255) NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /*
    |--------------------------------------------------------------------------
    | Attendance Records
    |--------------------------------------------------------------------------
    */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT NOT NULL AUTO_INCREMENT,
            member_id INT NOT NULL,
            session_id INT NOT NULL,
            time_in DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verification_status ENUM('Verified', 'Unverified') NOT NULL DEFAULT 'Verified',
            PRIMARY KEY (id),
            UNIQUE KEY uq_member_session (member_id, session_id),
            CONSTRAINT fk_attendance_member
                FOREIGN KEY (member_id) REFERENCES members(id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT,
            CONSTRAINT fk_attendance_session
                FOREIGN KEY (session_id) REFERENCES attendance_sessions(id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    | Optional audit trail for future logging features.
    |--------------------------------------------------------------------------
    */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT NOT NULL AUTO_INCREMENT,
            admin_id INT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_activity_admin
                FOREIGN KEY (admin_id) REFERENCES admins(id)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /*
    |--------------------------------------------------------------------------
    | Database Indexes
    |--------------------------------------------------------------------------
    */

    ensure_index($pdo, 'idx_members_full_name', 'members', 'full_name');
    ensure_index($pdo, 'idx_members_status', 'members', 'status');
    ensure_index($pdo, 'idx_attendance_session', 'attendance', 'session_id');
    ensure_index($pdo, 'idx_attendance_member', 'attendance', 'member_id');
    ensure_index($pdo, 'idx_attendance_time_in', 'attendance', 'time_in');
    ensure_index($pdo, 'idx_sessions_date', 'attendance_sessions', 'session_date');
    ensure_index($pdo, 'idx_activity_logs_created_at', 'activity_logs', 'created_at');

    $messages[] = 'Database tables and indexes initialized successfully.';

} catch (Throwable $e) {
    error_log('Database initialization failed: ' . $e->getMessage());

    $errors[] = 'Database initialization failed. Check the PHP error log for details.';
}

/*
|--------------------------------------------------------------------------
| Optional First Administrator Setup
|--------------------------------------------------------------------------
| Only create the initial administrator when no admin accounts exist.
| Set these environment variables before running the initializer:
|
| ATTENDANCE_ADMIN_USERNAME
| ATTENDANCE_ADMIN_PASSWORD
|
| If these variables are absent, no administrator is created.
|--------------------------------------------------------------------------
*/

if (!$errors) {
    try {
        $adminCount = (int) $pdo
            ->query('SELECT COUNT(*) FROM admins')
            ->fetchColumn();

        if ($adminCount === 0) {
            $initialUsername = getenv('ATTENDANCE_ADMIN_USERNAME');
            $initialPassword = getenv('ATTENDANCE_ADMIN_PASSWORD');

            if (
                $initialUsername !== false
                && $initialPassword !== false
                && trim($initialUsername) !== ''
                && strlen($initialPassword) >= 12
            ) {
                $stmt = $pdo->prepare(
                    'INSERT INTO admins (username, password_hash)
                     VALUES (:username, :password_hash)'
                );

                $stmt->execute([
                    ':username' => trim($initialUsername),
                    ':password_hash' => password_hash(
                        $initialPassword,
                        PASSWORD_DEFAULT
                    )
                ]);

                $messages[] = 'Initial administrator account created.';
            } else {
                $messages[] = 'No administrator account was created. Configure ATTENDANCE_ADMIN_USERNAME and ATTENDANCE_ADMIN_PASSWORD, or create an administrator using a separate setup process.';
            }
        } else {
            $messages[] = 'An administrator account already exists. Existing accounts were preserved.';
        }
    } catch (Throwable $e) {
        error_log('Initial administrator setup failed: ' . $e->getMessage());

        $errors[] = 'The tables were initialized, but administrator setup failed.';
    }
}

$isSuccess = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Database Setup | Committee Attendance System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f5fa;
            color: #263247;
            font-family: Arial, sans-serif;
        }

        .setup-card {
            width: 100%;
            max-width: 650px;
            padding: 32px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(27, 48, 110, 0.10);
        }

        .setup-heading {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e8ebf2;
        }

        .setup-heading h1 {
            margin: 0 0 8px;
            color: #1b306e;
            font-size: 25px;
        }

        .setup-heading p {
            margin: 0;
            color: #747e91;
            line-height: 1.6;
        }

        .status {
            margin-bottom: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .status.success {
            background: #e8f7ed;
            color: #176a36;
        }

        .status.error {
            background: #fdecec;
            color: #a32626;
        }

        .notice {
            margin-top: 22px;
            padding: 16px;
            border-left: 4px solid #fdc40f;
            background: #fff9df;
            font-size: 14px;
            line-height: 1.7;
        }

        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            border-radius: 8px;
            background: #1b306e;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .button:hover {
            background: #142555;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="setup-heading">
            <h1>Database Initialization</h1>
            <p>Committee Attendance Management System</p>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="status success">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="status error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </div>
        <?php endforeach; ?>

        <div class="notice">
            <strong>Security reminder:</strong>
            After confirming that initialization succeeded, remove
            <code>database/init.php</code> from the web-accessible project
            directory or otherwise disable web access to it.
            Do not store administrator passwords in this file.
        </div>

        <?php if ($isSuccess): ?>
            <a class="button" href="<?= htmlspecialchars(
                APP_URL . '/login.php',
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
                Proceed to Login
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
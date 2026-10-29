<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    $_SESSION['flash_message'] = 'Invalid session ID.';
    $_SESSION['flash_type'] = 'error';
    header('Location: attendance.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM attendance_sessions
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sessionData) {
    $_SESSION['flash_message'] = 'Session not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: attendance.php');
    exit;
}

$errors = [];

$sessionName = $sessionData['session_name'];
$sessionDate = $sessionData['session_date'];
$startTime = $sessionData['start_time'] ?? '';
$endTime = $sessionData['end_time'] ?? '';
$description = $sessionData['description'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    $sessionName = trim($_POST['session_name'] ?? '');
    $sessionDate = trim($_POST['session_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid form request. Refresh the page and try again.';
    }

    if ($sessionName === '' || strlen($sessionName) > 150) {
        $errors[] = 'Session name is required and must not exceed 150 characters.';
    }

    $dateObject = DateTime::createFromFormat('!Y-m-d', $sessionDate);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $sessionDate) {
        $errors[] = 'Please select a valid session date.';
    }

    $validTime = static function ($time) {
        return $time === '' || (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
    };

    if (!$validTime($startTime) || !$validTime($endTime)) {
        $errors[] = 'Please enter valid start and end times.';
    }

    if ($startTime !== '' && $endTime !== '' && $endTime <= $startTime) {
        $errors[] = 'End time must be later than start time.';
    }

    if (strlen($description) > 2000) {
        $errors[] = 'Description must not exceed 2,000 characters.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                UPDATE attendance_sessions
                SET
                    session_name = :session_name,
                    session_date = :session_date,
                    start_time = :start_time,
                    end_time = :end_time,
                    description = :description
                WHERE id = :id
            ");

            $stmt->execute([
                ':session_name' => $sessionName,
                ':session_date' => $sessionDate,
                ':start_time' => $startTime !== '' ? $startTime : null,
                ':end_time' => $endTime !== '' ? $endTime : null,
                ':description' => $description !== '' ? $description : null,
                ':id' => $id
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['flash_message'] = 'Session updated successfully.';
            $_SESSION['flash_type'] = 'success';

            header('Location: attendance.php');
            exit;

        } catch (PDOException $e) {
            error_log('Edit session error: ' . $e->getMessage());
            $errors[] = 'Unable to update this session.';
        }
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Session | Attendance System</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f4f6fb; color: #263247; }
        a { text-decoration: none; color: inherit; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; flex-shrink: 0; background: #1b306e; color: white; padding: 25px 16px; }
        .brand { padding: 0 12px 30px; border-bottom: 1px solid rgba(255,255,255,.15); }
        .brand h2 { font-size: 19px; }
        .brand p { margin-top: 6px; color: #d3dcf7; font-size: 12px; }
        .nav-label { margin: 25px 12px 10px; color: #aebbe0; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        .nav-link { display: block; padding: 13px 14px; margin: 5px 0; border-radius: 8px; color: #e5eaf8; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #304784; }
        .main-content { flex: 1; min-width: 0; }
        .topbar { background: white; padding: 20px 32px; min-height: 80px; display: flex; justify-content: space-between; align-items: center; gap: 15px; border-bottom: 1px solid #e8ebf2; }
        .topbar h1 { font-size: 23px; color: #1b306e; }
        .topbar p { margin-top: 5px; color: #7b8495; font-size: 13px; }
        .admin-info { font-size: 13px; }
        .admin-info strong { color: #1b306e; }
        .content { padding: 30px 32px; }
        .page-heading { margin-bottom: 24px; }
        .page-heading h2 { color: #1b306e; font-size: 23px; }
        .page-heading p { margin-top: 8px; color: #7b8495; font-size: 14px; }
        .form-card { max-width: 850px; background: white; border: 1px solid #e9edf5; border-radius: 12px; overflow: hidden; }
        .form-card-header { padding: 22px 25px; border-bottom: 1px solid #edf0f5; }
        .form-card-header h3 { color: #1b306e; font-size: 17px; }
        .form-card-header p { margin-top: 6px; color: #8790a2; font-size: 13px; }
        .form-body { padding: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: 1 / -1; }
        label { font-size: 13px; font-weight: bold; color: #4e586b; }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #dce1eb; border-radius: 7px; background: white; outline: none; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        input:focus, textarea:focus { border-color: #1b306e; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 28px; padding-top: 22px; border-top: 1px solid #edf0f5; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 17px; border: none; border-radius: 7px; background: #1b306e; color: white; cursor: pointer; font-size: 13px; font-weight: bold; }
        .btn-yellow { background: #fdc40f; color: #1b306e; }
        .btn-light { background: #edf1fc; color: #1b306e; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; background: #fff0f0; color: #a52828; border: 1px solid #f2caca; }
        .alert ul { padding-left: 20px; margin-top: 6px; }
        .footer { padding: 24px; text-align: center; color: #9199a9; font-size: 12px; }
        @media (max-width: 900px) {
            .layout { display: block; }
            .sidebar { width: 100%; padding: 16px; }
            .brand { padding-bottom: 15px; }
            .nav-label { margin-top: 15px; }
            .nav-link { display: inline-block; padding: 10px; }
            .topbar { align-items: flex-start; flex-direction: column; padding: 20px; }
            .content { padding: 20px; }
        }
        @media (max-width: 550px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: auto; }
            .form-body { padding: 18px; }
        }
    </style>
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <h2>Attendance System</h2>
            <p>Committee Management</p>
        </div>

        <div class="nav-label">MAIN MENU</div>
        <a href="dashboard.php" class="nav-link">Dashboard</a>

        <div class="nav-label">MEMBERS</div>
        <a href="members.php" class="nav-link">Committee Members</a>
        <a href="qr_generator.php" class="nav-link">QR Code Generator</a>

        <div class="nav-label">ATTENDANCE</div>
        <a href="scanner.php" class="nav-link">QR Scanner</a>
        <a href="attendance.php" class="nav-link">Attendance Records</a>

        <div class="nav-label">SYSTEM</div>
        <a href="logout.php" class="nav-link">Logout</a>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Edit Session</h1>
                <p>Update the details of this attendance session.</p>
            </div>

            <div class="admin-info">
                Administrator: <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Edit Session Details</h2>
                <p>Changes will apply to this session's attendance records.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert" role="alert">
                    <strong>Please correct the following:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-header">
                    <h3>Session Information</h3>
                    <p>Update the fields below.</p>
                </div>

                <form method="POST" action="edit_session.php" class="form-body">

                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                    <div class="form-grid">

                        <div class="form-group full-width">
                            <label for="session_name">Session Name *</label>
                            <input
                                type="text"
                                id="session_name"
                                name="session_name"
                                maxlength="150"
                                value="<?= h($sessionName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="session_date">Session Date *</label>
                            <input
                                type="date"
                                id="session_date"
                                name="session_date"
                                value="<?= h($sessionDate) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                value="<?= h($startTime) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                value="<?= h($endTime) ?>"
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                maxlength="2000"
                            ><?= h($description) ?></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <a href="attendance.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-yellow">Save Changes</button>
                    </div>

                </form>
            </div>

        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> Committee Attendance Management System
        </footer>

    </main>
</div>
</body>
</html>
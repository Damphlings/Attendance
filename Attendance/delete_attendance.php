<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

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
    $_SESSION['flash_message'] = 'Invalid attendance record ID.';
    $_SESSION['flash_type'] = 'error';
    header('Location: attendance.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.time_in,
        a.verification_status,
        m.full_name,
        m.member_code,
        s.session_name,
        s.session_date
    FROM attendance a
    INNER JOIN members m ON m.id = a.member_id
    INNER JOIN attendance_sessions s ON s.id = a.session_id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    $_SESSION['flash_message'] = 'Attendance record not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: attendance.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid form request. Return to the attendance records page and try again.');
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM attendance
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['flash_message'] = 'Attendance record deleted successfully.';
        $_SESSION['flash_type'] = 'success';

        header('Location: attendance.php');
        exit;

    } catch (PDOException $e) {
        error_log('Delete attendance error: ' . $e->getMessage());

        $_SESSION['flash_message'] = 'Unable to delete this attendance record.';
        $_SESSION['flash_type'] = 'error';

        header('Location: attendance.php');
        exit;
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Attendance | Attendance System</title>

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

        .confirm-card { max-width: 650px; padding: 30px; background: white; border: 1px solid #e9edf5; border-radius: 12px; }
        .warning-icon { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; border-radius: 50%; background: #fff0f0; color: #b42318; font-size: 25px; font-weight: bold; }
        .confirm-card h3 { color: #1b306e; font-size: 20px; margin-bottom: 12px; }
        .confirm-card > p { color: #687388; font-size: 14px; line-height: 1.7; }

        .details { margin: 22px 0; padding: 18px; background: #f8f9fc; border: 1px solid #edf0f5; border-radius: 8px; }
        .detail-row { display: flex; justify-content: space-between; gap: 15px; padding: 10px 0; border-bottom: 1px solid #e9edf5; font-size: 13px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #7b8495; }
        .detail-value { color: #263b70; font-weight: bold; text-align: right; }

        .notice { padding: 15px; margin-bottom: 22px; border-radius: 8px; font-size: 13px; line-height: 1.7; background: #fff0f0; border: 1px solid #f2caca; color: #a52828; }

        .actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 12px 17px; border: none; border-radius: 7px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .btn-light { background: #edf1fc; color: #1b306e; }
        .btn-danger { background: #c93636; color: white; }

        .footer { padding: 24px; text-align: center; color: #9199a9; font-size: 12px; }

        @media (max-width: 900px) {
            .layout { display: block; }
            .sidebar { width: 100%; padding: 16px; }
            .brand { padding-bottom: 15px; }
            .nav-label { margin-top: 15px; }
            .nav-link { display: inline-block; padding: 10px; }
            .topbar { align-items: flex-start; flex-direction: column; padding: 20px; }
            .content { padding: 20px; }
            .confirm-card { padding: 22px; }
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
        <a href="attendance.php" class="nav-link active">Attendance Records</a>

        <div class="nav-label">SYSTEM</div>
        <a href="logout.php" class="nav-link">Logout</a>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Delete Attendance</h1>
                <p>Confirm the attendance record you want to remove.</p>
            </div>

            <div class="admin-info">
                Administrator: <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Confirm Deletion</h2>
                <p>This removes the selected record from the attendance history.</p>
            </div>

            <div class="confirm-card">

                <div class="warning-icon">!</div>

                <h3>Delete this attendance record?</h3>

                <p>
                    Please check the details below before continuing.
                    This action cannot be undone.
                </p>

                <div class="details">

                    <div class="detail-row">
                        <span class="detail-label">Member</span>
                        <span class="detail-value"><?= h($record['full_name']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Member Code</span>
                        <span class="detail-value"><?= h($record['member_code']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Session</span>
                        <span class="detail-value"><?= h($record['session_name']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Session Date</span>
                        <span class="detail-value">
                            <?= h(date('M d, Y', strtotime($record['session_date']))) ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Time In</span>
                        <span class="detail-value">
                            <?= !empty($record['time_in'])
                                ? h(date('M d, Y g:i:s A', strtotime($record['time_in'])))
                                : '—' ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Verification</span>
                        <span class="detail-value">
                            <?= h($record['verification_status'] ?? 'Unverified') ?>
                        </span>
                    </div>

                </div>

                <div class="notice">
                    <strong>Warning:</strong>
                    Deleting this record removes the member's attendance entry for this session.
                    It does not delete the member or the session itself.
                </div>

                <form method="POST" action="delete_attendance.php">
                    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                    <div class="actions">
                        <a href="attendance.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-danger">Confirm Deletion</button>
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
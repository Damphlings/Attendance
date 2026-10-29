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
        a.*,
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

$sessions = $pdo->query("
    SELECT id, session_name, session_date
    FROM attendance_sessions
    ORDER BY session_date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

$sessionId = (int) $record['session_id'];
$timeIn = $record['time_in'] ?? '';
$verificationStatus = $record['verification_status'] ?? 'Verified';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    $sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $timeIn = trim($_POST['time_in'] ?? '');
    $verificationStatus = trim($_POST['verification_status'] ?? '');

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid form request. Refresh the page and try again.';
    }

    if (!$sessionId || $sessionId < 1) {
        $errors[] = 'Please select a valid session.';
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_sessions
            WHERE id = :id
        ");
        $stmt->execute([':id' => $sessionId]);

        if ((int) $stmt->fetchColumn() === 0) {
            $errors[] = 'The selected session does not exist.';
        }
    }

    $validDateTime = DateTime::createFromFormat('!Y-m-d\TH:i', $timeIn);

    if (
        !$validDateTime ||
        $validDateTime->format('Y-m-d\TH:i') !== $timeIn
    ) {
        $errors[] = 'Please enter a valid time-in date and time.';
    }

    $allowedStatuses = ['Verified', 'Unverified'];

    if (!in_array($verificationStatus, $allowedStatuses, true)) {
        $errors[] = 'Please select a valid verification status.';
    }

    if (!$errors) {
        try {
            $databaseTime = $validDateTime->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                UPDATE attendance
                SET
                    session_id = :session_id,
                    time_in = :time_in,
                    verification_status = :verification_status
                WHERE id = :id
            ");

            $stmt->execute([
                ':session_id' => $sessionId,
                ':time_in' => $databaseTime,
                ':verification_status' => $verificationStatus,
                ':id' => $id
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['flash_message'] = 'Attendance record updated successfully.';
            $_SESSION['flash_type'] = 'success';

            header('Location: attendance.php');
            exit;

        } catch (PDOException $e) {
            error_log('Edit attendance error: ' . $e->getMessage());

            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                $errors[] = 'This member already has an attendance record for the selected session.';
            } else {
                $errors[] = 'Unable to update the attendance record.';
            }
        }
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';

$timeInForInput = '';

if ($timeIn !== '') {
    $timestamp = strtotime($timeIn);

    if ($timestamp !== false) {
        $timeInForInput = date('Y-m-d\TH:i', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance | Attendance System>

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

        .form-card { max-width: 800px; background: white; border: 1px solid #e9edf5; border-radius: 12px; overflow: hidden; }
        .form-card-header { padding: 22px 25px; border-bottom: 1px solid #edf0f5; }
        .form-card-header h3 { color: #1b306e; font-size: 17px; }
        .form-card-header p { margin-top: 6px; color: #8790a2; font-size: 13px; }
        .form-body { padding: 25px; }

        .member-info { padding: 15px; margin-bottom: 22px; border-radius: 8px; background: #f5f7fc; border: 1px solid #e9edf5; }
        .member-info strong { display: block; color: #1b306e; font-size: 15px; }
        .member-info span { display: block; color: #7b8495; font-size: 12px; margin-top: 5px; }

        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .full-width { grid-column: 1 / -1; }

        label { font-size: 13px; font-weight: bold; color: #4e586b; }
        input, select { width: 100%; padding: 12px; border: 1px solid #dce1eb; border-radius: 7px; background: white; font: inherit; font-size: 13px; }
        input:focus, select:focus { outline: none; border-color: #1b306e; }

        .hint { font-size: 12px; color: #8790a2; line-height: 1.5; }

        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 28px; padding-top: 22px; border-top: 1px solid #edf0f5; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 17px; border: none; border-radius: 7px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .btn-yellow { background: #fdc40f; color: #1b306e; }
        .btn-light { background: #edf1fc; color: #1b306e; }

        .alert { padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-size: 13px; background: #fff0f0; color: #a52828; border: 1px solid #f2caca; }
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
            .full-width { grid-column: auto; }
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
        <a href="attendance.php" class="nav-link active">Attendance Records</a>

        <div class="nav-label">SYSTEM</div>
        <a href="logout.php" class="nav-link">Logout</a>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Edit Attendance</h1>
                <p>Correct an existing attendance record.</p>
            </div>

            <div class="admin-info">
                Administrator: <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Edit Record #<?= (int) $id ?></h2>
                <p>Changes are saved to the configured MySQL database.</p>
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
                    <h3>Attendance Details</h3>
                    <p>The member assigned to this record cannot be changed here.</p>
                </div>

                <form method="POST" action="edit_attendance.php" class="form-body">

                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                    <div class="member-info">
                        <strong><?= h($record['full_name']) ?></strong>
                        <span>Member Code: <?= h($record['member_code']) ?></span>
                    </div>

                    <div class="form-grid">

                        <div class="form-group full-width">
                            <label for="session_id">Attendance Session *</label>
                            <select id="session_id" name="session_id" required>
                                <option value="">Select a session</option>

                                <?php foreach ($sessions as $session): ?>
                                    <option
                                        value="<?= (int) $session['id'] ?>"
                                        <?= (int) $sessionId === (int) $session['id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($session['session_name']) ?>
                                        — <?= h($session['session_date']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <span class="hint">
                                A member can have only one attendance record per session
                                if your database enforces that unique constraint.
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="time_in">Time In *</label>
                            <input
                                type="datetime-local"
                                id="time_in"
                                name="time_in"
                                value="<?= h($timeInForInput) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="verification_status">Verification Status *</label>
                            <select id="verification_status" name="verification_status" required>
                                <option value="Verified" <?= $verificationStatus === 'Verified' ? 'selected' : '' ?>>
                                    Verified
                                </option>
                                <option value="Unverified" <?= $verificationStatus === 'Unverified' ? 'selected' : '' ?>>
                                    Unverified
                                </option>
                            </select>
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
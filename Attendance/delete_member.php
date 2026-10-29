<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

function h($value)
{
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
    $_SESSION['flash_message'] = 'Invalid member ID.';
    $_SESSION['flash_type'] = 'error';

    header('Location: members.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, member_code, full_name, position, status
        FROM members
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['flash_message'] = 'Member not found.';
        $_SESSION['flash_type'] = 'error';

        header('Location: members.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance
        WHERE member_id = :member_id
    ");

    $stmt->execute([':member_id' => $id]);

    $attendanceCount = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Delete member lookup error: ' . $e->getMessage());
    exit('Unable to retrieve member information.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid form request. Please return to the member list and try again.');
    }

    try {
        // Begin a transaction so the history check and action
        // are handled together.
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id
            FROM members
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);

        if (!$stmt->fetchColumn()) {
            $pdo->rollBack();

            $_SESSION['flash_message'] = 'Member no longer exists.';
            $_SESSION['flash_type'] = 'error';

            header('Location: members.php');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM attendance
            WHERE member_id = :member_id
        ");

        $stmt->execute([':member_id' => $id]);

        $attendanceCount = (int) $stmt->fetchColumn();

        if ($attendanceCount > 0) {

            // Preserve historical attendance records.
            $stmt = $pdo->prepare("
                UPDATE members
                SET status = 'Inactive'
                WHERE id = :id
            ");

            $stmt->execute([':id' => $id]);

            $message = 'Member deactivated because attendance history exists.';

        } else {

            // Safe to permanently remove the member if no history exists.
            $stmt = $pdo->prepare("
                DELETE FROM members
                WHERE id = :id
            ");

            $stmt->execute([':id' => $id]);

            $message = 'Member deleted successfully.';
        }

        $pdo->commit();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'success';

        header('Location: members.php');
        exit;

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Delete member error: ' . $e->getMessage());

        $_SESSION['flash_message'] =
            'Unable to delete or deactivate this member. Please check the database constraints.';

        $_SESSION['flash_type'] = 'error';

        header('Location: members.php');
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

    <title>Remove Member | Attendance System</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #263247;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #1b306e;
            color: white;
            padding: 25px 16px;
        }

        .brand {
            padding: 0 12px 30px;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }

        .brand h2 {
            font-size: 19px;
        }

        .brand p {
            margin-top: 6px;
            color: #d3dcf7;
            font-size: 12px;
        }

        .nav-label {
            margin: 25px 12px 10px;
            color: #aebbe0;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .nav-link {
            display: block;
            padding: 13px 14px;
            margin: 5px 0;
            border-radius: 8px;
            color: #e5eaf8;
            font-size: 14px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #304784;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            min-height: 80px;
            padding: 20px 32px;
            background: white;
            border-bottom: 1px solid #e8ebf2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .topbar h1 {
            font-size: 23px;
            color: #1b306e;
        }

        .topbar p {
            margin-top: 5px;
            font-size: 13px;
            color: #7b8495;
        }

        .admin-info {
            font-size: 13px;
        }

        .admin-info strong {
            color: #1b306e;
        }

        .content {
            padding: 30px 32px;
        }

        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading h2 {
            color: #1b306e;
            font-size: 23px;
        }

        .page-heading p {
            margin-top: 8px;
            color: #7b8495;
            font-size: 14px;
        }

        .confirm-card {
            max-width: 650px;
            padding: 30px;
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 12px;
        }

        .warning-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border-radius: 50%;
            background: #fff0f0;
            color: #b42318;
            font-size: 25px;
            font-weight: bold;
        }

        .confirm-card h3 {
            color: #1b306e;
            font-size: 20px;
            margin-bottom: 12px;
        }

        .confirm-card > p {
            color: #687388;
            font-size: 14px;
            line-height: 1.7;
        }

        .member-details {
            margin: 22px 0;
            padding: 18px;
            background: #f8f9fc;
            border: 1px solid #edf0f5;
            border-radius: 8px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #e9edf5;
            font-size: 13px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #7b8495;
        }

        .detail-value {
            color: #263b70;
            font-weight: bold;
            text-align: right;
        }

        .notice {
            padding: 15px;
            margin-bottom: 22px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.7;
        }

        .notice-warning {
            background: #fff7e0;
            border: 1px solid #f5df9a;
            color: #775600;
        }

        .notice-danger {
            background: #fff0f0;
            border: 1px solid #f2caca;
            color: #a52828;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 12px 17px;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-light {
            background: #edf1fc;
            color: #1b306e;
        }

        .btn-danger {
            background: #c93636;
            color: white;
        }

        .btn-danger:hover {
            background: #a92323;
        }

        .footer {
            padding: 24px;
            text-align: center;
            color: #9199a9;
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .brand {
                padding-bottom: 15px;
            }

            .nav-label {
                margin-top: 15px;
            }

            .nav-link {
                display: inline-block;
                padding: 10px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
                padding: 20px;
            }

            .content {
                padding: 20px;
            }

            .confirm-card {
                padding: 22px;
            }
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
        <a href="members.php" class="nav-link active">Committee Members</a>
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
                <h1>Remove Committee Member</h1>
                <p>Review the member record before proceeding.</p>
            </div>

            <div class="admin-info">
                Administrator:
                <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Confirm Removal</h2>
                <p>Check the member details and attendance history.</p>
            </div>

            <div class="confirm-card">

                <div class="warning-icon">!</div>

                <h3>Remove this committee member?</h3>

                <p>
                    Please review the details below before confirming.
                    The system will preserve existing attendance history.
                </p>

                <div class="member-details">

                    <div class="detail-row">
                        <span class="detail-label">Member ID</span>
                        <span class="detail-value">
                            <?= h($member['member_code']) ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value">
                            <?= h($member['full_name']) ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Position</span>
                        <span class="detail-value">
                            <?= h($member['position'] ?: '—') ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Current Status</span>
                        <span class="detail-value">
                            <?= h($member['status']) ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Attendance Records</span>
                        <span class="detail-value">
                            <?= number_format($attendanceCount) ?>
                        </span>
                    </div>

                </div>

                <?php if ($attendanceCount > 0): ?>

                    <div class="notice notice-warning">
                        <strong>Attendance history found.</strong><br>
                        This member will be marked as Inactive rather than
                        permanently deleted. Their existing attendance
                        records will remain in the database.
                    </div>

                <?php else: ?>

                    <div class="notice notice-danger">
                        <strong>No attendance history found.</strong><br>
                        Confirming this action will permanently delete this
                        member's record. This action cannot be undone.
                    </div>

                <?php endif; ?>

                <form method="POST" action="delete_member.php">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $member['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($_SESSION['csrf_token']) ?>"
                    >

                    <div class="actions">

                        <a href="members.php" class="btn btn-light">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-danger">
                            <?= $attendanceCount > 0
                                ? 'Deactivate Member'
                                : 'Delete Member' ?>
                        </button>

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
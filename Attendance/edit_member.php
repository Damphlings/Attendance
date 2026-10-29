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
    $stmt = $pdo->prepare(
        'SELECT * FROM members WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Load member error: ' . $e->getMessage());
    exit('Unable to load member information.');
}

if (!$member) {
    $_SESSION['flash_message'] = 'Member not found.';
    $_SESSION['flash_type'] = 'error';

    header('Location: members.php');
    exit;
}

$errors = [];

$memberCode = $member['member_code'];
$fullName = $member['full_name'];
$position = $member['position'] ?? '';
$email = $member['email'] ?? '';
$status = $member['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    $memberCode = trim($_POST['member_code'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = 'Invalid form request. Refresh the page and try again.';
    }

    if ($memberCode === '' || strlen($memberCode) > 50) {
        $errors[] = 'Enter a valid Member ID (maximum 50 characters).';
    }

    if ($fullName === '' || strlen($fullName) > 150) {
        $errors[] = 'Enter a valid full name (maximum 150 characters).';
    }

    if (strlen($position) > 100) {
        $errors[] = 'Position must not exceed 100 characters.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($email) > 150) {
        $errors[] = 'Email must not exceed 150 characters.';
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $errors[] = 'Invalid member status.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE members
                SET
                    member_code = :member_code,
                    full_name = :full_name,
                    position = :position,
                    email = :email,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ':member_code' => $memberCode,
                ':full_name' => $fullName,
                ':position' => $position !== '' ? $position : null,
                ':email' => $email !== '' ? $email : null,
                ':status' => $status,
                ':id' => $id
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['flash_message'] = 'Member information updated successfully.';
            $_SESSION['flash_type'] = 'success';

            header('Location: members.php');
            exit;

        } catch (PDOException $e) {
            error_log('Update member error: ' . $e->getMessage());

            if (
                stripos($e->getMessage(), 'UNIQUE constraint failed') !== false
            ) {
                $errors[] = 'That Member ID is already assigned to another member.';
            } else {
                $errors[] = 'Unable to update member information.';
            }
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

    <title>Edit Member | Attendance System</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #263247;
        }

        a { color: inherit; text-decoration: none; }

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #1b306e;
            color: #fff;
            padding: 25px 16px;
        }

        .brand {
            padding: 0 12px 30px;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }

        .brand h2 { font-size: 19px; }
        .brand p { margin-top: 6px; font-size: 12px; color: #d3dcf7; }

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

        .nav-link:hover, .nav-link.active { background: #304784; }

        .main-content { flex: 1; min-width: 0; }

        .topbar {
            background: #fff;
            padding: 20px 32px;
            min-height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e8ebf2;
        }

        .topbar h1 { font-size: 23px; color: #1b306e; }
        .topbar p { margin-top: 5px; color: #7b8495; font-size: 13px; }
        .admin-info { font-size: 13px; }
        .admin-info strong { color: #1b306e; }

        .content { padding: 30px 32px; }

        .page-heading { margin-bottom: 24px; }
        .page-heading h2 { color: #1b306e; font-size: 23px; }
        .page-heading p { margin-top: 8px; color: #7b8495; font-size: 14px; }

        .form-card {
            max-width: 850px;
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 12px;
            overflow: hidden;
        }

        .form-card-header {
            padding: 22px 25px;
            border-bottom: 1px solid #edf0f5;
        }

        .form-card-header h3 { color: #1b306e; font-size: 17px; }
        .form-card-header p { margin-top: 6px; color: #8790a2; font-size: 13px; }

        .form-body { padding: 25px; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: 1 / -1; }

        label { font-size: 13px; font-weight: bold; color: #4e586b; }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce1eb;
            border-radius: 7px;
            background: white;
            font: inherit;
            outline: none;
        }

        input:focus, select:focus { border-color: #1b306e; }

        .hint { color: #8790a2; font-size: 12px; line-height: 1.5; }

        .notice {
            margin-bottom: 20px;
            padding: 13px 15px;
            background: #edf1fc;
            color: #1b306e;
            border-radius: 7px;
            font-size: 13px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf0f5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 17px;
            border: none;
            border-radius: 7px;
            background: #1b306e;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-yellow { background: #fdc40f; color: #1b306e; }
        .btn-light { background: #edf1fc; color: #1b306e; }

        .alert {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fff0f0;
            border: 1px solid #f2caca;
            color: #a52828;
            font-size: 13px;
        }

        .alert ul { padding-left: 20px; margin-top: 6px; }
        .footer { padding: 24px; text-align: center; color: #9199a9; font-size: 12px; }

        @media (max-width: 760px) {
            .layout { display: block; }
            .sidebar { width: 100%; padding: 16px; }
            .brand { padding-bottom: 15px; }
            .nav-label { margin-top: 15px; }
            .nav-link { display: inline-block; padding: 10px; }
            .topbar { align-items: flex-start; flex-direction: column; padding: 20px; }
            .content { padding: 20px; }
        }

        @media (max-width: 500px) {
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
                <h1>Edit Committee Member</h1>
                <p>Update registered member information.</p>
            </div>

            <div class="admin-info">
                Administrator:
                <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Edit Member Information</h2>
                <p>Member ID: <?= h($member['member_code']) ?></p>
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
                    <h3>Member Details</h3>
                    <p>Update the fields below and save your changes.</p>
                </div>

                <form method="POST" action="edit_member.php" class="form-body">

                    <input type="hidden" name="id" value="<?= (int) $id ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($_SESSION['csrf_token']) ?>"
                    >

                    <div class="notice">
                        Updating these details will not change the member's
                        existing QR token or attendance history.
                    </div>

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="member_code">Member ID *</label>
                            <input
                                type="text"
                                id="member_code"
                                name="member_code"
                                maxlength="50"
                                value="<?= h($memberCode) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="150"
                                value="<?= h($fullName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="position">Committee Position</label>
                            <input
                                type="text"
                                id="position"
                                name="position"
                                maxlength="100"
                                value="<?= h($position) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                value="<?= h($email) ?>"
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="status">Member Status *</label>

                            <select id="status" name="status" required>
                                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>
                                    Active
                                </option>

                                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>
                                    Inactive
                                </option>
                            </select>

                            <span class="hint">
                                Inactive members should not be allowed to check in.
                            </span>
                        </div>

                    </div>

                    <div class="form-actions">
                        <a href="members.php" class="btn btn-light">Cancel</a>

                        <button type="submit" class="btn btn-yellow">
                            Save Changes
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
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

$errors = [];
$selectedMemberId = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);
$selectedMemberId = $selectedMemberId ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $selectedMemberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = 'Invalid form request. Please refresh the page.';
    }

    if (!$selectedMemberId || $selectedMemberId < 1) {
        $errors[] = 'Please select a valid member.';
    }

    if ($action !== 'generate') {
        $errors[] = 'Invalid action.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, member_code, full_name, status
                FROM members
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $selectedMemberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                $errors[] = 'Member not found.';
            } elseif ($member['status'] !== 'Active') {
                $errors[] = 'Only active members can receive a QR code.';
            } else {
                // Generate an unpredictable token.
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $stmt = $pdo->prepare("
                    UPDATE members
                    SET qr_token_hash = :token_hash
                    WHERE id = :id AND status = 'Active'
                ");

                $stmt->execute([
                    ':token_hash' => $tokenHash,
                    ':id' => $selectedMemberId
                ]);

                if ($stmt->rowCount() !== 1) {
                    $errors[] = 'The QR code could not be generated.';
                } else {
                    // Keep the raw token temporarily in the admin session.
                    if (!isset($_SESSION['qr_plain_tokens'])) {
                        $_SESSION['qr_plain_tokens'] = [];
                    }

                    $_SESSION['qr_plain_tokens'][(string) $selectedMemberId] = [
                        'token' => $rawToken,
                        'created_at' => time()
                    ];

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header(
                        'Location: qr_generator.php?member_id=' .
                        (int) $selectedMemberId
                    );
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('QR generation error: ' . $e->getMessage());
            $errors[] = 'Unable to generate the QR code.';
        }
    }
}

$members = $pdo->query("
    SELECT id, member_code, full_name, position, status, qr_token_hash
    FROM members
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedMember = null;
$rawToken = null;

if ($selectedMemberId) {
    $stmt = $pdo->prepare("
        SELECT id, member_code, full_name, position, status, qr_token_hash
        FROM members
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $selectedMemberId]);
    $selectedMember = $stmt->fetch(PDO::FETCH_ASSOC);

    $savedToken = $_SESSION['qr_plain_tokens'][(string) $selectedMemberId] ?? null;

    // The token is available only during its temporary session lifetime.
    if (
        is_array($savedToken) &&
        isset($savedToken['token'], $savedToken['created_at']) &&
        time() - (int) $savedToken['created_at'] <= 1800 &&
        $selectedMember &&
        !empty($selectedMember['qr_token_hash']) &&
        hash_equals(
            $selectedMember['qr_token_hash'],
            hash('sha256', $savedToken['token'])
        )
    ) {
        $rawToken = $savedToken['token'];
    } elseif (isset($_SESSION['qr_plain_tokens'][(string) $selectedMemberId])) {
        unset($_SESSION['qr_plain_tokens'][(string) $selectedMemberId]);
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Generator | Attendance System</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f4f6fb; color: #263247; }
        a { text-decoration: none; color: inherit; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; flex-shrink: 0; background: #1b306e; color: white; padding: 25px 16px; }
        .brand { padding: 0 12px 30px; border-bottom: 1px solid rgba(255,255,255,.15); }
        .brand h2 { font-size: 19px; }
        .brand p { margin-top: 6px; font-size: 12px; color: #d3dcf7; }
        .nav-label { margin: 25px 12px 10px; color: #aebbe0; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        .nav-link { display: block; padding: 13px 14px; margin: 5px 0; border-radius: 8px; color: #e5eaf8; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: #304784; }
        .main-content { flex: 1; min-width: 0; }
        .topbar { background: white; min-height: 80px; padding: 20px 32px; display: flex; justify-content: space-between; align-items: center; gap: 15px; border-bottom: 1px solid #e8ebf2; }
        .topbar h1 { font-size: 23px; color: #1b306e; }
        .topbar p { margin-top: 5px; font-size: 13px; color: #7b8495; }
        .admin-info { font-size: 13px; }
        .admin-info strong { color: #1b306e; }
        .content { padding: 30px 32px; }
        .page-heading { margin-bottom: 24px; }
        .page-heading h2 { color: #1b306e; font-size: 23px; }
        .page-heading p { margin-top: 8px; color: #7b8495; font-size: 14px; }
        .panel { background: white; border: 1px solid #e9edf5; border-radius: 12px; padding: 25px; margin-bottom: 22px; }
        .panel h3 { color: #1b306e; font-size: 17px; margin-bottom: 18px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
        .form-group { flex: 1; min-width: 220px; }
        label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: bold; color: #4e586b; }
        select { width: 100%; padding: 12px; border: 1px solid #dce1eb; border-radius: 7px; background: white; font: inherit; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 16px; border: none; border-radius: 7px; background: #1b306e; color: white; cursor: pointer; font-size: 13px; font-weight: bold; }
        .btn-yellow { background: #fdc40f; color: #1b306e; }
        .btn-light { background: #edf1fc; color: #1b306e; }
        .btn-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .alert { padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-size: 13px; }
        .alert-error { background: #fff0f0; border: 1px solid #f2caca; color: #a52828; }
        .alert ul { padding-left: 20px; }
        .qr-layout { display: grid; grid-template-columns: minmax(220px, 300px) 1fr; gap: 30px; align-items: start; }
        .qr-card { border: 1px solid #e4e8f1; border-radius: 10px; padding: 24px; text-align: center; }
        #qrcode { display: flex; justify-content: center; min-height: 220px; align-items: center; }
        #qrcode img, #qrcode canvas { max-width: 100%; height: auto; }
        .qr-card h4 { color: #1b306e; margin-top: 18px; font-size: 17px; }
        .qr-card p { color: #7b8495; font-size: 13px; margin-top: 7px; }
        .member-info p { margin-bottom: 12px; font-size: 14px; line-height: 1.5; }
        .member-info strong { color: #1b306e; }
        .token-note { margin-top: 16px; padding: 13px; background: #fff7e0; border-radius: 7px; color: #775600; font-size: 12px; line-height: 1.6; }
        .empty-state { color: #7b8495; font-size: 14px; line-height: 1.7; }
        .footer { padding: 24px; text-align: center; color: #9199a9; font-size: 12px; }
        @media (max-width: 900px) { .qr-layout { grid-template-columns: 1fr; } }
        @media (max-width: 760px) { .layout { display: block; } .sidebar { width: 100%; padding: 16px; } .brand { padding-bottom: 15px; } .nav-label { margin-top: 15px; } .nav-link { display: inline-block; padding: 10px; } .topbar { align-items: flex-start; flex-direction: column; padding: 20px; } .content { padding: 20px; } }
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
        <a href="qr_generator.php" class="nav-link active">QR Code Generator</a>

        <div class="nav-label">ATTENDANCE</div>
        <a href="scanner.php" class="nav-link">QR Scanner</a>
        <a href="attendance.php" class="nav-link">Attendance Records</a>

        <div class="nav-label">SYSTEM</div>
        <a href="logout.php" class="nav-link">Logout</a>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>QR Code Generator</h1>
                <p>Create unique QR codes for committee members.</p>
            </div>
            <div class="admin-info">
                Administrator: <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">
            <div class="page-heading">
                <h2>Generate Member QR Code</h2>
                <p>Choose an active member to generate or regenerate their QR code.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="panel">
                <h3>Select Member</h3>

                <form method="POST" action="qr_generator.php">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="generate">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="member_id">Committee Member</label>
                            <select name="member_id" id="member_id" required>
                                <option value="">Select a member</option>
                                <?php foreach ($members as $item): ?>
                                    <option
                                        value="<?= (int) $item['id'] ?>"
                                        <?= $selectedMemberId === (int) $item['id'] ? 'selected' : '' ?>
                                        <?= $item['status'] !== 'Active' ? 'disabled' : '' ?>
                                    >
                                        <?= h($item['member_code'] . ' — ' . $item['full_name']) ?>
                                        <?= $item['status'] !== 'Active' ? ' (Inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-yellow">
                            Generate / Regenerate QR
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($selectedMember): ?>
                <div class="panel">
                    <h3>QR Code Details</h3>

                    <?php if ($selectedMember['status'] !== 'Active'): ?>
                        <p class="empty-state">This member is inactive. Activate the member before generating a QR code.</p>

                    <?php elseif (!$rawToken): ?>
                        <p class="empty-state">
                            No usable token is available in this session. Generate a new QR code for this member.
                            If you leave this page or the temporary session expires, generate a new token to download or print it.
                        </p>

                    <?php else: ?>
                        <div class="qr-layout">
                            <div class="qr-card">
                                <div id="qrcode"></div>
                                <h4><?= h($selectedMember['full_name']) ?></h4>
                                <p><?= h($selectedMember['member_code']) ?></p>
                                <p><?= h($selectedMember['position'] ?: 'Committee Member') ?></p>
                            </div>

                            <div class="member-info">
                                <p><strong>Member ID:</strong> <?= h($selectedMember['member_code']) ?></p>
                                <p><strong>Name:</strong> <?= h($selectedMember['full_name']) ?></p>
                                <p><strong>Position:</strong> <?= h($selectedMember['position'] ?: 'Not specified') ?></p>
                                <p><strong>Status:</strong> <?= h($selectedMember['status']) ?></p>

                                <div class="token-note">
                                    This QR code contains a private attendance token. Do not share it publicly.
                                    Regenerating the QR code invalidates the previous one.
                                </div>

                                <div class="btn-row">
                                    <form method="POST" action="download_qr.php" id="downloadForm">
                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="member_id" value="<?= (int) $selectedMember['id'] ?>">
                                        <input type="hidden" name="token" value="<?= h($rawToken) ?>">
                                        <button type="submit" class="btn btn-yellow">Download PNG</button>
                                    </form>

                                    <form method="POST" action="print_qr.php" target="_blank">
                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="member_id" value="<?= (int) $selectedMember['id'] ?>">
                                        <input type="hidden" name="token" value="<?= h($rawToken) ?>">
                                        <button type="submit" class="btn">Print QR Card</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const token = <?= json_encode(
                                    $rawToken,
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                ) ?>;

                                const container = document.getElementById('qrcode');

                                if (typeof QRCode === 'undefined') {
                                    container.textContent = 'QR library could not be loaded. Check your internet connection.';
                                    return;
                                }

                                new QRCode(container, {
                                    text: token,
                                    width: 220,
                                    height: 220,
                                    colorDark: '#1b306e',
                                    colorLight: '#ffffff',
                                    correctLevel: QRCode.CorrectLevel.M
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> Committee Attendance Management System
        </footer>
    </main>
</div>

</body>
</html>
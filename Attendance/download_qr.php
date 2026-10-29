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

$submittedToken = $_POST['csrf_token'] ?? '';
$memberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
$rawToken = $_POST['token'] ?? '';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !is_string($submittedToken) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) ||
    !$memberId ||
    !is_string($rawToken) ||
    !preg_match('/^[a-f0-9]{64}$/', $rawToken)
) {
    http_response_code(403);
    exit('Invalid request. Return to the QR generator and try again.');
}

$stmt = $pdo->prepare("
    SELECT id, member_code, full_name, position, status, qr_token_hash
    FROM members
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$member ||
    $member['status'] !== 'Active' ||
    empty($member['qr_token_hash']) ||
    !hash_equals($member['qr_token_hash'], hash('sha256', $rawToken))
) {
    http_response_code(403);
    exit('The QR token is invalid or has been replaced. Generate a new QR code.');
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Member QR Code</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6fb; color: #263247; padding: 30px 15px; }
        .card { max-width: 520px; margin: 0 auto; padding: 30px; text-align: center; background: white; border: 1px solid #e5e9f2; border-radius: 12px; }
        h1 { color: #1b306e; font-size: 23px; margin-bottom: 8px; }
        p { color: #7b8495; font-size: 14px; line-height: 1.6; }
        #qrcode { display: flex; justify-content: center; margin: 25px auto; min-height: 230px; align-items: center; }
        #qrcode img, #qrcode canvas { max-width: 100%; height: auto; }
        .member-name { color: #1b306e; font-size: 18px; font-weight: bold; margin-top: 15px; }
        .member-code { margin-top: 5px; }
        .actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 25px; }
        button, a { border: none; border-radius: 7px; padding: 12px 17px; font-weight: bold; font-size: 13px; cursor: pointer; text-decoration: none; }
        button { background: #fdc40f; color: #1b306e; }
        a { background: #edf1fc; color: #1b306e; }
        .note { margin-top: 20px; font-size: 12px; }
    </style>
</head>
<body>

<div class="card">
    <h1>Download QR Code</h1>
    <p>Save this QR code as a PNG image for your committee member.</p>

    <div id="qrcode"></div>

    <div class="member-name"><?= h($member['full_name']) ?></div>
    <p class="member-code"><?= h($member['member_code']) ?></p>
    <p><?= h($member['position'] ?: 'Committee Member') ?></p>

    <div class="actions">
        <button type="button" id="downloadButton">Download PNG</button>
        <a href="qr_generator.php?member_id=<?= (int) $member['id'] ?>">Back to Generator</a>
    </div>

    <p class="note">Keep this QR code private. Anyone who possesses it may be able to check in as this member.</p>
</div>

<script>
    const token = <?= json_encode(
        $rawToken,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const memberCode = <?= json_encode(
        $member['member_code'],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const qrContainer = document.getElementById('qrcode');

    if (typeof QRCode === 'undefined') {
        qrContainer.textContent = 'QR library failed to load. Check your internet connection.';
        document.getElementById('downloadButton').disabled = true;
    } else {
        new QRCode(qrContainer, {
            text: token,
            width: 260,
            height: 260,
            colorDark: '#1b306e',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    document.getElementById('downloadButton').addEventListener('click', function () {
        const canvas = qrContainer.querySelector('canvas');

        if (!canvas) {
            alert('The QR code is not ready yet. Please wait and try again.');
            return;
        }

        const image = document.createElement('canvas');
        const padding = 24;
        const labelHeight = 70;

        image.width = canvas.width + padding * 2;
        image.height = canvas.height + padding * 2 + labelHeight;

        const ctx = image.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, image.width, image.height);
        ctx.drawImage(canvas, padding, padding);

        ctx.fillStyle = '#1b306e';
        ctx.textAlign = 'center';
        ctx.font = 'bold 18px Arial';
        ctx.fillText(<?= json_encode(
            $member['full_name'],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>, image.width / 2, canvas.height + padding + 28);

        ctx.fillStyle = '#444444';
        ctx.font = '14px Arial';
        ctx.fillText(memberCode, image.width / 2, canvas.height + padding + 52);

        const link = document.createElement('a');
        link.download = 'QR-' + memberCode.replace(/[^a-zA-Z0-9_-]/g, '_') + '.png';
        link.href = image.toDataURL('image/png');
        link.click();
    });
</script>

</body>
</html>
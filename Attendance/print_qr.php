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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Member QR Card</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f1f3f8;
            color: #263247;
            padding: 30px;
        }

        .toolbar {
            text-align: center;
            margin-bottom: 25px;
        }

        .toolbar button, .toolbar a {
            display: inline-block;
            margin: 5px;
            padding: 11px 17px;
            border: none;
            border-radius: 7px;
            background: #1b306e;
            color: white;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar a { background: #fdc40f; color: #1b306e; }

        .qr-card {
            width: 340px;
            margin: 0 auto;
            padding: 25px;
            background: white;
            border: 2px solid #1b306e;
            border-radius: 14px;
            text-align: center;
            break-inside: avoid;
        }

        .school-name {
            color: #1b306e;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-title {
            margin-top: 8px;
            font-size: 19px;
            font-weight: bold;
            color: #1b306e;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            margin: 20px auto;
            min-height: 210px;
            align-items: center;
        }

        #qrcode img, #qrcode canvas {
            max-width: 100%;
            height: auto;
        }

        .member-name {
            margin-top: 12px;
            font-size: 18px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .member-code {
            margin-top: 7px;
            font-size: 14px;
            color: #1b306e;
            font-weight: bold;
        }

        .position {
            margin-top: 5px;
            font-size: 13px;
            color: #697489;
        }

        .instruction {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #dfe4ef;
            font-size: 11px;
            line-height: 1.5;
            color: #697489;
        }

        @media print {
            @page { size: auto; margin: 10mm; }

            body { padding: 0; background: white; }
            .toolbar { display: none; }

            .qr-card {
                margin: 0;
                box-shadow: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print QR Card</button>
    <a href="qr_generator.php?member_id=<?= (int) $member['id'] ?>">Back to Generator</a>
</div>

<main class="qr-card">
    <div class="school-name">Committee Attendance</div>
    <div class="card-title">Member QR Card</div>

    <div id="qrcode"></div>

    <div class="member-name"><?= h($member['full_name']) ?></div>
    <div class="member-code"><?= h($member['member_code']) ?></div>
    <div class="position"><?= h($member['position'] ?: 'Committee Member') ?></div>

    <div class="instruction">
        Present this QR code to the attendance scanner during an authorized session.
        Keep it secure and do not share it with other members.
    </div>
</main>

<script>
    const token = <?= json_encode(
        $rawToken,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const qrContainer = document.getElementById('qrcode');

    if (typeof QRCode === 'undefined') {
        qrContainer.textContent = 'QR library failed to load. Check your internet connection.';
    } else {
        new QRCode(qrContainer, {
            text: token,
            width: 220,
            height: 220,
            colorDark: '#1b306e',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    }
</script>

</body>
</html>
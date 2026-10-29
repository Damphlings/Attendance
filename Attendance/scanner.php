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

date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');
$attendanceSessionTypes = [
    'Daily Attendance',
    'Event Attendance',
    'Committee Attendance'
];

try {
    foreach ($attendanceSessionTypes as $sessionName) {
        $stmt = $pdo->prepare(
            'SELECT id FROM attendance_sessions
             WHERE session_date = :session_date AND session_name = :session_name
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([
            ':session_date' => $today,
            ':session_name' => $sessionName
        ]);

        if (!$stmt->fetchColumn()) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO attendance_sessions (session_name, session_date, description)
                 VALUES (:session_name, :session_date, :description)'
            );
            $insertStmt->execute([
                ':session_name' => $sessionName,
                ':session_date' => $today,
                ':description' => 'Attendance category created automatically for QR check-ins.'
            ]);
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id, session_name, session_date
         FROM attendance_sessions
         WHERE session_date = :session_date
         ORDER BY session_name ASC, id ASC'
    );
    $stmt->execute([':session_date' => $today]);
    $attendanceSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $selectedSessionId = 0;
    foreach ($attendanceSessions as $attendanceSession) {
        if ($attendanceSession['session_name'] === 'Daily Attendance') {
            $selectedSessionId = (int) $attendanceSession['id'];
            break;
        }
    }
    if ($selectedSessionId === 0) {
        $selectedSessionId = (int) ($attendanceSessions[0]['id'] ?? 0);
    }
} catch (PDOException $e) {
    error_log('Prepare attendance sessions error: ' . $e->getMessage());
    $attendanceSessions = [];
    $selectedSessionId = 0;
    $loadError = 'Unable to prepare attendance recording. Check your database setup.';
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>QR Scanner | Attendance System</title>

    <script src="https://unpkg.com/html5-qrcode"></script>

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
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .topbar h1 {
            color: #1b306e;
            font-size: 23px;
        }

        .topbar p {
            margin-top: 5px;
            color: #7b8495;
            font-size: 13px;
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
            line-height: 1.6;
        }

        .scanner-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(260px, .7fr);
            gap: 22px;
            align-items: start;
        }

        .panel {
            padding: 25px;
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        .panel h3 {
            margin-bottom: 18px;
            color: #1b306e;
            font-size: 17px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #4e586b;
            font-size: 13px;
            font-weight: bold;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce1eb;
            border-radius: 7px;
            background: white;
            font: inherit;
        }

        .camera-box {
            width: 100%;
            max-width: 620px;
            min-height: 280px;
            margin: 0 auto;
            overflow: hidden;
            border: 1px solid #e3e8f1;
            border-radius: 10px;
            background: #f8f9fc;
        }

        #reader {
            width: 100%;
        }

        #reader video {
            border-radius: 8px;
        }

        .camera-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 11px 16px;
            border: none;
            border-radius: 7px;
            background: #1b306e;
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-yellow {
            background: #fdc40f;
            color: #1b306e;
        }

        .btn-light {
            background: #edf1fc;
            color: #1b306e;
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: .5;
        }

        .status-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #f0f3f9;
            color: #59657b;
            font-size: 13px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .status-box.success {
            background: #e8f8ee;
            color: #176b39;
            border: 1px solid #bce7ca;
        }

        .status-box.error {
            background: #fff0f0;
            color: #a52828;
            border: 1px solid #f2caca;
        }

        .status-box.warning {
            background: #fff7e0;
            color: #775600;
            border: 1px solid #f5df9a;
        }

        .attendance-details {
            padding: 15px;
            background: #f8f9fc;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.8;
            color: #687388;
        }

        .attendance-details strong {
            color: #1b306e;
        }

        .instructions {
            margin-top: 18px;
            padding-left: 18px;
            color: #687388;
            font-size: 13px;
            line-height: 1.9;
        }

        .footer {
            padding: 24px;
            text-align: center;
            color: #9199a9;
            font-size: 12px;
        }

        @media (max-width: 1000px) {
            .scanner-layout {
                grid-template-columns: 1fr;
            }
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
        <a href="scanner.php" class="nav-link active">QR Scanner</a>
        <a href="attendance.php" class="nav-link">Attendance Records</a>

        <div class="nav-label">SYSTEM</div>
        <a href="logout.php" class="nav-link">Logout</a>

    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>QR Attendance Scanner</h1>
                <p>Scan committee member QR codes to record attendance.</p>
            </div>

            <div class="admin-info">
                Administrator:
                <strong><?= h($adminUsername) ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <h2>Attendance Check-In</h2>
                <p>Start the camera and scan a committee member's generated QR code.</p>
            </div>

            <?php if (!empty($loadError)): ?>

                <div class="panel">
                    <div class="status-box error">
                        <?= h($loadError) ?>
                    </div>
                </div>

            <?php elseif ($selectedSessionId < 1): ?>

                <div class="panel">
                    <h3>Attendance Is Not Ready</h3>

                    <div class="status-box warning">
                        The system could not prepare today's attendance record. Please check the database connection.
                    </div>
                </div>

            <?php else: ?>

                <div class="scanner-layout">

                    <div class="panel">

                        <h3>Scan Member QR Code</h3>

                        <div class="form-group">
                            <label for="sessionSelect">Attendance Type</label>
                            <select id="sessionSelect" name="session_id">
                                <?php foreach ($attendanceSessions as $attendanceSession): ?>
                                    <option
                                        value="<?= (int) $attendanceSession['id'] ?>"
                                        data-date="<?= h($attendanceSession['session_date']) ?>"
                                        <?= (int) $attendanceSession['id'] === $selectedSessionId ? 'selected' : '' ?>
                                    >
                                        <?= h($attendanceSession['session_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="camera-box">
                            <div id="reader"></div>
                        </div>

                        <div class="camera-actions">
                            <button type="button" id="startButton" class="btn btn-yellow">
                                Start Camera
                            </button>

                            <button type="button" id="stopButton" class="btn btn-light" disabled>
                                Stop Camera
                            </button>

                            <button type="button" id="scanAgainButton" class="btn" disabled>
                                Scan Next Member
                            </button>
                        </div>

                        <div id="resultBox" class="status-box" role="status" aria-live="polite">
                            Press Start Camera, allow camera access, then hold a generated member QR code in view.
                        </div>

                    </div>

                    <div class="panel">

                        <h3>Selected Attendance</h3>

                        <div class="attendance-details" id="attendanceDetails">
                            <strong>Session:</strong> Daily Attendance<br>
                            <strong>Date:</strong> <?= h(date('F d, Y', strtotime($today))) ?>
                        </div>

                        <h3 style="margin-top: 25px;">Instructions</h3>

                        <ol class="instructions">
                            <li>Allow browser access to the camera.</li>
                            <li>Position the generated QR code inside the scanner.</li>
                            <li>The verified member's name and attendance result will appear here.</li>
                            <li>Press <strong>Scan Next Member</strong> to continue.</li>
                        </ol>

                        <div class="status-box warning">
                            A successful scan should be confirmed by the server.
                            A QR code being readable does not, by itself, mean attendance
                            has been recorded.
                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> Committee Attendance Management System
        </footer>

    </main>

</div>

<?php if ($selectedSessionId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sessionSelect = document.getElementById('sessionSelect');
    const attendanceDetails = document.getElementById('attendanceDetails');
    const startButton = document.getElementById('startButton');
    const stopButton = document.getElementById('stopButton');
    const scanAgainButton = document.getElementById('scanAgainButton');
    const resultBox = document.getElementById('resultBox');
    const csrfToken = <?= json_encode(
        $csrfToken,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    let scanner = null;
    let scannerRunning = false;
    let processingScan = false;
    let lastScannedToken = '';
    let lastScanTime = 0;

    function showResult(message, type = '') {
        resultBox.className = 'status-box' + (type ? ' ' + type : '');
        resultBox.textContent = message;
    }

    function updateAttendanceDetails() {
        const selectedOption = sessionSelect.options[sessionSelect.selectedIndex];
        const sessionName = selectedOption.textContent.trim();
        const sessionDate = selectedOption.dataset.date;

        attendanceDetails.replaceChildren();

        const sessionLine = document.createElement('div');
        const sessionLabel = document.createElement('strong');
        sessionLabel.textContent = 'Session: ';
        sessionLine.append(sessionLabel, document.createTextNode(sessionName));

        const dateLine = document.createElement('div');
        const dateLabel = document.createElement('strong');
        dateLabel.textContent = 'Date: ';
        dateLine.append(
            dateLabel,
            document.createTextNode(new Date(sessionDate + 'T00:00:00').toLocaleDateString())
        );

        attendanceDetails.append(sessionLine, dateLine);
    }

    sessionSelect.addEventListener('change', async function () {
        if (scannerRunning) {
            await stopScanner();
        }
        scanAgainButton.disabled = true;
        lastScannedToken = '';
        lastScanTime = 0;
        updateAttendanceDetails();
        showResult('Attendance type changed. Press Start Camera to scan.');
    });

    async function stopScanner() {
        if (!scanner || !scannerRunning) {
            return;
        }

        try {
            await scanner.stop();
        } catch (error) {
            console.error('Unable to stop scanner:', error);
        }

        scannerRunning = false;
        startButton.disabled = false;
        stopButton.disabled = true;
    }

    async function submitAttendance(token) {
        if (!/^[a-f0-9]{64}$/.test(token)) {
            showResult('This QR code does not contain a valid attendance token.', 'error');
            return;
        }

        // Prevent accidental duplicate requests from repeated camera frames.
        if (processingScan) {
            return;
        }

        const now = Date.now();

        if (token === lastScannedToken && now - lastScanTime < 4000) {
            return;
        }

        processingScan = true;
        lastScannedToken = token;
        lastScanTime = now;

        showResult('QR code detected. Verifying member and recording attendance...');

        await stopScanner();

        try {
            const response = await fetch('api/record_atendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    qr_token: token,
                    session_id: Number(sessionSelect.value),
                    csrf_token: csrfToken
                })
            });

            const responseText = await response.text();
            let data;

            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(
                    'Attendance server returned an invalid response (HTTP ' +
                    response.status + '). Check the PHP error log.'
                );
            }

            if (!response.ok || data.success !== true) {
                const existingAttendance = data.attendance || {};
                const existingName = existingAttendance.full_name
                    || existingAttendance.member_name
                    || '';
                const attendeeNotice = existingName
                    ? ' Attendee: ' + existingName + '.'
                    : '';

                showResult(
                    (data.message || 'Attendance could not be recorded.') + attendeeNotice,
                    data.duplicate ? 'success' : 'error'
                );
                return;
            }

            const attendee = data.attendance || {};
            const attendeeName = attendee.full_name || attendee.member_name || '';
            const memberCode = attendee.member_code ? ' (' + attendee.member_code + ')' : '';
            const position = attendee.position ? ' — ' + attendee.position : '';
            const attendanceMessage = attendeeName
                ? (data.message || 'Attendance recorded successfully.') + ' Attendee: ' + attendeeName + memberCode + position + '.'
                : (data.message || 'Attendance recorded successfully.');

            showResult(attendanceMessage, 'success');

        } catch (error) {
            console.error('Attendance request failed:', error);

            showResult(
                error.message || 'Could not contact the attendance server. Check the API endpoint and try again.',
                'error'
            );

        } finally {
            processingScan = false;
            scanAgainButton.disabled = false;
        }
    }

    startButton.addEventListener('click', async function () {
        if (typeof Html5Qrcode === 'undefined') {
            showResult(
                'The QR scanner library could not load. Check your internet connection.',
                'error'
            );
            return;
        }

        if (scannerRunning) {
            return;
        }

        try {
            scanner = scanner || new Html5Qrcode('reader');

            showResult('Starting camera...');

            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                async function (decodedText) {
                    await submitAttendance(decodedText);
                },
                function () {
                    // Ignore normal frames in which no QR code is detected.
                }
            );

            scannerRunning = true;
            startButton.disabled = true;
            stopButton.disabled = false;
            scanAgainButton.disabled = true;

            showResult('Camera is running. Position a member QR code inside the scanning area.');

        } catch (error) {
            console.error('Camera start error:', error);

            showResult(
                'Unable to start the camera. Check camera permissions and ensure the browser supports camera access.',
                'error'
            );
        }
    });

    stopButton.addEventListener('click', async function () {
        await stopScanner();
        showResult('Camera stopped. Press Start Camera when you are ready.');
    });

    scanAgainButton.addEventListener('click', async function () {
        if (processingScan) {
            return;
        }

        lastScannedToken = '';
        lastScanTime = 0;
        scanAgainButton.disabled = true;

        await startButton.click();
    });

    updateAttendanceDetails();
});
</script>
<?php endif; ?>

</body>
</html>
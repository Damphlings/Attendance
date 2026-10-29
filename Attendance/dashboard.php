<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

// Selected date; defaults to today.
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Validate the date to prevent invalid filter values.
$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = date('Y-m-d');
}

$totalMembers = 0;
$presentToday = 0;
$notYetPresent = 0;
$totalRecords = 0;
$recentAttendance = [];
$databaseError = false;

try {
    // Total active committee members.
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM members
        WHERE status = 'Active'
    ");

    $totalMembers = (int) $stmt->fetchColumn();

    // Count distinct members who attended on the selected date.
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.member_id)
        FROM attendance a
        INNER JOIN attendance_sessions s
            ON s.id = a.session_id
        INNER JOIN members m
            ON m.id = a.member_id
        WHERE s.session_date = :selected_date
          AND m.status = 'Active'
    ");

    $stmt->execute([
        ':selected_date' => $selectedDate
    ]);

    $presentToday = (int) $stmt->fetchColumn();

    // Active members who have not attended any session on this date.
    $notYetPresent = max(0, $totalMembers - $presentToday);

    // Total attendance records for the selected date.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance a
        INNER JOIN attendance_sessions s
            ON s.id = a.session_id
        WHERE s.session_date = :selected_date
    ");

    $stmt->execute([
        ':selected_date' => $selectedDate
    ]);

    $totalRecords = (int) $stmt->fetchColumn();

    // Most recent attendance records for the selected date.
    $stmt = $pdo->prepare("
        SELECT
            a.id AS attendance_id,
            m.member_code,
            m.full_name,
            m.position,
            s.session_name,
            a.time_in,
            a.verification_status
        FROM attendance a
        INNER JOIN members m
            ON m.id = a.member_id
        INNER JOIN attendance_sessions s
            ON s.id = a.session_id
        WHERE s.session_date = :selected_date
        ORDER BY a.time_in DESC, a.id DESC
        LIMIT 10
    ");

    $stmt->execute([
        ':selected_date' => $selectedDate
    ]);

    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard database error: ' . $e->getMessage());
    $databaseError = true;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTime($value)
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTime($value))->format('h:i A');
    } catch (Exception $e) {
        return $value;
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Attendance Management System</title>

    <link rel="stylesheet" href="assets/css/dashboard.css">

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
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

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

        .brand h2 {
            font-size: 19px;
            line-height: 1.5;
        }

        .brand p {
            margin-top: 5px;
            font-size: 12px;
            color: #d3dcf7;
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
            transition: .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #304784;
            color: #fff;
        }

        .nav-link.active {
            border-left: 4px solid #fdc40f;
            padding-left: 10px;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            background: #fff;
            min-height: 80px;
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #e8ebf2;
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
            color: #4b5568;
        }

        .admin-info strong {
            color: #1b306e;
        }

        .content {
            padding: 30px 32px;
        }

        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .welcome h2 {
            font-size: 23px;
            color: #1b306e;
        }

        .welcome p {
            margin-top: 8px;
            color: #7b8495;
            font-size: 14px;
        }

        .date-filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .date-filter label {
            font-size: 13px;
            font-weight: bold;
        }

        .date-filter input {
            padding: 10px;
            border: 1px solid #dce1eb;
            border-radius: 7px;
            background: #fff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border: none;
            border-radius: 7px;
            background: #1b306e;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .btn:hover {
            background: #263f88;
        }

        .btn-yellow {
            background: #fdc40f;
            color: #1b306e;
        }

        .btn-yellow:hover {
            background: #e9b300;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #edf0f6;
            box-shadow: 0 3px 12px rgba(27,48,110,.035);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }

        .stat-title {
            color: #788297;
            font-size: 13px;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #edf1fc;
            color: #1b306e;
            font-size: 18px;
        }

        .stat-card h3 {
            color: #1b306e;
            font-size: 30px;
            margin-bottom: 7px;
        }

        .stat-card small {
            font-size: 12px;
            color: #929bad;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .action-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 10px;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: .2s;
        }

        .action-card:hover {
            border-color: #b6c3e7;
            transform: translateY(-2px);
        }

        .action-card strong {
            display: block;
            color: #1b306e;
            font-size: 14px;
        }

        .action-card span {
            display: block;
            color: #8790a2;
            font-size: 12px;
            margin-top: 6px;
        }

        .action-arrow {
            color: #1b306e;
            font-size: 22px;
        }

        .table-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            padding: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid #edf0f5;
        }

        .table-header h3 {
            color: #1b306e;
            font-size: 17px;
        }

        .table-header p {
            margin-top: 5px;
            color: #8a93a4;
            font-size: 12px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            white-space: nowrap;
        }

        thead {
            background: #f8f9fc;
        }

        th {
            color: #758096;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: bold;
        }

        th,
        td {
            padding: 16px 20px;
            border-bottom: 1px solid #edf0f5;
        }

        td {
            color: #4e586b;
            font-size: 13px;
        }

        tbody tr:hover {
            background: #fafbfe;
        }

        .member-name {
            color: #263b70;
            font-weight: bold;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e6f7ed;
            color: #198754;
            font-size: 11px;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: #8a93a4;
        }

        .empty-state strong {
            display: block;
            color: #536078;
            margin-bottom: 8px;
        }

        .alert {
            background: #fff3f3;
            border: 1px solid #f2caca;
            color: #a52828;
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 13px;
        }

        .footer {
            padding: 24px 32px;
            color: #9199a9;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-actions {
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
                margin-right: 3px;
                padding: 10px;
            }

            .topbar,
            .content {
                padding: 20px;
            }

            .welcome {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 25px;
            }

            .table-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <h2>Attendance System</h2>
            <p>Committee Management</p>
        </div>

        <div class="nav-label">MAIN MENU</div>

        <a href="dashboard.php" class="nav-link active">
            Dashboard
        </a>

        <div class="nav-label">MEMBERS</div>

        <a href="members.php" class="nav-link">
            Committee Members
        </a>

        <a href="qr_generator.php" class="nav-link">
            QR Code Generator
        </a>

        <div class="nav-label">ATTENDANCE</div>

        <a href="scanner.php" class="nav-link">
            QR Scanner
        </a>

        <a href="attendance.php" class="nav-link">
            Attendance Records
        </a>

        <div class="nav-label">SYSTEM</div>

        <a
            href="logout.php"
            class="nav-link"
            onclick="return confirm('Are you sure you want to log out?');"
        >
            Logout
        </a>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Dashboard</h1>
                <p>Committee Attendance Management System</p>
            </div>

            <div class="admin-info">
                Administrator:
                <strong><?= h($adminUsername) ?></strong>
            </div>

        </header>

        <section class="content">

            <div class="welcome">

                <div>
                    <h2>Welcome back!</h2>

                    <p>
                        Here's your attendance overview for
                        <?= h(date('F d, Y', strtotime($selectedDate))) ?>.
                    </p>
                </div>

                <form method="GET" action="dashboard.php" class="date-filter">

                    <label for="date">Attendance Date</label>

                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?= h($selectedDate) ?>"
                        required
                    >

                    <button type="submit" class="btn">
                        Filter
                    </button>

                    <a href="dashboard.php" class="btn btn-yellow">
                        Today
                    </a>

                </form>

            </div>

            <?php if ($databaseError): ?>

                <div class="alert" role="alert">
                    Unable to load dashboard information.
                    Please check your MySQL database and confirm that
                    the required tables have been initialized.
                </div>

            <?php else: ?>

                <!-- STATISTICS -->
                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-title">Total Members</span>
                            <div class="stat-icon">♙</div>
                        </div>

                        <h3><?= number_format($totalMembers) ?></h3>

                        <small>Active committee members</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-title">Present</span>
                            <div class="stat-icon">✓</div>
                        </div>

                        <h3><?= number_format($presentToday) ?></h3>

                        <small>Members checked in on this date</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-title">Not Yet Present</span>
                            <div class="stat-icon">◷</div>
                        </div>

                        <h3><?= number_format($notYetPresent) ?></h3>

                        <small>Active members without a check-in</small>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-title">Attendance Records</span>
                            <div class="stat-icon">▤</div>
                        </div>

                        <h3><?= number_format($totalRecords) ?></h3>

                        <small>Total check-ins on this date</small>
                    </div>

                </div>

                <!-- QUICK ACTIONS -->
                <div class="quick-actions">

                    <a href="scanner.php" class="action-card">
                        <div>
                            <strong>Scan QR Code</strong>
                            <span>Verify members and record attendance</span>
                        </div>

                        <div class="action-arrow">›</div>
                    </a>

                    <a href="members.php" class="action-card">
                        <div>
                            <strong>Manage Members</strong>
                            <span>Register and update committee members</span>
                        </div>

                        <div class="action-arrow">›</div>
                    </a>

                </div>

                <!-- RECENT ATTENDANCE TABLE -->
                <div class="table-card">

                    <div class="table-header">

                        <div>
                            <h3>Attendance Records</h3>

                            <p>
                                Check-ins for
                                <?= h(date('F d, Y', strtotime($selectedDate))) ?>
                            </p>
                        </div>

                        <a
                            href="export_excel.php?date=<?= urlencode($selectedDate) ?>"
                            class="btn btn-yellow"
                        >
                            Export to Excel
                        </a>

                    </div>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Member ID</th>
                                    <th>Member Name</th>
                                    <th>Position</th>
                                    <th>Session</th>
                                    <th>Time In</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (empty($recentAttendance)): ?>

                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <strong>No attendance records found.</strong>
                                            No members have checked in on the
                                            selected date.
                                        </div>
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($recentAttendance as $record): ?>

                                    <tr>
                                        <td>
                                            <?= h($record['member_code']) ?>
                                        </td>

                                        <td class="member-name">
                                            <?= h($record['full_name']) ?>
                                        </td>

                                        <td>
                                            <?= h($record['position'] ?: '—') ?>
                                        </td>

                                        <td>
                                            <?= h($record['session_name']) ?>
                                        </td>

                                        <td>
                                            <?= h(formatTime($record['time_in'])) ?>
                                        </td>

                                        <td>
                                            <span class="status">
                                                <?= h($record['verification_status']) ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

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
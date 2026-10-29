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

$search = trim($_GET['search'] ?? '');
$sessionFilter = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

if (!$sessionFilter || $sessionFilter < 1) {
    $sessionFilter = null;
}

$validDate = static function ($date) {
    if ($date === '') {
        return true;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};

$errors = [];

if (!$validDate($dateFrom)) {
    $errors[] = 'The start date is invalid.';
    $dateFrom = '';
}

if (!$validDate($dateTo)) {
    $errors[] = 'The end date is invalid.';
    $dateTo = '';
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $errors[] = 'The start date cannot be later than the end date.';
}

$orderOptions = [
    'newest' => 'a.time_in DESC, a.id DESC',
    'oldest' => 'a.time_in ASC, a.id ASC'
];

$orderBy = $orderOptions[$sort] ?? $orderOptions['newest'];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.full_name LIKE :search_name OR m.member_code LIKE :search_code OR s.session_name LIKE :search_session)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_code'] = '%' . $search . '%';
    $params[':search_session'] = '%' . $search . '%';
}

if ($sessionFilter !== null) {
    $where[] = 'a.session_id = :session_id';
    $params[':session_id'] = $sessionFilter;
}

if ($dateFrom !== '') {
    $where[] = 's.session_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 's.session_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        a.id,
        a.member_id,
        a.session_id,
        a.time_in,
        a.verification_status,
        m.member_code,
        m.full_name,
        m.position,
        s.session_name,
        s.session_date,
        s.start_time,
        s.end_time
    FROM attendance a
    INNER JOIN members m ON m.id = a.member_id
    INNER JOIN attendance_sessions s ON s.id = a.session_id
    $whereSql
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecords = count($records);
$verifiedRecords = 0;

foreach ($records as $record) {
    if (strtolower((string) ($record['verification_status'] ?? '')) === 'verified') {
        $verifiedRecords++;
    }
}

$unverifiedRecords = $totalRecords - $verifiedRecords;

$sessions = $pdo->query("
    SELECT id, session_name, session_date
    FROM attendance_sessions
    ORDER BY session_date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';

$exportQuery = http_build_query([
    'search' => $search,
    'session_id' => $sessionFilter ?? '',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records | Attendance System</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #263247;
        }

        a { text-decoration: none; color: inherit; }

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #1b306e;
            color: white;
            padding: 25px 16px;
        }

        .brand { padding: 0 12px 30px; border-bottom: 1px solid rgba(255,255,255,.15); }
        .brand h2 { font-size: 19px; }
        .brand p { margin-top: 6px; color: #d3dcf7; font-size: 12px; }

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
            background: white;
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

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-heading h2 { color: #1b306e; font-size: 23px; }
        .page-heading p { margin-top: 7px; color: #7b8495; font-size: 14px; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-primary { background: #1b306e; color: white; }
        .btn-yellow { background: #fdc40f; color: #1b306e; }
        .btn-light { background: #edf1fc; color: #1b306e; }
        .btn-danger { background: #fff0f0; color: #b42318; }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 11px;
            padding: 22px;
        }

        .stat-card p { color: #7b8495; font-size: 13px; }
        .stat-card h3 { color: #1b306e; font-size: 27px; margin-top: 12px; }

        .table-card {
            background: white;
            border: 1px solid #e9edf5;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #edf0f5;
        }

        .table-header h3 { color: #1b306e; font-size: 16px; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        input, select {
            padding: 10px 11px;
            border: 1px solid #dce1eb;
            border-radius: 7px;
            background: white;
            font: inherit;
            font-size: 12px;
        }

        .filter-panel {
            padding: 18px 20px;
            background: #fbfcfe;
            border-bottom: 1px solid #edf0f5;
        }

        .filter-panel form {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
        }

        .filter-group { display: flex; flex-direction: column; gap: 7px; }
        .filter-group label { color: #667085; font-size: 11px; font-weight: bold; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }

        th {
            background: #f8f9fc;
            color: #758097;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 15px 16px;
        }

        td {
            padding: 15px 16px;
            border-bottom: 1px solid #edf0f5;
            font-size: 12px;
        }

        tbody tr:hover { background: #fafbfe; }
        .member-name { font-weight: bold; color: #1b306e; }
        .muted { margin-top: 5px; color: #8992a2; font-size: 11px; }

        .badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-verified { background: #e9f7ef; color: #20764b; }
        .badge-other { background: #fff5ce; color: #806000; }

        .actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .actions .btn { padding: 7px 9px; }

        .empty { padding: 40px 20px; text-align: center; color: #8992a2; }
        .empty strong { display: block; color: #1b306e; margin-bottom: 8px; }

        .alert { padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-size: 13px; }
        .alert-success { background: #e9f7ef; color: #20764b; }
        .alert-error { background: #fff0f0; color: #a52828; }

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

        @media (max-width: 600px) {
            .stats { grid-template-columns: 1fr; }
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
                <h1>Attendance Records</h1>
                <p>Review, filter, correct, and export attendance.</p>
            </div>

            <div class="admin-info">
                Administrator: <strong><?= h($_SESSION['admin_username'] ?? 'Administrator') ?></strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <div>
                    <h2>Attendance Management</h2>
                    <p>Search by member or session, or narrow records by date.</p>
                </div>

                <a href="export_excel.php?<?= h($exportQuery) ?>" class="btn btn-yellow">
                    Export to Excel
                </a>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert <?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>">
                    <?= h($flashMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?= h($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat-card">
                    <p>Matching Records</p>
                    <h3><?= number_format($totalRecords) ?></h3>
                </div>

                <div class="stat-card">
                    <p>Verified Records</p>
                    <h3><?= number_format($verifiedRecords) ?></h3>
                </div>

                <div class="stat-card">
                    <p>Other Statuses</p>
                    <h3><?= number_format($unverifiedRecords) ?></h3>
                </div>
            </div>

            <div class="table-card">

                <div class="table-header">
                    <h3>Attendance History</h3>

                    <form method="GET" action="attendance.php" class="filters">
                        <input
                            type="search"
                            name="search"
                            placeholder="Search member or session"
                            value="<?= h($search) ?>"
                        >

                        <select name="sort" aria-label="Sort records">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                        </select>

                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="filter-panel">
                    <form method="GET" action="attendance.php">

                        <input type="hidden" name="search" value="<?= h($search) ?>">
                        <input type="hidden" name="sort" value="<?= h($sort) ?>">

                        <div class="filter-group">
                            <label for="session_id">Session</label>
                            <select id="session_id" name="session_id">
                                <option value="">All sessions</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option
                                        value="<?= (int) $session['id'] ?>"
                                        <?= $sessionFilter === (int) $session['id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($session['session_name']) ?>
                                        (<?= h($session['session_date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_from">Date From</label>
                            <input
                                type="date"
                                id="date_from"
                                name="date_from"
                                value="<?= h($dateFrom) ?>"
                            >
                        </div>

                        <div class="filter-group">
                            <label for="date_to">Date To</label>
                            <input
                                type="date"
                                id="date_to"
                                name="date_to"
                                value="<?= h($dateTo) ?>"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="attendance.php" class="btn btn-light">Clear Filters</a>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Session</th>
                                <th>Session Date</th>
                                <th>Time In</th>
                                <th>Verification</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty">
                                        <strong>No attendance records found</strong>
                                        Try changing your search terms or filters.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($records as $record): ?>
                                <?php
                                $status = trim((string) ($record['verification_status'] ?? 'Unverified'));
                                $isVerified = strtolower($status) === 'verified';
                                ?>

                                <tr>
                                    <td>
                                        <div class="member-name"><?= h($record['full_name']) ?></div>
                                        <div class="muted">
                                            <?= h($record['member_code']) ?>
                                            <?php if (!empty($record['position'])): ?>
                                                · <?= h($record['position']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td><?= h($record['session_name']) ?></td>

                                    <td>
                                        <?= h(date('M d, Y', strtotime($record['session_date']))) ?>
                                    </td>

                                    <td>
                                        <?= !empty($record['time_in'])
                                            ? h(date('M d, Y g:i:s A', strtotime($record['time_in'])))
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $isVerified ? 'badge-verified' : 'badge-other' ?>">
                                            <?= h($status !== '' ? $status : 'Unverified') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <a
                                                class="btn btn-light"
                                                href="edit_attendance.php?id=<?= (int) $record['id'] ?>"
                                            >Edit</a>

                                            <a
                                                class="btn btn-danger"
                                                href="delete_attendance.php?id=<?= (int) $record['id'] ?>"
                                            >Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> Committee Attendance Management System
        </footer>

    </main>
</div>
</body>
</html>
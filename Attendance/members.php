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

// Search and filter parameters.
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';

$allowedStatuses = ['All', 'Active', 'Inactive'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

$members = [];
$totalMembers = 0;
$activeMembers = 0;
$inactiveMembers = 0;
$databaseError = false;

try {
    // Dashboard counts.
    $totalMembers = (int) $pdo->query(
        "SELECT COUNT(*) FROM members"
    )->fetchColumn();

    $activeMembers = (int) $pdo->query(
        "SELECT COUNT(*) FROM members WHERE status = 'Active'"
    )->fetchColumn();

    $inactiveMembers = (int) $pdo->query(
        "SELECT COUNT(*) FROM members WHERE status = 'Inactive'"
    )->fetchColumn();

    // Build the member query using prepared statements.
    $sql = "
        SELECT
            id,
            member_code,
            full_name,
            position,
            email,
            status,
            qr_token_hash,
            created_at
        FROM members
        WHERE (
            member_code LIKE :search_code
            OR full_name LIKE :search_name
            OR position LIKE :search_position
        )
    ";

    $params = [
        ':search_code' => '%' . $search . '%',
        ':search_name' => '%' . $search . '%',
        ':search_position' => '%' . $search . '%'
    ];

    if ($statusFilter !== 'All') {
        $sql .= " AND status = :status";
        $params[':status'] = $statusFilter;
    }

    $sql .= " ORDER BY full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Members page database error: ' . $e->getMessage());
    $databaseError = true;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Committee Members | Attendance System</title>

    <link rel="stylesheet" href="assets/css/members.css">

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
        input,
        select {
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

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
        }

        .page-heading h2 {
            font-size: 23px;
            color: #1b306e;
        }

        .page-heading p {
            margin-top: 7px;
            color: #7b8495;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 11px 15px;
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

        .btn-light {
            background: #edf1fc;
            color: #1b306e;
        }

        .btn-light:hover {
            background: #dfe6fa;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 22px;
            background: #fff;
            border: 1px solid #edf0f6;
            border-radius: 12px;
        }

        .stat-card p {
            font-size: 13px;
            color: #7b8495;
            margin-bottom: 12px;
        }

        .stat-card h3 {
            font-size: 29px;
            color: #1b306e;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 10px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            font-size: 12px;
            color: #5c667a;
            font-weight: bold;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #dce1eb;
            border-radius: 7px;
            background: #fff;
            outline: none;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #1b306e;
        }

        .table-card {
            background: #fff;
            border: 1px solid #e9edf5;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        th,
        td {
            padding: 16px 18px;
            border-bottom: 1px solid #edf0f5;
        }

        td {
            font-size: 13px;
            color: #4e586b;
        }

        tbody tr:hover {
            background: #fafbfe;
        }

        .member-name {
            color: #263b70;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-active {
            background: #e6f7ed;
            color: #198754;
        }

        .badge-inactive {
            background: #fce8e8;
            color: #b42318;
        }

        .badge-qr {
            background: #eaf0ff;
            color: #1b306e;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-link {
            display: inline-block;
            padding: 7px 9px;
            border-radius: 6px;
            background: #edf1fc;
            color: #1b306e;
            font-size: 12px;
            font-weight: bold;
        }

        .action-link:hover {
            background: #dfe6fa;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: #8a93a4;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 8px;
            color: #536078;
        }

        .alert {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fff3f3;
            border: 1px solid #f2caca;
            color: #a52828;
            font-size: 13px;
        }

        .footer {
            padding: 24px 32px;
            text-align: center;
            font-size: 12px;
            color: #9199a9;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                align-items: flex-start;
                flex-direction: column;
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
        <a
            href="logout.php"
            class="nav-link"
            onclick="return confirm('Are you sure you want to log out?');"
        >Logout</a>

    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Committee Members</h1>
                <p>Manage your committee member records and QR codes.</p>
            </div>

            <div class="admin-info">
                Administrator:
                <strong>
                    <?= h($_SESSION['admin_username'] ?? 'Administrator') ?>
                </strong>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <div>
                    <h2>Member Management</h2>
                    <p>Register, search, and manage committee members.</p>
                </div>

                <a href="add_member.php" class="btn btn-yellow">
                    + Add Member
                </a>
            </div>

            <?php if ($databaseError): ?>

                <div class="alert" role="alert">
                    Unable to load committee members. Check your MySQL
                    connection and database table structure.
                </div>

            <?php else: ?>

                <div class="stats-grid">

                    <div class="stat-card">
                        <p>Total Members</p>
                        <h3><?= number_format($totalMembers) ?></h3>
                    </div>

                    <div class="stat-card">
                        <p>Active Members</p>
                        <h3><?= number_format($activeMembers) ?></h3>
                    </div>

                    <div class="stat-card">
                        <p>Inactive Members</p>
                        <h3><?= number_format($inactiveMembers) ?></h3>
                    </div>

                </div>

                <form method="GET" action="members.php" class="filters">

                    <div class="filter-group">
                        <label for="search">Search Members</label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            placeholder="Search ID, name, or position..."
                            value="<?= h($search) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="status">Member Status</label>

                        <select id="status" name="status">
                            <option
                                value="All"
                                <?= $statusFilter === 'All' ? 'selected' : '' ?>
                            >
                                All Members
                            </option>

                            <option
                                value="Active"
                                <?= $statusFilter === 'Active' ? 'selected' : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>
                            >
                                Inactive
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn">
                        Search
                    </button>

                    <a href="members.php" class="btn btn-light">
                        Reset
                    </a>

                </form>

                <div class="table-card">

                    <div class="table-header">
                        <div>
                            <h3>Registered Members</h3>

                            <p>
                                <?= count($members) ?>
                                member(s) found
                            </p>
                        </div>

                        <a href="qr_generator.php" class="btn btn-yellow">
                            QR Code Generator
                        </a>
                    </div>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Member ID</th>
                                    <th>Full Name</th>
                                    <th>Position</th>
                                    <th>Email</th>
                                    <th>QR Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (empty($members)): ?>

                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <strong>No members found.</strong>

                                            Try another search or add a new
                                            committee member.
                                        </div>
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($members as $member): ?>

                                    <tr>
                                        <td>
                                            <?= h($member['member_code']) ?>
                                        </td>

                                        <td class="member-name">
                                            <?= h($member['full_name']) ?>
                                        </td>

                                        <td>
                                            <?= h($member['position'] ?: '—') ?>
                                        </td>

                                        <td>
                                            <?= h($member['email'] ?: '—') ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($member['qr_token_hash'])): ?>

                                                <span class="badge badge-qr">
                                                    Generated
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-inactive">
                                                    Not Generated
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($member['status'] === 'Active'): ?>

                                                <span class="badge badge-active">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-inactive">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="actions">

                                                <a
                                                    href="edit_member.php?id=<?= (int) $member['id'] ?>"
                                                    class="action-link"
                                                >
                                                    Edit
                                                </a>

                                                <a
                                                    href="qr_generator.php?member_id=<?= (int) $member['id'] ?>"
                                                    class="action-link"
                                                >
                                                    QR Code
                                                </a>

                                            </div>
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
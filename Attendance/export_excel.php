<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

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

if (
    !$validDate($dateFrom) ||
    !$validDate($dateTo) ||
    ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo)
) {
    http_response_code(400);
    exit('Invalid date filters. Return to Attendance Records and try again.');
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
        m.member_code,
        m.full_name,
        m.position,
        s.session_name,
        s.session_date,
        a.time_in,
        a.verification_status
    FROM attendance a
    INNER JOIN members m ON m.id = a.member_id
    INNER JOIN attendance_sessions s ON s.id = a.session_id
    $whereSql
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'attendance_records_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

if ($output === false) {
    http_response_code(500);
    exit('Unable to create the export file.');
}

// UTF-8 BOM helps Excel display Unicode names correctly.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Record ID',
    'Member Code',
    'Full Name',
    'Position',
    'Session Name',
    'Session Date',
    'Time In',
    'Verification Status'
]);

/*
 * Prefix user-controlled text beginning with spreadsheet formula
 * characters so Excel does not interpret it as a formula.
 */
$safeCsvText = static function ($value) {
    $value = (string) ($value ?? '');

    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
};

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $timeIn = '';

    if (!empty($row['time_in'])) {
        $timestamp = strtotime($row['time_in']);
        $timeIn = $timestamp !== false
            ? date('Y-m-d H:i:s', $timestamp)
            : $row['time_in'];
    }

    fputcsv($output, [
        (int) $row['id'],
        $safeCsvText($row['member_code']),
        $safeCsvText($row['full_name']),
        $safeCsvText($row['position']),
        $safeCsvText($row['session_name']),
        $safeCsvText($row['session_date']),
        $safeCsvText($timeIn),
        $safeCsvText($row['verification_status'])
    ]);
}

fclose($output);
exit;
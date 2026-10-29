<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function getAttendanceResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    getAttendanceResponse(405, [
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ]);
}

if (empty($_SESSION['admin_id'])) {
    getAttendanceResponse(401, [
        'success' => false,
        'message' => 'Please log in again.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Read and Validate Filters
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$limit = filter_var(
    $_GET['limit'] ?? 100,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 500]]
);

if ($limit === false) {
    $limit = 100;
}

$offset = filter_var(
    $_GET['offset'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
);

if ($offset === false) {
    $offset = 0;
}

if ($sessionId !== '') {
    $validatedSessionId = filter_var(
        $sessionId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedSessionId === false) {
        getAttendanceResponse(400, [
            'success' => false,
            'message' => 'Invalid session ID.'
        ]);
    }

    $sessionId = (string) $validatedSessionId;
}

$datePattern = '/^\d{4}-\d{2}-\d{2}$/';

foreach (['date_from' => $dateFrom, 'date_to' => $dateTo] as $field => $date) {
    if ($date !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);

        if (
            !preg_match($datePattern, $date)
            || !$parsedDate
            || $parsedDate->format('Y-m-d') !== $date
        ) {
            getAttendanceResponse(400, [
                'success' => false,
                'message' => 'Invalid date filter: ' . $field . '. Use YYYY-MM-DD.'
            ]);
        }
    }
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    getAttendanceResponse(400, [
        'success' => false,
        'message' => 'The start date cannot be later than the end date.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Build the Filtered Query
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(
        m.full_name LIKE :search_name
        OR m.member_code LIKE :search_code
        OR s.session_name LIKE :search_session
    )';

    $searchValue = '%' . $search . '%';

    $params[':search_name'] = $searchValue;
    $params[':search_code'] = $searchValue;
    $params[':search_session'] = $searchValue;
}

if ($sessionId !== '') {
    $where[] = 'a.session_id = :session_id';
    $params[':session_id'] = (int) $sessionId;
}

if ($dateFrom !== '') {
    $where[] = 's.session_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 's.session_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

try {
    $countSql = "
        SELECT COUNT(*)
        FROM attendance a
        INNER JOIN members m ON m.id = a.member_id
        INNER JOIN attendance_sessions s ON s.id = a.session_id
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $totalRecords = (int) $countStmt->fetchColumn();

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
        ORDER BY a.time_in DESC, a.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $records = $stmt->fetchAll();

    foreach ($records as &$record) {
        $record['id'] = (int) $record['id'];
        $record['member_id'] = (int) $record['member_id'];
        $record['session_id'] = (int) $record['session_id'];
    }
    unset($record);

    getAttendanceResponse(200, [
        'success' => true,
        'total' => $totalRecords,
        'limit' => $limit,
        'offset' => $offset,
        'records' => $records
    ]);

} catch (PDOException $e) {
    error_log('Get attendance error: ' . $e->getMessage());

    getAttendanceResponse(500, [
        'success' => false,
        'message' => 'Unable to retrieve attendance records.'
    ]);
}
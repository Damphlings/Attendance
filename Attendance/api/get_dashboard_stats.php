<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function dashboardStatsResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    dashboardStatsResponse(405, [
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ]);
}

if (empty($_SESSION['admin_id'])) {
    dashboardStatsResponse(401, [
        'success' => false,
        'message' => 'Please log in again.'
    ]);
}

try {
    $today = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Total Active Members
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM members
         WHERE LOWER(status) = 'active'"
    );

    $activeMembers = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Total Members
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM members'
    );

    $totalMembers = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Total Attendance Records
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM attendance'
    );

    $totalAttendance = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Attendance Records for Today
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM attendance a
         INNER JOIN attendance_sessions s ON s.id = a.session_id
         WHERE s.session_date = :today'
    );

    $stmt->execute([
        ':today' => $today
    ]);

    $todayAttendance = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Verified Attendance for Today
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM attendance a
         INNER JOIN attendance_sessions s ON s.id = a.session_id
         WHERE s.session_date = :today
           AND LOWER(a.verification_status) = 'verified'"
    );

    $stmt->execute([
        ':today' => $today
    ]);

    $todayVerified = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Sessions Scheduled Today
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM attendance_sessions
         WHERE session_date = :today'
    );

    $stmt->execute([
        ':today' => $today
    ]);

    $todaySessions = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Recent Attendance Records
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        'SELECT
            a.id,
            a.time_in,
            a.verification_status,
            m.member_code,
            m.full_name,
            m.position,
            s.session_name,
            s.session_date
         FROM attendance a
         INNER JOIN members m ON m.id = a.member_id
         INNER JOIN attendance_sessions s ON s.id = a.session_id
         ORDER BY a.time_in DESC, a.id DESC
         LIMIT 5'
    );

    $recentAttendance = $stmt->fetchAll();

    foreach ($recentAttendance as &$record) {
        $record['id'] = (int) $record['id'];
    }
    unset($record);

    /*
    |--------------------------------------------------------------------------
    | Attendance Rate for Today
    |--------------------------------------------------------------------------
    | Uses active members as the denominator.
    |--------------------------------------------------------------------------
    */

    $attendanceRate = $activeMembers > 0
        ? round(($todayAttendance / $activeMembers) * 100, 2)
        : 0;

    dashboardStatsResponse(200, [
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'today' => $today,
        'stats' => [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'total_attendance' => $totalAttendance,
            'today_attendance' => $todayAttendance,
            'today_verified' => $todayVerified,
            'today_sessions' => $todaySessions,
            'today_attendance_rate' => $attendanceRate
        ],
        'recent_attendance' => $recentAttendance
    ]);

} catch (PDOException $e) {
    error_log('Dashboard statistics error: ' . $e->getMessage());

    dashboardStatsResponse(500, [
        'success' => false,
        'message' => 'Unable to retrieve dashboard statistics.'
    ]);
}
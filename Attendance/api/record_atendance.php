<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function recordAttendanceResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    recordAttendanceResponse(405, [
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
}

if (empty($_SESSION['admin_id'])) {
    recordAttendanceResponse(401, [
        'success' => false,
        'message' => 'Please log in again.'
    ]);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody ?: '', true);

    if (!is_array($input)) {
        recordAttendanceResponse(400, [
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
    }
} else {
    $input = $_POST;
}

// CSRF validation.
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? ($input['csrf_token'] ?? '');

$sessionCsrfToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($csrfToken)
    || !is_string($sessionCsrfToken)
    || $csrfToken === ''
    || $sessionCsrfToken === ''
    || !hash_equals($sessionCsrfToken, $csrfToken)
) {
    recordAttendanceResponse(403, [
        'success' => false,
        'message' => 'Security token validation failed.'
    ]);
}

$qrToken = $input['qr_token'] ?? '';
$sessionId = filter_var(
    $input['session_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (
    !is_string($qrToken)
    || !preg_match('/^[a-fA-F0-9]{64}$/', trim($qrToken))
) {
    recordAttendanceResponse(400, [
        'success' => false,
        'message' => 'A valid QR token is required.'
    ]);
}

if ($sessionId === false || $sessionId === null) {
    recordAttendanceResponse(400, [
        'success' => false,
        'message' => 'Please select a valid attendance session.'
    ]);
}

$tokenHash = hash('sha256', trim($qrToken));

try {
    $pdo->beginTransaction();

    // Verify that the QR token belongs to an active member.
    $memberStmt = $pdo->prepare(
        'SELECT id, member_code, full_name, position, status
         FROM members
         WHERE qr_token_hash = :token_hash
         LIMIT 1'
    );

    $memberStmt->execute([
        ':token_hash' => $tokenHash
    ]);

    $member = $memberStmt->fetch();

    if (!$member) {
        $pdo->rollBack();

        recordAttendanceResponse(404, [
            'success' => false,
            'message' => 'Member QR code not recognized.'
        ]);
    }

    if (strcasecmp((string) $member['status'], 'Active') !== 0) {
        $pdo->rollBack();

        recordAttendanceResponse(403, [
            'success' => false,
            'message' => 'Inactive members cannot be checked in.'
        ]);
    }

    // Confirm that the selected session exists.
    $sessionStmt = $pdo->prepare(
        'SELECT id, session_name, session_date, start_time, end_time
         FROM attendance_sessions
         WHERE id = :session_id
         LIMIT 1'
    );

    $sessionStmt->execute([
        ':session_id' => $sessionId
    ]);

    $attendanceSession = $sessionStmt->fetch();

    if (!$attendanceSession) {
        $pdo->rollBack();

        recordAttendanceResponse(404, [
            'success' => false,
            'message' => 'The selected attendance session was not found.'
        ]);
    }

    // Prevent duplicate attendance for the same member and session.
    $duplicateStmt = $pdo->prepare(
        'SELECT id, time_in
         FROM attendance
         WHERE member_id = :member_id
           AND session_id = :session_id
         LIMIT 1'
    );

    $duplicateStmt->execute([
        ':member_id' => (int) $member['id'],
        ':session_id' => (int) $attendanceSession['id']
    ]);

    $existingAttendance = $duplicateStmt->fetch();

    if ($existingAttendance) {
        $pdo->rollBack();

        recordAttendanceResponse(409, [
            'success' => false,
            'duplicate' => true,
            'message' => 'This member has already been recorded for this session.',
            'attendance' => [
                'id' => (int) $existingAttendance['id'],
                'time_in' => $existingAttendance['time_in'],
                'member_name' => $member['full_name'],
                'session_name' => $attendanceSession['session_name']
            ]
        ]);
    }

    $timeIn = date('Y-m-d H:i:s');

    $insertStmt = $pdo->prepare(
        'INSERT INTO attendance
            (member_id, session_id, time_in, verification_status)
         VALUES
            (:member_id, :session_id, :time_in, :verification_status)'
    );

    $insertStmt->execute([
        ':member_id' => (int) $member['id'],
        ':session_id' => (int) $attendanceSession['id'],
        ':time_in' => $timeIn,
        ':verification_status' => 'Verified'
    ]);

    $attendanceId = (int) $pdo->lastInsertId();

    $pdo->commit();

    recordAttendanceResponse(201, [
        'success' => true,
        'message' => 'Attendance recorded successfully.',
        'attendance' => [
            'id' => $attendanceId,
            'member_id' => (int) $member['id'],
            'member_code' => $member['member_code'],
            'full_name' => $member['full_name'],
            'position' => $member['position'],
            'session_id' => (int) $attendanceSession['id'],
            'session_name' => $attendanceSession['session_name'],
            'session_date' => $attendanceSession['session_date'],
            'time_in' => $timeIn,
            'verification_status' => 'Verified'
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Also handle duplicate entries enforced by a database UNIQUE constraint.
    if (
        str_contains(strtolower($e->getMessage()), 'unique constraint')
        || str_contains(strtolower($e->getMessage()), 'unique failed')
    ) {
        recordAttendanceResponse(409, [
            'success' => false,
            'duplicate' => true,
            'message' => 'Attendance has already been recorded for this member and session.'
        ]);
    }

    error_log('Record attendance error: ' . $e->getMessage());

    recordAttendanceResponse(500, [
        'success' => false,
        'message' => 'Unable to record attendance. Please try again.'
    ]);
}
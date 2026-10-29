<?php
require_once __DIR__ . '/config/app.php';

/*
|--------------------------------------------------------------------------
| Attendance Management System
|--------------------------------------------------------------------------
| Entry point for the application.
| Redirects authenticated users to the dashboard.
| Redirects unauthenticated users to the login page.
|--------------------------------------------------------------------------
*/

// Check whether the administrator is logged in.
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

// If no administrator session exists, redirect to login.
header('Location: ' . APP_URL . '/login.php');
exit;
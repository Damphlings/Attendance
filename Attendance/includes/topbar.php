<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Dashboard';

$adminName = $_SESSION['admin_username'] ?? 'Administrator';

$currentDate = date('l, F j, Y');
?>

<header class="topbar">
    <div class="topbar-left">
        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Toggle navigation"
            aria-controls="sidebar"
            aria-expanded="false"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="topbar-heading">
            <h1><?= e($pageTitle) ?></h1>
            <p>Committee Attendance Management System</p>
        </div>
    </div>

    <div class="topbar-right">
        <div class="topbar-date">
            <span class="date-icon" aria-hidden="true">▦</span>
            <span><?= e($currentDate) ?></span>
        </div>

        <div class="topbar-profile">
            <div class="profile-avatar">
                <?= e(strtoupper(substr((string) $adminName, 0, 1))) ?>
            </div>

            <div class="profile-details">
                <strong><?= e($adminName) ?></strong>
                <span>Administrator</span>
            </div>
        </div>
    </div>
</header>

<main class="page-content">
    <?php display_flash_message(); ?>
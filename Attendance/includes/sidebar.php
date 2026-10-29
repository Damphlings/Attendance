<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$currentPage = basename(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''
);

$adminName = $_SESSION['admin_username'] ?? 'Administrator';

$navigation = [
    [
        'label' => 'Dashboard',
        'icon' => '▦',
        'url' => 'dashboard.php',
        'pages' => ['dashboard.php']
    ],
    [
        'label' => 'Members',
        'icon' => '♙',
        'url' => 'members.php',
        'pages' => [
            'members.php',
            'add_member.php',
            'edit_member.php'
        ]
    ],
    [
        'label' => 'QR Generator',
        'icon' => '▧',
        'url' => 'qr_generator.php',
        'pages' => [
            'qr_generator.php',
            'download_qr.php',
            'print_qr.php'
        ]
    ],
    [
        'label' => 'QR Scanner',
        'icon' => '▣',
        'url' => 'scanner.php',
        'pages' => ['scanner.php']
    ],
    [
        'label' => 'Attendance Records',
        'icon' => '☷',
        'url' => 'attendance.php',
        'pages' => [
            'attendance.php',
            'edit_attendance.php'
        ]
    ]
];
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <?php if (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
                <img
                    src="<?= e(APP_URL) ?>/assets/images/logo.png"
                    alt="System Logo"
                >
            <?php else: ?>
                <span>CA</span>
            <?php endif; ?>
        </div>

        <div class="sidebar-brand-text">
            <h2>Attendance</h2>
            <span>Management System</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= e(strtoupper(substr((string) $adminName, 0, 1))) ?>
        </div>

        <div class="sidebar-user-info">
            <strong><?= e($adminName) ?></strong>
            <span>Administrator</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        <span class="nav-section-label">MAIN MENU</span>

        <?php foreach ($navigation as $item): ?>
            <?php
            $active = in_array($currentPage, $item['pages'], true);
            ?>

            <a
                href="<?= e(APP_URL . '/' . $item['url']) ?>"
                class="nav-link <?= $active ? 'active' : '' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>
            >
                <span class="nav-icon" aria-hidden="true">
                    <?= e($item['icon']) ?>
                </span>

                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">
        <div class="sidebar-version">
            Version <?= e(APP_VERSION) ?>
        </div>

        <a href="<?= e(APP_URL) ?>/logout.php" class="nav-link logout-link">
            <span class="nav-icon" aria-hidden="true">↪</span>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<div class="main-area">
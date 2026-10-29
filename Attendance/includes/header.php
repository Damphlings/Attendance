<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$extraCss = $extraCss ?? [];

$cssFiles = [
    'assets/css/style.css',
    'assets/css/dashboard.css',
    'assets/css/members.css',
    'assets/css/scanner.css',
    'assets/css/attendance.css'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <meta name="description" content="Committee Attendance Management System">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= e(APP_URL) ?>/assets/css/style.css"
    >

    <?php foreach (array_slice($cssFiles, 1) as $cssFile): ?>
        <link
            rel="stylesheet"
            href="<?= e(APP_URL . '/' . $cssFile) ?>"
        >
    <?php endforeach; ?>

    <?php foreach ($extraCss as $cssFile): ?>
        <link
            rel="stylesheet"
            href="<?= e(APP_URL . '/' . ltrim($cssFile, '/')) ?>"
        >
    <?php endforeach; ?>
</head>
<body>
<div class="app-layout">
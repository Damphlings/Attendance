<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MySQL Database Configuration
|--------------------------------------------------------------------------
| Create a database in phpMyAdmin and set the credentials below.
| Default database name: attendance
|--------------------------------------------------------------------------
*/

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'attendance';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ]);
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to connect to the MySQL database. Create the database in phpMyAdmin and verify the DB credentials in config/db.php.');
}
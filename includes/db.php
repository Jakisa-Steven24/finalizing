<?php
require_once __DIR__ . '/../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $exception) {
    error_log('[MIU SIMS] Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    if (defined('APP_DEBUG') && APP_DEBUG) {
        exit('<h1>Database connection failed</h1><pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
    exit('Database connection failed. Check the database settings and confirm that database.sql has been imported.');
}

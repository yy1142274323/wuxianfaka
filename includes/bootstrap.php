<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (!app_installed()) {
    redirect('install.php');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection is not initialized.');
}

$settings = load_settings($pdo);

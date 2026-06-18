<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'your_database';
const DB_USER = 'your_user';
const DB_PASS = 'your_password';

const ADMIN_USER = 'admin';
const ADMIN_PASS_HASH = '$2y$10$replace_this_with_password_hash';
const ADMIN_SAFE_CODE_HASH = '$2y$10$replace_this_with_safe_code_hash';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

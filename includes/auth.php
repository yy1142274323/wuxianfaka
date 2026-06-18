<?php
declare(strict_types=1);

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('admin_login.php');
    }
}

function login_admin(string $username, string $password, string $safeCode): bool
{
    $validUser = defined('ADMIN_USER') && hash_equals((string)ADMIN_USER, $username);
    $validPass = defined('ADMIN_PASS_HASH') && password_verify($password, (string)ADMIN_PASS_HASH);
    $validCode = defined('ADMIN_SAFE_CODE_HASH') && password_verify($safeCode, (string)ADMIN_SAFE_CODE_HASH);

    if (!$validUser || !$validPass || !$validCode) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $username;
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

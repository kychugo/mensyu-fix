<?php
/**
 * Session 初始化與管理
 */

require_once __DIR__ . '/config.php';

// 設定 session cookie 安全屬性
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
// Secure flag：僅在 HTTPS 連線時啟用（保護 session cookie）
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 生成或取得 CSRF Token
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證 CSRF Token
 */
function verifyCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 確認用戶已登入，否則導向登入頁
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * 確認用戶為管理員，否則導向首頁
 */
function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * 確認用戶未登入（用於登入/注冊頁）
 */
function requireGuest(): void
{
    if (!empty($_SESSION['user_id'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin/index.php');
        } else {
            header('Location: ' . BASE_URL . '/dashboard.php');
        }
        exit;
    }
}

/**
 * 取得目前登入用戶 ID（未登入返回 null）
 */
function getCurrentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * 確認目前用戶是否為管理員
 */
function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * 確認目前用戶是否已登入
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

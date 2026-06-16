<?php
// 延长会话有效期（365天）
$lifetime = 86400 * 365;
ini_set('session.cookie_lifetime', $lifetime);
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params($lifetime);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 自动登录：如果未登录但存在 remember_token Cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $pdo = getDB();
    // 确保 remember_token 字段存在（首次自动创建）
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(64) NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // 忽略错误（字段已存在或无法修改）
    }

    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE remember_token = ? AND remember_token IS NOT NULL LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 自动登录
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // 可选：刷新令牌以增加安全性
        $newToken = generateRememberToken();
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$newToken, $user['id']]);
        setRememberCookie($newToken, 365);
    } else {
        // 无效令牌，清除 Cookie
        clearRememberCookie();
    }
}
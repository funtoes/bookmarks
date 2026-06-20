<?php
// 延长会话有效期（365天）
$lifetime = 86400 * 365; // 一年（秒）
ini_set('session.cookie_lifetime', $lifetime);
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params($lifetime, '/', null, false, true);

// 为每个用户创建专属的 Session 存储目录，防止共享主机上被其他应用误删
$sessionDir = sys_get_temp_dir() . '/sessions_' . substr(md5(__DIR__), 0, 8);
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
session_save_path($sessionDir);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 自动登录：如果未登录但存在 remember_token Cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $pdo = getDB();
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // 刷新 token
        $newToken = generateRememberToken();
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$newToken, $user['id']]);
        setRememberCookie($newToken, 365);
    } else {
        clearRememberCookie();
    }
}

// ========== 新增：验证已登录用户的真实性 ==========
if (isLoggedIn()) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    if (!$stmt->fetch()) {
        // 用户已被删除（例如数据库重置），强制清除登录状态
        session_destroy();
        clearRememberCookie();
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
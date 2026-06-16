<?php
require_once __DIR__ . '/init.php';

// 清除数据库中的令牌
if (isLoggedIn()) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([currentUserId()]);
}

// 清除 Cookie 和 Session
clearRememberCookie();
session_destroy();
setFlash('success', '您已成功退出。');
header('Location: ' . BASE_URL . '/login.php');
exit;
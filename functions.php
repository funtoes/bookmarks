<?php
/**
 * 检查用户是否已登录
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * 获取当前登录用户ID，未登录返回null
 */
function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * 要求登录，未登录则跳转到登录页
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * 设置提示消息（存储在session中，显示后清除）
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * 获取并清除提示消息
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * 生成安全的URL slug（用于分类拖拽排序等）
 */
function safeOutput(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 生成安全的记住我令牌
 */
function generateRememberToken(): string {
    return bin2hex(random_bytes(32)); // 64字符
}

/**
 * 设置“记住我”Cookie
 */
function setRememberCookie(string $token, int $days = 365): void {
    $expire = time() + 86400 * $days;
    setcookie('remember_token', $token, [
        'expires'  => $expire,
        'path'     => '/',
        'httponly' => true,   // 防止 JS 读取
        'samesite' => 'Lax',  // 防止 CSRF
        // 如果网站使用 HTTPS，请取消下面一行的注释
        'secure'   => true,
    ]);
}

/**
 * 清除“记住我”Cookie
 */
function clearRememberCookie(): void {
    if (isset($_COOKIE['remember_token'])) {
        unset($_COOKIE['remember_token']);
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
<?php
require_once __DIR__ . '/init.php';
$pdo = getDB();   // 确保数据库连接可用

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 插入默认值（如果不存在）
$pdo->exec("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('registration_open', '1')");

// 检查注册开关
$stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'registration_open'");
$stmt->execute();
$regOpen = $stmt->fetchColumn();
if ($regOpen === '0') {
    http_response_code(403);
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head><meta charset="UTF-8"><title>注册已关闭</title>
    <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="auth-container">
        <h1>注册已关闭</h1>
        <div class="alert alert-error">管理员已关闭新用户注册功能。如有需要请联系管理员。</div>
        <p><a href="login.php">返回登录</a></p>
    </div>
    </body></html>';
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $password === '') {
        $error = '请填写所有必填字段。';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度应在3-20个字符之间。';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6个字符。';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致。';
    } else {
        $pdo = getDB();
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '该用户名已被注册。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hash]);
            $success = '注册成功！请 <a href="login.php">登录</a>。';
			// 插入默认分类
			$newUserId = $pdo->lastInsertId();
			$pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, '未分类', 0)")->execute([$newUserId]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 书签管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-container">
    <h1>书签管理</h1>
    <h2>用户注册</h2>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= safeOutput($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php else: ?>
        <form method="post" action="register.php">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password2">确认密码</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn">注册</button>
        </form>
    <?php endif; ?>
    <p class="auth-link">已有账号？<a href="login.php">去登录</a></p>
</div>
</body>
</html>
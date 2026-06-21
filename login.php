<?php
require_once __DIR__ . '/init.php';

// 如果已登录，直接跳转到首页
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码。';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // 处理“记住我”
            $pdo = getDB();
            if (isset($_POST['remember']) && $_POST['remember'] === '1') {
                $token = generateRememberToken();
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
                setRememberCookie($token, 365);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                clearRememberCookie();
            }

            setFlash('success', '登录成功，欢迎回来！');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = '用户名或密码错误。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 书签管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>📑 书签管理</h1>
            <p>欢迎回来，请登录你的账号</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= safeOutput($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="请输入用户名">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="请输入密码">
            </div>
            <div class="form-group remember-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span class="checkmark"></span>
                    记住我
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">登录</button>
        </form>

        <div class="auth-footer">
            <?php if (getSetting('registration_open', '1') === '1'): ?>
			<p class="auth-link">还没有账号？<a href="register.php">立即注册</a></p>
			<?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
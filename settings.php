<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取当前用户信息
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$message = '';
$messageType = '';

// 处理默认视图修改
if (isset($_POST['action']) && $_POST['action'] === 'update_view') {
    $view = $_POST['default_view'] ?? 'card';
    if (in_array($view, ['card', 'table'])) {
		$pdo->prepare("UPDATE users SET default_view = ? WHERE id = ?")->execute([$view, $userId]);
		// 清除临时视图，让首页立即生效
		unset($_SESSION['temp_view']);
		setFlash('success', '默认视图已更新。');
		header('Location: settings.php');
		exit;
	} else {
		setFlash('error', '无效的视图类型。');
		header('Location: settings.php');
		exit;
}
}

// 处理密码修改
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($oldPassword, $user['password'])) {
        $message = '当前密码错误。';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = '新密码至少6个字符。';
        $messageType = 'error';
    } elseif ($newPassword !== $confirm) {
        $message = '两次输入的新密码不一致。';
        $messageType = 'error';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        $message = '密码修改成功！';
        $messageType = 'success';
    }
}

// 处理导出
if (isset($_POST['export'])) {
    header('Location: export.php');
    exit;
}

// 处理导入（文件上传交给 import.php）

// 确保默认值存在
$stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('registration_open', '1')");
$stmt->execute();

// 处理注册开关更新
if (isset($_POST['action']) && $_POST['action'] === 'toggle_registration') {
    $newState = $_POST['registration_open'] === '0' ? '0' : '1';
    $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE `key` = 'registration_open'");
    $stmt->execute([$newState]);
    setFlash('success', '注册功能已' . ($newState === '1' ? '开启' : '关闭') . '。');
    header('Location: settings.php');
    exit;
}

// 读取当前注册开关状态
$stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'registration_open'");
$stmt->execute();
$regOpen = $stmt->fetchColumn() === '1';

// API 密钥管理
if (isset($_POST['action']) && $_POST['action'] === 'generate_api_key') {
    $newKey = bin2hex(random_bytes(32)); // 64字符随机串
    $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->execute([$newKey, $userId]);
    setFlash('success', 'API 密钥已生成/重置，请妥善保存。');
    header('Location: settings.php');
    exit;
}
// 统计信息
$stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ?");
$stmt->execute([$userId]);
$categoryCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE user_id = ?");
$stmt->execute([$userId]);
$bookmarkCount = $stmt->fetchColumn();

$registrationDate = date('Y-m-d H:i', strtotime($user['created_at']));

// 处理页脚设置更新
if (isset($_POST['action']) && $_POST['action'] === 'update_footer') {
    $icp = trim($_POST['icp'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $github = trim($_POST['github'] ?? '');

    // 更新或插入
    $settings = ['icp' => $icp, 'contact' => $contact, 'github' => $github];
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    setFlash('success', '页脚信息已更新。');
    header('Location: settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设置</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="index.php" class="logo">📑 书签管理</a>
            <form class="search-form" method="get" action="" onsubmit="return false;">
                <select class="search-engine">
                    <option value="site" selected>本站</option>
                    <option value="memo">备忘录</option>
                    <option value="baidu">百度</option>
                    <option value="google">谷歌</option>
                    <option value="bing">必应</option>
                    <option value="sogou">搜狗</option>
                    <option value="so360">360</option>
                    <option value="duckduckgo">DDGo</option>
                    <option value="yandex">Yandex</option>
					<option value="yaru">Ya.ru</option>
                </select>
                <input type="text" name="search" class="search-input" placeholder="搜索书签...">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
        <div class="header-actions">
            <a href="add.php" class="btn-add">+ 添加书签</a>
            <a href="memos.php" class="btn-icon" title="备忘录">📝</a>
            <a href="categories.php" class="btn-icon" title="分类管理">📁</a>
            <a href="settings.php" class="btn-icon" title="设置">⚙️</a>
            <a href="logout.php" class="btn-icon" title="退出">🚪</a>
        </div>
    </div>
</header>

<div class="content-container settings-container">
	<?php $flash = getFlash(); if ($flash): ?>
	<div class="flash-message flash-<?= $flash['type'] ?>"><?= safeOutput($flash['message']) ?></div>
	<?php endif; ?>

    <?php if ($message): ?>
    <div class="flash-message flash-<?= $messageType ?>"><?= safeOutput($message) ?></div>
    <?php endif; ?>

	<!-- 我的信息 -->
    <div class="setting-card">
        <h3>我的信息</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">用户名</span>
                <span class="info-value"><?= safeOutput($user['username']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">注册时间</span>
                <span class="info-value"><?= safeOutput($registrationDate) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">分类数目</span>
                <span class="info-value"><?= $categoryCount ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">书签数目</span>
                <span class="info-value"><?= $bookmarkCount ?></span>
            </div>
        </div>
    </div>
	
    <!-- 默认视图 -->
    <div class="setting-card">
        <h3>默认书签视图</h3>
        <form method="post">
            <input type="hidden" name="action" value="update_view">
            <label><input type="radio" name="default_view" value="card" <?= ($user['default_view'] ?? 'card') === 'card' ? 'checked' : '' ?>> 卡片视图</label>
            <label><input type="radio" name="default_view" value="table" <?= ($user['default_view'] ?? '') === 'table' ? 'checked' : '' ?>> 表格视图</label>
            <button type="submit" class="btn btn-sm">保存</button>
        </form>
    </div>

    <!-- 修改密码 -->
    <div class="setting-card">
        <h3>修改密码</h3>
        <form method="post">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="old_password" required>
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">更新密码</button>
        </form>
    </div>

    <!-- 导入导出 -->
    <div class="setting-card">
        <h3>书签数据管理</h3>
        <div class="io-buttons">
            <form method="post" style="display:inline">
                <button type="submit" name="export" class="btn">导出书签（HTML）</button>
            </form>
            <form method="post" action="import.php" enctype="multipart/form-data" style="display:inline">
                <label class="btn btn-outline">
                    导入书签文件
                    <input type="file" name="bookmark_file" accept=".html,.htm" required style="display:none" onchange="this.form.submit()">
                </label>
            </form>
        </div>
        <p class="hint">支持从浏览器导出的书签HTML文件。</p>
    </div>
	
	<!-- 注册控制 -->
	<div class="setting-card">
		<h3>注册控制</h3>
		<p>当前状态：<strong><?= $regOpen ? '开启' : '关闭' ?></strong></p>
		<?php if (isAdmin()): ?>
			<form method="post">
				<input type="hidden" name="action" value="toggle_registration">
				<button type="submit" name="registration_open" value="<?= $regOpen ? '0' : '1' ?>" class="btn <?= $regOpen ? 'btn-danger' : 'btn-primary' ?>">
					<?= $regOpen ? '关闭注册' : '开启注册' ?>
				</button>
			</form>
			<p class="hint">关闭后，新用户将无法注册。</p>
		<?php else: ?>
			<div class="alert alert-info">⚠️ 只有管理员才能修改注册状态。</div>
		<?php endif; ?>
	</div>
	
	<!-- API 密钥 -->
	<div class="setting-card">
    <h3>API 密钥（用于浏览器扩展）</h3>
    <?php
    $stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentKey = $stmt->fetchColumn();
    ?>
    <?php if ($currentKey): ?>
        <p>你的 API 密钥：<code><?= safeOutput($currentKey) ?></code></p>
        <p class="hint">将此密钥填入 Chrome 扩展设置中，即可在不登录网站时添加书签。</p>
        <form method="post" onsubmit="return confirm('重置后旧密钥将失效，扩展也需要更新密钥，确定重置吗？')">
            <input type="hidden" name="action" value="generate_api_key">
            <button type="submit" class="btn btn-warning btn-sm">重置密钥</button>
        </form>
    <?php else: ?>
        <p>尚未生成 API 密钥。</p>
        <form method="post">
            <input type="hidden" name="action" value="generate_api_key">
            <button type="submit" class="btn btn-primary btn-sm">生成密钥</button>
        </form>
    <?php endif; ?>
	</div>
	<div class="setting-card">
    <h3>页脚设置</h3>
    <form method="post">
        <input type="hidden" name="action" value="update_footer">
        <div class="form-group">
            <label>备案号</label>
            <input type="text" name="icp" placeholder="例如：粤ICP备XXXXXX号" value="<?= safeOutput(getSetting('icp')) ?>">
        </div>
        <div class="form-group">
            <label>联系方式（邮箱）</label>
            <input type="email" name="contact" placeholder="例如：admin@example.com" value="<?= safeOutput(getSetting('contact')) ?>">
        </div>
        <div class="form-group">
            <label>博客</label>
            <input type="url" name="github" placeholder="例如：https://github.com/funtoes/bookmarks" value="<?= safeOutput(getSetting('github')) ?>">
        </div>
        <button type="submit" class="btn">保存页脚设置</button>
    </form>
</div>
</div>

<script src="script.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
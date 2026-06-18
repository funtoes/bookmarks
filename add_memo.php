<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

$cats = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order");
$cats->execute([$userId]);
$categories = $cats->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);

    if ($content === '') {
        $error = '内容不能为空';
    } elseif ($catId <= 0) {
        $error = '请选择分类';
    } else {
        $stmt = $pdo->prepare("INSERT INTO memos (user_id, category_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $catId, $content]);
        setFlash('success', '备忘录已添加');
        header('Location: memos.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加备忘录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="memos.php" class="back-link">← 返回备忘录</a>
            <h1 class="page-title">➕ 添加备忘录</h1>
        </div>
    </div>
</header>

<div class="memo-editor-container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= safeOutput($error) ?></div>
    <?php endif; ?>

    <form method="post" class="memo-editor-form">
        <div class="form-group">
            <label for="category_id">📁 分类</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- 选择分类 --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= safeOutput($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content">📝 内容</label>
            <textarea id="content" name="content" rows="12" placeholder="在这里写下你的备忘内容…" required></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 保存备忘录</button>
            <a href="memos.php" class="btn btn-cancel">取消</a>
        </div>
    </form>
</div>
</body>
</html>
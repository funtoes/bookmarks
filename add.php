<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取当前用户的分类列表
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$catStmt->execute([$userId]);
$categories = $catStmt->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($title === '' || $url === '') {
        $error = '标题和链接不能为空。';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = '请输入有效的网址（包含 http:// 或 https://）。';
    } elseif ($categoryId <= 0) {
        $error = '请选择分类。';
    } else {
        // 验证分类属于当前用户
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        if (!$stmt->fetch()) {
            $error = '分类无效。';
        } else {
            // 检查重复链接
            $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND url = ? LIMIT 1");
            $stmt->execute([$userId, $url]);
            if ($stmt->fetch()) {
                $error = '该书签已存在，请勿重复添加。';
            } else {
                // 插入书签
                $insert = $pdo->prepare("INSERT INTO bookmarks (user_id, category_id, title, url) VALUES (?, ?, ?, ?)");
                $insert->execute([$userId, $categoryId, $title, $url]);
                setFlash('success', '书签添加成功！');
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加书签</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-container" style="max-width: 500px;">
    <h1>添加书签</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= safeOutput($error) ?></div>
    <?php endif; ?>
    <form method="post" action="add.php">
        <div class="form-group">
            <label for="url">网址</label>
            <div style="display: flex; gap: 5px;">
                <input type="url" id="url" name="url" required placeholder="https://example.com" value="<?= safeOutput($_POST['url'] ?? '') ?>" style="flex:1;">
                <button type="button" id="fetchTitleBtn" class="btn btn-sm">获取标题</button>
            </div>
        </div>
        <div class="form-group">
            <label for="title">标题</label>
            <input type="text" id="title" name="title" required value="<?= safeOutput($_POST['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="category_id">分类</label>
            <select class="nice-select" id="category_id" name="category_id" required>
                <option value="">-- 选择分类 --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= safeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">添加书签</button>
        <p style="text-align:center; margin-top:15px;"><a href="index.php">返回首页</a></p>
    </form>
</div>
<script src="script.js"></script>
</body>
</html>
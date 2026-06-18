<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取分类列表
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
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="index.php" class="back-link">← 返回首页</a>
            <h1 class="page-title">🔖 添加书签</h1>
        </div>
    </div>
</header>

<div class="memo-editor-container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= safeOutput($error) ?></div>
    <?php endif; ?>

    <form method="post" class="memo-editor-form">
        <!-- 网址 -->
        <div class="form-group">
            <label for="url">🔗 网址</label>
            <div class="url-input-group">
                <input type="url" id="url" name="url" required placeholder="https://example.com" value="<?= safeOutput($_POST['url'] ?? '') ?>">
                <button type="button" id="fetchTitleBtn" class="btn-fetch-title">获取标题</button>
            </div>
        </div>

        <!-- 标题 -->
        <div class="form-group">
            <label for="title">📝 标题</label>
            <input type="text" id="title" name="title" required value="<?= safeOutput($_POST['title'] ?? '') ?>" placeholder="书签标题">
        </div>

        <!-- 分类 -->
        <div class="form-group">
            <label for="category_id">📁 分类</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- 选择分类 --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= safeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 添加书签</button>
            <a href="index.php" class="btn btn-cancel">取消</a>
        </div>
    </form>
</div>

<script src="script.js"></script>
</body>
</html>
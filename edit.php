<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取分类
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$catStmt->execute([$userId]);
$categories = $catStmt->fetchAll();

// 获取要编辑的书签
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', '无效的书签ID。');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$bookmark = $stmt->fetch();

if (!$bookmark) {
    setFlash('error', '书签不存在或无权编辑。');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($title === '' || $url === '') {
        $error = '标题和链接不能为空。';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = '请输入有效的网址。';
    } elseif ($categoryId <= 0) {
        $error = '请选择分类。';
    } else {
        // 验证分类
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        if (!$stmt->fetch()) {
            $error = '分类无效。';
        } else {
            $update = $pdo->prepare("UPDATE bookmarks SET title = ?, url = ?, category_id = ? WHERE id = ? AND user_id = ?");
            $update->execute([$title, $url, $categoryId, $id, $userId]);
            setFlash('success', '书签更新成功！');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑书签</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="index.php" class="back-link">← 返回首页</a>
            <h1 class="page-title">✏️ 编辑书签</h1>
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
                <input type="url" id="url" name="url" required value="<?= safeOutput($_POST['url'] ?? $bookmark['url']) ?>" placeholder="https://example.com">
                <button type="button" id="fetchTitleBtn" class="btn-fetch-title">获取标题</button>
            </div>
        </div>

        <!-- 标题 -->
        <div class="form-group">
            <label for="title">📝 标题</label>
            <input type="text" id="title" name="title" required value="<?= safeOutput($_POST['title'] ?? $bookmark['title']) ?>" placeholder="书签标题">
        </div>

        <!-- 分类 -->
        <div class="form-group">
            <label for="category_id">📁 分类</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- 选择分类 --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($bookmark['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= safeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 保存修改</button>
            <a href="index.php" class="btn btn-cancel">取消</a>
        </div>
    </form>
</div>

<script src="script.js"></script>
</body>
</html>
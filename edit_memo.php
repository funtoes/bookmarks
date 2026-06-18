<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM memos WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$memo = $stmt->fetch();
if (!$memo) {
    setFlash('error', '备忘录不存在');
    header('Location: memos.php');
    exit;
}

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
        $stmt = $pdo->prepare("UPDATE memos SET content = ?, category_id = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$content, $catId, $id, $userId]);
        setFlash('success', '备忘录已更新');
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
    <title>编辑备忘录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="memos.php" class="back-link">← 返回备忘录</a>
            <h1 class="page-title">✏️ 编辑备忘录</h1>
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
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $memo['category_id'] ? 'selected' : '' ?>>
                        <?= safeOutput($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content">📝 内容</label>
            <textarea id="content" name="content" rows="12" placeholder="在这里写下你的备忘内容…" required><?= safeOutput($memo['content']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 保存修改</button>
            <a href="memos.php" class="btn btn-cancel">取消</a>
        </div>
    </form>
</div>
</body>
</html>
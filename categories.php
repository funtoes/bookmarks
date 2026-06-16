<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 处理新增/编辑/删除分类
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');

    if ($action === 'add' && $name !== '') {
        // 检查分类名是否已存在
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? LIMIT 1");
        $stmt->execute([$userId, $name]);
        if ($stmt->fetch()) {
            setFlash('error', '分类名称已存在，请使用其他名称。');
        } else {
            // 获取当前最大sort_order
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories WHERE user_id = ?");
            $stmt->execute([$userId]);
            $nextOrder = $stmt->fetchColumn();
            $insert = $pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, ?, ?)");
            $insert->execute([$userId, $name, $nextOrder]);
            setFlash('success', '分类已添加。');
        }
        header('Location: categories.php');
        exit;

    } elseif ($action === 'edit' && isset($_POST['id']) && $name !== '') {
        $id = (int)$_POST['id'];

        // 禁止将“未分类”改名
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $oldName = $stmt->fetchColumn();
        if ($oldName === '未分类' && $name !== '未分类') {
            setFlash('error', '不能修改“未分类”的名称。');
            header('Location: categories.php');
            exit;
        }

        // 检查名称是否与其他分类冲突（排除自身）
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ? LIMIT 1");
        $stmt->execute([$userId, $name, $id]);
        if ($stmt->fetch()) {
            setFlash('error', '分类名称已存在，请使用其他名称。');
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $id, $userId]);
            setFlash('success', '分类已更新。');
        }
        header('Location: categories.php');
        exit;

    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];

        // 禁止删除“未分类”
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $catName = $stmt->fetchColumn();
        if ($catName === '未分类') {
            setFlash('error', '不能删除“未分类”分类。');
            header('Location: categories.php');
            exit;
        }

        // 确保“未分类”存在（若不存在则创建）
        $defaultCat = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = '未分类' LIMIT 1");
        $defaultCat->execute([$userId]);
        $uncatId = $defaultCat->fetchColumn();
        if (!$uncatId) {
            $pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, '未分类', 0)")->execute([$userId]);
            $uncatId = $pdo->lastInsertId();
        }

        // 将该分类下的书签移至“未分类”
        $pdo->prepare("UPDATE bookmarks SET category_id = ? WHERE category_id = ? AND user_id = ?")
            ->execute([$uncatId, $id, $userId]);

        // 删除分类
        $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?")->execute([$id, $userId]);

        setFlash('success', '分类已删除，内部书签已移至“未分类”。');
        header('Location: categories.php');
        exit;
    }
}

// 获取所有分类（按sort_order排序）
$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$cats->execute([$userId]);
$categories = $cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="index.php" class="back-link" title="返回首页">← 返回首页</a>
            <h1 class="page-title">📁 分类管理</h1>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-icon" title="书签列表">📑</a>
            <a href="add.php" class="btn-icon" title="添加书签">＋</a>
        </div>
    </div>
</header>

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash-message flash-<?= $flash['type'] ?>"><?= safeOutput($flash['message']) ?></div>
<?php endif; ?>

<div class="content-container">
    <div class="category-list" id="sortable-list">
        <?php foreach ($categories as $cat): ?>
        <div class="category-item" draggable="true" data-id="<?= $cat['id'] ?>">
            <span class="drag-handle">☰</span>
            <span class="cat-name"><?= safeOutput($cat['name']) ?></span>
            <div class="cat-actions">
                <button class="btn btn-sm btn-edit" data-id="<?= $cat['id'] ?>" data-name="<?= safeOutput($cat['name']) ?>">编辑</button>
                <form method="post" style="display:inline" onsubmit="return confirm('确定删除此分类？内部书签将移入“未分类”。')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="add-category-form">
        <h3>新增分类</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="分类名称" required>
            <button type="submit" class="btn">添加</button>
        </form>
    </div>
</div>

<!-- 编辑分类模态框 -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>编辑分类名称</h3>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <input type="text" name="name" id="edit-name" required>
            <button type="submit" class="btn">保存</button>
        </form>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
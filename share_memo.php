<?php
require_once __DIR__ . '/init.php';
$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(404);
    echo '无效链接';
    exit;
}
$pdo = getDB();
$stmt = $pdo->prepare("SELECT content, created_at, c.name AS cat_name FROM memos m LEFT JOIN categories c ON m.category_id = c.id WHERE m.share_token = ?");
$stmt->execute([$token]);
$memo = $stmt->fetch();
if (!$memo) {
    http_response_code(404);
    echo '备忘录不存在或链接已失效';
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分享备忘录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="share-container">
    <div class="share-header">
        <span class="share-cat">📁 <?= safeOutput($memo['cat_name']) ?></span>
        <span class="share-time"><?= date('Y-m-d H:i', strtotime($memo['created_at'])) ?></span>
    </div>
    <div class="share-content"><?= nl2br(safeOutput($memo['content'])) ?></div>
    <p class="share-footer">由 <strong><?= safeOutput($_SERVER['HTTP_HOST'] ?? '') ?></strong> 的备忘录分享</p>
</div>
</body>
</html>
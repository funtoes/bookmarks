<?php
require_once __DIR__ . '/init.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$userId = currentUserId();

// 更新点击次数和最后点击时间
$stmt = $pdo->prepare("UPDATE bookmarks SET clicks = clicks + 1, last_click = NOW() WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);

// 获取真实URL并重定向
$stmt = $pdo->prepare("SELECT url FROM bookmarks WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$row = $stmt->fetch();

if ($row) {
    header('Location: ' . $row['url']);
} else {
    header('Location: index.php');
}
exit;
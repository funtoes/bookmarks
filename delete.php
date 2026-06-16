<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() > 0) {
        setFlash('success', '书签已删除。');
    } else {
        setFlash('error', '书签不存在或无权删除。');
    }
} else {
    setFlash('error', '无效的书签ID。');
}

// 获取返回参数（安全跳转）
$return = $_GET['return'] ?? '';
if ($return !== '') {
    // 只保留查询字符串部分，避免外部 URL
    header('Location: ' . BASE_URL . '/index.php?' . $return);
} else {
    header('Location: ' . BASE_URL . '/index.php');
}
exit;
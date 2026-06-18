<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("DELETE FROM memos WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
setFlash('success', '备忘录已删除');
header('Location: memos.php');
exit;
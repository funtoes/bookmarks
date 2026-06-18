<?php
require_once __DIR__ . '/init.php';
requireLogin();
header('Content-Type: application/json');

$pdo = getDB();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM memos WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$token = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("UPDATE memos SET share_token = ? WHERE id = ?");
$stmt->execute([$token, $id]);

echo json_encode(['success' => true, 'token' => $token]);
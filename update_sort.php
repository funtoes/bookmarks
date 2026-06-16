<?php
require_once __DIR__ . '/init.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

$pdo = getDB();
$userId = currentUserId();

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['order']) || !is_array($input['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的排序数据']);
    exit;
}

$order = array_values(array_unique(array_map('intval', $input['order'])));

// 获取该用户所有分类 ID
$stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ?");
$stmt->execute([$userId]);
$validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 过滤掉不属于当前用户的 ID
$order = array_values(array_intersect($order, $validIds));

if (empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '没有有效的分类ID']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND user_id = ?");
    $updated = 0;
    foreach ($order as $position => $id) {
        $stmt->execute([$position, $id, $userId]);
        $updated += $stmt->rowCount();
    }

    // 未包含在 order 中的其余分类，按原顺序排在后面（可选，也可忽略）
    $remainingIds = array_diff($validIds, $order);
    if (!empty($remainingIds)) {
        $position = count($order);
        $stmt2 = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND user_id = ?");
        foreach ($remainingIds as $id) {
            $stmt2->execute([$position, $id, $userId]);
            $position++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库更新失败，请重试']);
}
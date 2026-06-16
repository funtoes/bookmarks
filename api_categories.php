<?php
/**
 * api_categories.php
 * 返回当前用户的分类列表
 * 支持 Session 登录（优先）或 API 密钥认证
 * 请求方式：GET
 * 参数（可选）：api_key - 当未登录时使用的 API 密钥
 * 返回：JSON 数组 [{id, name}, ...] 或错误信息
 */

require_once __DIR__ . '/init.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$userId = null;

// 1. 检查是否已通过 Session 登录
if (isLoggedIn()) {
    $userId = currentUserId();
} else {
    // 2. 尝试从 GET 参数获取 api_key
    $apiKey = $_GET['api_key'] ?? '';
    if ($apiKey !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ? AND api_key IS NOT NULL AND api_key != ''");
        $stmt->execute([$apiKey]);
        $userId = $stmt->fetchColumn();
    }
}

// 3. 若仍未获取到用户 ID，返回 401
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => '未授权：请先登录或提供有效的 API 密钥']);
    exit;
}

// 4. 查询该用户的分类（按排序字段）
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

echo json_encode($categories);
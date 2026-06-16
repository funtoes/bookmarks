<?php
/**
 * api_add_bookmark.php
 * 添加书签
 * 支持 Session 登录（优先）或 API 密钥认证
 * 请求方式：POST
 * Content-Type: application/json
 * 请求体 JSON：
 *   - url: string 必填
 *   - title: string 必填
 *   - category_id: int 必填
 *   - api_key: string 可选（当未登录时使用）
 * 返回：JSON {success: true/false, message: string}
 */

require_once __DIR__ . '/init.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();

// 读取 JSON 输入
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的请求数据']);
    exit;
}

$userId = null;

// 1. 检查 Session 登录
if (isLoggedIn()) {
    $userId = currentUserId();
} else {
    // 2. 尝试使用 API 密钥
    $apiKey = $input['api_key'] ?? '';
    if ($apiKey !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ? AND api_key IS NOT NULL AND api_key != ''");
        $stmt->execute([$apiKey]);
        $userId = $stmt->fetchColumn();
    }
}

// 3. 未授权
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权：请先登录或提供有效的 API 密钥']);
    exit;
}

// 4. 提取并验证参数
$title = trim($input['title'] ?? '');
$url = trim($input['url'] ?? '');
$categoryId = (int)($input['category_id'] ?? 0);

if ($title === '' || $url === '') {
    echo json_encode(['success' => false, 'message' => '标题和链接不能为空']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => '无效的网址，需以 http:// 或 https:// 开头']);
    exit;
}

// 5. 验证分类是否属于该用户
$stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
$stmt->execute([$categoryId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '分类无效或不属于当前用户']);
    exit;
}

// 6. 检查重复链接（同一用户下相同 URL 不可重复添加）
$stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND url = ? LIMIT 1");
$stmt->execute([$userId, $url]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '该书签已存在，请勿重复添加']);
    exit;
}

// 7. 插入书签
$stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, category_id, title, url) VALUES (?, ?, ?, ?)");
$stmt->execute([$userId, $categoryId, $title, $url]);

echo json_encode(['success' => true, 'message' => '书签已添加']);
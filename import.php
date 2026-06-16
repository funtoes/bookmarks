<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['bookmark_file'])) {
    setFlash('error', '请上传书签文件。');
    header('Location: settings.php');
    exit;
}

$file = $_FILES['bookmark_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', '文件上传失败，错误代码：' . $file['error']);
    header('Location: settings.php');
    exit;
}

$content = file_get_contents($file['tmp_name']);
if ($content === false) {
    setFlash('error', '无法读取文件内容。');
    header('Location: settings.php');
    exit;
}

// 移除 BOM
$content = ltrim($content, "\xEF\xBB\xBF");

// 统一编码为 UTF-8
$encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
if ($encoding !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'ISO-8859-1');
}

// 移除 HTML 注释
$content = preg_replace('/<!--.*?-->/s', '', $content);

// 准备结果数组
$categories = [];       // 分类名 -> [书签列表]
$uncategorized = [];    // 未分类书签

// 栈：存放当前分类名列表（应对嵌套）
$stack = [];

// 匹配所有相关的标签：<DL>, </DL>, <DT><H3>分类名</H3>, <DT><A ...>Title</A>
$regex = '/<DL[^>]*>|<\/DL>|<DT>\s*<H3[^>]*>(.*?)<\/H3>|<DT>\s*<A\s[^>]*?HREF="(.*?)"[^>]*>(.*?)<\/A>/is';
preg_match_all($regex, $content, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

foreach ($matches as $match) {
    $full = $match[0];

    if (preg_match('/^<DL/i', $full)) {
        // 深度增加（未使用，但保留）
    } elseif (preg_match('/^<\/DL>/i', $full)) {
        // 深度减少，弹出栈
        if (count($stack) > 0) {
            array_pop($stack);
        }
    } elseif (isset($match[1]) && $match[1] !== null && $match[2] === null) {
        // 分类名
        $name = trim(strip_tags($match[1]));
        if ($name !== '') {
            $stack[] = $name;
            if (!isset($categories[$name])) {
                $categories[$name] = [];
            }
        }
    } elseif (isset($match[2]) && $match[2] !== null) {
        // 书签
        $url = $match[2];
        $title = trim(strip_tags($match[3] ?? ''));
        if ($url && $title) {
            $currentCategory = end($stack);
            if ($currentCategory !== false && $currentCategory !== null) {
                $categories[$currentCategory][] = ['title' => $title, 'url' => $url];
            } else {
                $uncategorized[] = ['title' => $title, 'url' => $url];
            }
        }
    }
}

// 确保“未分类”存在
$defCatStmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = '未分类' LIMIT 1");
$defCatStmt->execute([$userId]);
$uncatId = $defCatStmt->fetchColumn();
if (!$uncatId) {
    $pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, '未分类', 0)")->execute([$userId]);
    $uncatId = $pdo->lastInsertId();
}

// 准备去重检查语句和插入语句
$checkStmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND url = ? LIMIT 1");
$insertStmt = $pdo->prepare("INSERT INTO bookmarks (user_id, category_id, title, url) VALUES (?, ?, ?, ?)");

$inserted = 0;
$skipped = 0;

/**
 * 批量插入书签（带去重检查）
 */
function insertBookmarks(array $bookmarks, int $userId, int $categoryId, PDO $pdo, &$inserted, &$skipped): void {
    global $checkStmt, $insertStmt;
    foreach ($bookmarks as $bm) {
        // 检查是否已存在相同 URL
        $checkStmt->execute([$userId, $bm['url']]);
        if ($checkStmt->fetch()) {
            $skipped++;
            continue;
        }
        $insertStmt->execute([$userId, $categoryId, $bm['title'], $bm['url']]);
        $inserted++;
    }
}

// 插入未分类书签
if (!empty($uncategorized)) {
    insertBookmarks($uncategorized, $userId, $uncatId, $pdo, $inserted, $skipped);
}

// 插入各分类书签
foreach ($categories as $catName => $items) {
    // 查找或创建分类
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? LIMIT 1");
    $catStmt->execute([$userId, $catName]);
    $catId = $catStmt->fetchColumn();
    if (!$catId) {
        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM categories WHERE user_id = ?");
        $maxOrder->execute([$userId]);
        $nextOrder = $maxOrder->fetchColumn();
        $pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, ?, ?)")->execute([$userId, $catName, $nextOrder]);
        $catId = $pdo->lastInsertId();
    }

    insertBookmarks($items, $userId, $catId, $pdo, $inserted, $skipped);
}

// 生成提示消息
$msg = "导入完成，新增 {$inserted} 个书签。";
if ($skipped > 0) {
    $msg .= " 跳过 {$skipped} 个重复项。";
}
setFlash('success', $msg);
header('Location: settings.php');
exit;
<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取分类
$cats = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order");
$cats->execute([$userId]);
$categories = $cats->fetchAll();

// 获取所有书签，按分类
$stmt = $pdo->prepare("SELECT b.title, b.url, b.created_at, c.name AS cat_name 
                       FROM bookmarks b 
                       JOIN categories c ON b.category_id = c.id 
                       WHERE b.user_id = ? 
                       ORDER BY c.sort_order, b.created_at DESC");
$stmt->execute([$userId]);
$bookmarks = $stmt->fetchAll();

// 按分类分组
$grouped = [];
foreach ($categories as $cat) {
    $grouped[$cat['id']] = ['name' => $cat['name'], 'items' => []];
}
foreach ($bookmarks as $bm) {
    // 如果分类已删除但书签还在（通过外键不会发生，但以防万一）
    if (isset($grouped[$bm['cat_name']])) {
        // 这里实际用 cat_name 不直接，我们用分类ID分组更好，但查询时已经有了cat_name，可以改用分类ID
        // 为方便，重新组织：按分类ID分组
    }
}
// 更好的方式：重新查询按分类ID分组
$grouped = [];
$catMap = [];
foreach ($categories as $cat) {
    $catMap[$cat['id']] = $cat['name'];
    $grouped[$cat['id']] = [];
}
$allBm = $pdo->prepare("SELECT id, title, url, created_at, category_id FROM bookmarks WHERE user_id = ? ORDER BY category_id, created_at DESC");
$allBm->execute([$userId]);
while ($bm = $allBm->fetch()) {
    $cid = $bm['category_id'];
    if (isset($grouped[$cid])) {
        $grouped[$cid][] = $bm;
    } else {
        // 未被分类的（理论上不存在，因为删除分类会迁移）放入未分类
        if (!isset($grouped[0])) $grouped[0] = [];
        $grouped[0][] = $bm;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="bookmarks_' . date('Ymd_His') . '.html"');

echo '<!DOCTYPE NETSCAPE-Bookmark-file-1>' . "\n";
echo '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">' . "\n";
echo '<TITLE>Bookmarks</TITLE>' . "\n";
echo '<H1>Bookmarks</H1>' . "\n";
echo '<DL><p>' . "\n";

foreach ($grouped as $cid => $items) {
    if (empty($items)) continue;
    $catName = htmlspecialchars($catMap[$cid] ?? '未分类', ENT_QUOTES, 'UTF-8');
    echo '    <DT><H3>' . $catName . '</H3>' . "\n";
    echo '    <DL><p>' . "\n";
    foreach ($items as $bm) {
        $addDate = strtotime($bm['created_at']);
        $title = htmlspecialchars($bm['title'], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($bm['url'], ENT_QUOTES, 'UTF-8');
        echo '        <DT><A HREF="' . $url . '" ADD_DATE="' . $addDate . '">' . $title . '</A>' . "\n";
    }
    echo '    </DL><p>' . "\n";
}
echo '</DL><p>' . "\n";
exit;
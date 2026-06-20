<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取当前用户的所有分类
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$catStmt->execute([$userId]);
$categories = $catStmt->fetchAll();

// 视图处理
$viewQuery = $_GET['view'] ?? null;
if ($viewQuery && in_array($viewQuery, ['card', 'table'])) {
    $view = $viewQuery;
    $_SESSION['temp_view'] = $view;
} elseif (isset($_SESSION['temp_view'])) {
    $view = $_SESSION['temp_view'];
} else {
    $userStmt = $pdo->prepare("SELECT default_view FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $view = $user['default_view'] ?? 'card';
}

// 筛选条件
$selectedCategory = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'created_at';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$allowedSorts = ['created_at', 'clicks', 'last_click'];
if (!in_array($sort, $allowedSorts)) $sort = 'created_at';

// 构建查询
$where = "b.user_id = :uid";
$params = [':uid' => $userId];
if ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
    $where .= " AND b.category_id = :cid";
    $params[':cid'] = (int)$selectedCategory;
}
if ($search !== '') {
    $where .= " AND (b.title LIKE :search OR b.url LIKE :search2)";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
}

$splitMode = ($selectedCategory === 'all' && $view === 'card' && $search === '');

if ($splitMode) {
    // 最新书签 20 条
    $sqlNew = "SELECT b.id, b.title, b.url, b.created_at, b.clicks, b.last_click, c.name AS category_name
            FROM bookmarks b LEFT JOIN categories c ON b.category_id = c.id WHERE $where ORDER BY b.created_at DESC LIMIT 20";
    $stmtNew = $pdo->prepare($sqlNew);
    $stmtNew->execute($params);
    $newBookmarks = $stmtNew->fetchAll();

    $newIds = array_column($newBookmarks, 'id');
    $hotPage = max(1, (int)($_GET['hot_page'] ?? 1));
    $hotPerPage = 60;
    $hotOffset = ($hotPage - 1) * $hotPerPage;

    $excludeParams = [];
    if (!empty($newIds)) {
        $notInPlaceholders = [];
        foreach ($newIds as $i => $id) {
            $key = ":ex{$i}";
            $notInPlaceholders[] = $key;
            $excludeParams[$key] = $id;
        }
        $notInStr = implode(',', $notInPlaceholders);
        $countSql = "SELECT COUNT(*) FROM bookmarks b WHERE $where AND b.id NOT IN ($notInStr)";
        $sqlHot = "SELECT b.id, b.title, b.url, b.created_at, b.clicks, b.last_click, c.name AS category_name
                FROM bookmarks b LEFT JOIN categories c ON b.category_id = c.id
                WHERE $where AND b.id NOT IN ($notInStr) ORDER BY b.clicks DESC, b.created_at DESC LIMIT $hotPerPage OFFSET $hotOffset";
    } else {
        $countSql = "SELECT COUNT(*) FROM bookmarks b WHERE $where";
        $sqlHot = "SELECT b.id, b.title, b.url, b.created_at, b.clicks, b.last_click, c.name AS category_name
                FROM bookmarks b LEFT JOIN categories c ON b.category_id = c.id
                WHERE $where ORDER BY b.clicks DESC, b.created_at DESC LIMIT $hotPerPage OFFSET $hotOffset";
    }

    $countParams = array_merge($params, $excludeParams);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $hotTotal = $countStmt->fetchColumn();
    $hotTotalPages = ceil($hotTotal / $hotPerPage);

    $stmtHot = $pdo->prepare($sqlHot);
    $stmtHot->execute($countParams);
    $hotBookmarks = $stmtHot->fetchAll();

    $bookmarks = [];
    $totalPages = 0;
} else {
    $perPage = ($view === 'card') ? 80 : 20;
    $offset = ($page - 1) * $perPage;
    $orderBy = "$sort $order";

    $countSql = "SELECT COUNT(*) FROM bookmarks b WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    $sql = "SELECT b.id, b.title, b.url, b.created_at, b.clicks, b.last_click, c.name AS category_name
            FROM bookmarks b LEFT JOIN categories c ON b.category_id = c.id
            WHERE $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookmarks = $stmt->fetchAll();
    $totalPages = ceil($total / $perPage);
}

// 辅助函数
function buildQuery(array $overrides = []): string {
    $params = $_GET;
    unset($params['page']);
    $params = array_merge($params, $overrides);
    return http_build_query($params);
}
function pageUrl(int $pageNum): string { return '?' . buildQuery(['page' => $pageNum]); }
function sortUrl(string $field): string {
    $currentSort = $_GET['sort'] ?? 'created_at';
    $currentOrder = $_GET['order'] ?? 'desc';
    if ($currentSort === $field) {
        // 当前字段：切换顺序
        $newOrder = ($currentOrder === 'asc') ? 'desc' : 'asc';
    } else {
        // 新字段：默认降序
        $newOrder = 'desc';
    }
    return '?' . buildQuery(['sort' => $field, 'order' => $newOrder]);
}
function sortIndicator(string $field): string {
    $currentSort = $_GET['sort'] ?? 'created_at';
    if ($currentSort !== $field) return '';
    return ($_GET['order'] ?? 'desc') === 'asc' ? ' ↑' : ' ↓';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的书签</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- 整合后的顶部菜单栏 -->
<header class="main-header">
    <div class="header-inner">
        <!-- 左侧：Logo + 搜索 -->
        <div class="header-left">
            <a href="index.php" class="logo">📑 书签管理</a>
            <form class="search-form" method="get" action="" onsubmit="return false;">
                <select class="search-engine">
                    <option value="site" selected>本站</option>
                    <option value="memo">备忘录</option>
                    <option value="baidu">百度</option>
                    <option value="google">谷歌</option>
                    <option value="bing">必应</option>
                    <option value="sogou">搜狗</option>
                    <option value="so360">360</option>
                    <option value="duckduckgo">DDGo</option>
                    <option value="yandex">Yandex</option>
					<option value="yaru">Ya.ru</option>
                </select>
                <input type="text" name="search" class="search-input" placeholder="搜索书签...">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>

        <!-- 右侧：视图切换 + 添加书签 + 用户菜单 -->
        <div class="header-actions">
			<div class="view-toggle">
				<a href="?<?= buildQuery(['view' => 'card', 'page' => 1]) ?>" class="view-btn <?= $view==='card'?'active':'' ?>" title="卡片视图">▦</a>
				<a href="?<?= buildQuery(['view' => 'table', 'page' => 1]) ?>" class="view-btn <?= $view==='table'?'active':'' ?>" title="表格视图">☰</a>
			</div>
			<a href="add.php" class="btn-add">+ 添加书签</a>
			<a href="memos.php" class="btn-icon" title="备忘录">📝</a>
			<a href="categories.php" class="btn-icon" title="分类管理">📁</a>
			<a href="settings.php" class="btn-icon" title="设置">⚙️</a>
			<a href="logout.php" class="btn-icon" title="退出登录">🚪</a>
		</div>
    </div>
</header>

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash-message flash-<?= $flash['type'] ?>"><?= safeOutput($flash['message']) ?></div>
<?php endif; ?>

<!-- 分类导航 -->
<div class="category-nav">
    <a href="?<?= buildQuery(['category' => 'all', 'page' => 1]) ?>" class="cat-btn <?= $selectedCategory==='all'?'active':'' ?>">全部</a>
    <?php foreach ($categories as $cat): ?>
        <a href="?<?= buildQuery(['category' => $cat['id'], 'page' => 1]) ?>" class="cat-btn <?= $selectedCategory==$cat['id']?'active':'' ?>"><?= safeOutput($cat['name']) ?></a>
    <?php endforeach; ?>
</div>

<!-- 分类排序按钮（仅非全部分类 + 卡片视图显示） -->
<?php if ($selectedCategory !== 'all' && $view === 'card'): ?>
<div class="sort-bar">
    <span class="sort-label">排序：</span>
    <a href="?<?= buildQuery(['sort' => 'created_at', 'order' => ($sort === 'created_at' && $order === 'desc') ? 'asc' : 'desc']) ?>" 
       class="sort-btn <?= $sort === 'created_at' ? 'active' : '' ?>">
        添加日期 <?= $sort === 'created_at' ? ($order === 'asc' ? '↑' : '↓') : '' ?>
    </a>
    <a href="?<?= buildQuery(['sort' => 'clicks', 'order' => ($sort === 'clicks' && $order === 'desc') ? 'asc' : 'desc']) ?>" 
       class="sort-btn <?= $sort === 'clicks' ? 'active' : '' ?>">
        点击次数 <?= $sort === 'clicks' ? ($order === 'asc' ? '↑' : '↓') : '' ?>
    </a>
    <a href="?<?= buildQuery(['sort' => 'last_click', 'order' => ($sort === 'last_click' && $order === 'desc') ? 'asc' : 'desc']) ?>" 
       class="sort-btn <?= $sort === 'last_click' ? 'active' : '' ?>">
        最后点击 <?= $sort === 'last_click' ? ($order === 'asc' ? '↑' : '↓') : '' ?>
    </a>
</div>
<?php endif; ?>

<!-- 书签展示区（原样保留） -->
<?php if ($splitMode): ?>
    <?php if (!empty($newBookmarks)): ?>
        <h2 class="section-title">最新添加</h2>
        <div class="card-grid">
            <?php foreach ($newBookmarks as $bm): ?>
                <a href="tracking.php?id=<?= $bm['id'] ?>" target="_blank" class="card-item" title="<?= safeOutput($bm['title']) ?>" rel="noopener"><?= safeOutput($bm['title']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($hotBookmarks)): ?>
        <h2 class="section-title hot">热门书签</h2>
        <div class="card-grid">
            <?php foreach ($hotBookmarks as $bm): ?>
                <a href="tracking.php?id=<?= $bm['id'] ?>" target="_blank" class="card-item" title="<?= safeOutput($bm['title']) ?>" rel="noopener"><?= safeOutput($bm['title']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($hotTotalPages > 1): ?>
        <div class="pagination">
            <?php $hotBaseParams = $_GET; unset($hotBaseParams['hot_page']); $hotBaseQuery = http_build_query($hotBaseParams); ?>
            <?php if ($hotPage > 1): ?><a href="?<?= $hotBaseQuery ?>&hot_page=<?= $hotPage-1 ?>" class="page-link">上一页</a><?php endif; ?>
            <?php
            $range = 2; $start = max(1, $hotPage - $range); $end = min($hotTotalPages, $hotPage + $range);
            if ($start > 1) { echo '<a href="?'.$hotBaseQuery.'&hot_page=1" class="page-link">1</a>'; if ($start > 2) echo '<span class="page-ellipsis">…</span>'; }
            for ($i = $start; $i <= $end; $i++) echo '<a href="?'.$hotBaseQuery.'&hot_page='.$i.'" class="page-link '.($i==$hotPage?'active':'').'">'.$i.'</a>';
            if ($end < $hotTotalPages) { if ($end < $hotTotalPages - 1) echo '<span class="page-ellipsis">…</span>'; echo '<a href="?'.$hotBaseQuery.'&hot_page='.$hotTotalPages.'" class="page-link">'.$hotTotalPages.'</a>'; }
            ?>
            <?php if ($hotPage < $hotTotalPages): ?><a href="?<?= $hotBaseQuery ?>&hot_page=<?= $hotPage+1 ?>" class="page-link">下一页</a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><p>暂无热门书签</p></div>
    <?php endif; ?>

<?php elseif ($view === 'card'): ?>
    <?php if (empty($bookmarks)): ?>
        <div class="empty-state"><p>暂无书签</p></div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($bookmarks as $bm): ?>
                <a href="tracking.php?id=<?= $bm['id'] ?>" target="_blank" class="card-item" title="<?= safeOutput($bm['title']) ?>" rel="noopener"><?= safeOutput($bm['title']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (empty($bookmarks)): ?>
        <div class="empty-state"><p>暂无书签</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="bookmark-table">
                <thead><tr>
                    <th>标题</th><th>链接</th>
                    <th><a href="<?= sortUrl('created_at') ?>" class="sort-link">添加日期<?= sortIndicator('created_at') ?></a></th>
                    <th><a href="<?= sortUrl('clicks') ?>" class="sort-link">点击次数<?= sortIndicator('clicks') ?></a></th>
                    <th><a href="<?= sortUrl('last_click') ?>" class="sort-link">最后点击<?= sortIndicator('last_click') ?></a></th>
                    <th>所属分类</th><th>操作</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($bookmarks as $bm): ?>
                    <tr>
                        <td><a href="tracking.php?id=<?= $bm['id'] ?>" target="_blank" class="bookmark-title" title="<?= safeOutput($bm['title']) ?>" rel="noopener"><?= safeOutput($bm['title']) ?></a></td>
                        <td class="url-cell" title="<?= safeOutput($bm['url']) ?>"><?= safeOutput(strlen($bm['url'])>40 ? substr($bm['url'],0,40).'...' : $bm['url']) ?></td>
                        <td><?= date('Y-m-d', strtotime($bm['created_at'])) ?></td>
                        <td><?= $bm['clicks'] ?></td>
                        <td><?= $bm['last_click'] ? date('Y-m-d H:i', strtotime($bm['last_click'])) : '从未' ?></td>
                        <td><?= safeOutput($bm['category_name']) ?></td>
                        <td class="actions">
                            <a href="edit.php?id=<?= $bm['id'] ?>" class="btn btn-sm btn-warning">编辑</a>
                            <a href="delete.php?id=<?= $bm['id'] ?>&return=<?= urlencode(buildQuery(['page' => 1])) ?>" 
   class="btn btn-sm btn-danger" 
   data-confirm="确定删除该书签吗？">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$splitMode && $totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?><a href="<?= pageUrl($page-1) ?>" class="page-link">上一页</a><?php endif; ?>
    <?php
    $range = 2; $start = max(1, $page - $range); $end = min($totalPages, $page + $range);
    if ($start > 1) { echo '<a href="'.pageUrl(1).'" class="page-link">1</a>'; if ($start > 2) echo '<span class="page-ellipsis">…</span>'; }
    for ($i = $start; $i <= $end; $i++) echo '<a href="'.pageUrl($i).'" class="page-link '.($i==$page?'active':'').'">'.$i.'</a>';
    if ($end < $totalPages) { if ($end < $totalPages - 1) echo '<span class="page-ellipsis">…</span>'; echo '<a href="'.pageUrl($totalPages).'" class="page-link">'.$totalPages.'</a>'; }
    ?>
    <?php if ($page < $totalPages): ?><a href="<?= pageUrl($page+1) ?>" class="page-link">下一页</a><?php endif; ?>
</div>
<?php endif; ?>

<script src="script.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/init.php';
requireLogin();

$pdo = getDB();
$userId = currentUserId();

// 获取分类列表
$cats = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$cats->execute([$userId]);
$categories = $cats->fetchAll();

// 筛选参数
$selectedCategory = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = "m.user_id = :uid";
$params = [':uid' => $userId];

if ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
    $where .= " AND m.category_id = :cid";
    $params[':cid'] = (int)$selectedCategory;
}
if ($search !== '') {
    $where .= " AND m.content LIKE :search";
    $params[':search'] = "%$search%";
}

// 总数
$countSql = "SELECT COUNT(*) FROM memos m WHERE $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// 查询备忘录
$sql = "SELECT m.id, m.content, m.share_token, m.created_at, c.name AS cat_name
        FROM memos m
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE $where
        ORDER BY m.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$memos = $stmt->fetchAll();

function buildMemoQuery(array $overrides = []): string {
    $params = $_GET;
    unset($params['page']);
    $params = array_merge($params, $overrides);
    return http_build_query($params);
}
function memoPageUrl(int $p): string {
    return '?' . buildMemoQuery(['page' => $p]);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>备忘录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="index.php" class="logo">📑 书签管理</a>
            <form class="search-form" method="get" action="" onsubmit="return false;">
                <select class="search-engine">
                    <option value="site">本站</option>
                    <option value="memo" selected>备忘录</option>
                    <option value="baidu">百度</option>
                    <option value="google">谷歌</option>
                    <option value="bing">必应</option>
                    <option value="sogou">搜狗</option>
                    <option value="so360">360</option>
                    <option value="duckduckgo">DDGo</option>
                    <option value="yandex">Yandex</option>
					<option value="yaru">Ya.ru</option>
                </select>
                <input type="text" name="search" value="<?= safeOutput($search) ?>" class="search-input">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
        <div class="header-actions">
            <a href="add_memo.php" class="btn-add">+ 添加备忘录</a>
            <a href="categories.php" class="btn-icon" title="分类管理">📁</a>
            <a href="settings.php" class="btn-icon" title="设置">⚙️</a>
            <a href="logout.php" class="btn-icon" title="退出">🚪</a>
        </div>
    </div>
</header>

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash-message flash-<?= $flash['type'] ?>"><?= safeOutput($flash['message']) ?></div>
<?php endif; ?>


    <div class="category-nav">
        <a href="?<?= buildMemoQuery(['category' => 'all', 'page' => 1]) ?>" class="cat-btn <?= $selectedCategory==='all'?'active':'' ?>">全部</a>
        <?php foreach ($categories as $cat): ?>
            <a href="?<?= buildMemoQuery(['category' => $cat['id'], 'page' => 1]) ?>" class="cat-btn <?= $selectedCategory==$cat['id']?'active':'' ?>"><?= safeOutput($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>


<div class="memo-list">
    <?php if (empty($memos)): ?>
        <div class="empty-state"><p>暂无备忘录</p></div>
    <?php else: ?>
        <?php foreach ($memos as $memo): 
            $fullContent = $memo['content'];
            $summary = $fullContent;
            $showToggle = false;
            if (mb_strlen($fullContent) > 100) {
                $summary = mb_substr($fullContent, 0, 100) . '...';
                $showToggle = true;
            }
        ?>
        <div class="memo-card">
            <div class="memo-header">
                <span class="memo-category">📁 <?= safeOutput($memo['cat_name']) ?></span>
                <span class="memo-time"><?= date('Y-m-d H:i', strtotime($memo['created_at'])) ?></span>
            </div>
            <div class="memo-content" id="content-<?= $memo['id'] ?>">
                <div class="memo-summary"><?= nl2br(safeOutput($summary)) ?></div>
                <?php if ($showToggle): ?>
                <div class="memo-full" style="display:none;"><?= nl2br(safeOutput($fullContent)) ?></div>
                <?php endif; ?>
            </div>
            <div class="memo-actions">
                <?php if ($showToggle): ?>
                <button class="btn btn-sm toggle-memo" onclick="toggleMemo(<?= $memo['id'] ?>, this)">展开 ▼</button>
                <?php endif; ?>
                <button class="btn btn-sm copy-memo" data-content="<?= safeOutput($fullContent) ?>">📋 复制内容</button>
                <a href="edit_memo.php?id=<?= $memo['id'] ?>" class="btn btn-sm btn-warning">✏️ 编辑</a>
                <button class="btn btn-sm copy-share" data-id="<?= $memo['id'] ?>" data-token="<?= $memo['share_token'] ?>">🔗 复制分享</button>
                <a href="delete_memo.php?id=<?= $memo['id'] ?>" 
   class="btn btn-sm btn-danger" 
   data-confirm="确定删除该备忘录吗？">🗑 删除</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?><a href="<?= memoPageUrl($page-1) ?>" class="page-link">上一页</a><?php endif; ?>
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="<?= memoPageUrl($i) ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?= memoPageUrl($page+1) ?>" class="page-link">下一页</a><?php endif; ?>
</div>
<?php endif; ?>

<script src="script.js"></script>
<script>
// 展开/收起备忘录（直接操作 style.display，稳定无冲突）
function toggleMemo(id, btn) {
    const container = document.getElementById('content-' + id);
    if (!container) return;
    const summary = container.querySelector('.memo-summary');
    const full = container.querySelector('.memo-full');
    if (!summary || !full) return;

    if (full.style.display === 'none') {
        // 当前显示摘要，切换为全文
        summary.style.display = 'none';
        full.style.display = 'block';
        btn.textContent = '收起 ▲';
    } else {
        // 当前显示全文，切换回摘要
        full.style.display = 'none';
        summary.style.display = 'block';
        btn.textContent = '展开 ▼';
    }
}

// ========== Toast 提示函数 ==========
function showToast(message, type) {
    var old = document.querySelector('.toast');
    if (old) old.remove();
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || '');
    var icons = { success: '✅', error: '❌' };
    toast.innerHTML = '<span class="toast-icon">' + (icons[type] || 'ℹ️') + '</span>' + message;
    document.body.appendChild(toast);
    requestAnimationFrame(function() {
        toast.classList.add('show');
    });
    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.remove(); }, 250);
    }, 2500);
}

// ========== 复制内容 ==========
document.querySelectorAll('.copy-memo').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var content = this.getAttribute('data-content');
        if (!content) return;
        navigator.clipboard.writeText(content).then(function() {
            showToast('内容已复制到剪贴板', 'success');
        }).catch(function() {
            showToast('复制失败，请手动复制', 'error');
        });
    });
});

// ========== 复制分享链接 ==========
document.querySelectorAll('.copy-share').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var token = this.getAttribute('data-token');
        if (!token) {
            fetch('generate_share_token.php?id=' + id)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        btn.setAttribute('data-token', data.token);
                        copyShareLink(data.token);
                    } else {
                        showToast('生成分享链接失败', 'error');
                    }
                }).catch(function() {
                    showToast('网络请求失败', 'error');
                });
        } else {
            copyShareLink(token);
        }
    });
});

function copyShareLink(token) {
    var shareUrl = '<?= BASE_URL ?>/share_memo.php?token=' + token;
    navigator.clipboard.writeText(shareUrl).then(function() {
        showToast('分享链接已复制到剪贴板', 'success');
    }).catch(function() {
        showToast('复制失败', 'error');
    });
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
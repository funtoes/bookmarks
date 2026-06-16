<?php
require_once __DIR__ . '/init.php';
requireLogin(); // 仅限登录用户调用

header('Content-Type: application/json');

$url = $_GET['url'] ?? '';
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'title' => '']);
    exit;
}

// 尝试获取网页标题
$title = '';
try {
    // 优先使用 curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BookmarkManager/1.0)',
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (compatible; BookmarkManager/1.0)',
                'follow_location' => true,
            ],
            'ssl' => ['verify_peer' => false],
        ]);
        $html = @file_get_contents($url, false, $context);
    } else {
        echo json_encode(['success' => false, 'title' => '服务器不支持获取远程内容']);
        exit;
    }

    if ($html && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim($matches[1]);
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
} catch (Exception $e) {
    // 忽略错误，返回空标题
}

echo json_encode(['success' => true, 'title' => $title]);
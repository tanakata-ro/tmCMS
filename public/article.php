<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

$pdo = Database::getInstance();
$id  = (int)($_GET['id'] ?? 0);
if ($id === 0) { http_response_code(404); exit('記事が見つかりません。'); }

$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(u.nickname, u.username) AS author_name,
           u.avatar_path, u.bio
    FROM posts p JOIN users u ON p.author_id = u.id WHERE p.id = ?
");
$stmt->execute([$id]); $post = $stmt->fetch();
if (!$post) { http_response_code(404); exit('記事が見つかりません。'); }

if ($post['status'] === 'unlisted') {
    if ((string)($_GET['pswd'] ?? '') !== $post['access_password']) {
        http_response_code(403); exit('パスワードが必要です。');
    }
} elseif ($post['status'] !== 'published') {
    http_response_code(404); exit('記事が見つかりません。');
}

// ビューカウントを加算
$pdo->prepare('UPDATE posts SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);
$post['view_count'] = (int)$post['view_count'] + 1;

// タグ取得
$tStmt = $pdo->prepare("SELECT t.id, t.name, t.slug FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?");
$tStmt->execute([$id]); $post['tags'] = $tStmt->fetchAll();

// Markdown + BBCode 変換
$content = $post['content'];
if (class_exists('BbCode')) $content = BbCode::parse($content);
$parsedown = class_exists('Parsedown') ? new Parsedown() : null;
$post['html'] = $parsedown ? $parsedown->text($content) : nl2br(Security::e($content));

// 広告設定を取得
$ads = ['enabled' => false, 'before_content' => '', 'after_content' => ''];
if (!empty($post['show_ads'])) {
    $adStmt = $pdo->prepare("SELECT key, value FROM settings WHERE key IN ('ad_before_content','ad_after_content','ads_enabled')");
    $adStmt->execute();
    $adSettings = array_column($adStmt->fetchAll(), 'value', 'key');
    if (!empty($adSettings['ads_enabled'])) {
        $ads = [
            'enabled'        => true,
            'before_content' => $adSettings['ad_before_content'] ?? '',
            'after_content'  => $adSettings['ad_after_content']  ?? '',
        ];
    }
}

Theme::render('article', [
    'page_title'      => $post['title'],
    'meta_description'=> $post['meta_description'] ?? '',
    'meta_keywords'   => $post['meta_keywords']    ?? '',
    'og_image'        => $post['thumbnail_path']   ?? '',
    'post'            => $post,
    'ads'             => $ads,
    'tags'            => $pdo->query('SELECT id, name, slug FROM tags ORDER BY name')->fetchAll(),
    'head_extra'      => '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">',
]);

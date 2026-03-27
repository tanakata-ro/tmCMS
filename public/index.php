<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

$pdo  = Database::getInstance();
$page = max(1, (int)($_GET['page'] ?? 1));

// タグ絞り込み: ?tag=slug（推奨）または ?tag_id=N（後方互換）
$tagSlug = trim((string)($_GET['tag'] ?? ''));
$tagId   = null;
$tagName = null;

if ($tagSlug !== '') {
    $st = $pdo->prepare('SELECT id, name FROM tags WHERE slug = ?');
    $st->execute([$tagSlug]);
    $row = $st->fetch();
    if ($row) { $tagId = (int)$row['id']; $tagName = $row['name']; }
} elseif (isset($_GET['tag_id']) && ctype_digit((string)$_GET['tag_id'])) {
    $tagId = (int)$_GET['tag_id'];
    $st = $pdo->prepare('SELECT name FROM tags WHERE id = ?');
    $st->execute([$tagId]);
    $tagName = $st->fetchColumn() ?: null;
}

$offset  = ($page - 1) * POSTS_PER_PAGE;
$allTags = $pdo->query('SELECT id, name, slug FROM tags ORDER BY name')->fetchAll();

$join = ''; $where = "p.status = 'published'"; $params = [];
if ($tagId) {
    $join  = 'JOIN post_tags pt ON p.id = pt.post_id';
    $where .= ' AND pt.tag_id = :tag_id';
    $params[':tag_id'] = $tagId;
}

$cs = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM posts p {$join} WHERE {$where}");
$cs->execute($params);
$total      = (int)$cs->fetchColumn();
$totalPages = (int)ceil($total / POSTS_PER_PAGE);

$ss = $pdo->prepare("
    SELECT DISTINCT p.id, p.title, p.content, p.updated_at, p.author_id,
           COALESCE(u.nickname, u.username) AS author_name
    FROM posts p JOIN users u ON p.author_id = u.id {$join}
    WHERE {$where} ORDER BY p.updated_at DESC LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $ss->bindValue($k, $v);
$ss->bindValue(':lim', POSTS_PER_PAGE, PDO::PARAM_INT);
$ss->bindValue(':off', $offset,        PDO::PARAM_INT);
$ss->execute();
$rawPosts = $ss->fetchAll();

$parsedown = class_exists('Parsedown') ? new Parsedown() : null;
$posts = array_map(function ($p) use ($parsedown) {
    $raw = $parsedown
        ? $parsedown->text(class_exists('BbCode') ? BbCode::strip($p['content']) : $p['content'])
        : $p['content'];
    $plain        = strip_tags($raw);
    $p['preview'] = mb_substr($plain, 0, 120) . (mb_strlen($plain) > 120 ? '…' : '');
    return $p;
}, $rawPosts);

// ページネーションのbase_query: slug があれば slug、なければ tag_id
if ($tagSlug !== '' && $tagId) {
    $base = 'tag=' . urlencode($tagSlug) . '&';
} elseif ($tagId) {
    $base = 'tag_id=' . $tagId . '&';
} else {
    $base = '';
}

$pageTitle = $tagName ? 'タグ: ' . $tagName : SITE_NAME;

Theme::render('index', [
    'page_title'  => $pageTitle,
    'posts'       => $posts,
    'tags'        => $allTags,
    'filter_tag'  => $tagId,
    'pagination'  => [
        'page'        => $page,
        'total'       => $total,
        'total_pages' => $totalPages,
        'base_query'  => $base,
    ],
]);

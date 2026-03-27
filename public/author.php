<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

if (!function_exists('tmcms_pager')) {
    function tmcms_pager(array $pg): string {
        if ($pg['total_pages'] <= 1) return '';
        $cur   = $pg['page'];
        $total = $pg['total_pages'];
        $base  = $pg['base_query'];
        $html  = '<nav class="pagination" aria-label="ページ">';
        $html .= $cur > 1
            ? "<a href=\"?{$base}page=".($cur-1)."\" aria-label=\"前のページ\">&lsaquo;</a>"
            : '<span class="disabled" aria-hidden="true">&lsaquo;</span>';
        $pages = [];
        for ($i = 1; $i <= $total; $i++) {
            if ($i === 1 || $i === $total || ($i >= $cur - 2 && $i <= $cur + 2)) {
                $pages[] = $i;
            }
        }
        $prev = null;
        foreach ($pages as $p) {
            if ($prev !== null && $p - $prev > 1) {
                $html .= '<span class="ellipsis">…</span>';
            }
            $html .= $p === $cur
                ? "<span class=\"active\" aria-current=\"page\">{$p}</span>"
                : "<a href=\"?{$base}page={$p}\">{$p}</a>";
            $prev = $p;
        }
        $html .= $cur < $total
            ? "<a href=\"?{$base}page=".($cur+1)."\" aria-label=\"次のページ\">&rsaquo;</a>"
            : '<span class="disabled" aria-hidden="true">&rsaquo;</span>';
        $html .= '</nav>';
        return $html;
    }
}


$pdo      = Database::getInstance();
$authorId = (int)($_GET['id'] ?? 0);
if ($authorId === 0) { http_response_code(404); exit('著者が見つかりません。'); }

$aStmt = $pdo->prepare('SELECT id, username, nickname, bio, avatar_path FROM users WHERE id = ?');
$aStmt->execute([$authorId]); $author = $aStmt->fetch();
if (!$author) { http_response_code(404); exit('著者が見つかりません。'); }

$author['display_name'] = $author['nickname'] ?: $author['username'];

$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * POSTS_PER_PAGE;

$cs = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ? AND status = 'published'");
$cs->execute([$authorId]); $total = (int)$cs->fetchColumn();
$totalPages = (int)ceil($total / POSTS_PER_PAGE);

$ss = $pdo->prepare("
    SELECT id, title, content, updated_at, view_count, author_id
    FROM posts WHERE author_id = ? AND status = 'published'
    ORDER BY updated_at DESC LIMIT ? OFFSET ?
");
$ss->execute([$authorId, POSTS_PER_PAGE, $offset]);
$rawPosts = $ss->fetchAll();

$parsedown = class_exists('Parsedown') ? new Parsedown() : null;
$posts = array_map(function($p) use ($parsedown) {
    $raw = $parsedown ? $parsedown->text(class_exists('BbCode') ? BbCode::strip($p['content']) : $p['content']) : $p['content'];
    $plain = strip_tags($raw);
    $p['preview'] = mb_substr($plain, 0, 80) . (mb_strlen($plain) > 80 ? '…' : '');
    return $p;
}, $rawPosts);

$allTags = $pdo->query('SELECT id, name, slug FROM tags ORDER BY name')->fetchAll();

Theme::render('author', [
    'page_title' => $author['display_name'] . 'の記事',
    'author'     => $author,
    'posts'      => $posts,
    'tags'       => $allTags,
    'pagination' => [
        'page'        => $page,
        'total'       => $total,
        'total_pages' => $totalPages,
        'base_query'  => 'id=' . $authorId . '&',
    ],
]);

<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin', 'editor', 'reviewer']);

$pdo    = Database::getInstance();
$userId = Auth::id();
$role   = Auth::role();
$flash  = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    if ($role !== 'reviewer') {
        $pdo->beginTransaction();
        try {
            if (isset($_POST['create_group']) && trim((string)$_POST['group_name']) !== '') {
                $pdo->prepare('INSERT INTO article_groups (user_id, name) VALUES (?, ?)')->execute([$userId, trim((string)$_POST['group_name'])]);
                $_SESSION['flash'] = 'グループを作成しました。';
            }
            if (isset($_POST['delete_group']) && (int)($_POST['group_id'] ?? 0) > 0) {
                $gid = (int)$_POST['group_id'];
                $pdo->prepare('UPDATE posts SET group_id = NULL WHERE group_id = ? AND author_id = ?')->execute([$gid, $userId]);
                $pdo->prepare('DELETE FROM article_groups WHERE id = ? AND user_id = ?')->execute([$gid, $userId]);
                $_SESSION['flash'] = 'グループを削除しました。';
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); }
        header('Location: dashboard.php' . (isset($_GET['group_id']) ? '?group_id=' . urlencode((string)$_GET['group_id']) : ''));
        exit;
    }
}

const PER = POSTS_PER_PAGE;
$allPage = max(1, (int)($_GET['all_page'] ?? 1));
$myPage  = max(1, (int)($_GET['my_page']  ?? 1));
$groupId = $_GET['group_id'] ?? 'all';

$reviewPosts = $pdo->query("SELECT p.id,p.title,p.status,p.updated_at,p.view_count,p.author_id,COALESCE(u.nickname,u.username) AS author_name FROM posts p JOIN users u ON p.author_id=u.id WHERE p.status='review' ORDER BY p.updated_at DESC")->fetchAll();

$allPosts = []; $allPages = 0;
if ($role === 'admin') {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status!='review'")->fetchColumn();
    $allPages = (int)ceil($total / PER);
    $s = $pdo->prepare("SELECT p.id,p.title,p.status,p.updated_at,p.view_count,p.access_password,p.author_id,COALESCE(u.nickname,u.username) AS author_name FROM posts p JOIN users u ON p.author_id=u.id WHERE p.status!='review' ORDER BY p.updated_at DESC LIMIT ? OFFSET ?");
    $s->execute([PER, ($allPage-1)*PER]); $allPosts = $s->fetchAll();
}

$myPosts = []; $myPages = 0; $myGroups = [];
if ($role !== 'reviewer') {
    $sg = $pdo->prepare('SELECT id,name FROM article_groups WHERE user_id=? ORDER BY name');
    $sg->execute([$userId]); $myGroups = $sg->fetchAll();
    $where = ['p.author_id=?',"p.status!='review'"]; $params = [$userId];
    if ($groupId==='none') $where[]='p.group_id IS NULL';
    elseif (ctype_digit((string)$groupId)) { $where[]='p.group_id=?'; $params[]=(int)$groupId; }
    $cond = implode(' AND ', $where);
    $sc = $pdo->prepare("SELECT COUNT(*) FROM posts p WHERE {$cond}"); $sc->execute($params);
    $myTotal = (int)$sc->fetchColumn(); $myPages = (int)ceil($myTotal/PER);
    $sm = $pdo->prepare("SELECT p.id,p.title,p.status,p.updated_at,p.view_count,p.access_password,p.group_id,p.author_id,g.name AS group_name FROM posts p LEFT JOIN article_groups g ON p.group_id=g.id WHERE {$cond} ORDER BY p.updated_at DESC LIMIT ? OFFSET ?");
    $sm->execute([...$params, PER, ($myPage-1)*PER]); $myPosts = $sm->fetchAll();
}

function sc(string $st): string {
    $m = ['published'=>['公開済み','sc-published'],'draft'=>['下書き','sc-draft'],'review'=>['評価待ち','sc-review'],'unlisted'=>['限定公開','sc-unlisted'],'scheduled'=>['予約公開','sc-scheduled']];
    [$l,$c] = $m[$st] ?? ['不明','sc-draft'];
    return "<span class=\"status-chip {$c}\">{$l}</span>";
}
function pager(string $k, int $total, int $cur): string {
    if ($total <= 1) return '';
    $h = '<div class="pagination">';
    // 前へ
    $pp = array_merge($_GET, [$k => $cur - 1]);
    $h .= $cur > 1
        ? '<a href="?' . htmlspecialchars(http_build_query($pp)) . '">&lsaquo;</a>'
        : '<span class="disabled">&lsaquo;</span>';
    // ページ番号: 1 … 4 5 6 … 10
    $pages = [];
    for ($i = 1; $i <= $total; $i++) {
        if ($i === 1 || $i === $total || ($i >= $cur - 2 && $i <= $cur + 2)) {
            $pages[] = $i;
        }
    }
    $prev = null;
    foreach ($pages as $p) {
        if ($prev !== null && $p - $prev > 1) $h .= '<span class="ellipsis">…</span>';
        $qa = array_merge($_GET, [$k => $p]);
        $h .= $p === $cur
            ? "<span class=\"active\">{$p}</span>"
            : '<a href="?' . htmlspecialchars(http_build_query($qa)) . "\">{$p}</a>";
        $prev = $p;
    }
    // 次へ
    $np = array_merge($_GET, [$k => $cur + 1]);
    $h .= $cur < $total
        ? '<a href="?' . htmlspecialchars(http_build_query($np)) . '">&rsaquo;</a>'
        : '<span class="disabled">&rsaquo;</span>';
    return $h . '</div>';
}
function plist(array $posts, int $uid, string $role, bool $showAuthor=false): string {
    if (!$posts) return '<p style="padding:.75rem 1.25rem;color:#9e9e9e;font-size:.875rem">記事がありません。</p>';
    $h='';
    foreach ($posts as $p) {
        $canEdit = ($role==='admin'||$uid===(int)$p['author_id']);
        $views   = (int)($p['view_count']??0);
        $h.='<div class="post-item"><div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem">';
        $h.='<span class="post-item__title">'.Security::e(mb_strimwidth($p['title'],0,60,'…')).'</span>';
        $h.='<div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">';
        if ($showAuthor && isset($p['author_name'])) $h.='<span style="font-size:.78rem;color:#9e9e9e">'.Security::e($p['author_name']).'</span>';
        $h.=sc($p['status']);
        $h.='<span style="font-size:.75rem;color:#9e9e9e">'.date('Y/m/d',strtotime($p['updated_at'])).'</span>';
        $h.='<span style="font-size:.75rem;color:#9e9e9e">&#128065; '.$views.'</span>';
        $h.='</div></div><div class="post-item__actions">';
        if ($canEdit) $h.='<a href="post_edit.php?id='.(int)$p['id'].'" class="btn btn--outline btn--sm">編集</a>';
        if ($p['status']==='unlisted'&&!empty($p['access_password'])) {
            $url='../public/article.php?id='.(int)$p['id'].'&pswd='.urlencode($p['access_password']);
            $h.='<a href="'.Security::e($url).'" target="_blank" class="btn btn--outline btn--sm">限定リンク</a>';
        } else {
            $lbl=$p['status']==='review'?'評価する':'プレビュー';
            $h.='<a href="../public/article.php?id='.(int)$p['id'].'" target="_blank" class="btn btn--outline btn--sm">'.$lbl.'</a>';
        }
        if ($canEdit) {
            $tok=Csrf::token();
            $h.='<form method="post" action="post_delete.php" onsubmit="return confirm(\'削除しますか？\')" style="display:inline"><input type="hidden" name="_csrf" value="'.Security::e($tok).'"><input type="hidden" name="post_id" value="'.(int)$p['id'].'"><button type="submit" class="btn btn--danger btn--sm">削除</button></form>';
        }
        $h.='</div></div>';
    }
    return $h;
}

// ─── コンテンツ生成 ───────────────────────────────────────────
ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>

<div class="accordion elev-1">
  <button class="accordion__header" onclick="toggle(this)">
    評価待ちの記事
    <?php if ($reviewPosts): ?><span class="chip chip--dark" style="margin-left:.5rem"><?= count($reviewPosts) ?></span><?php endif; ?>
    <span class="accordion__arrow" style="margin-left:auto">&#9660;</span>
  </button>
  <div class="accordion__body"><?= plist($reviewPosts,$userId,$role,true) ?></div>
</div>

<?php if ($role==='admin'): ?>
<div class="accordion elev-1">
  <button class="accordion__header" onclick="toggle(this)">すべての記事<span class="accordion__arrow" style="margin-left:auto">&#9660;</span></button>
  <div class="accordion__body"><?= plist($allPosts,$userId,$role,true) ?><?= pager('all_page',$allPages,$allPage) ?></div>
</div>
<?php endif; ?>

<?php if ($role!=='reviewer'): ?>
<div class="accordion elev-1">
  <button class="accordion__header" onclick="toggle(this)">記事グループ管理<span class="accordion__arrow" style="margin-left:auto">&#9660;</span></button>
  <div class="accordion__body"><div style="padding:.75rem 1.25rem">
    <?php if ($myGroups): ?><table class="tbl" style="margin-bottom:1rem">
      <?php foreach ($myGroups as $g): ?><tr><td><?= Security::e($g['name']) ?></td><td style="width:80px;text-align:right">
        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')"><?= Csrf::field() ?><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>"><button type="submit" name="delete_group" class="btn btn--danger btn--sm">削除</button></form>
      </td></tr><?php endforeach; ?>
    </table><?php endif; ?>
    <form method="post" style="display:flex;gap:.5rem"><?= Csrf::field() ?>
      <input type="text" name="group_name" placeholder="新しいグループ名" style="flex:1;padding:.5rem .75rem;border:1px solid #bdbdbd;border-radius:4px;font-family:inherit;font-size:.875rem">
      <button type="submit" name="create_group" class="btn btn--primary btn--sm">作成</button>
    </form>
  </div></div>
</div>

<div class="accordion elev-1" id="my-acc">
  <button class="accordion__header" onclick="toggle(this)">自分の記事<span class="accordion__arrow" style="margin-left:auto">&#9660;</span></button>
  <div class="accordion__body" id="my-body">
    <div class="group-tabs" style="padding:.75rem 1.25rem 0">
      <?php $tabs=[['all','すべて'],['none','未分類']]; foreach ($myGroups as $g) $tabs[]=[(string)$g['id'],$g['name']]; ?>
      <?php foreach ($tabs as [$v,$l]): ?><a href="?group_id=<?= urlencode($v) ?>" class="group-tab<?= $groupId===$v?' active':'' ?>"><?= Security::e($l) ?></a><?php endforeach; ?>
    </div>
    <?= plist($myPosts,$userId,$role) ?><?= pager('my_page',$myPages,$myPage) ?>
  </div>
</div>
<?php endif; ?>

<script>
function toggle(btn){btn.classList.toggle('open');const b=btn.nextElementSibling;b.style.maxHeight=b.style.maxHeight?null:b.scrollHeight+'px';}
document.addEventListener('DOMContentLoaded',()=>{const b=document.getElementById('my-body');if(b){b.style.maxHeight=b.scrollHeight+'px';b.previousElementSibling.classList.add('open');}});
</script>
<?php
Theme::renderAdmin('ダッシュボード', ob_get_clean());

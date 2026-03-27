<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin']);
$pdo = Database::getInstance();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? '')); $slug = trim((string)($_POST['slug'] ?? ''));
        if ($name && $slug) {
            try { $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]); $_SESSION['flash'] = 'タグを追加しました。'; }
            catch (Throwable) { $_SESSION['flash'] = 'エラー: 同じ名前またはURL名のタグが存在します。'; }
        }
    }
    if ($action === 'delete' && (int)($_POST['tag_id'] ?? 0) > 0) {
        $tid = (int)$_POST['tag_id'];
        $pdo->prepare('DELETE FROM post_tags WHERE tag_id = ?')->execute([$tid]);
        $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$tid]);
        $_SESSION['flash'] = 'タグを削除しました。';
    }
    header('Location: tag_manager.php'); exit;
}
$tags = $pdo->query('SELECT t.id, t.name, t.slug, COUNT(pt.post_id) AS cnt FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id GROUP BY t.id ORDER BY t.name')->fetchAll();

ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">タグを追加</div>
  <form method="post" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field" style="flex:1;min-width:120px;margin:0"><label>タグ名</label><input type="text" name="name" required></div>
    <div class="field" style="flex:1;min-width:120px;margin:0"><label>URL名（英数字・ハイフン）</label><input type="text" name="slug" pattern="[a-zA-Z0-9\-_]+" title="英数字とハイフン・アンダースコアのみ使えます" placeholder="例: php-tips" required></div>
    <button type="submit" class="btn btn--primary btn--sm">追加</button>
  </form>
</div></div>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">タグ一覧</div>
  <?php if ($tags): ?>
  <table class="tbl">
    <thead><tr><th>名前</th><th>URL名</th><th>記事数</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tags as $t): ?>
      <tr>
        <td><?= Security::e($t['name']) ?></td>
        <td style="color:#9e9e9e;font-size:.82rem"><?= Security::e($t['slug']) ?></td>
        <td><?= (int)$t['cnt'] ?></td>
        <td><form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="tag_id" value="<?= (int)$t['id'] ?>">
          <button type="submit" class="btn btn--danger btn--sm">削除</button>
        </form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p style="color:#9e9e9e;font-size:.875rem">タグがまだありません。</p><?php endif; ?>
</div></div>
<?php
Theme::renderAdmin('タグ管理', ob_get_clean());

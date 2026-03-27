<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin']);
$pdo = Database::getInstance(); $myId = Auth::id();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $u = trim((string)($_POST['username'] ?? '')); $p = (string)($_POST['password'] ?? '');
        $r = (string)($_POST['role'] ?? 'editor'); $n = trim((string)($_POST['nickname'] ?? ''));
        if ($u === '' || strlen($p) < 8) { $_SESSION['flash'] = 'ユーザー名と8文字以上のパスワードが必要です。'; }
        elseif (!in_array($r, ['admin','editor','reviewer'], true)) { $_SESSION['flash'] = '無効なロールです。'; }
        else {
            try { $pdo->prepare('INSERT INTO users (username, password_hash, nickname, role) VALUES (?,?,?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT), $n ?: null, $r]); $_SESSION['flash'] = 'ユーザーを作成しました。'; }
            catch (Throwable) { $_SESSION['flash'] = 'エラー: 同じユーザー名が存在します。'; }
        }
    }
    if ($action === 'delete' && (int)($_POST['uid'] ?? 0) > 0) {
        $uid = (int)$_POST['uid'];
        if ($uid === $myId) { $_SESSION['flash'] = '自分自身は削除できません。'; }
        else { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]); $_SESSION['flash'] = 'ユーザーを削除しました。'; }
    }
    if ($action === 'change_role' && (int)($_POST['uid'] ?? 0) > 0) {
        $uid = (int)$_POST['uid']; $r = (string)($_POST['role'] ?? '');
        if ($uid !== $myId && in_array($r, ['admin','editor','reviewer'], true)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$r, $uid]); $_SESSION['flash'] = 'ロールを変更しました。';
        }
    }
    header('Location: user_manager.php'); exit;
}
$users = $pdo->query('SELECT id, username, nickname, role, created_at FROM users ORDER BY id')->fetchAll();

ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">ユーザーを追加</div>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <div class="field"><label>ユーザー名</label><input type="text" name="username" required autocomplete="off"></div>
      <div class="field"><label>表示名（任意）</label><input type="text" name="nickname"></div>
      <div class="field"><label>パスワード（8文字以上）</label><input type="password" name="password" required autocomplete="new-password"></div>
      <div class="field"><label>ロール</label>
        <select name="role">
          <option value="editor">editor（記事投稿）</option>
          <option value="admin">admin（全権限）</option>
          <option value="reviewer">reviewer（評価のみ）</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn--primary btn--sm">追加</button>
  </form>
</div></div>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">ユーザー一覧</div>
  <table class="tbl">
    <thead><tr><th>ID</th><th>ユーザー名</th><th>表示名</th><th>ロール</th><th>作成日</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="color:#9e9e9e;font-size:.8rem"><?= (int)$u['id'] ?></td>
        <td><?= Security::e($u['username']) ?></td>
        <td style="color:#757575;font-size:.85rem"><?= Security::e($u['nickname'] ?? '') ?></td>
        <td><span class="chip"><?= Security::e($u['role']) ?></span></td>
        <td style="font-size:.78rem;color:#9e9e9e"><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
        <td>
          <?php if ((int)$u['id'] !== $myId): ?>
          <form method="post" style="display:inline-flex;gap:.35rem;align-items:center">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <select name="role" style="padding:.28rem .5rem;font-size:.8rem;border:1px solid #bdbdbd;border-radius:4px;width:auto">
              <?php foreach (['admin','editor','reviewer'] as $r): ?>
              <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn--outline btn--sm">変更</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="btn btn--danger btn--sm">削除</button>
          </form>
          <?php else: ?><span style="font-size:.78rem;color:#9e9e9e">（自分）</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php
Theme::renderAdmin('ユーザー管理', ob_get_clean());

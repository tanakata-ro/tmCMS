<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

$pdo  = Database::getInstance();
$myId = Auth::id();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$myId]); $me = $stmt->fetch();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $bio      = trim((string)($_POST['bio']      ?? ''));
        $pdo->prepare('UPDATE users SET nickname = ?, bio = ? WHERE id = ?')
            ->execute([$nickname ?: null, $bio ?: null, $myId]);
        $_SESSION['flash'] = 'プロフィールを更新しました。';
        header('Location: profile.php'); exit;
    }

    if ($action === 'upload_avatar' && !empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $result = Security::validateUpload($_FILES['avatar']);
        if ($result['ok']) {
            $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $base = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads/';
            $dir  = $base . 'avatars/';
            if (!is_dir($dir)) { mkdir($dir, 0755, true); file_put_contents($dir.'.htaccess',"Options -ExecCGI\nAddType text/plain .php\n"); }
            $fname = 'u' . $myId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!empty($me['avatar_path'])) { $old=$dir.basename($me['avatar_path']); if(file_exists($old))unlink($old); }
            move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $fname);
            $scheme = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on')?'https':'http';
            $bp = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/\\');
            $url = $scheme.'://'.$_SERVER['HTTP_HOST'].$bp.'/uploads/avatars/'.$fname;
            $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$url, $myId]);
            $_SESSION['flash'] = 'アイコンを更新しました。';
        } else { $errors[] = $result['error']; }
        if (empty($errors)) { header('Location: profile.php'); exit; }
    }

    if ($action === 'change_password') {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password']     ?? '');
        $cfm = (string)($_POST['confirm_password'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) $errors[] = '現在のパスワードが正しくありません。';
        if (strlen($new) < 8)  $errors[] = '新しいパスワードは8文字以上にしてください。';
        if ($new !== $cfm)     $errors[] = 'パスワードが一致しません。';
        if (empty($errors)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $myId]);
            Auth::logout(); header('Location: login.php'); exit;
        }
    }
}
$stmt->execute([$myId]); $me = $stmt->fetch();

ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert--error"><ul style="margin:0;padding-left:1.25rem">
  <?php foreach ($errors as $e): ?><li><?= Security::e($e) ?></li><?php endforeach; ?>
</ul></div><?php endif; ?>

<div style="max-width:560px;margin:0 auto">

<div class="card elev-1"><div class="card__body">
  <div class="card__label">アイコン画像</div>
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
    <?php if (!empty($me['avatar_path'])): ?>
    <img src="<?= Security::e($me['avatar_path']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #e0e0e0">
    <?php else: ?>
    <div style="width:64px;height:64px;border-radius:50%;background:#eeeeee;display:flex;align-items:center;justify-content:center;border:1px solid #e0e0e0">
      <svg style="width:32px;height:32px;fill:#9e9e9e" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
    </div>
    <?php endif; ?>
    <div>
      <p style="font-size:.8rem;color:#757575;margin:0 0 .5rem">JPG/PNG/GIF/WebP、10MBまで</p>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:center">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" name="avatar" accept="image/*" required style="font-size:.82rem">
        <button type="submit" class="btn btn--primary btn--sm">アップロード</button>
      </form>
    </div>
  </div>
</div></div>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">プロフィール</div>
  <p style="font-size:.82rem;color:#757575;background:#fafafa;padding:.5rem .75rem;border-radius:4px;margin:0 0 1rem">
    ユーザー名: <strong><?= Security::e($me['username']) ?></strong> / ロール: <strong><?= Security::e($me['role']) ?></strong>
  </p>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="update_profile">
    <div class="field"><label>表示名</label><input type="text" name="nickname" value="<?= Security::e($me['nickname'] ?? '') ?>"></div>
    <div class="field"><label>一言（記事末尾の著者情報に表示）</label><input type="text" name="bio" value="<?= Security::e($me['bio'] ?? '') ?>" maxlength="200"></div>
    <button type="submit" class="btn btn--primary">更新</button>
  </form>
</div></div>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">パスワード変更</div>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="field"><label>現在のパスワード</label><input type="password" name="current_password" required></div>
    <div class="field"><label>新しいパスワード（8文字以上）</label><input type="password" name="new_password" required></div>
    <div class="field"><label>新しいパスワード（確認）</label><input type="password" name="confirm_password" required></div>
    <button type="submit" class="btn btn--danger">パスワードを変更してログアウト</button>
  </form>
</div></div>

</div>
<?php
Theme::renderAdmin('プロフィール', ob_get_clean());

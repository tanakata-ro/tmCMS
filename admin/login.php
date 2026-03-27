<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::start();
if (Auth::check()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    if ($u === '' || $p === '') {
        $error = 'ユーザー名とパスワードを入力してください。';
    } else {
        $r = Auth::login($u, $p, Database::getInstance());
        if ($r['ok']) { header('Location: dashboard.php'); exit; }
        $error = $r['error'];
    }
}
$tok = Csrf::token();

// ログインページはレイアウトを使わず直接出力（未ログイン時はnavが使えないため）
Theme::init();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>ログイン — <?= Security::e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Noto+Sans+JP:wght@400;500&display=swap" rel="stylesheet">
<?php
// ログイン画面: admin/style.css を直接読み込む（テーマCSSより確実）
$__loginCss = __DIR__ . '/style.css';
$__themeCss = dirname(__DIR__) . '/themes/' . (defined('ACTIVE_THEME') ? ACTIVE_THEME : 'default') . '/admin_style.css';
if (is_file($__themeCss)) $__loginCss = $__themeCss;
?>
<style><?= file_get_contents($__loginCss) ?></style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card elev-2">
    <div class="login-card__logo">
      <h1><?= Security::e(SITE_NAME) ?></h1>
      <p>管理画面</p>
    </div>
    <?php if ($error): ?><div class="alert alert--error"><?= Security::e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= Security::e($tok) ?>">
      <div class="field">
        <label for="u">ユーザー名</label>
        <input type="text" id="u" name="username" value="<?= Security::e($_POST['username'] ?? '') ?>" autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="pw">パスワード</label>
        <input type="password" id="pw" name="password" autocomplete="current-password">
      </div>
      <div style="margin-top:1.25rem">
        <button type="submit" class="btn btn--primary btn--full btn--lg">ログイン</button>
      </div>
    </form>
  </div>
</div>
</body></html>

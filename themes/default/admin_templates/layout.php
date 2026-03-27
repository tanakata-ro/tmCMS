<?php
// 管理画面CSSのパスを解決
// layout.php は themes/default/admin_templates/ にあるので
// admin/style.css は 3階層上の admin/ ディレクトリにある
$__adminCssPath = dirname(__DIR__, 3) . '/admin/style.css';
// テーマの admin_style.css があればそちらを優先
$__themeCssPath = dirname(__DIR__) . '/admin_style.css';
if (is_file($__themeCssPath)) {
    $__adminCssPath = $__themeCssPath;
}
$__css = is_file($__adminCssPath) ? file_get_contents($__adminCssPath) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= Security::e($tmcms['page_title']) ?> — <?= Security::e($tmcms['site_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Noto+Sans+JP:wght@400;500&display=swap" rel="stylesheet">
<?php if ($__css !== ''): ?>
<style><?= $__css ?></style>
<?php elseif (!empty($tmcms['admin_css'])): ?>
<link rel="stylesheet" href="<?= Security::e($tmcms['admin_css']) ?>">
<?php endif; ?>
<?= $tmcms['head_extra'] ?? '' ?>
</head>
<body>
<header class="app-bar">
  <span class="app-bar__title"><?= Security::e($tmcms['site_name']) ?></span>
  <div class="app-bar__actions">
    <?php foreach ($tmcms['nav_items'] ?? [] as $item): ?>
    <a href="<?= Security::e($item['href']) ?>"><?= Security::e($item['label']) ?></a>
    <?php endforeach; ?>
    <form method="post" action="logout.php" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= Security::e(Csrf::token()) ?>">
      <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.7);font-size:.875rem;cursor:pointer;font-family:inherit;padding:.35rem .6rem">ログアウト</button>
    </form>
  </div>
</header>
<div class="page">
  <?= $tmcms['content'] ?>
</div>
</body>
</html>

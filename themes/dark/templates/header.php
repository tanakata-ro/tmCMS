<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tmcms['page_title']) ? tmcms_e($tmcms['page_title']) . ' — ' : '' ?><?= tmcms_e($tmcms['site_name']) ?></title>
<?php if (!empty($tmcms['meta_description'])): ?>
<meta name="description" content="<?= tmcms_e($tmcms['meta_description']) ?>">
<?php endif; ?>
<?php if (!empty($tmcms['meta_keywords'])): ?>
<meta name="keywords" content="<?= tmcms_e($tmcms['meta_keywords']) ?>">
<?php endif; ?>
<?php if (!empty($tmcms['og_image'])): ?>
<meta property="og:image" content="<?= tmcms_e($tmcms['og_image']) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= tmcms_e($tmcms['site_name']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Noto+Sans+JP:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= tmcms_e($tmcms['theme_url']) ?>/style.css">
<?= $tmcms['head_extra'] ?? '' ?>
</head>
<body>
<header class="site-header">
  <a href="<?= tmcms_url('public/') ?>" class="site-header__name"><?= tmcms_e($tmcms['site_name']) ?></a>
</header>

<?php tmcms_header($tmcms); ?>

<div class="container">
  <main class="main-col">
    <h1 style="font-size:1.1rem;font-weight:500;color:#424242;margin:0 0 1rem">
      <?= tmcms_e($tmcms['page_title'] ?? $tmcms['site_name']) ?>
    </h1>

    <?php if (!empty($tmcms['filter_tag'])): ?>
    <p style="font-size:.875rem;margin:0 0 1rem">
      <a href="<?= tmcms_url('public/') ?>" style="color:#424242">すべての記事に戻る</a>
    </p>
    <?php endif; ?>

    <?php if (empty($tmcms['posts'])): ?>
    <p style="color:#9e9e9e;font-size:.9rem">記事がまだありません。</p>
    <?php else: ?>
    <?php foreach ($tmcms['posts'] as $post): ?>
    <article class="post-card">
      <h2 class="post-card__title">
        <a href="<?= tmcms_url('public/article.php?id=' . (int)$post['id']) ?>">
          <?= tmcms_e($post['title']) ?>
        </a>
      </h2>
      <div class="post-card__meta">
        <a href="<?= tmcms_url('public/author.php?id=' . (int)$post['author_id']) ?>"
           style="color:#424242;text-decoration:none;font-weight:500">
          <?= tmcms_e($post['author_name']) ?>
        </a>
        &nbsp;·&nbsp;
        <?= date('Y年m月d日', strtotime($post['updated_at'])) ?>
      </div>
      <p class="post-card__preview"><?= tmcms_e($post['preview']) ?></p>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php echo tmcms_pager($tmcms['pagination']); ?>
  </main>

  <?php tmcms_sidebar($tmcms); ?>
</div>

<?php tmcms_footer($tmcms); ?>

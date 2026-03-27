<?php tmcms_header($tmcms); ?>

<div class="container">
  <main class="main-col">

    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;display:flex;gap:1.25rem;align-items:flex-start;border:1px solid #f0f0f0">
      <?php if (!empty($tmcms['author']['avatar_path'])): ?>
      <img src="<?= tmcms_e($tmcms['author']['avatar_path']) ?>"
           style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid #e0e0e0;flex-shrink:0" alt="">
      <?php else: ?>
      <div style="width:72px;height:72px;border-radius:50%;background:#eeeeee;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #e0e0e0">
        <svg style="width:36px;height:36px;fill:#9e9e9e" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
      </div>
      <?php endif; ?>
      <div>
        <h1 style="margin:0 0 .35rem;font-size:1.2rem;font-weight:700">
          <?= tmcms_e($tmcms['author']['display_name']) ?>
        </h1>
        <?php if (!empty($tmcms['author']['bio'])): ?>
        <p style="margin:0;font-size:.9rem;color:#616161;line-height:1.6">
          <?= tmcms_e($tmcms['author']['bio']) ?>
        </p>
        <?php endif; ?>
        <p style="margin:.5rem 0 0;font-size:.8rem;color:#9e9e9e">
          記事数: <?= (int)$tmcms['pagination']['total'] ?>
        </p>
      </div>
    </div>

    <h2 style="font-size:1rem;font-weight:500;color:#424242;margin:0 0 1rem;border-bottom:2px solid #212121;padding-bottom:.4rem">
      投稿した記事
    </h2>

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

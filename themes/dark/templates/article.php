<?php tmcms_header($tmcms); ?>

<div class="article-wrap">
  <article class="article-card">
    <h1 class="article__title"><?= tmcms_e($tmcms['post']['title']) ?></h1>

    <div class="article__meta">
      <?php if (!empty($tmcms['post']['avatar_path'])): ?>
      <img src="<?= tmcms_e($tmcms['post']['avatar_path']) ?>" class="avatar" alt="">
      <?php endif; ?>
      <a href="<?= tmcms_url('public/author.php?id=' . (int)$tmcms['post']['author_id']) ?>"
         style="color:#424242;text-decoration:none;font-weight:500">
        <?= tmcms_e($tmcms['post']['author_name']) ?>
      </a>
      <span>·</span>
      <span><?= date('Y年m月d日', strtotime($tmcms['post']['updated_at'])) ?></span>
      <span>·</span>
    </div>

    <?php if (!empty($tmcms['post']['tags'])): ?>
    <div class="article__tags">
      <?php foreach ($tmcms['post']['tags'] as $t): ?>
      <a href="<?= tmcms_url('public/?tag=' . urlencode($t['slug'] ?: (string)$t['id'])) ?>" class="article__tag">
        <?= tmcms_e($t['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* 記事上部広告 */
    if (!empty($tmcms['ads']['enabled']) && !empty($tmcms['ads']['before_content'])): ?>
    <div class="tmcms-ad tmcms-ad--before">
      <?= $tmcms['ads']['before_content'] ?>
    </div>
    <?php endif; ?>

    <div class="article__content"><?= $tmcms['post']['html'] ?></div>

    <?php /* 記事下部広告 */
    if (!empty($tmcms['ads']['enabled']) && !empty($tmcms['ads']['after_content'])): ?>
    <div class="tmcms-ad tmcms-ad--after">
      <?= $tmcms['ads']['after_content'] ?>
    </div>
    <?php endif; ?>

    <!-- 著者情報ブロック -->
    <div class="author-bio">
      <?php if (!empty($tmcms['post']['avatar_path'])): ?>
      <img src="<?= tmcms_e($tmcms['post']['avatar_path']) ?>" class="author-bio__avatar" alt="">
      <?php else: ?>
      <div class="author-bio__placeholder">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
      </div>
      <?php endif; ?>
      <div>
        <p class="author-bio__name">
          <a href="<?= tmcms_url('public/author.php?id=' . (int)$tmcms['post']['author_id']) ?>"
             style="color:#212121;text-decoration:none">
            <?= tmcms_e($tmcms['post']['author_name']) ?>
          </a>
        </p>
        <?php if (!empty($tmcms['post']['bio'])): ?>
        <p class="author-bio__bio"><?= tmcms_e($tmcms['post']['bio']) ?></p>
        <?php else: ?>
        <p class="author-bio__bio-empty">（プロフィール未設定）</p>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <a href="<?= tmcms_url('public/') ?>" class="back-link">&#8592; 一覧に戻る</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>hljs.highlightAll();</script>

<?php tmcms_footer($tmcms); ?>

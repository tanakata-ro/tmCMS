<aside class="aside-col">
  <div class="sidebar-box">
    <p class="sidebar-box__title">タグ</p>
    <ul>
      <li><a href="<?= tmcms_url('public/') ?>">すべて</a></li>
      <?php foreach ($tmcms['tags'] ?? [] as $t): ?>
      <li><a href="<?= tmcms_url('public/?tag=' . urlencode($t['slug'] ?: (string)$t['id'])) ?>"><?= tmcms_e($t['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</aside>

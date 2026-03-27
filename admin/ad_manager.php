<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin']);

$pdo   = Database::getInstance();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    foreach (['ads_enabled', 'ad_before_content', 'ad_after_content'] as $k) {
        $v = (string)($_POST[$k] ?? '');
        $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")
            ->execute([$k, $v]);
    }
    $_SESSION['flash'] = '広告設定を保存しました。';
    header('Location: ad_manager.php'); exit;
}

$stmt = $pdo->query("SELECT key, value FROM settings WHERE key IN ('ads_enabled','ad_before_content','ad_after_content')");
$settings = array_column($stmt->fetchAll(), 'value', 'key');

ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>

<div class="card elev-1"><div class="card__body">
  <div class="card__label">広告設定</div>
  <p style="font-size:.875rem;color:#757575;margin:0 0 1rem">
    ここで設定した広告コードは、各記事の編集画面で「広告を表示する」をONにした記事に表示されます。
  </p>
  <form method="post">
    <?= Csrf::field() ?>
    <div class="field">
      <label style="display:flex;align-items:center;gap:.5rem;font-weight:500;cursor:pointer">
        <input type="checkbox" name="ads_enabled" value="1" <?= !empty($settings['ads_enabled']) ? 'checked' : '' ?>>
        広告機能を有効にする
      </label>
      <p class="hint">OFFにするとすべての広告が非表示になります。</p>
    </div>
    <hr class="divider">
    <div class="field">
      <label>記事本文の前に表示する広告コード</label>
      <textarea name="ad_before_content" style="min-height:100px;font-family:monospace;font-size:.82rem;border:1px solid #bdbdbd;border-radius:4px;padding:.6rem .75rem;width:100%" placeholder="Google AdSense などのコードを貼り付け"><?= Security::e($settings['ad_before_content'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label>記事本文の後に表示する広告コード</label>
      <textarea name="ad_after_content" style="min-height:100px;font-family:monospace;font-size:.82rem;border:1px solid #bdbdbd;border-radius:4px;padding:.6rem .75rem;width:100%" placeholder="Google AdSense などのコードを貼り付け"><?= Security::e($settings['ad_after_content'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn--primary">保存</button>
  </form>
</div></div>

<div class="card elev-1" style="margin-top:1rem"><div class="card__body">
  <div class="card__label">記事ごとの広告設定について</div>
  <p style="font-size:.875rem;color:#757575;line-height:1.7;margin:0">
    各記事の編集画面（公開設定）に「この記事に広告を表示する」チェックボックスがあります。
  </p>
</div></div>
<?php
Theme::renderAdmin('広告管理', ob_get_clean());

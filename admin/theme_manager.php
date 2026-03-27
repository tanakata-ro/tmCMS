<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin']);

$pdo   = Database::getInstance();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'activate' && !empty($_POST['slug'])) {
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_POST['slug']);
        $dir  = dirname(__DIR__) . '/themes/' . $slug . '/';
        if (is_dir($dir) && file_exists($dir . 'theme.json')) {
            $configFile = dirname(__DIR__) . '/core/config.php';
            $config = file_get_contents($configFile);
            $config = preg_replace("/define\('ACTIVE_THEME',\s*'[^']*'\);/", "define('ACTIVE_THEME', '" . addslashes($slug) . "');", $config);
            file_put_contents($configFile, $config);
            $_SESSION['flash'] = 'テーマ「' . $slug . '」を有効化しました。';
        }
        header('Location: theme_manager.php'); exit;
    }

    if ($action === 'upload_zip' && !empty($_FILES['theme_zip'])) {
        $file = $_FILES['theme_zip'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
                $errors[] = 'ZIPファイルのみアップロードできます。';
            } else {
                $result = Theme::installFromZip($file['tmp_name']);
                if ($result['ok']) { $_SESSION['flash'] = 'テーマをインストールしました。'; header('Location: theme_manager.php'); exit; }
                else { $errors[] = $result['error']; }
            }
        }
    }

    if ($action === 'install_github' && !empty($_POST['github_url'])) {
        $result = Theme::installFromGitHub(trim((string)$_POST['github_url']));
        if ($result['ok']) { $_SESSION['flash'] = 'テーマをインストールしました。'; header('Location: theme_manager.php'); exit; }
        else { $errors[] = $result['error']; }
    }

    if ($action === 'delete' && !empty($_POST['slug'])) {
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_POST['slug']);
        $result = Theme::delete($slug);
        $_SESSION['flash'] = $result['ok'] ? 'テーマを削除しました。' : 'エラー: ' . $result['error'];
        header('Location: theme_manager.php'); exit;
    }
}

$themes = Theme::all();

ob_start();
?>
<?php if ($flash): ?><div class="alert alert--success"><?= Security::e($flash) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert--error"><ul style="margin:0;padding-left:1.25rem">
  <?php foreach ($errors as $e): ?><li><?= Security::e($e) ?></li><?php endforeach; ?>
</ul></div><?php endif; ?>

<div class="row" style="margin-bottom:1rem">
  <div class="col-main"><div class="card elev-1"><div class="card__body">
    <div class="card__label">ZIPからインストール</div>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:flex-end">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="upload_zip">
      <div class="field" style="flex:1;margin:0">
        <input type="file" name="theme_zip" accept=".zip" required style="border:1px solid #bdbdbd;border-radius:4px;padding:.5rem;font-size:.875rem;width:100%">
      </div>
      <button type="submit" class="btn btn--primary btn--sm">アップロード</button>
    </form>
  </div></div></div>
  <div class="col-side"><div class="card elev-1"><div class="card__body">
    <div class="card__label">GitHubからインストール</div>
    <form method="post" style="display:flex;gap:.5rem;align-items:flex-end">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="install_github">
      <div class="field" style="flex:1;margin:0">
        <input type="text" name="github_url" placeholder="https://github.com/user/theme">
      </div>
      <button type="submit" class="btn btn--primary btn--sm">インストール</button>
    </form>
  </div></div></div>
</div>

<div class="card__label" style="margin-bottom:.75rem">インストール済みテーマ</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem">
  <?php foreach ($themes as $t): ?>
  <div style="border:<?= $t['active'] ? '2px solid #212121' : '1px solid #e0e0e0' ?>;border-radius:4px;overflow:hidden;background:#fff">
    <?php if ($t['screenshot']): ?>
    <img src="<?= Security::e($t['screenshot']) ?>" style="width:100%;height:160px;object-fit:cover;display:block" alt="">
    <?php else: ?>
    <div style="width:100%;height:160px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#bdbdbd;font-size:.875rem"><?= Security::e($t['name']) ?></div>
    <?php endif; ?>
    <div style="padding:.9rem 1rem">
      <div style="font-weight:600;font-size:.95rem;margin-bottom:.25rem">
        <?= Security::e($t['name']) ?>
        <?php if ($t['active']): ?><span style="background:#212121;color:#fff;padding:.1rem .45rem;border-radius:2px;font-size:.72rem;margin-left:.4rem">有効</span><?php endif; ?>
        <?php if ($t['has_admin']): ?><span style="background:#2e7d32;color:#fff;padding:.1rem .45rem;border-radius:2px;font-size:.72rem;margin-left:.2rem">管理画面対応</span><?php endif; ?>
      </div>
      <p style="font-size:.8rem;color:#757575;margin:0 0 .5rem"><?= Security::e($t['description']) ?></p>
      <p style="font-size:.75rem;color:#9e9e9e;margin:0 0 .75rem">v<?= Security::e($t['version']) ?></p>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <?php if (!$t['active']): ?>
        <form method="post" style="display:inline">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="activate">
          <input type="hidden" name="slug" value="<?= Security::e($t['slug']) ?>">
          <button type="submit" class="btn btn--primary btn--sm">有効化</button>
        </form>
        <?php endif; ?>
        <?php if ($t['slug'] !== 'default'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="slug" value="<?= Security::e($t['slug']) ?>">
          <button type="submit" class="btn btn--danger btn--sm">削除</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card elev-1" style="margin-top:1.5rem"><div class="card__body">
  <div class="card__label">テーマの作り方</div>
  <p style="font-size:.875rem;color:#616161;margin:0 0 .5rem">ZIPにまとめてアップロードするだけでインストールできます。</p>
  <pre style="background:#f5f5f5;padding:1rem;border-radius:4px;font-size:.82rem;overflow-x:auto">your-theme/
├── theme.json          必須
├── style.css           フロント用スタイル
├── admin_style.css     管理画面用スタイル（任意）
├── screenshot.png      プレビュー画像（任意）
├── templates/          フロントテンプレート
│   ├── index.php
│   ├── article.php
│   ├── author.php
│   ├── header.php
│   ├── footer.php
│   └── sidebar.php
└── admin_templates/    管理画面テンプレート（任意）
    └── layout.php      管理画面の共通レイアウト</pre>
</div></div>
<?php
Theme::renderAdmin('テーマ管理', ob_get_clean());

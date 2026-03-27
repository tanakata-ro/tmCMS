<?php
define('TMCMS_INSTALLING', true);
define('TMCMS_ROOT', dirname(__DIR__));

if (file_exists(__DIR__ . '/.installed')) {
    http_response_code(403);
    exit('<h1>インストール済みです。</h1><p>セキュリティのため install/ ディレクトリを削除してください。</p>');
}

session_name('tmcms_install');
session_start();

$step   = max(1, min(4, (int)($_GET['step'] ?? 1)));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['_tok'] ?? '', $_POST['_tok'] ?? '')) {
        $errors[] = '不正なリクエストです。再読み込みしてください。';
    } else {
        switch ($step) {
            case 2:
                $db = ['driver'=>$_POST['db_driver']??'sqlite','host'=>trim($_POST['db_host']??'localhost'),'port'=>trim($_POST['db_port']??'3306'),'name'=>trim($_POST['db_name']??''),'user'=>trim($_POST['db_user']??''),'pass'=>$_POST['db_pass']??''];
                try { testConn($db); $_SESSION['inst_db']=$db; header('Location: ?step=3'); exit; }
                catch (Exception $e) { $errors[] = 'DB接続失敗: ' . $e->getMessage(); }
                break;
            case 3:
                $site = ['name'=>trim($_POST['site_name']??''),'url'=>rtrim(trim($_POST['site_url']??''),'/'),'tz'=>$_POST['timezone']??'Asia/Tokyo','adminUser'=>trim($_POST['admin_user']??''),'adminPass'=>$_POST['admin_pass']??'','adminPass2'=>$_POST['admin_pass2']??''];
                if (!$site['name'])              $errors[]='サイト名を入力してください。';
                if (!$site['url'])               $errors[]='サイトURLを入力してください。';
                if (!$site['adminUser'])         $errors[]='管理者ユーザー名を入力してください。';
                if (strlen($site['adminPass'])<8) $errors[]='パスワードは8文字以上にしてください。';
                if ($site['adminPass']!==$site['adminPass2']) $errors[]='パスワードが一致しません。';
                if (empty($errors)) { $_SESSION['inst_site']=$site; header('Location: ?step=4'); exit; }
                break;
            case 4:
                $r = doInstall();
                if ($r['ok']) { session_destroy(); header('Location: ?done=1'); exit; }
                $errors = $r['errors'];
                break;
        }
    }
}

if (isset($_GET['done'])) { renderDone(); exit; }
if (empty($_SESSION['_tok'])) $_SESSION['_tok'] = bin2hex(random_bytes(16));
$tok  = $_SESSION['_tok'];
$db   = $_SESSION['inst_db']   ?? [];
$site = $_SESSION['inst_site'] ?? [];

function testConn(array $d): void {
    match($d['driver']) {
        'sqlite' => new PDO('sqlite::memory:'),
        'mysql'  => new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),
        'pgsql'  => new PDO("pgsql:host={$d['host']};port={$d['port']};dbname={$d['name']}",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),
        default  => throw new Exception("未対応: {$d['driver']}"),
    };
}

function doInstall(): array {
    $root=$_TMCMS_ROOT=TMCMS_ROOT;$db=$_SESSION['inst_db']??null;$site=$_SESSION['inst_site']??null;
    if (!$db||!$site) return['ok'=>false,'errors'=>['セッションが切れました。最初からやり直してください。']];
    try {
        $sqlite=$root.'/db/blog.db';$slug='admin_'.bin2hex(random_bytes(4));
        if (!is_writable($root.'/core/')) return['ok'=>false,'errors'=>['core/ に書き込み権限がありません（755に変更してください）。']];
        file_put_contents($root.'/core/config.php', buildConf($db,$site,$sqlite,$slug));
        $pdo=buildPdo($db,$sqlite); createTables($pdo,$db['driver']);
        $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,'admin')")->execute([$site['adminUser'],password_hash($site['adminPass'],PASSWORD_DEFAULT)]);
        $ht=$root.'/db/.htaccess';if(!file_exists($ht))file_put_contents($ht,"Require all denied\n");
        file_put_contents(__DIR__.'/.installed',date('Y-m-d H:i:s'));
        $_SESSION['done_slug']=$slug;$_SESSION['done_url']=$site['url'];
        return['ok'=>true,'errors'=>[]];
    } catch(Throwable $e){return['ok'=>false,'errors'=>['インストールエラー: '.$e->getMessage()]];}
}

function buildPdo(array $d,string $sp): PDO {
    return match($d['driver']){
        'sqlite'=>(function()use($sp){$dir=dirname($sp);if(!is_dir($dir))mkdir($dir,0755,true);$p=new PDO('sqlite:'.$sp);$p->exec('PRAGMA journal_mode=WAL;PRAGMA foreign_keys=ON;');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);return $p;})(),
        'mysql' =>new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),
        'pgsql' =>new PDO("pgsql:host={$d['host']};port={$d['port']};dbname={$d['name']}",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),
    };
}

function createTables(PDO $pdo, string $drv): void {
    $ai=$drv==='pgsql'?'SERIAL PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $now=$drv==='pgsql'?'NOW()':"datetime('now')";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users(id {$ai},username TEXT NOT NULL UNIQUE,password_hash TEXT NOT NULL,nickname TEXT,bio TEXT,avatar_path TEXT,role TEXT NOT NULL DEFAULT 'editor',created_at TEXT NOT NULL DEFAULT ({$now}));
        CREATE TABLE IF NOT EXISTS posts(id {$ai},title TEXT NOT NULL,content TEXT NOT NULL,author_id INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'draft',access_password TEXT,thumbnail_path TEXT,meta_description TEXT,meta_keywords TEXT,group_id INTEGER,publish_at TEXT,show_ads INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT ({$now}),updated_at TEXT NOT NULL DEFAULT ({$now}));
        CREATE TABLE IF NOT EXISTS tags(id {$ai},name TEXT NOT NULL UNIQUE,slug TEXT NOT NULL UNIQUE);
        CREATE TABLE IF NOT EXISTS post_tags(post_id INTEGER NOT NULL,tag_id INTEGER NOT NULL,PRIMARY KEY(post_id,tag_id));
        CREATE TABLE IF NOT EXISTS article_groups(id {$ai},user_id INTEGER NOT NULL,name TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT ({$now}));
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
        CREATE TABLE IF NOT EXISTS login_attempts(ip TEXT PRIMARY KEY,attempts INTEGER NOT NULL DEFAULT 0,locked_until INTEGER);
    ");
}

function buildConf(array $db, array $site, string $sp, string $slug): string {
    $at=date('Y-m-d H:i:s');$e=fn($v)=>var_export($v,true);
    return "<?php\n/**\n * tmCMS 設定ファイル — 自動生成: {$at}\n */\ndefine('DB_DRIVER',{$e($db['driver'])});\ndefine('DB_SQLITE_PATH',{$e($sp)});\ndefine('DB_HOST',{$e($db['host'])});\ndefine('DB_PORT',{$e($db['port'])});\ndefine('DB_NAME',{$e($db['name'])});\ndefine('DB_USER',{$e($db['user'])});\ndefine('DB_PASS',{$e($db['pass'])});\ndefine('DB_CHARSET','utf8mb4');\n\ndefine('SITE_NAME',{$e($site['name'])});\ndefine('SITE_URL',{$e($site['url'])});\ndefine('POSTS_PER_PAGE',10);\ndefine('TIMEZONE',{$e($site['tz'])});\ndefine('ADMIN_SLUG',{$e($slug)});\ndefine('ACTIVE_THEME','default');\n\ndefine('SESSION_NAME','tmcms_sess');\ndefine('LOGIN_MAX_ATTEMPTS',5);\ndefine('LOGIN_LOCKOUT_TIME',900);\ndefine('CSRF_TOKEN_EXPIRE',3600);\n\ndefine('UPLOAD_DIR',__DIR__.'/../uploads/');\ndefine('UPLOAD_MAX_SIZE',10*1024*1024);\ndefine('UPLOAD_ALLOWED_TYPES',['image/jpeg','image/png','image/gif','image/webp']);\n\ndate_default_timezone_set(TIMEZONE);\n";
}

function renderDone(): void {
    $slug=$_SESSION['done_slug']??'admin';$url=$_SESSION['done_url']??'/';session_destroy();
    echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>インストール完了</title><link rel='preconnect' href='https://fonts.googleapis.com'><link href='https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap' rel='stylesheet'><style>*{box-sizing:border-box}body{font-family:Roboto,sans-serif;background:#fafafa;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#fff;padding:2.5rem;border-radius:4px;box-shadow:0 3px 6px rgba(0,0,0,.12);max-width:440px;width:100%}h1{color:#212121;margin:0 0 .75rem}p{color:#616161;font-size:.9rem}.btn{display:inline-block;padding:.6rem 1.4rem;background:#212121;color:#fff;border-radius:4px;text-decoration:none;margin:.35rem;font-size:.9rem}.warn{background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;padding:1rem;margin-top:1.5rem;font-size:.82rem;color:#424242}code{background:#f5f5f5;padding:.1rem .3rem;border-radius:2px}</style></head><body><div class='box'><h1>インストール完了</h1><p>tmCMS のセットアップが完了しました。</p><a href='{$url}' class='btn'>サイトを開く</a><a href='{$url}/{$slug}/login.php' class='btn'>管理画面へ</a><div class='warn'><strong>セキュリティ上の注意</strong><br><code>install/</code> フォルダをサーバーから削除してください。<br><br>管理画面URL: <code>/{$slug}/login.php</code></div></div></body></html>";
}

$labels=['要件確認','データベース','サイト設定','インストール'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>tmCMS インストール — Step <?= $step ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box}
body{font-family:Roboto,sans-serif;background:#fafafa;margin:0;padding:2rem 1rem;color:#212121}
.inst{max-width:520px;margin:0 auto;background:#fff;border-radius:4px;box-shadow:0 3px 6px rgba(0,0,0,.12);overflow:hidden}
.inst-hd{background:#212121;color:#fff;padding:1.5rem 2rem}
.inst-hd h1{margin:0;font-size:1.25rem;font-weight:700}
.inst-hd p{margin:.3rem 0 0;opacity:.7;font-size:.8rem}
.steps{display:flex;background:#424242;padding:.6rem 1.5rem}
.si{flex:1;text-align:center;font-size:.72rem;color:rgba(255,255,255,.5);display:flex;flex-direction:column;align-items:center;gap:.2rem}
.sn{width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.2);color:rgba(255,255,255,.7);font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center}
.si.active .sn{background:#fff;color:#212121}.si.done .sn{background:#fff;color:#212121}
.si.active{color:#fff;font-weight:500}.si.done{color:rgba(255,255,255,.8)}
.bd{padding:1.75rem 2rem}
h2{margin:0 0 1rem;font-size:1.05rem;font-weight:500}
.errors{background:#fdecea;border-left:3px solid #c62828;padding:.7rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#b71c1c;border-radius:0 4px 4px 0}
.errors li{margin:.2rem 0}
.f{margin-bottom:.9rem}
label{display:block;font-size:.78rem;font-weight:500;color:#616161;margin-bottom:.3rem}
input,select{width:100%;padding:.55rem .7rem;border:1px solid #bdbdbd;border-radius:4px;font-size:.9rem;font-family:inherit;outline:none}
input:focus,select:focus{border-color:#212121;box-shadow:0 0 0 2px rgba(0,0,0,.08)}
.hint{font-size:.72rem;color:#9e9e9e;margin-top:.2rem}
.btns{display:flex;gap:.5rem;margin-top:1.25rem}
.btn{padding:.55rem 1.25rem;background:#212121;color:#fff;border:none;border-radius:4px;font-size:.9rem;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
.btn:hover{background:#424242}
.btn-out{background:transparent;border:1px solid #bdbdbd;color:#424242;padding:.5rem 1.1rem}
.btn-out:hover{background:#f5f5f5}
.chk{display:flex;align-items:center;gap:.5rem;padding:.4rem 0;border-bottom:1px solid #f5f5f5;font-size:.875rem}
.ok{color:#2e7d32;font-weight:700}.bad{color:#c62828;font-weight:700}
.db-extra{display:none}
.dbrow{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
</style>
</head>
<body>
<div class="inst">
  <div class="inst-hd">
    <h1>tmCMS インストーラー</h1>
    <p>Step <?= $step ?> / 4 — <?= $labels[$step-1] ?></p>
  </div>
  <div class="steps">
    <?php foreach ($labels as $i => $l): $n=$i+1; $cls=$n<$step?'done':($n===$step?'active':''); ?>
    <div class="si <?= $cls ?>">
      <div class="sn"><?= $n<$step?'&#10003;':$n ?></div>
      <span><?= htmlspecialchars($l) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="bd">
    <?php if ($errors): ?><div class="errors"><ul style="margin:0;padding-left:1.25rem"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($step===1): ?>
    <h2>インストール前の確認</h2>
    <?php $chk=['PHP 8.0以上'=>version_compare(PHP_VERSION,'8.0','>='),'PDO (SQLite)'=>extension_loaded('pdo_sqlite'),'PDO (MySQL)'=>extension_loaded('pdo_mysql'),'PDO (PostgreSQL)'=>extension_loaded('pdo_pgsql'),'core/ 書き込み可'=>is_writable(TMCMS_ROOT.'/core/'),'db/ 書き込み可'=>is_writable(TMCMS_ROOT.'/db/')||is_writable(TMCMS_ROOT.'/')];
    $ok=$chk['PHP 8.0以上']&&$chk['PDO (SQLite)']&&$chk['core/ 書き込み可']; ?>
    <?php foreach($chk as $l=>$pass): ?><div class="chk"><span class="<?=$pass?'ok':'bad'?>"><?=$pass?'OK':'NG'?></span><?= htmlspecialchars($l) ?></div><?php endforeach; ?>
    <?php if ($ok): ?><div class="btns"><a href="?step=2" class="btn">次へ</a></div>
    <?php else: ?><p style="color:#c62828;font-size:.875rem;margin-top:.75rem">必須要件を満たしていません。</p><?php endif; ?>

    <?php elseif($step===2): ?>
    <h2>データベース設定</h2>
    <form method="post" action="?step=2">
      <input type="hidden" name="_tok" value="<?= htmlspecialchars($tok) ?>">
      <div class="f"><label>DB の種類</label>
        <select name="db_driver" id="drv" onchange="toggleDb(this.value)">
          <option value="sqlite">SQLite（レンタルサーバー向け・設定不要）</option>
          <option value="mysql" <?=($db['driver']??'')==='mysql'?'selected':''?>>MySQL</option>
          <option value="pgsql"  <?=($db['driver']??'')==='pgsql'?'selected':''?>>PostgreSQL</option>
        </select>
        <p class="hint">さくらレンタルサーバーなら SQLite を選んでください。</p>
      </div>
      <div class="db-extra" id="dbex">
        <div class="dbrow">
          <div class="f"><label>ホスト</label><input type="text" name="db_host" value="<?=htmlspecialchars($db['host']??'localhost')?>"></div>
          <div class="f"><label>ポート</label><input type="text" name="db_port" value="<?=htmlspecialchars($db['port']??'3306')?>"></div>
        </div>
        <div class="f"><label>DB名</label><input type="text" name="db_name" value="<?=htmlspecialchars($db['name']??'')?>"></div>
        <div class="dbrow">
          <div class="f"><label>ユーザー名</label><input type="text" name="db_user" value="<?=htmlspecialchars($db['user']??'')?>"></div>
          <div class="f"><label>パスワード</label><input type="password" name="db_pass"></div>
        </div>
      </div>
      <div class="btns"><a href="?step=1" class="btn btn-out">戻る</a><button type="submit" class="btn">接続テスト → 次へ</button></div>
    </form>
    <script>function toggleDb(v){document.getElementById('dbex').style.display=v==='sqlite'?'none':'block';}toggleDb(document.getElementById('drv').value);</script>

    <?php elseif($step===3): ?>
    <h2>サイト設定・管理者アカウント</h2>
    <form method="post" action="?step=3">
      <input type="hidden" name="_tok" value="<?= htmlspecialchars($tok) ?>">
      <div class="f"><label>サイト名</label><input type="text" name="site_name" value="<?=htmlspecialchars($site['name']??'')?>" required></div>
      <div class="f"><label>サイトURL</label><input type="url" name="site_url" value="<?=htmlspecialchars($site['url']??('https://'.($_SERVER['HTTP_HOST']??'')))?>" required><p class="hint">末尾のスラッシュなし</p></div>
      <div class="f"><label>タイムゾーン</label>
        <select name="timezone">
          <?php foreach(['Asia/Tokyo'=>'Asia/Tokyo（日本）','UTC'=>'UTC','America/New_York'=>'America/New_York'] as $v=>$l): ?>
          <option value="<?=$v?>" <?=($site['tz']??'Asia/Tokyo')===$v?'selected':''?>><?=$l?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <hr style="border:none;border-top:1px solid #e0e0e0;margin:1rem 0">
      <div class="f"><label>管理者ユーザー名</label><input type="text" name="admin_user" value="<?=htmlspecialchars($site['adminUser']??'')?>" required autocomplete="off"></div>
      <div class="f"><label>パスワード（8文字以上）</label><input type="password" name="admin_pass" required autocomplete="new-password"></div>
      <div class="f"><label>パスワード（確認）</label><input type="password" name="admin_pass2" required autocomplete="new-password"></div>
      <div class="btns"><a href="?step=2" class="btn btn-out">戻る</a><button type="submit" class="btn">次へ</button></div>
    </form>

    <?php elseif($step===4): ?>
    <h2>確認してインストール</h2>
    <div style="background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;padding:.9rem 1rem;font-size:.875rem;line-height:1.8;margin-bottom:1.25rem">
      <strong>DB:</strong> <?=htmlspecialchars($db['driver']??'')?><br>
      <strong>サイト名:</strong> <?=htmlspecialchars($site['name']??'')?><br>
      <strong>URL:</strong> <?=htmlspecialchars($site['url']??'')?><br>
      <strong>管理者:</strong> <?=htmlspecialchars($site['adminUser']??'')?>
    </div>
    <form method="post" action="?step=4">
      <input type="hidden" name="_tok" value="<?= htmlspecialchars($tok) ?>">
      <div class="btns"><a href="?step=3" class="btn btn-out">戻る</a><button type="submit" class="btn">インストール実行</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>
</body></html>

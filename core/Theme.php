<?php
/**
 * Theme.php — tmCMS テーマシステム
 *
 * 【フロントテーマ構成】
 *   themes/{name}/
 *     theme.json
 *     style.css
 *     screenshot.png     (任意)
 *     templates/
 *       index.php        記事一覧
 *       article.php      記事詳細
 *       author.php       著者別一覧
 *       header.php       共通ヘッダー (任意)
 *       footer.php       共通フッター (任意)
 *       sidebar.php      サイドバー   (任意)
 *
 * 【管理画面テーマ構成】
 *   themes/{name}/
 *     admin_style.css        管理画面CSS
 *     admin_templates/
 *       layout.php           共通レイアウト (必須)
 *                            ← $tmcms['content'] にページHTML が入る
 *
 * 【各管理ページの書き方】
 *   <?php
 *   // PHPロジック...
 *   ob_start();
 *   ?>
 *   <!-- コンテンツHTML (DOCTYPE/html/head/body/header タグ不要) -->
 *   <?php
 *   Theme::renderAdmin('ページタイトル', ob_get_clean());
 *
 * 【layout.php で使える変数】
 *   $tmcms['page_title']  ページタイトル
 *   $tmcms['site_name']   サイト名
 *   $tmcms['content']     ページのHTML
 *   $tmcms['nav_items']   ナビゲーション配列
 *   $tmcms['admin_css']   admin_style.css のURL
 *   $tmcms['head_extra']  <head>に追加するHTML
 *   $tmcms['role']        ログインユーザーのロール
 *   $tmcms['username']    ユーザー名
 */
class Theme
{
    private static string $active    = 'default';
    private static string $themesDir = '';
    private static string $themesUrl = '';
    private static bool   $inited    = false;

    // ── 初期化 ──────────────────────────────────────────────
    public static function init(): void
    {
        if (self::$inited) return;
        self::$themesDir = dirname(__DIR__) . '/themes/';
        // themesUrl はリクエスト時に buildUrl() 経由で生成するため空にしておく
        self::$themesUrl = '';
        self::$active    = defined('ACTIVE_THEME') ? ACTIVE_THEME : 'default';
        if (!is_dir(self::$themesDir . self::$active)) self::$active = 'default';
        self::$inited = true;
    }

    // ── フロントテンプレートのレンダリング ──────────────────
    public static function render(string $tpl, array $data = []): void
    {
        self::init();
        $file = self::$themesDir . self::$active . '/templates/' . $tpl . '.php';
        if (!file_exists($file)) {
            $file = self::$themesDir . 'default/templates/' . $tpl . '.php';
        }
        if (!file_exists($file)) {
            http_response_code(500);
            exit("テンプレートが見つかりません: {$tpl}");
        }
        $tmcms = array_merge(self::baseCtx(), $data);
        (static fn($f, $tmcms) => include $f)($file, $tmcms);
    }

    // ── 管理画面レンダリング ─────────────────────────────────
    /**
     * 管理画面の各ページから呼ぶ唯一のメソッド。
     *
     * 使い方:
     *   ob_start();
     *   // ...HTMLコンテンツ...
     *   Theme::renderAdmin('ページタイトル', ob_get_clean());
     *
     * または追加データを渡す場合:
     *   Theme::renderAdmin('記事編集', ob_get_clean(), [
     *       'head_extra' => '<link rel="stylesheet" href="...">',
     *   ]);
     */
    public static function renderAdmin(string $pageTitle, string $content, array $extra = []): void
    {
        self::init();

        // layout.php を解決（テーマ → default → なければエラー）
        $layoutFile = self::$themesDir . self::$active . '/admin_templates/layout.php';
        if (!file_exists($layoutFile)) {
            $layoutFile = self::$themesDir . 'default/admin_templates/layout.php';
        }
        if (!file_exists($layoutFile)) {
            // レイアウトがない場合はそのまま出力
            echo $content;
            return;
        }

        $tmcms = array_merge(self::adminBaseCtx(), $extra, [
            'page_title' => $pageTitle,
            'content'    => $content,
        ]);

        (static fn($f, $tmcms) => include $f)($layoutFile, $tmcms);
    }

    // ── パーシャル ───────────────────────────────────────────
    public static function partial(string $name, array $tmcms = []): void
    {
        self::init();
        $file = self::$themesDir . self::$active . '/templates/' . $name . '.php';
        if (!file_exists($file)) {
            $file = self::$themesDir . 'default/templates/' . $name . '.php';
        }
        if (file_exists($file)) {
            (static fn($f, $tmcms) => include $f)($file, $tmcms);
        }
    }

    // ── CSS URL ──────────────────────────────────────────────
    public static function cssUrl(bool $admin = false): string
    {
        self::init();
        $file = $admin ? 'admin_style.css' : 'style.css';

        // アクティブテーマにファイルがあればそのURLを返す
        if (is_file(self::$themesDir . self::$active . '/' . $file)) {
            return self::buildUrl('/themes/' . self::$active . '/' . $file);
        }
        // default テーマにフォールバック
        if (is_file(self::$themesDir . 'default/' . $file)) {
            return self::buildUrl('/themes/default/' . $file);
        }
        // admin_style.css がなければ style.css を返す
        if ($admin) return self::cssUrl(false);
        return '';
    }

    /**
     * サイトルートからの相対パスを絶対URLに変換する。
     * SITE_URL の代わりに $_SERVER から動的に生成するので
     * サブディレクトリ設置でも正しく動く。
     */
    private static function buildUrl(string $path): string
    {
        // インストール済みならSITE_URLを使う（最も確実）
        if (defined('SITE_URL') && SITE_URL !== '') {
            return rtrim(SITE_URL, '/') . $path;
        }
        // フォールバック: $_SERVER から生成
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $path;
    }

    public static function themeUrl(): string
    {
        self::init();
        return self::buildUrl('/themes/' . self::$active);
    }

    // ── テーマ管理 ───────────────────────────────────────────
    public static function all(): array
    {
        self::init();
        $themes = [];
        if (!is_dir(self::$themesDir)) return $themes;
        foreach (scandir(self::$themesDir) as $dir) {
            if ($dir[0] === '.' || !is_dir(self::$themesDir . $dir)) continue;
            $meta = self::readMeta($dir);
            $themes[] = [
                'slug'        => $dir,
                'name'        => $meta['name']        ?? $dir,
                'description' => $meta['description'] ?? '',
                'author'      => $meta['author']      ?? '',
                'version'     => $meta['version']     ?? '1.0',
                'has_admin'   => is_dir(self::$themesDir . $dir . '/admin_templates'),
                'active'      => $dir === self::$active,
                'screenshot'  => is_file(self::$themesDir . $dir . '/screenshot.png')
                                  ? self::buildUrl('/themes/' . $dir . '/screenshot.png') : null,
            ];
        }
        return $themes;
    }

    public static function installFromZip(string $tmp): array
    {
        if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => 'ZipArchive 拡張が必要です。'];
        self::init();
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) return ['ok' => false, 'error' => 'ZIPを開けません。'];
        $root = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (str_contains($n, '/')) { $root = explode('/', $n)[0]; break; }
        }
        if (!$root || $zip->locateName($root . '/theme.json') === false) {
            $zip->close();
            return ['ok' => false, 'error' => 'theme.json が見つかりません。'];
        }
        if (!is_dir(self::$themesDir . $root)) mkdir(self::$themesDir . $root, 0755, true);
        $zip->extractTo(self::$themesDir);
        $zip->close();
        return ['ok' => true, 'slug' => $root];
    }

    public static function installFromGitHub(string $url): array
    {
        self::init();
        if (!preg_match('#^https://github\.com/([^/]+/[^/]+)$#', rtrim($url, '/'), $m)) {
            return ['ok' => false, 'error' => '有効な GitHub リポジトリ URL を入力してください。'];
        }
        $content = @file_get_contents("https://github.com/{$m[1]}/archive/refs/heads/main.zip");
        if ($content === false) return ['ok' => false, 'error' => 'GitHub からのダウンロードに失敗しました。'];
        $tmp = tempnam(sys_get_temp_dir(), 'tmcms_');
        file_put_contents($tmp, $content);
        $r = self::installFromZip($tmp);
        unlink($tmp);
        return $r;
    }

    public static function delete(string $slug): array
    {
        self::init();
        if ($slug === 'default') return ['ok' => false, 'error' => 'default テーマは削除できません。'];
        $dir = self::$themesDir . $slug . '/';
        if (!is_dir($dir)) return ['ok' => false, 'error' => 'テーマが見つかりません。'];
        self::rmdirRecursive($dir);
        return ['ok' => true];
    }

    // ── 内部ヘルパー ─────────────────────────────────────────
    private static function baseCtx(): array
    {
        return [
            'site_name'  => defined('SITE_NAME') ? SITE_NAME : 'tmCMS',
            'site_url'   => defined('SITE_URL')  ? SITE_URL  : '',
            'theme_url'  => self::buildUrl('/themes/' . self::$active),
            'theme_slug' => self::$active,
            'admin_slug' => defined('ADMIN_SLUG') ? ADMIN_SLUG : 'admin',
            'tags'       => [],
        ];
    }

    private static function adminBaseCtx(): array
    {
        Auth::start();
        $user = Auth::user() ?? [];
        return [
            'site_name'  => defined('SITE_NAME') ? SITE_NAME : 'tmCMS',
            'site_url'   => defined('SITE_URL')  ? SITE_URL  : '',
            'theme_url'  => self::buildUrl('/themes/' . self::$active),
            'admin_slug' => defined('ADMIN_SLUG') ? ADMIN_SLUG : 'admin',
            'role'       => $user['role']     ?? '',
            'username'   => $user['username'] ?? '',
            'user_id'    => $user['user_id']  ?? 0,
            'admin_css'  => self::cssUrl(true),
            'nav_items'  => self::buildNav($user['role'] ?? ''),
            'head_extra' => '',
        ];
    }

    private static function buildNav(string $role): array
    {
        $nav = [];
        if (in_array($role, ['admin', 'editor'], true)) {
            $nav[] = ['label' => 'ダッシュボード', 'href' => 'dashboard.php',    'icon' => 'home'];
            $nav[] = ['label' => '新規記事',        'href' => 'post_edit.php',   'icon' => 'edit'];
        }
        if ($role === 'admin') {
            $nav[] = ['label' => 'タグ',        'href' => 'tag_manager.php',   'icon' => 'tag'];
            $nav[] = ['label' => 'ユーザー',    'href' => 'user_manager.php',  'icon' => 'people'];
            $nav[] = ['label' => 'テーマ',      'href' => 'theme_manager.php', 'icon' => 'palette'];
            $nav[] = ['label' => '広告',        'href' => 'ad_manager.php',    'icon' => 'campaign'];
        }
        $nav[] = ['label' => 'プロフィール', 'href' => 'profile.php', 'icon' => 'person'];
        return $nav;
    }

    private static function readMeta(string $slug): array
    {
        $f = self::$themesDir . $slug . '/theme.json';
        return file_exists($f) ? (json_decode(file_get_contents($f), true) ?? []) : [];
    }

    private static function rmdirRecursive(string $dir): void
    {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . $f;
            is_dir($p) ? self::rmdirRecursive($p . '/') : unlink($p);
        }
        rmdir(rtrim($dir, '/'));
    }
}

// ── グローバルヘルパー ────────────────────────────────────────
function tmcms_e(mixed $v): string        { return Security::e($v); }
function tmcms_url(string $p = ''): string { return (defined('SITE_URL') ? SITE_URL : '') . '/' . ltrim($p, '/'); }
function tmcms_header(array $t = []): void  { Theme::partial('header', $t); }
function tmcms_footer(array $t = []): void  { Theme::partial('footer', $t); }
function tmcms_sidebar(array $t = []): void { Theme::partial('sidebar', $t); }
function tmcms_partial(string $n, array $t = []): void { Theme::partial($n, $t); }

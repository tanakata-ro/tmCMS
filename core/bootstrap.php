<?php
/**
 * bootstrap.php — 全PHPファイルの先頭でこれ1行だけ require すれば OK
 */

$_configFile = __DIR__ . '/config.php';
if (!defined('TMCMS_INSTALLING')) {
    if (!file_exists($_configFile)) {
        header('Location: /install/');
        exit;
    }
    require_once $_configFile;
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/BbCode.php';
require_once __DIR__ . '/Theme.php';

// ── 自動マイグレーション ──────────────────────────────────
if (!defined('TMCMS_INSTALLING')) {
    try {
        $__pdo = Database::getInstance();
        $__cols = [
            'users'  => ['avatar_path TEXT', 'bio TEXT'],
            'posts'  => ['view_count INTEGER NOT NULL DEFAULT 0', 'show_ads INTEGER NOT NULL DEFAULT 0'],
        ];
        foreach ($__cols as $__tbl => $__defs) {
            foreach ($__defs as $__def) {
                try { $__pdo->exec("ALTER TABLE {$__tbl} ADD COLUMN {$__def}"); } catch (Throwable $__e) {}
            }
        }
        unset($__pdo, $__cols, $__tbl, $__defs, $__def, $__e);
    } catch (Throwable $__ex) { unset($__ex); }
}

// ── Parsedown ────────────────────────────────────────────
$_parsedownPaths = [
    dirname(__DIR__) . '/vendor/erusev/parsedown/Parsedown.php',
    dirname(__DIR__) . '/vendor/Parsedown.php',
    dirname(__DIR__) . '/Parsedown.php',
];
foreach ($_parsedownPaths as $_p) {
    if (file_exists($_p)) { require_once $_p; break; }
}
unset($_configFile, $_parsedownPaths, $_p);

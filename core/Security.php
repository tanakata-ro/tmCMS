<?php

class Security
{
    // ── XSS対策 ───────────────────────────────────────────
    /** HTML出力時は必ずこれを通す */
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── ブルートフォース対策 ──────────────────────────────
    public static function isLocked(string $ip, PDO $pdo): bool
    {
        self::ensureTable($pdo);
        $row = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE ip = ?');
        $row->execute([$ip]);
        $r = $row->fetch();
        if (!$r) return false;
        if ($r['locked_until'] && time() < (int)$r['locked_until']) return true;
        if ($r['locked_until'] && time() >= (int)$r['locked_until']) {
            self::clearAttempts($ip, $pdo);
            return false;
        }
        return (int)$r['attempts'] >= (defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5);
    }

    public static function recordFail(string $ip, PDO $pdo): void
    {
        self::ensureTable($pdo);
        $max = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5;
        $ttl = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;

        $stmt = $pdo->prepare('SELECT attempts FROM login_attempts WHERE ip = ?');
        $stmt->execute([$ip]);
        $r = $stmt->fetch();

        if (!$r) {
            $pdo->prepare('INSERT INTO login_attempts (ip, attempts, locked_until) VALUES (?, 1, NULL)')->execute([$ip]);
        } else {
            $n    = (int)$r['attempts'] + 1;
            $lock = $n >= $max ? time() + $ttl : null;
            $pdo->prepare('UPDATE login_attempts SET attempts = ?, locked_until = ? WHERE ip = ?')->execute([$n, $lock, $ip]);
        }
    }

    public static function clearAttempts(string $ip, PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    }

    public static function lockRemaining(string $ip, PDO $pdo): int
    {
        $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE ip = ?');
        $stmt->execute([$ip]);
        $r = $stmt->fetch();
        return ($r && $r['locked_until']) ? max(0, (int)$r['locked_until'] - time()) : 0;
    }

    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
            ip           TEXT PRIMARY KEY,
            attempts     INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER
        )');
    }

    // ── ファイルアップロード検証 ──────────────────────────
    /** @return array{ok: bool, error: string, mime?: string, safe_name?: string} */
    public static function validateUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'アップロードエラー (code:' . $file['error'] . ')'];
        }
        $max = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 10 * 1024 * 1024;
        if ($file['size'] > $max) {
            return ['ok' => false, 'error' => 'ファイルサイズが上限を超えています。'];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = defined('UPLOAD_ALLOWED_TYPES') ? UPLOAD_ALLOWED_TYPES : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return ['ok' => false, 'error' => '許可されていないファイル形式です。'];
        }
        $safe = self::sanitizeFilename($file['name']);
        if ($safe === '') {
            return ['ok' => false, 'error' => '無効なファイル名です。'];
        }
        return ['ok' => true, 'error' => '', 'mime' => $mime, 'safe_name' => $safe];
    }

    public static function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w\-\.]/', '', $name) ?? '';
        return ltrim($name, '.');
    }

    public static function isSafePath(string $path, string $base): bool
    {
        $r = realpath($path);
        $b = realpath($base);
        if ($r === false || $b === false) return false;
        return str_starts_with($r, $b . DIRECTORY_SEPARATOR);
    }

    // ── IPアドレス取得 ────────────────────────────────────
    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}

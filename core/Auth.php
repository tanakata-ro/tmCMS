<?php

class Auth
{
    private static bool $started = false;

    // ── セッション開始 ────────────────────────────────────
    public static function start(): void
    {
        if (self::$started || session_status() !== PHP_SESSION_NONE) {
            self::$started = true;
            return;
        }
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'tmcms_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
    }

    // ── アクセス制御 ──────────────────────────────────────
    public static function requireLogin(string $redirect = ''): void
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            $url = $redirect ?: '/' . (defined('ADMIN_SLUG') ? ADMIN_SLUG : 'admin') . '/login.php';
            header('Location: ' . $url);
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            exit('この操作を行う権限がありません。');
        }
    }

    // ── ログイン処理 ──────────────────────────────────────
    /**
     * @return array{ok: bool, error: string, locked_for: int}
     */
    public static function login(string $username, string $password, PDO $pdo): array
    {
        self::start();
        $ip = Security::clientIp();

        // ブルートフォースチェック
        if (Security::isLocked($ip, $pdo)) {
            $mins = (int)ceil(Security::lockRemaining($ip, $pdo) / 60);
            return ['ok' => false, 'error' => "ログイン試行回数の上限に達しました。約{$mins}分後にお試しください。", 'locked_for' => Security::lockRemaining($ip, $pdo)];
        }

        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            Security::clearAttempts($ip, $pdo);
            session_regenerate_id(true);  // セッション固定攻撃対策

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // ハッシュのアップグレード（必要な場合のみ）
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }

            return ['ok' => true, 'error' => '', 'locked_for' => 0];
        }

        Security::recordFail($ip, $pdo);
        // タイミング攻撃対策（ユーザーが存在しない場合も同じ時間がかかるように）
        password_verify('dummy', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234');

        return ['ok' => false, 'error' => 'ユーザー名またはパスワードが正しくありません。', 'locked_for' => 0];
    }

    // ── ログアウト ────────────────────────────────────────
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── ユーザー情報 ──────────────────────────────────────
    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['user_id'])) return null;
        return [
            'user_id'  => (int)$_SESSION['user_id'],
            'username' => (string)$_SESSION['username'],
            'role'     => (string)$_SESSION['role'],
        ];
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function id(): int
    {
        self::start();
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function role(): string
    {
        self::start();
        return (string)($_SESSION['role'] ?? '');
    }

    public static function hasRole(string $role): bool
    {
        return self::role() === $role;
    }
}

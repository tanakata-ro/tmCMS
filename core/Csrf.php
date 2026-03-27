<?php

class Csrf
{
    private const KEY = '_csrf';

    /** フォームに埋め込むhiddenフィールドを返す */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    /** トークンを生成して返す */
    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        self::purge();
        $tok = bin2hex(random_bytes(32));
        $_SESSION[self::KEY][$tok] = time() + (defined('CSRF_TOKEN_EXPIRE') ? CSRF_TOKEN_EXPIRE : 3600);
        return $tok;
    }

    /**
     * POSTリクエストのCSRFトークンを検証する。
     * 失敗したら 403 で即終了。
     */
    public static function verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $tok = (string)($_POST['_csrf'] ?? '');
        if (!self::validate($tok)) {
            http_response_code(403);
            exit('不正なリクエストです（CSRFトークン不一致）。');
        }
        unset($_SESSION[self::KEY][$tok]); // ワンタイム消費
    }

    private static function validate(string $tok): bool
    {
        Auth::start();
        if ($tok === '' || empty($_SESSION[self::KEY])) return false;
        foreach ($_SESSION[self::KEY] as $stored => $exp) {
            if (hash_equals($stored, $tok)) return time() <= $exp;
        }
        return false;
    }

    private static function purge(): void
    {
        $now = time();
        foreach ($_SESSION[self::KEY] ?? [] as $k => $exp) {
            if ($exp < $now) unset($_SESSION[self::KEY][$k]);
        }
    }
}

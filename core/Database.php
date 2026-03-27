<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    private static function connect(): PDO
    {
        $driver = DB_DRIVER;

        switch ($driver) {
            case 'sqlite':
                $dir = dirname(DB_SQLITE_PATH);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
                $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
                break;

            case 'mysql':
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                ]);
                break;

            case 'pgsql':
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                $pdo->exec("SET NAMES 'UTF8'");
                break;

            default:
                throw new RuntimeException("未対応のDBドライバ: {$driver}");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

        return $pdo;
    }

    public static function reset(): void { self::$instance = null; }
    private function __construct() {}
    private function __clone() {}
}

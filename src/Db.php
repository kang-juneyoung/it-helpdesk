<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * PDO 연결. 모든 쿼리는 준비된 문장(prepared statement)으로만 실행한다.
 * 사용자 입력을 SQL 문자열에 이어 붙이지 않는 것이 SQL 인젝션 방지의 기본이다.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function connect(?string $dsn = null, ?string $user = null, ?string $pass = null): PDO
    {
        $dsn ??= Config::dsn();
        $pdo = new PDO($dsn, $user ?? Config::get('DB_USER'), $pass ?? Config::get('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }

    /** 웹 요청 하나에서 연결을 한 번만 연다. */
    public static function get(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    public static function set(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** 스키마 파일을 실행한다. 드라이버에 맞는 파일을 고른다. */
    public static function migrate(PDO $pdo): void
    {
        $file = dirname(__DIR__) . '/db/schema.' . (self::driver($pdo) === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
        $sql = (string) file_get_contents($file);
        // 주석 줄을 지우고 세미콜론으로 나눠 한 문장씩 실행한다.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }
}

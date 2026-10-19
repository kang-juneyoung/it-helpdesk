<?php
declare(strict_types=1);

namespace App;

/**
 * 현재 시각을 한 곳에서만 얻는다. 테스트에서 시각을 고정해
 * "로그인 잠금 10분", "백업 26시간 초과" 같은 시간 규칙을 확인하기 위해서다.
 */
final class Clock
{
    private static ?int $fixed = null;

    public static function ts(): int
    {
        return self::$fixed ?? time();
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s', self::ts());
    }

    public static function today(): string
    {
        return date('Y-m-d', self::ts());
    }

    public static function freeze(string $datetime): void
    {
        self::$fixed = strtotime($datetime);
    }

    public static function advance(int $seconds): void
    {
        self::$fixed = self::ts() + $seconds;
    }

    public static function reset(): void
    {
        self::$fixed = null;
    }
}

<?php
declare(strict_types=1);

namespace App;

/**
 * 설정은 환경변수에서 읽는다. 비밀번호를 코드나 저장소에 넣지 않기 위해서다.
 *
 *   DB_DSN   예) mysql:host=127.0.0.1;dbname=helpdesk;charset=utf8mb4
 *            예) sqlite:/var/data/helpdesk.db   (데모·테스트)
 *   DB_USER, DB_PASS
 *   BACKUP_DIR        백업 파일이 쌓이는 폴더 (bin/check_backup.php 가 점검)
 *   BACKUP_MAX_HOURS  마지막 백업 후 이 시간이 지나면 실패로 기록 (기본 26시간)
 */
final class Config
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }

    public static function dsn(): string
    {
        return self::get('DB_DSN', 'sqlite:' . dirname(__DIR__) . '/data/helpdesk.db');
    }
}

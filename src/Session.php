<?php
declare(strict_types=1);

namespace App;

/**
 * 세션·CSRF·화면 메시지(flash) 처리.
 */
final class Session
{
    private const IDLE_SECONDS = 3600; // 1시간 동안 아무것도 안 하면 자동 로그아웃

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,                       // 스크립트로 세션 쿠키를 읽지 못하게
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('HDSESSID');
        session_start();

        $last = $_SESSION['last_seen'] ?? null;
        if ($last !== null && time() - $last > self::IDLE_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
            self::flash('warn', '오랫동안 사용하지 않아 로그아웃되었습니다.');
        }
        $_SESSION['last_seen'] = time();
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);              // 세션 고정 공격 방지
        $_SESSION['user'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}

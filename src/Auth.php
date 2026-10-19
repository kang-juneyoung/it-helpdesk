<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * 로그인 처리.
 *  - 비밀번호는 password_hash()로 저장 (평문·MD5 저장 금지)
 *  - 연속 5회 실패하면 10분 잠금 (무차별 대입 방어)
 *  - 없는 아이디와 틀린 비밀번호의 안내 문구를 같게 해 아이디 존재 여부를 숨긴다
 */
final class Auth
{
    public const MAX_FAILS = 5;
    public const LOCK_SECONDS = 600;
    public const MSG_FAIL = '아이디 또는 비밀번호가 맞지 않습니다.';

    // 없는 아이디로 시도해도 비밀번호 검사 시간이 비슷하도록 쓰는 가짜 해시
    private const DUMMY_HASH = '$2y$10$HY8DH58wQ5tcGq7exeN9Pu5FDBylxLZpJrIwusR14tLz0pvG7Dbcm';

    public function __construct(private PDO $pdo, private AuditLog $audit)
    {
    }

    /**
     * @return array{ok: bool, user?: array, error?: string}
     */
    public function attempt(string $username, string $password, string $ip = ''): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if ($user === false) {
            password_verify($password, self::DUMMY_HASH);
            $this->audit->write(null, 'login_fail', 'user', "없는 아이디: {$username}", $ip);
            return ['ok' => false, 'error' => self::MSG_FAIL];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > Clock::ts()) {
            $this->audit->write($user, 'login_locked', 'user', '잠금 중 로그인 시도', $ip);
            $minutes = (int) ceil((strtotime($user['locked_until']) - Clock::ts()) / 60);
            return ['ok' => false, 'error' => "로그인 실패가 반복되어 잠겼습니다. {$minutes}분 뒤 다시 시도하거나 전산실에 문의하세요."];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_count'] + 1;
            $lockedUntil = $fails >= self::MAX_FAILS ? date('Y-m-d H:i:s', Clock::ts() + self::LOCK_SECONDS) : null;
            $this->pdo->prepare('UPDATE users SET failed_count = ?, locked_until = ? WHERE id = ?')
                ->execute([$lockedUntil ? 0 : $fails, $lockedUntil, $user['id']]);
            $this->audit->write($user, 'login_fail', 'user', "비밀번호 오류 {$fails}회" . ($lockedUntil ? ' → 10분 잠금' : ''), $ip);
            return ['ok' => false, 'error' => self::MSG_FAIL];
        }

        // 성공: 실패 횟수 초기화, 해시 방식이 바뀌었으면 다시 저장
        $newHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : $user['password_hash'];
        $this->pdo->prepare('UPDATE users SET failed_count = 0, locked_until = NULL, password_hash = ? WHERE id = ?')
            ->execute([$newHash, $user['id']]);
        $this->audit->write($user, 'login', 'user', '로그인 성공', $ip);

        unset($user['password_hash']);
        return ['ok' => true, 'user' => $user];
    }

    public function createUser(string $username, string $password, string $name, string $dept, string $role): int
    {
        $username = strtolower(trim($username));
        $v = (new Validator())
            ->required(['u' => $username], 'u', '아이디')
            ->pattern(['u' => $username], 'u', '아이디', '/^[a-z][a-z0-9._-]{2,39}$/', '영문 소문자로 시작, 3~40자')
            ->required(['n' => $name], 'n', '이름')
            ->maxLen(['n' => $name], 'n', '이름', 40);
        $errors = $v->errors();
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            $errors['u'] = '이미 있는 아이디입니다.';
        }
        if ($errors) {
            throw new ValidationException(array_filter(['username' => $errors['u'] ?? null, 'name' => $errors['n'] ?? null]));
        }
        if (!in_array($role, ['admin', 'staff'], true)) {
            throw new \InvalidArgumentException('권한은 admin 또는 staff 이어야 합니다.');
        }
        if (mb_strlen($password) < 8) {
            throw new \InvalidArgumentException('비밀번호는 8자 이상이어야 합니다.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, name, dept, role, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $dept, $role, Clock::now()]);
        return (int) $this->pdo->lastInsertId();
    }

    public function users(): array
    {
        return $this->pdo->query(
            'SELECT id, username, name, dept, role, failed_count, locked_until, created_at FROM users ORDER BY role, dept, name'
        )->fetchAll();
    }

    /**
     * 임시 비밀번호 발급. 화면에 한 번만 보여 주고 DB에는 해시만 저장한다.
     * 헷갈리는 글자(0/O, 1/l/I)는 뺀다 — 전화로 불러 줄 때 틀리지 않게.
     */
    public static function tempPassword(int $length = 10): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $temp = '';
        for ($i = 0; $i < $length; $i++) {
            $temp .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $temp;
    }

    public function resetPassword(int $userId, array $admin, string $ip = ''): string
    {
        $temp = self::tempPassword();
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ?, failed_count = 0, locked_until = NULL WHERE id = ?');
        $stmt->execute([password_hash($temp, PASSWORD_DEFAULT), $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new \DomainException('사용자를 찾을 수 없습니다.');
        }
        $this->audit->write($admin, 'update', 'user', "사용자 #{$userId} 임시 비밀번호 발급", $ip);
        return $temp;
    }

    /** 관리자가 잠금을 풀어 준다. */
    public function unlock(int $userId, array $admin, string $ip = ''): void
    {
        $this->pdo->prepare('UPDATE users SET failed_count = 0, locked_until = NULL WHERE id = ?')->execute([$userId]);
        $this->audit->write($admin, 'unlock', 'user', "사용자 #{$userId} 잠금 해제", $ip);
    }
}

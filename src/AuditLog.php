<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * 감사 로그. "누가, 언제, 무엇을" 했는지 남긴다.
 * 로그인 성공·실패, 데이터 등록·수정·삭제, CSV 내보내기를 기록한다.
 */
final class AuditLog
{
    public function __construct(private PDO $pdo)
    {
    }

    public function write(?array $user, string $action, string $target = '', string $detail = '', string $ip = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, username, action, target, detail, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'] ?? null,
            $user['username'] ?? '',
            $action,
            $target,
            mb_substr($detail, 0, 255),
            $ip,
            Clock::now(),
        ]);
    }

    /** 최근 기록. 행동 종류로 거를 수 있다. */
    public function recent(int $limit = 100, ?string $action = null): array
    {
        $limit = max(1, min($limit, 500));
        if ($action !== null && $action !== '') {
            $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT $limit");
            $stmt->execute([$action]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT $limit");
        }
        return $stmt->fetchAll();
    }
}

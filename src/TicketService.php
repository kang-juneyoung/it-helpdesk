<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * 장애 접수·처리.
 *
 * 상태 흐름:  접수 ──시작──▶ 처리중 ──완료──▶ 완료
 *              └──────바로 완료──────────▶ 완료   (전화로 바로 해결한 경우)
 *             완료 ──재접수──▶ 처리중                (같은 문제가 다시 생긴 경우)
 *
 * 일반 직원(staff)은 접수와 본인 건 조회만, 상태 변경은 전산 담당(admin)만 한다.
 */
final class TicketService
{
    public const CATEGORIES = ['PC', '네트워크', '프린터', 'ERP·프로그램', '계정·권한', '기타'];
    public const PRIORITIES = ['긴급', '높음', '보통', '낮음'];
    public const STATUSES   = ['접수', '처리중', '완료'];

    /** 허용되는 상태 변경: [현재 상태][행동] => 다음 상태 */
    private const TRANSITIONS = [
        '접수'   => ['start' => '처리중', 'resolve' => '완료'],
        '처리중' => ['resolve' => '완료'],
        '완료'   => ['reopen' => '처리중'],
    ];

    public function __construct(private PDO $pdo, private AuditLog $audit)
    {
    }

    public function validate(array $data): array
    {
        return (new Validator())
            ->required($data, 'title', '제목')
            ->maxLen($data, 'title', '제목', 100)
            ->in($data, 'category', '분류', self::CATEGORIES)
            ->in($data, 'priority', '긴급도', self::PRIORITIES)
            ->required($data, 'content', '내용')
            ->maxLen($data, 'content', '내용', 2000)
            ->errors();
    }

    public function create(array $input, array $requester, string $ip = ''): int
    {
        $data = [
            'title'    => trim((string) ($input['title'] ?? '')),
            'category' => (string) ($input['category'] ?? ''),
            'priority' => (string) ($input['priority'] ?? '보통'),
            'content'  => trim((string) ($input['content'] ?? '')),
        ];
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $assetId = (int) ($input['asset_id'] ?? 0) ?: null;
        if ($assetId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM assets WHERE id = ?');
            $check->execute([$assetId]);
            $assetId = $check->fetchColumn() ? $assetId : null;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tickets (title, category, priority, status, requester_id, asset_id, content, created_at)
             VALUES (?, ?, ?, '접수', ?, ?, ?, ?)"
        );
        $stmt->execute([$data['title'], $data['category'], $data['priority'], $requester['id'], $assetId, $data['content'], Clock::now()]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit->write($requester, 'create', 'ticket', "#{$id} {$data['title']}", $ip);
        return $id;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, r.name AS requester_name, r.dept AS requester_dept, a.name AS assignee_name,
                    s.asset_no, s.model AS asset_model
             FROM tickets t
             JOIN users r ON r.id = t.requester_id
             LEFT JOIN users a ON a.id = t.assignee_id
             LEFT JOIN assets s ON s.id = t.asset_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** 직원은 본인 건만 볼 수 있다. */
    public function canView(array $ticket, array $user): bool
    {
        return $user['role'] === 'admin' || (int) $ticket['requester_id'] === (int) $user['id'];
    }

    public function list(array $user, array $filter = []): array
    {
        $where = [];
        $params = [];
        if ($user['role'] !== 'admin') {
            $where[] = 't.requester_id = ?';
            $params[] = $user['id'];
        }
        if (in_array($filter['status'] ?? '', self::STATUSES, true)) {
            $where[] = 't.status = ?';
            $params[] = $filter['status'];
        } elseif (($filter['status'] ?? '') === 'open') {
            $where[] = "t.status <> '완료'";
        }
        $sql = "SELECT t.*, r.name AS requester_name, r.dept AS requester_dept, a.name AS assignee_name
                FROM tickets t
                JOIN users r ON r.id = t.requester_id
                LEFT JOIN users a ON a.id = t.assignee_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY CASE t.status WHEN '접수' THEN 0 WHEN '처리중' THEN 1 ELSE 2 END,
                          CASE t.priority WHEN '긴급' THEN 0 WHEN '높음' THEN 1 WHEN '보통' THEN 2 ELSE 3 END,
                          t.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 상태 변경. 허용되지 않는 변경은 예외를 던진다.
     * 완료할 때는 처리 내용을 반드시 적어야 한다 (나중에 같은 장애가 나면 찾아보기 위해).
     */
    public function transition(int $id, string $action, array $actor, string $resolution = '', string $ip = ''): array
    {
        if ($actor['role'] !== 'admin') {
            throw new \DomainException('상태 변경은 전산 담당자만 할 수 있습니다.');
        }
        $ticket = $this->find($id);
        if ($ticket === null) {
            throw new \DomainException('장애 건을 찾을 수 없습니다.');
        }
        $next = self::TRANSITIONS[$ticket['status']][$action] ?? null;
        if ($next === null) {
            throw new \DomainException("'{$ticket['status']}' 상태에서는 이 작업을 할 수 없습니다.");
        }
        $resolution = trim($resolution);
        if ($action === 'resolve' && $resolution === '') {
            throw new ValidationException(['resolution' => '처리 내용을 입력하세요.']);
        }

        $now = Clock::now();
        $sets = ['status = ?', 'assignee_id = ?'];
        $params = [$next, $actor['id']];
        if ($action === 'start' || ($action === 'resolve' && $ticket['started_at'] === null)) {
            $sets[] = 'started_at = ?';
            $params[] = $now;
        }
        if ($action === 'resolve') {
            $sets[] = 'resolved_at = ?';
            $sets[] = 'resolution = ?';
            array_push($params, $now, $resolution);
        }
        if ($action === 'reopen') {
            $sets[] = 'resolved_at = NULL';
        }
        $params[] = $id;

        // 동시에 두 사람이 처리해도 꼬이지 않도록 현재 상태를 조건에 넣는다
        $params[] = $ticket['status'];
        $stmt = $this->pdo->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ?');
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            throw new \DomainException('다른 담당자가 먼저 상태를 바꿨습니다. 새로고침 후 다시 확인하세요.');
        }

        $label = ['start' => '처리 시작', 'resolve' => '완료', 'reopen' => '재접수'][$action];
        $this->audit->write($actor, 'update', 'ticket', "#{$id} {$ticket['status']} → {$next} ({$label})", $ip);
        return $this->find($id);
    }

    /**
     * 통계: 미처리 건수, 이번 달 접수·완료, 이번 달 평균 처리 시간(분), 분류별 건수.
     * 처리 시간은 DB 함수 차이(MySQL TIMESTAMPDIFF / SQLite julianday)를 피하려고 PHP에서 계산한다.
     */
    public function stats(): array
    {
        $monthStart = date('Y-m-01 00:00:00', Clock::ts());

        $open = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status <> '완료'")->fetchColumn();
        $urgent = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status <> '완료' AND priority = '긴급'")->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tickets WHERE created_at >= ?');
        $stmt->execute([$monthStart]);
        $createdThisMonth = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT created_at, resolved_at FROM tickets WHERE resolved_at >= ?');
        $stmt->execute([$monthStart]);
        $minutes = array_map(
            fn ($r) => (strtotime($r['resolved_at']) - strtotime($r['created_at'])) / 60,
            $stmt->fetchAll()
        );

        $stmt = $this->pdo->prepare('SELECT category, COUNT(*) AS cnt FROM tickets WHERE created_at >= ? GROUP BY category ORDER BY cnt DESC');
        $stmt->execute([$monthStart]);

        return [
            'open'               => $open,
            'urgent_open'        => $urgent,
            'created_this_month' => $createdThisMonth,
            'resolved_this_month'=> count($minutes),
            'avg_minutes'        => $minutes ? (int) round(array_sum($minutes) / count($minutes)) : null,
            'by_category'        => $stmt->fetchAll(),
        ];
    }
}

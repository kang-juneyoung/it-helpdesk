<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * 전산 장비 관리대장 (PC, 노트북, 서버, 네트워크 장비, 프린터).
 */
final class AssetRepository
{
    public const CATEGORIES = ['PC', '노트북', '서버', '네트워크', '프린터', '기타'];
    public const STATUSES   = ['사용', '예비', '수리', '폐기'];

    private const FIELDS = ['asset_no', 'category', 'model', 'user_name', 'dept', 'location',
                            'ip', 'os', 'purchased_on', 'warranty_until', 'status', 'memo'];

    public function __construct(private PDO $pdo, private AuditLog $audit)
    {
    }

    /** 입력 정리: 앞뒤 공백 제거, 빈 날짜는 NULL. */
    public static function normalize(array $input): array
    {
        $data = [];
        foreach (self::FIELDS as $f) {
            $data[$f] = trim((string) ($input[$f] ?? ''));
        }
        $data['asset_no'] = strtoupper($data['asset_no']);
        foreach (['purchased_on', 'warranty_until'] as $f) {
            if ($data[$f] === '') {
                $data[$f] = null;
            }
        }
        return $data;
    }

    public function validate(array $data, ?int $id = null): array
    {
        $v = (new Validator())
            ->required($data, 'asset_no', '자산번호')
            ->pattern($data, 'asset_no', '자산번호', '/^[A-Z]{2,4}-\d{4}-\d{3}$/', '예: PC-2024-013')
            ->in($data, 'category', '분류', self::CATEGORIES)
            ->in($data, 'status', '상태', self::STATUSES)
            ->maxLen($data, 'model', '모델명', 80)
            ->maxLen($data, 'user_name', '사용자', 40)
            ->maxLen($data, 'dept', '부서', 40)
            ->maxLen($data, 'location', '위치', 60)
            ->maxLen($data, 'memo', '메모', 255)
            ->ip($data, 'ip', 'IP 주소')
            ->date($data, 'purchased_on', '구매일')
            ->date($data, 'warranty_until', '보증 만료일');

        if ($v->ok() && $data['purchased_on'] && $data['warranty_until'] && $data['warranty_until'] < $data['purchased_on']) {
            $v->add('warranty_until', '보증 만료일이 구매일보다 앞설 수 없습니다.');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM assets WHERE asset_no = ? AND id <> ?');
        $stmt->execute([$data['asset_no'], $id ?? 0]);
        if ($stmt->fetch()) {
            $v->add('asset_no', '이미 등록된 자산번호입니다.');
        }
        return $v->errors();
    }

    /** 같은 IP를 쓰는 다른 장비가 있으면 경고용으로 돌려준다 (IP 충돌은 흔한 장애 원인). */
    public function ipConflicts(string $ip, ?int $exceptId = null): array
    {
        if ($ip === '') {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT asset_no, user_name FROM assets WHERE ip = ? AND id <> ? AND status <> '폐기'");
        $stmt->execute([$ip, $exceptId ?? 0]);
        return $stmt->fetchAll();
    }

    public function search(array $filter = []): array
    {
        $where = [];
        $params = [];
        if (($q = trim((string) ($filter['q'] ?? ''))) !== '') {
            // 검색어의 %, _ 를 글자 그대로 찾도록 '!'로 이스케이프 (MySQL·SQLite 공통 문법)
            $where[] = "(asset_no LIKE ? ESCAPE '!' OR model LIKE ? ESCAPE '!' OR user_name LIKE ? ESCAPE '!'"
                     . " OR ip LIKE ? ESCAPE '!' OR location LIKE ? ESCAPE '!')";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        foreach (['category', 'status', 'dept'] as $f) {
            if (($val = (string) ($filter[$f] ?? '')) !== '') {
                $where[] = "$f = ?";
                $params[] = $val;
            }
        }
        $sql = 'SELECT * FROM assets' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY asset_no';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function departments(): array
    {
        return $this->pdo->query("SELECT DISTINCT dept FROM assets WHERE dept <> '' ORDER BY dept")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function create(array $data, array $actor, string $ip = ''): int
    {
        $data['updated_at'] = Clock::now();
        $cols = array_keys($data);
        $stmt = $this->pdo->prepare(
            'INSERT INTO assets (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
        );
        $stmt->execute(array_values($data));
        $id = (int) $this->pdo->lastInsertId();
        $this->audit->write($actor, 'create', 'asset', "{$data['asset_no']} 등록", $ip);
        return $id;
    }

    public function update(int $id, array $data, array $actor, string $ip = ''): void
    {
        $before = $this->find($id);
        if ($before === null) {
            throw new \RuntimeException('장비를 찾을 수 없습니다.');
        }
        $data['updated_at'] = Clock::now();
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE assets SET $sets WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);

        // 무엇이 바뀌었는지 감사 로그에 남긴다
        $changes = [];
        foreach (self::FIELDS as $f) {
            if ((string) ($before[$f] ?? '') !== (string) ($data[$f] ?? '')) {
                $changes[] = "{$f}: " . ($before[$f] ?? '') . ' → ' . ($data[$f] ?? '');
            }
        }
        $this->audit->write($actor, 'update', 'asset', "{$data['asset_no']} " . ($changes ? implode(', ', $changes) : '변경 없음'), $ip);
    }

    public function delete(int $id, array $actor, string $ip = ''): void
    {
        $asset = $this->find($id);
        if ($asset === null) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            // 장애 이력은 남기고 장비 연결만 끊는다
            $this->pdo->prepare('UPDATE tickets SET asset_id = NULL WHERE asset_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM assets WHERE id = ?')->execute([$id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->audit->write($actor, 'delete', 'asset', "{$asset['asset_no']} 삭제", $ip);
    }

    /** 보증 만료가 가까운 장비 (폐기 제외). */
    public function warrantyExpiring(int $days = 60): array
    {
        $until = date('Y-m-d', Clock::ts() + $days * 86400);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM assets WHERE warranty_until IS NOT NULL AND warranty_until <= ? AND status <> '폐기' ORDER BY warranty_until"
        );
        $stmt->execute([$until]);
        return $stmt->fetchAll();
    }
}

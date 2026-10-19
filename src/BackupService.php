<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * 백업 점검 일지.
 *
 * 두 가지 방법으로 기록한다.
 *  1) 수동: 전산 담당자가 화면에서 결과를 적는다 (테이프·외장 디스크 교체 등)
 *  2) 자동: bin/check_backup.php 를 cron 으로 매일 돌려 백업 폴더를 검사한다
 *
 * "백업이 돌았는지"가 아니라 "최근 파일이 실제로 있고 크기가 0이 아닌지"를 본다.
 * 백업 프로그램이 성공이라고 해도 파일이 비어 있는 경우가 실제로 있기 때문이다.
 */
final class BackupService
{
    public const RESULTS = ['성공', '실패'];

    public function __construct(private PDO $pdo, private AuditLog $audit)
    {
    }

    public function record(array $input, ?array $actor, string $source = '수동', string $ip = ''): int
    {
        $data = [
            'target'    => trim((string) ($input['target'] ?? '')),
            'result'    => (string) ($input['result'] ?? ''),
            'size_mb'   => (string) ($input['size_mb'] ?? '0'),
            'file_name' => trim((string) ($input['file_name'] ?? '')),
            'detail'    => trim((string) ($input['detail'] ?? '')),
        ];
        $v = (new Validator())
            ->required($data, 'target', '백업 대상')
            ->maxLen($data, 'target', '백업 대상', 60)
            ->in($data, 'result', '결과', self::RESULTS)
            ->maxLen($data, 'detail', '비고', 255);
        if (!is_numeric($data['size_mb']) || (float) $data['size_mb'] < 0) {
            $v->add('size_mb', '크기는 0 이상의 숫자로 입력하세요.');
        }
        if (!$v->ok()) {
            throw new ValidationException($v->errors());
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO backups (target, result, size_mb, file_name, detail, source, checked_by, checked_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['target'], $data['result'], round((float) $data['size_mb'], 1),
            $data['file_name'], $data['detail'], $source, $actor['id'] ?? null, Clock::now(),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit->write($actor, 'backup', 'backup', "{$data['target']} {$data['result']} ({$source})", $ip);
        return $id;
    }

    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));
        return $this->pdo->query(
            "SELECT b.*, u.name AS checker FROM backups b LEFT JOIN users u ON u.id = b.checked_by ORDER BY b.checked_at DESC, b.id DESC LIMIT $limit"
        )->fetchAll();
    }

    /**
     * 대상별 최신 기록 하나씩.
     * 입력 순서(id)가 아니라 점검 시각(checked_at) 기준이다.
     * 과거 날짜로 나중에 입력한 수동 기록이 "최신"으로 잡히면 경보가 틀리게 뜨기 때문이다.
     */
    public function latestByTarget(): array
    {
        return $this->pdo->query(
            'SELECT b.* FROM backups b
             WHERE b.id = (SELECT b2.id FROM backups b2 WHERE b2.target = b.target
                           ORDER BY b2.checked_at DESC, b2.id DESC LIMIT 1)
             ORDER BY b.target'
        )->fetchAll();
    }

    /**
     * 확인이 필요한 대상: 최신 기록이 실패이거나, 마지막 기록이 너무 오래된 경우.
     * @return array<int, array{target: string, reason: string, checked_at: string}>
     */
    public function alerts(int $maxHours = 26): array
    {
        $alerts = [];
        foreach ($this->latestByTarget() as $row) {
            $age = (Clock::ts() - strtotime($row['checked_at'])) / 3600;
            if ($row['result'] === '실패') {
                $alerts[] = ['target' => $row['target'], 'reason' => '최근 백업 실패: ' . $row['detail'], 'checked_at' => $row['checked_at']];
            } elseif ($age > $maxHours) {
                $alerts[] = ['target' => $row['target'], 'reason' => sprintf('%d시간 동안 점검 기록 없음', (int) $age), 'checked_at' => $row['checked_at']];
            }
        }
        return $alerts;
    }

    /**
     * 백업 폴더 자동 점검 (cron 용).
     * 폴더 구조: BACKUP_DIR/<대상 이름>/<백업 파일들>
     * 각 대상 폴더에서 가장 최근 파일을 찾아 시간·크기를 확인하고 결과를 기록한다.
     *
     * @return array<int, array{target: string, result: string, detail: string}>
     */
    public function scanDirectory(string $dir, int $maxHours = 26): array
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException("백업 폴더가 없습니다: {$dir}");
        }
        $results = [];
        $targets = array_filter(scandir($dir) ?: [], fn ($n) => $n[0] !== '.' && is_dir("$dir/$n"));
        sort($targets);

        foreach ($targets as $target) {
            $files = array_filter(glob("$dir/$target/*") ?: [], 'is_file');
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latest = $files[0] ?? null;

            if ($latest === null) {
                $row = ['result' => '실패', 'size_mb' => 0, 'file_name' => '', 'detail' => '백업 파일 없음'];
            } else {
                $size = filesize($latest);
                $ageHours = (Clock::ts() - filemtime($latest)) / 3600;
                $row = ['result' => '성공', 'size_mb' => round($size / 1048576, 1), 'file_name' => basename($latest), 'detail' => ''];
                if ($size === 0) {
                    $row['result'] = '실패';
                    $row['detail'] = '최근 백업 파일 크기 0';
                } elseif ($ageHours > $maxHours) {
                    $row['result'] = '실패';
                    $row['detail'] = sprintf('최근 백업이 %d시간 전 (기준 %d시간)', (int) $ageHours, $maxHours);
                }
            }
            $this->record(['target' => $target] + $row, null, '자동');
            $results[] = ['target' => $target, 'result' => $row['result'], 'detail' => $row['detail'] ?: $row['file_name']];
        }
        return $results;
    }
}

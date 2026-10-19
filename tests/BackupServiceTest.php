<?php
declare(strict_types=1);

namespace Tests;

use App\BackupService;
use App\Clock;
use App\ValidationException;

final class BackupServiceTest extends DbTestCase
{
    private BackupService $backups;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backups = new BackupService($this->pdo, $this->audit);
        $this->dir = sys_get_temp_dir() . '/hd_backup_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $f) {
            unlink($f);
        }
        foreach (glob($this->dir . '/*') ?: [] as $d) {
            rmdir($d);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function backupFile(string $target, string $name, int $bytes, string $modified): void
    {
        @mkdir("{$this->dir}/{$target}");
        file_put_contents("{$this->dir}/{$target}/{$name}", str_repeat('x', $bytes));
        touch("{$this->dir}/{$target}/{$name}", strtotime($modified));
    }

    public function testManualRecordValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->backups->record(['target' => '', 'result' => '애매함', 'size_mb' => '-1'], $this->admin);
    }

    public function testAlertWhenLatestResultFailed(): void
    {
        $this->backups->record(['target' => 'ERP_DB', 'result' => '성공', 'size_mb' => '2300'], $this->admin);
        Clock::advance(3600);
        $this->backups->record(['target' => 'ERP_DB', 'result' => '실패', 'detail' => '디스크 가득 참'], $this->admin);

        $alerts = $this->backups->alerts();

        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('디스크 가득 참', $alerts[0]['reason']);
    }

    public function testAlertWhenNoRecordForTooLong(): void
    {
        $this->backups->record(['target' => '파일서버', 'result' => '성공', 'size_mb' => '51200'], $this->admin);
        Clock::advance(27 * 3600);

        $this->assertSame('파일서버', $this->backups->alerts(26)[0]['target']);
        $this->assertSame([], $this->backups->alerts(48));
    }

    public function testLatestIsDecidedByCheckTimeNotInsertOrder(): void
    {
        $this->backups->record(['target' => 'ERP_DB', 'result' => '성공', 'size_mb' => '2300'], null, '자동');
        // 이틀 전 결과를 나중에 수동으로 입력한 경우
        $this->pdo->prepare("INSERT INTO backups (target, result, checked_at) VALUES ('ERP_DB', '실패', ?)")
            ->execute([date('Y-m-d H:i:s', Clock::ts() - 2 * 86400)]);

        $latest = $this->backups->latestByTarget();

        $this->assertSame('성공', $latest[0]['result']);
        $this->assertSame([], $this->backups->alerts());
    }

    public function testScanDirectoryJudgesEachTarget(): void
    {
        $this->backupFile('ERP_DB', 'erp_old.bak', 100, '2026-10-17 02:00');
        $this->backupFile('ERP_DB', 'erp_new.bak', 2048, '2026-10-19 02:00');   // 최신, 정상
        $this->backupFile('그룹웨어', 'gw.sql.gz', 0, '2026-10-19 02:00');       // 크기 0
        $this->backupFile('파일서버', 'nas.hbk', 500, '2026-10-16 02:00');       // 3일 전
        mkdir("{$this->dir}/MES_DB");                                            // 파일 없음

        $results = array_column($this->backups->scanDirectory($this->dir, 26), null, 'target');

        $this->assertSame('성공', $results['ERP_DB']['result']);
        $this->assertSame('erp_new.bak', $results['ERP_DB']['detail']);
        $this->assertSame('최근 백업 파일 크기 0', $results['그룹웨어']['detail']);
        $this->assertStringContainsString('80시간 전', $results['파일서버']['detail']);
        $this->assertSame('백업 파일 없음', $results['MES_DB']['detail']);
        $this->assertSame(4, $this->count("SELECT COUNT(*) FROM backups WHERE source = '자동'"));
    }

    public function testScanDirectoryFailsLoudlyWhenFolderMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->backups->scanDirectory($this->dir . '/없는폴더');
    }
}

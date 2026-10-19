<?php
declare(strict_types=1);

/*
 * 백업 폴더 자동 점검 (cron 용).
 *
 *   BACKUP_DIR=/backup php bin/check_backup.php
 *
 * BACKUP_DIR 아래 폴더 하나가 백업 대상 하나다.
 *   /backup/ERP_DB/erp_20261019.bak
 *   /backup/파일서버/nas_20261019.hbk
 * 대상마다 가장 최근 파일의 시각과 크기를 확인해 백업 점검 일지에 기록한다.
 * 하나라도 실패하면 종료 코드 1 → cron 메일·모니터링에서 잡을 수 있다.
 *
 * crontab 예 (매일 07:10):
 *   10 7 * * *  cd /var/www/it-helpdesk && BACKUP_DIR=/backup php bin/check_backup.php >> /var/log/check_backup.log 2>&1
 */

use App\AuditLog;
use App\BackupService;
use App\Config;
use App\Db;

require dirname(__DIR__) . '/src/bootstrap.php';

$dir = Config::get('BACKUP_DIR') ?? ($argv[1] ?? null);
if ($dir === null) {
    fwrite(STDERR, "사용법: BACKUP_DIR=/backup php bin/check_backup.php  (또는 폴더를 인자로)\n");
    exit(2);
}
$maxHours = (int) Config::get('BACKUP_MAX_HOURS', '26');

$pdo = Db::connect();
$service = new BackupService($pdo, new AuditLog($pdo));

try {
    $results = $service->scanDirectory($dir, $maxHours);
} catch (\RuntimeException $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] 오류: ' . $e->getMessage() . "\n");
    exit(2);
}

$failed = 0;
foreach ($results as $r) {
    $failed += $r['result'] === '실패' ? 1 : 0;
    // 한글은 화면에서 두 칸을 차지해 printf 폭 지정이 어긋난다 → 표시 폭(mb_strwidth)으로 맞춤
    $pad = str_repeat(' ', max(0, 12 - mb_strwidth($r['target'])));
    echo "[" . date('Y-m-d H:i:s') . "] {$r['target']}{$pad} {$r['result']} {$r['detail']}\n";
}
if ($results === []) {
    echo "[" . date('Y-m-d H:i:s') . "] 점검할 대상 폴더가 없습니다: {$dir}\n";
}
exit($failed > 0 ? 1 : 0);

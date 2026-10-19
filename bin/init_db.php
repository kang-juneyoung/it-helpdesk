<?php
declare(strict_types=1);

/*
 * DB 초기화 + 데모 데이터.
 *   php bin/init_db.php          테이블 생성 + 데모 데이터 (가상의 제조회사)
 *   php bin/init_db.php --empty  테이블만 생성하고 관리자 계정 하나만 만든다
 *
 * 데모 데이터의 회사·사람·장비는 모두 가상이다.
 */

use App\AuditLog;
use App\Auth;
use App\Clock;
use App\Db;

require dirname(__DIR__) . '/src/bootstrap.php';

$empty = in_array('--empty', $argv, true);

$dsn = App\Config::dsn();
if (str_starts_with($dsn, 'sqlite:')) {
    $file = substr($dsn, 7);
    @mkdir(dirname($file), 0775, true);
    if (is_file($file)) {
        unlink($file);
    }
}

$pdo = Db::connect();
Db::migrate($pdo);
if (Db::driver($pdo) === 'mysql') {
    foreach (['audit_logs', 'backups', 'tickets', 'assets', 'users'] as $t) {
        $pdo->exec("DELETE FROM $t");
    }
}

$audit = new AuditLog($pdo);
$auth  = new Auth($pdo, $audit);

$adminId = $auth->createUser('admin', 'admin1234!', '강전산', '전산실', 'admin');
if ($empty) {
    echo "테이블 생성 완료. 관리자 계정: admin / admin1234! (로그인 후 바로 바꾸세요)\n";
    exit(0);
}

// ── 직원 ────────────────────────────────────────────────
$staff = [
    ['kim',  '김생산', '생산팀'],
    ['lee',  '이품질', '품질팀'],
    ['park', '박영업', '영업팀'],
    ['choi', '최회계', '회계팀'],
    ['jung', '정연구', '연구소'],
];
$ids = [];
foreach ($staff as [$u, $name, $dept]) {
    $ids[$u] = $auth->createUser($u, 'staff1234!', $name, $dept, 'staff');
}

// ── 장비 ────────────────────────────────────────────────
$today = Clock::ts();
$d = fn (int $days) => date('Y-m-d', $today + $days * 86400);
$assets = [
    ['SV-2021-001', '서버', 'Dell PowerEdge R450',    '전산실', '전산실',   '전산실 랙 1', '192.168.10.10', 'Windows Server 2019', $d(-1600), $d(25),   '사용', 'ERP DB 서버'],
    ['SV-2022-002', '서버', 'Synology RS1221+',       '전산실', '전산실',   '전산실 랙 1', '192.168.10.20', 'DSM 7.2',             $d(-1200), $d(600),  '사용', '파일서버 (NAS)'],
    ['SV-2023-003', '서버', 'Ubuntu 22.04 VM',         '전산실', '전산실',   '전산실 랙 2', '192.168.10.30', 'Ubuntu 22.04 LTS',    $d(-800),  null,     '사용', '그룹웨어 (PHP/MySQL)'],
    ['NW-2022-001', '네트워크', 'ipTIME Pro 6000',    '전산실', '전산실',   '전산실 랙 1', '192.168.0.1',   '',                    $d(-1100), $d(-30),  '사용', '방화벽·공유기'],
    ['NW-2023-002', '네트워크', 'NETGEAR GS724T',     '전산실', '생산팀',   '공장 1동 배전반', '192.168.0.2', '',                  $d(-900),  $d(200),  '사용', '공장 스위치'],
    ['PC-2022-011', 'PC', 'LG 일체형 24V70Q',          '김생산', '생산팀',   '공장 1동 사무실', '192.168.0.31', 'Windows 10 Pro',   $d(-1300), $d(-100), '사용', 'Windows 10 지원 종료 — 교체 대상'],
    ['PC-2023-012', 'PC', '삼성 DM500SFA',             '이품질', '품질팀',   '공장 2동 품질실', '192.168.0.32', 'Windows 11 Pro',   $d(-700),  $d(400),  '사용', ''],
    ['NB-2024-013', '노트북', 'LG 그램 15Z90S',         '박영업', '영업팀',   '본관 2층',       '192.168.0.33', 'Windows 11 Pro',   $d(-400),  $d(700),  '사용', '외근용 VPN 설정'],
    ['PC-2024-014', 'PC', '삼성 DM500SGA',             '최회계', '회계팀',   '본관 2층',       '192.168.0.34', 'Windows 11 Pro',   $d(-380),  $d(45),   '사용', ''],
    ['PC-2025-015', 'PC', 'Dell OptiPlex 7020',        '정연구', '연구소',   '연구동 1층',     '192.168.0.35', 'Windows 11 Pro',   $d(-200),  $d(900),  '사용', 'CAD 작업용'],
    ['PR-2022-001', '프린터', 'HP LaserJet M428fdw',    '공용',   '회계팀',   '본관 2층 복도',  '192.168.0.50', '',                  $d(-1000), $d(-200), '사용', ''],
    ['PR-2023-002', '프린터', 'Canon iR-ADV C3530',     '공용',   '생산팀',   '공장 1동 사무실', '192.168.0.51', '',                 $d(-600),  $d(130),  '수리', '급지 롤러 교체 대기'],
    ['PC-2019-003', 'PC', 'HP ProDesk 400 G6',         '',       '전산실',   '전산실 창고',     '',             'Windows 10 Pro',   $d(-2400), $d(-1300), '예비', '임시 대여용'],
];
$stmt = $pdo->prepare('INSERT INTO assets (asset_no, category, model, user_name, dept, location, ip, os, purchased_on, warranty_until, status, memo, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$assetIds = [];
foreach ($assets as $a) {
    $stmt->execute([...$a, Clock::now()]);
    $assetIds[$a[0]] = (int) $pdo->lastInsertId();
}

// ── 장애 이력 (오늘 기준 며칠 전으로 만든다) ────────────────
$at = fn (float $daysAgo) => date('Y-m-d H:i:s', (int) ($today - $daysAgo * 86400));
$tickets = [
    // [요청자, 제목, 분류, 긴급도, 장비, 내용, 접수(일 전), 시작(일 전), 완료(일 전), 처리 내용]
    ['choi', '회계팀 공용 프린터 인쇄 대기열 멈춤', '프린터', '높음', 'PR-2022-001',
        "오전부터 인쇄를 눌러도 대기열에만 쌓이고 나오지 않습니다.\n세금계산서 출력해야 해서 급합니다.", 12, 11.98, 11.95,
        '인쇄 스풀러 서비스 정지 상태. 대기열 파일 삭제 후 스풀러 재시작. 드라이버 최신 버전으로 교체.'],
    ['kim', '공장 1동 ERP 접속 느림', '네트워크', '긴급', 'NW-2023-002',
        "생산 실적 입력 화면이 30초 이상 걸립니다. 1동 전체가 같은 증상입니다.", 9, 8.99, 8.9,
        '공장 스위치 포트 하나에서 루프 발생(작업자가 랜선 양쪽을 같은 허브에 꽂음). 해당 케이블 제거, 스위치 루프 방지(STP) 설정.'],
    ['park', '외근 중 VPN 연결 안 됨', '네트워크', '보통', 'NB-2024-013',
        "거래처에서 VPN 연결 시 인증 실패가 뜹니다.", 7, 6.9, 6.85,
        'VPN 계정 비밀번호 만료(90일 정책). 원격으로 초기화하고 변경 방법 안내.'],
    ['jung', 'CAD 프로그램 라이선스 오류', 'ERP·프로그램', '높음', 'PC-2025-015',
        "실행하면 '라이선스 서버를 찾을 수 없음' 메시지가 나옵니다.", 5, 4.95, 4.7,
        '라이선스 서버(192.168.10.30) 방화벽 규칙이 윈도우 업데이트 후 초기화됨. 27000번 포트 다시 허용.'],
    ['lee', '품질실 PC 부팅 시 블루스크린', 'PC', '높음', 'PC-2023-012',
        "켜면 파란 화면이 뜨고 재부팅을 반복합니다.", 3, 2.95, 2.6,
        '최근 설치한 USB 측정기 드라이버 충돌. 안전모드에서 드라이버 제거 후 제조사 최신 드라이버 설치.'],
    ['kim', '생산팀 공용 프린터 용지 걸림 반복', '프린터', '보통', 'PR-2023-002',
        "하루에 서너 번씩 용지가 걸립니다.", 1.2, 1.1, null, null],
    ['choi', '신규 입사자 그룹웨어 계정 요청', '계정·권한', '보통', null,
        "다음 주 월요일 입사하는 회계팀 신입 계정 만들어 주세요.\n결재선은 팀장님 → 이사님입니다.", 0.3, null, null, null],
    ['kim', '공장 1동 PC 윈도우 업데이트 후 ERP 화면 깨짐', 'ERP·프로그램', '긴급', 'PC-2022-011',
        "ERP 생산실적 화면 글자가 겹쳐 보여서 입력을 못 합니다.", 0.05, null, null, null],
];
$ins = $pdo->prepare('INSERT INTO tickets (title, category, priority, status, requester_id, asset_id, content, assignee_id, resolution, created_at, started_at, resolved_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($tickets as [$u, $title, $cat, $pri, $assetNo, $content, $c, $s, $r, $resolution]) {
    $status = $r !== null ? '완료' : ($s !== null ? '처리중' : '접수');
    $ins->execute([
        $title, $cat, $pri, $status, $ids[$u], $assetNo ? $assetIds[$assetNo] : null, $content,
        $s !== null ? $adminId : null, $resolution, $at($c), $s !== null ? $at($s) : null, $r !== null ? $at($r) : null,
    ]);
}

// ── 백업 기록 ───────────────────────────────────────────
$bk = $pdo->prepare("INSERT INTO backups (target, result, size_mb, file_name, detail, source, checked_by, checked_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
for ($i = 6; $i >= 1; $i--) {
    $day = date('Ymd', $today - $i * 86400);
    $bk->execute(['ERP_DB', '성공', 2310.4 + $i, "erp_{$day}.bak", '', '자동', null, $at($i - 0.1)]);
    $bk->execute(['파일서버', '성공', 51200.0, "nas_{$day}.hbk", '', '자동', null, $at($i - 0.12)]);
    $bk->execute(['그룹웨어', $i === 1 ? '실패' : '성공', $i === 1 ? 0 : 845.2, $i === 1 ? "gw_{$day}.sql.gz" : "gw_{$day}.sql.gz",
                  $i === 1 ? '최근 백업 파일 크기 0' : '', '자동', null, $at($i - 0.11)]);
}
$bk->execute(['ERP_DB', '성공', 2310.0, '', '월간 복구 테스트: 테스트 서버에 복원 후 전표 조회 확인', '수동', $adminId, $at(2.2)]);

// 감사 로그 몇 건
$audit->write(['id' => $ids['park'], 'username' => 'park'], 'login_fail', 'user', '비밀번호 오류 1회', '192.168.0.33');
$audit->write(['id' => $adminId, 'username' => 'admin'], 'export', 'asset', '13건 CSV 내보내기', '192.168.0.10');

echo "데모 데이터 생성 완료 ({$dsn})\n";
echo "  전산 담당: admin / admin1234!\n";
echo "  일반 직원: kim, lee, park, choi, jung / staff1234!\n";

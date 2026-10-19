<?php
declare(strict_types=1);

/*
 * 프런트 컨트롤러. 모든 요청이 이 파일을 거친다.
 * 주소 예: index.php?r=tickets, index.php?r=assets/edit&id=3
 */

use App\AssetRepository;
use App\AuditLog;
use App\Auth;
use App\BackupService;
use App\Config;
use App\Csv;
use App\Db;
use App\Session;
use App\TicketService;
use App\ValidationException;
use App\View;

require dirname(__DIR__) . '/src/bootstrap.php';

// 보안 헤더: 다른 사이트에 iframe으로 끼워 넣기 금지, 외부 스크립트 금지
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
header('Referrer-Policy: same-origin');

Session::start();

$pdo     = Db::get();
$audit   = new AuditLog($pdo);
$auth    = new Auth($pdo, $audit);
$assets  = new AssetRepository($pdo, $audit);
$tickets = new TicketService($pdo, $audit);
$backups = new BackupService($pdo, $audit);

$route  = (string) ($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$user   = Session::user();

function redirect(string $route, array $query = []): never
{
    $qs = http_build_query(['r' => $route] + $query);
    header("Location: index.php?{$qs}");
    exit;
}

function respond(string $html, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function error_page(string $title, string $message, int $status): never
{
    $u = Session::user();
    $vars = ['title' => $title, 'message' => $message, 'user' => $u,
             'isAdmin' => ($u['role'] ?? '') === 'admin', 'route' => (string) ($_GET['r'] ?? '')];
    respond(View::render('layout/error', $vars, $u ? 'layout/main' : 'layout/bare'), $status);
}

function deny(string $message = '권한이 없습니다.'): never
{
    error_page('접근 제한', $message, 403);
}

// POST 요청은 모두 CSRF 토큰을 확인한다 (다른 사이트가 몰래 보낸 요청 차단)
if ($method === 'POST' && !Session::checkCsrf($_POST['_csrf'] ?? null)) {
    error_page('요청 만료', '페이지가 오래되었습니다. 새로고침 후 다시 시도하세요.', 400);
}

// ── 로그인 ───────────────────────────────────────────────
if ($route === 'login') {
    if ($user) {
        redirect('');
    }
    $error = null;
    $username = '';
    if ($method === 'POST') {
        $username = (string) ($_POST['username'] ?? '');
        $result = $auth->attempt($username, (string) ($_POST['password'] ?? ''), $ip);
        if ($result['ok']) {
            Session::login($result['user']);
            redirect('');
        }
        $error = $result['error'];
    }
    respond(View::render('auth/login', ['error' => $error, 'username' => $username, 'user' => null], 'layout/bare'));
}

if ($user === null) {
    redirect('login');
}
$isAdmin = $user['role'] === 'admin';
$base = ['user' => $user, 'isAdmin' => $isAdmin, 'route' => $route];

if ($route === 'logout' && $method === 'POST') {
    $audit->write($user, 'logout', 'user', '로그아웃', $ip);
    Session::logout();
    redirect('login');
}

// ── 대시보드 ─────────────────────────────────────────────
if ($route === '') {
    if (!$isAdmin) {
        redirect('tickets');
    }
    respond(View::render('dashboard', $base + [
        'title'    => '대시보드',
        'stats'    => $tickets->stats(),
        'open'     => array_slice($tickets->list($user, ['status' => 'open']), 0, 8),
        'alerts'   => $backups->alerts((int) Config::get('BACKUP_MAX_HOURS', '26')),
        'latest'   => $backups->latestByTarget(),
        'warranty' => $assets->warrantyExpiring(60),
    ]));
}

// ── 장애 접수·처리 ───────────────────────────────────────
if ($route === 'tickets') {
    $status = (string) ($_GET['status'] ?? ($isAdmin ? 'open' : ''));
    respond(View::render('tickets/list', $base + [
        'title'   => $isAdmin ? '장애 처리' : '내 장애 접수',
        'tickets' => $tickets->list($user, ['status' => $status]),
        'status'  => $status,
    ]));
}

if ($route === 'tickets/new') {
    $errors = [];
    $old = ['priority' => '보통'];
    if ($method === 'POST') {
        $old = $_POST;
        try {
            $id = $tickets->create($_POST, $user, $ip);
            Session::flash('ok', "접수되었습니다. (접수번호 #{$id})");
            redirect('tickets/view', ['id' => $id]);
        } catch (ValidationException $e) {
            $errors = $e->errors;
        }
    }
    respond(View::render('tickets/new', $base + [
        'title'  => '장애 접수',
        'errors' => $errors,
        'old'    => $old,
        'myAssets' => $assets->search(['q' => $user['name']]),
    ]));
}

if ($route === 'tickets/view') {
    $ticket = $tickets->find((int) ($_GET['id'] ?? 0));
    if ($ticket === null || !$tickets->canView($ticket, $user)) {
        deny('장애 건을 찾을 수 없거나 볼 권한이 없습니다.');
    }
    respond(View::render('tickets/view', $base + ['title' => "장애 #{$ticket['id']}", 'ticket' => $ticket, 'errors' => []]));
}

if ($route === 'tickets/action' && $method === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $tickets->transition($id, (string) ($_POST['action'] ?? ''), $user, (string) ($_POST['resolution'] ?? ''), $ip);
        Session::flash('ok', '상태를 변경했습니다.');
    } catch (ValidationException $e) {
        Session::flash('error', implode(' ', $e->errors));
    } catch (\DomainException $e) {
        Session::flash('error', $e->getMessage());
    }
    redirect('tickets/view', ['id' => $id]);
}

// ── 이하 전산 담당자 전용 ────────────────────────────────
if (!$isAdmin) {
    deny('전산 담당자만 사용할 수 있는 메뉴입니다.');
}

if ($route === 'assets') {
    $filter = array_intersect_key($_GET, array_flip(['q', 'category', 'status', 'dept']));
    respond(View::render('assets/list', $base + [
        'title'  => '장비 관리대장',
        'assets' => $assets->search($filter),
        'filter' => $filter,
        'depts'  => $assets->departments(),
    ]));
}

if ($route === 'assets/export') {
    $rows = $assets->search(array_intersect_key($_GET, array_flip(['q', 'category', 'status', 'dept'])));
    $audit->write($user, 'export', 'asset', count($rows) . '건 CSV 내보내기', $ip);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assets_' . date('Ymd') . '.csv"');
    echo Csv::build($rows, [
        'asset_no' => '자산번호', 'category' => '분류', 'model' => '모델', 'user_name' => '사용자',
        'dept' => '부서', 'location' => '위치', 'ip' => 'IP', 'os' => 'OS',
        'purchased_on' => '구매일', 'warranty_until' => '보증만료', 'status' => '상태', 'memo' => '메모',
    ]);
    exit;
}

if ($route === 'assets/edit') {
    $id = (int) ($_GET['id'] ?? 0) ?: null;
    $asset = $id ? $assets->find($id) : null;
    if ($id && $asset === null) {
        deny('장비를 찾을 수 없습니다.');
    }
    $errors = [];
    $old = $asset ?? ['category' => 'PC', 'status' => '사용'];
    if ($method === 'POST') {
        $data = AssetRepository::normalize($_POST);
        $old = $data;
        $errors = $assets->validate($data, $id);
        if (!$errors) {
            $conflicts = $assets->ipConflicts($data['ip'], $id);
            if ($id) {
                $assets->update($id, $data, $user, $ip);
            } else {
                $id = $assets->create($data, $user, $ip);
            }
            Session::flash('ok', "{$data['asset_no']} 저장했습니다.");
            foreach ($conflicts as $c) {
                Session::flash('warn', "IP {$data['ip']}를 {$c['asset_no']}({$c['user_name']})도 쓰고 있습니다. IP 충돌을 확인하세요.");
            }
            redirect('assets');
        }
    }
    respond(View::render('assets/edit', $base + [
        'title'  => $id ? '장비 수정' : '장비 등록',
        'id'     => $id,
        'old'    => $old,
        'errors' => $errors,
    ]));
}

if ($route === 'assets/delete' && $method === 'POST') {
    $assets->delete((int) ($_POST['id'] ?? 0), $user, $ip);
    Session::flash('ok', '삭제했습니다.');
    redirect('assets');
}

if ($route === 'backups') {
    $errors = [];
    $old = ['result' => '성공'];
    if ($method === 'POST') {
        $old = $_POST;
        try {
            $backups->record($_POST, $user, '수동', $ip);
            Session::flash('ok', '백업 점검 결과를 기록했습니다.');
            redirect('backups');
        } catch (ValidationException $e) {
            $errors = $e->errors;
        }
    }
    respond(View::render('backups/list', $base + [
        'title'   => '백업 점검',
        'records' => $backups->recent(50),
        'alerts'  => $backups->alerts((int) Config::get('BACKUP_MAX_HOURS', '26')),
        'targets' => array_column($backups->latestByTarget(), 'target'),
        'errors'  => $errors,
        'old'     => $old,
    ]));
}

if ($route === 'users') {
    $errors = [];
    $old = [];
    $issued = null;
    if ($method === 'POST') {
        $op = (string) ($_POST['op'] ?? '');
        try {
            if ($op === 'create') {
                $old = $_POST;
                $temp = Auth::tempPassword();
                $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
                $newId = $auth->createUser((string) ($_POST['username'] ?? ''), $temp, trim((string) ($_POST['name'] ?? '')),
                                           trim((string) ($_POST['dept'] ?? '')), $role);
                $audit->write($user, 'create', 'user', "사용자 #{$newId} " . strtolower(trim((string) $_POST['username'])) . " 생성 ({$role})", $ip);
                $issued = ['username' => strtolower(trim((string) $_POST['username'])), 'name' => $_POST['name'], 'password' => $temp];
                $old = [];
            } elseif ($op === 'unlock') {
                $auth->unlock((int) ($_POST['id'] ?? 0), $user, $ip);
                Session::flash('ok', '잠금을 해제했습니다.');
                redirect('users');
            } elseif ($op === 'reset') {
                $target = array_values(array_filter($auth->users(), fn ($u) => (int) $u['id'] === (int) ($_POST['id'] ?? 0)))[0] ?? null;
                if ($target === null) {
                    throw new \DomainException('사용자를 찾을 수 없습니다.');
                }
                $issued = ['username' => $target['username'], 'name' => $target['name'], 'password' => $auth->resetPassword((int) $target['id'], $user, $ip)];
            }
        } catch (ValidationException $e) {
            $errors = $e->errors;
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
    }
    // 임시 비밀번호가 보이는 화면은 브라우저가 저장하지 않게 한다
    header('Cache-Control: no-store');
    respond(View::render('users/list', $base + [
        'title'  => '계정 관리',
        'users'  => $auth->users(),
        'errors' => $errors,
        'old'    => $old,
        'issued' => $issued,
    ]));
}

if ($route === 'audit') {
    $action = (string) ($_GET['action'] ?? '');
    respond(View::render('audit/list', $base + [
        'title'  => '감사 로그',
        'logs'   => $audit->recent(200, $action),
        'action' => $action,
    ]));
}

error_page('페이지 없음', '요청한 페이지를 찾을 수 없습니다.', 404);

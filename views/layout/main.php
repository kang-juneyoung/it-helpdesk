<?php
/** @var string $content @var array $user @var bool $isAdmin @var string $title @var string $route */
use App\Session;

$menu = $isAdmin
    ? ['' => '대시보드', 'tickets' => '장애 처리', 'assets' => '장비대장', 'backups' => '백업 점검', 'users' => '계정', 'audit' => '감사 로그']
    : ['tickets' => '내 접수 목록', 'tickets/new' => '장애 접수'];
$section = explode('/', $route)[0];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · 사내 전산 관리</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">사내 전산 관리</a>
    <nav class="menu">
        <?php foreach ($menu as $r => $label): ?>
            <?php $active = $r === '' ? $route === '' : ($r === $route || ($r === $section && !isset($menu[$route]))); ?>
            <a href="index.php<?= $r === '' ? '' : '?r=' . e($r) ?>" class="<?= $active ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="index.php?r=logout" class="who">
        <?= csrf_field() ?>
        <span><?= e($user['dept']) ?> <?= e($user['name']) ?></span>
        <button type="submit" class="link">로그아웃</button>
    </form>
</header>
<main class="page">
    <?php foreach (Session::takeFlash() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>

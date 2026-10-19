<?php
/** @var array $tickets @var string $status @var bool $isAdmin @var string $title */
$tabs = $isAdmin
    ? ['open' => '미처리', '접수' => '접수', '처리중' => '처리중', '완료' => '완료', 'all' => '전체']
    : ['' => '전체', 'open' => '진행 중', '완료' => '완료'];
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
    <a class="btn primary" href="index.php?r=tickets/new">+ 장애 접수</a>
</div>

<nav class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="index.php?r=tickets&amp;status=<?= e($key) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$tickets): ?>
    <p class="card muted">해당하는 장애 건이 없습니다.</p>
<?php else: ?>
<table class="table">
    <thead>
    <tr><th>번호</th><th>긴급도</th><th>상태</th><th>제목</th><th>분류</th><th>요청자</th><th>접수</th><th>담당</th></tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
        <tr>
            <td data-label="번호">#<?= e($t['id']) ?></td>
            <td data-label="긴급도"><span class="badge pri-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span></td>
            <td data-label="상태"><span class="badge st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
            <td data-label="제목" class="title-cell"><a href="index.php?r=tickets/view&amp;id=<?= e($t['id']) ?>"><?= e($t['title']) ?></a></td>
            <td data-label="분류"><?= e($t['category']) ?></td>
            <td data-label="요청자"><?= e($t['requester_dept']) ?> <?= e($t['requester_name']) ?></td>
            <td data-label="접수"><?= e(short_time($t['created_at'])) ?></td>
            <td data-label="담당"><?= e($t['assignee_name'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
/** @var array $logs @var string $action */
$actions = ['' => '전체', 'login' => '로그인', 'login_fail' => '로그인 실패', 'login_locked' => '잠금 중 시도',
            'create' => '등록', 'update' => '수정', 'delete' => '삭제', 'export' => '내보내기', 'backup' => '백업'];
?>
<h1>감사 로그 <span class="muted small">최근 200건</span></h1>
<nav class="tabs">
    <?php foreach ($actions as $key => $label): ?>
        <a href="index.php?r=audit&amp;action=<?= e($key) ?>" class="<?= $action === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<table class="table">
    <thead><tr><th>시각</th><th>사용자</th><th>행동</th><th>대상</th><th>내용</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
        <tr class="<?= str_starts_with($l['action'], 'login_') ? 'row-warn' : '' ?>">
            <td data-label="시각"><?= e($l['created_at']) ?></td>
            <td data-label="사용자"><?= e($l['username'] ?: '-') ?></td>
            <td data-label="행동"><?= e($actions[$l['action']] ?? $l['action']) ?></td>
            <td data-label="대상"><?= e($l['target']) ?></td>
            <td data-label="내용"><?= e($l['detail']) ?></td>
            <td data-label="IP" class="mono"><?= e($l['ip']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

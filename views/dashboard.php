<?php
/** @var array $stats @var array $open @var array $alerts @var array $latest @var array $warranty */
?>
<h1>대시보드</h1>

<section class="kpis">
    <a class="kpi <?= $stats['urgent_open'] ? 'kpi-bad' : '' ?>" href="index.php?r=tickets&amp;status=open">
        <span class="kpi-label">미처리 장애</span>
        <span class="kpi-value"><?= e($stats['open']) ?>건</span>
        <span class="kpi-sub">긴급 <?= e($stats['urgent_open']) ?>건</span>
    </a>
    <div class="kpi">
        <span class="kpi-label">이번 달 접수</span>
        <span class="kpi-value"><?= e($stats['created_this_month']) ?>건</span>
        <span class="kpi-sub">완료 <?= e($stats['resolved_this_month']) ?>건</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">이번 달 평균 처리 시간</span>
        <span class="kpi-value"><?= e(minutes_label($stats['avg_minutes'])) ?></span>
        <span class="kpi-sub">접수부터 완료까지</span>
    </div>
    <a class="kpi <?= $alerts ? 'kpi-bad' : 'kpi-good' ?>" href="index.php?r=backups">
        <span class="kpi-label">백업 상태</span>
        <span class="kpi-value"><?= $alerts ? count($alerts) . '건 확인 필요' : '정상' ?></span>
        <span class="kpi-sub">대상 <?= count($latest) ?>개</span>
    </a>
</section>

<?php if ($alerts): ?>
<section class="card alert-card">
    <h2>백업 확인 필요</h2>
    <ul class="plain">
        <?php foreach ($alerts as $a): ?>
            <li><strong><?= e($a['target']) ?></strong> — <?= e($a['reason']) ?> <span class="muted">(<?= e(short_time($a['checked_at'])) ?>)</span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>처리할 장애</h2>
            <a href="index.php?r=tickets&amp;status=open">전체 보기</a>
        </div>
        <?php if (!$open): ?>
            <p class="muted">미처리 장애가 없습니다.</p>
        <?php else: ?>
            <ul class="ticket-mini">
                <?php foreach ($open as $t): ?>
                    <li>
                        <a href="index.php?r=tickets/view&amp;id=<?= e($t['id']) ?>">
                            <span class="badge pri-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span>
                            <span class="badge st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span>
                            <?= e($t['title']) ?>
                        </a>
                        <span class="muted small"><?= e($t['requester_dept']) ?> <?= e($t['requester_name']) ?> · <?= e(short_time($t['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>이번 달 장애 분류</h2>
        <?php if (!$stats['by_category']): ?>
            <p class="muted">이번 달 접수 건이 없습니다.</p>
        <?php else: ?>
            <?php $max = max(array_column($stats['by_category'], 'cnt')); ?>
            <ul class="bars">
                <?php foreach ($stats['by_category'] as $c): ?>
                    <li>
                        <span class="bar-label"><?= e($c['category']) ?></span>
                        <span class="bar"><span class="bar-fill w<?= (int) round($c['cnt'] / $max * 10) * 10 ?>"></span></span>
                        <span class="bar-num"><?= e($c['cnt']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2 class="mt">보증 만료 · 60일 내 만료 예정</h2>
        <?php if (!$warranty): ?>
            <p class="muted">해당 장비가 없습니다.</p>
        <?php else: ?>
            <ul class="plain">
                <?php foreach ($warranty as $w): ?>
                    <li>
                        <a href="index.php?r=assets/edit&amp;id=<?= e($w['id']) ?>"><?= e($w['asset_no']) ?></a>
                        <?= e($w['model']) ?> · <?= e($w['user_name']) ?>
                        <?php $expired = $w['warranty_until'] < App\Clock::today(); ?>
                        <span class="<?= $expired ? 'text-bad' : 'muted' ?>">(<?= e($w['warranty_until']) ?><?= $expired ? ' 만료' : '' ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

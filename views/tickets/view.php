<?php
/** @var array $ticket @var bool $isAdmin */
$t = $ticket;
?>
<div class="page-head">
    <h1>#<?= e($t['id']) ?> <?= e($t['title']) ?></h1>
    <a class="btn" href="index.php?r=tickets">목록</a>
</div>

<div class="grid-2">
    <section class="card">
        <dl class="info">
            <dt>상태</dt><dd><span class="badge st-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></dd>
            <dt>긴급도</dt><dd><span class="badge pri-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span></dd>
            <dt>분류</dt><dd><?= e($t['category']) ?></dd>
            <dt>요청자</dt><dd><?= e($t['requester_dept']) ?> <?= e($t['requester_name']) ?></dd>
            <dt>관련 장비</dt><dd><?= $t['asset_no'] ? e($t['asset_no'] . ' ' . $t['asset_model']) : '-' ?></dd>
            <dt>접수</dt><dd><?= e($t['created_at']) ?></dd>
            <dt>처리 시작</dt><dd><?= e($t['started_at'] ?? '-') ?></dd>
            <dt>완료</dt><dd><?= e($t['resolved_at'] ?? '-') ?></dd>
            <dt>담당</dt><dd><?= e($t['assignee_name'] ?? '-') ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2>내용</h2>
        <p class="pre"><?= e($t['content']) ?></p>
        <?php if ($t['resolution']): ?>
            <h2 class="mt">처리 내용</h2>
            <p class="pre"><?= e($t['resolution']) ?></p>
        <?php endif; ?>
    </section>
</div>

<?php if ($isAdmin): ?>
<section class="card">
    <h2>처리</h2>
    <?php if ($t['status'] === '접수'): ?>
        <form method="post" action="index.php?r=tickets/action" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
            <button name="action" value="start" class="btn">처리 시작 (내가 맡기)</button>
        </form>
    <?php endif; ?>

    <?php if ($t['status'] !== '완료'): ?>
        <form method="post" action="index.php?r=tickets/action" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
            <label>처리 내용 <span class="req">*</span>
                <textarea name="resolution" rows="4" maxlength="2000" placeholder="원인과 조치를 적어 두면 같은 장애가 다시 났을 때 찾아볼 수 있습니다." required></textarea>
            </label>
            <div class="actions">
                <button name="action" value="resolve" class="btn primary">완료 처리</button>
            </div>
        </form>
    <?php else: ?>
        <form method="post" action="index.php?r=tickets/action" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
            <button name="action" value="reopen" class="btn">같은 문제 재발 — 다시 처리중으로</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

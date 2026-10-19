<?php
/** @var array $assets @var array $filter @var array $depts */
use App\AssetRepository;
$qs = http_build_query(['r' => 'assets/export'] + $filter);
?>
<div class="page-head">
    <h1>장비 관리대장 <span class="muted small"><?= count($assets) ?>대</span></h1>
    <div>
        <a class="btn" href="index.php?<?= e($qs) ?>">CSV 내보내기</a>
        <a class="btn primary" href="index.php?r=assets/edit">+ 장비 등록</a>
    </div>
</div>

<form method="get" action="index.php" class="card filters">
    <input type="hidden" name="r" value="assets">
    <input type="search" name="q" value="<?= e($filter['q'] ?? '') ?>" placeholder="자산번호·모델·사용자·IP·위치">
    <select name="category">
        <option value="">분류 전체</option>
        <?php foreach (AssetRepository::CATEGORIES as $c): ?>
            <option <?= ($filter['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="dept">
        <option value="">부서 전체</option>
        <?php foreach ($depts as $d): ?>
            <option <?= ($filter['dept'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">상태 전체</option>
        <?php foreach (AssetRepository::STATUSES as $s): ?>
            <option <?= ($filter['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">검색</button>
</form>

<?php if (!$assets): ?>
    <p class="card muted">조건에 맞는 장비가 없습니다.</p>
<?php else: ?>
<table class="table">
    <thead>
    <tr><th>자산번호</th><th>분류</th><th>모델</th><th>사용자</th><th>부서</th><th>위치</th><th>IP</th><th>OS</th><th>보증만료</th><th>상태</th></tr>
    </thead>
    <tbody>
    <?php foreach ($assets as $a): ?>
        <tr>
            <td data-label="자산번호"><a href="index.php?r=assets/edit&amp;id=<?= e($a['id']) ?>"><?= e($a['asset_no']) ?></a></td>
            <td data-label="분류"><?= e($a['category']) ?></td>
            <td data-label="모델"><?= e($a['model']) ?></td>
            <td data-label="사용자"><?= e($a['user_name']) ?></td>
            <td data-label="부서"><?= e($a['dept']) ?></td>
            <td data-label="위치"><?= e($a['location']) ?></td>
            <td data-label="IP" class="mono"><?= e($a['ip']) ?></td>
            <td data-label="OS"><?= e($a['os']) ?></td>
            <td data-label="보증만료" class="<?= $a['warranty_until'] && $a['warranty_until'] < App\Clock::today() ? 'text-bad' : '' ?>"><?= e($a['warranty_until'] ?? '-') ?></td>
            <td data-label="상태"><span class="badge as-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

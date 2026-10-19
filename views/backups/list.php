<?php
/** @var array $records @var array $alerts @var array $targets @var array $errors @var array $old */
?>
<h1>백업 점검</h1>

<?php if ($alerts): ?>
<section class="card alert-card">
    <h2>확인 필요</h2>
    <ul class="plain">
        <?php foreach ($alerts as $a): ?>
            <li><strong><?= e($a['target']) ?></strong> — <?= e($a['reason']) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<details class="card" <?= $errors ? 'open' : '' ?>>
    <summary>수동 점검 결과 기록</summary>
    <form method="post" action="index.php?r=backups" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="row">
            <label>백업 대상 <span class="req">*</span>
                <input type="text" name="target" list="targets" value="<?= e($old['target'] ?? '') ?>" placeholder="예: ERP DB" required>
                <datalist id="targets">
                    <?php foreach ($targets as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
                </datalist>
                <?php if (isset($errors['target'])): ?><span class="err"><?= e($errors['target']) ?></span><?php endif; ?>
            </label>
            <label>결과
                <select name="result">
                    <?php foreach (App\BackupService::RESULTS as $r): ?>
                        <option <?= ($old['result'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>크기(MB)
                <input type="number" name="size_mb" min="0" step="0.1" value="<?= e($old['size_mb'] ?? '0') ?>">
                <?php if (isset($errors['size_mb'])): ?><span class="err"><?= e($errors['size_mb']) ?></span><?php endif; ?>
            </label>
        </div>
        <label>비고
            <input type="text" name="detail" maxlength="255" value="<?= e($old['detail'] ?? '') ?>" placeholder="예: 외장 디스크 교체, 복구 테스트 완료">
        </label>
        <div class="actions"><button class="btn primary">기록</button></div>
    </form>
</details>

<table class="table">
    <thead><tr><th>점검 시각</th><th>대상</th><th>결과</th><th>크기</th><th>파일</th><th>비고</th><th>방식</th></tr></thead>
    <tbody>
    <?php foreach ($records as $r): ?>
        <tr>
            <td data-label="점검 시각"><?= e(short_time($r['checked_at'])) ?></td>
            <td data-label="대상"><?= e($r['target']) ?></td>
            <td data-label="결과"><span class="badge bk-<?= e($r['result']) ?>"><?= e($r['result']) ?></span></td>
            <td data-label="크기"><?= e(number_format((float) $r['size_mb'], 1)) ?> MB</td>
            <td data-label="파일" class="mono"><?= e($r['file_name']) ?></td>
            <td data-label="비고"><?= e($r['detail']) ?></td>
            <td data-label="방식"><?= e($r['source']) ?><?= $r['checker'] ? ' · ' . e($r['checker']) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="muted small">자동 점검: <code>php bin/check_backup.php</code> 를 매일 cron으로 실행하면 백업 폴더의 최신 파일 시각·크기를 확인해 여기에 기록합니다.</p>

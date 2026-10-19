<?php
/** @var ?int $id @var array $old @var array $errors @var string $title */
use App\AssetRepository;

$field = function (string $name, string $label, string $type = 'text', string $placeholder = '', bool $required = false) use ($old, $errors): string {
    $html = '<label>' . e($label) . ($required ? ' <span class="req">*</span>' : '')
          . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($old[$name] ?? '') . '" placeholder="' . e($placeholder) . '"'
          . ($required ? ' required' : '') . '>';
    if (isset($errors[$name])) {
        $html .= '<span class="err">' . e($errors[$name]) . '</span>';
    }
    return $html . '</label>';
};
$select = function (string $name, string $label, array $options) use ($old, $errors): string {
    $html = '<label>' . e($label) . '<select name="' . e($name) . '">';
    foreach ($options as $o) {
        $html .= '<option' . (($old[$name] ?? '') === $o ? ' selected' : '') . '>' . e($o) . '</option>';
    }
    $html .= '</select>';
    if (isset($errors[$name])) {
        $html .= '<span class="err">' . e($errors[$name]) . '</span>';
    }
    return $html . '</label>';
};
?>
<h1><?= e($title) ?></h1>
<form method="post" action="index.php?r=assets/edit<?= $id ? '&amp;id=' . e($id) : '' ?>" class="card form" novalidate>
    <?= csrf_field() ?>
    <div class="row">
        <?= $field('asset_no', '자산번호', 'text', 'PC-2024-013', true) ?>
        <?= $select('category', '분류', AssetRepository::CATEGORIES) ?>
        <?= $select('status', '상태', AssetRepository::STATUSES) ?>
    </div>
    <div class="row">
        <?= $field('model', '모델명', 'text', '예: LG 울트라PC 15U50T') ?>
        <?= $field('os', 'OS', 'text', '예: Windows 11 Pro') ?>
    </div>
    <div class="row">
        <?= $field('user_name', '사용자') ?>
        <?= $field('dept', '부서') ?>
        <?= $field('location', '위치', 'text', '예: 본관 2층 회계팀') ?>
    </div>
    <div class="row">
        <?= $field('ip', 'IP 주소', 'text', '192.168.0.21') ?>
        <?= $field('purchased_on', '구매일', 'date') ?>
        <?= $field('warranty_until', '보증 만료일', 'date') ?>
    </div>
    <?= $field('memo', '메모') ?>
    <div class="actions">
        <a class="btn" href="index.php?r=assets">취소</a>
        <button type="submit" class="btn primary">저장</button>
    </div>
</form>

<?php if ($id): ?>
<form method="post" action="index.php?r=assets/delete" class="danger-zone">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($id) ?>">
    <label class="check"><input type="checkbox" name="confirm" required> 삭제 확인</label>
    <button type="submit" class="btn danger">이 장비 삭제</button>
    <span class="muted small">장애 이력은 남고 장비 연결만 끊어집니다. 폐기한 장비는 삭제 대신 상태를 '폐기'로 바꾸는 것을 권장합니다.</span>
</form>
<?php endif; ?>

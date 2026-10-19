<?php
/** @var array $errors @var array $old @var array $myAssets */
use App\TicketService;
?>
<h1>장애 접수</h1>
<form method="post" action="index.php?r=tickets/new" class="card form" novalidate>
    <?= csrf_field() ?>
    <label>제목 <span class="req">*</span>
        <input type="text" name="title" maxlength="100" value="<?= e($old['title'] ?? '') ?>" placeholder="예: 2층 회계팀 프린터 인쇄 안 됨" required>
        <?php if (isset($errors['title'])): ?><span class="err"><?= e($errors['title']) ?></span><?php endif; ?>
    </label>

    <div class="row">
        <label>분류 <span class="req">*</span>
            <select name="category" required>
                <option value="">선택</option>
                <?php foreach (TicketService::CATEGORIES as $c): ?>
                    <option <?= ($old['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category'])): ?><span class="err"><?= e($errors['category']) ?></span><?php endif; ?>
        </label>
        <label>긴급도
            <select name="priority">
                <?php foreach (TicketService::PRIORITIES as $p): ?>
                    <option <?= ($old['priority'] ?? '보통') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hint">업무가 완전히 멈췄으면 '긴급'</span>
        </label>
    </div>

    <?php if ($myAssets): ?>
    <label>관련 장비 (선택)
        <select name="asset_id">
            <option value="">선택 안 함</option>
            <?php foreach ($myAssets as $a): ?>
                <option value="<?= e($a['id']) ?>" <?= (string) ($old['asset_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['asset_no']) ?> · <?= e($a['category']) ?> <?= e($a['model']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>

    <label>내용 <span class="req">*</span>
        <textarea name="content" rows="6" maxlength="2000" placeholder="언제부터, 어떤 화면에서, 어떤 메시지가 나오는지 적어 주세요." required><?= e($old['content'] ?? '') ?></textarea>
        <?php if (isset($errors['content'])): ?><span class="err"><?= e($errors['content']) ?></span><?php endif; ?>
    </label>

    <div class="actions">
        <a class="btn" href="index.php?r=tickets">취소</a>
        <button type="submit" class="btn primary">접수하기</button>
    </div>
</form>

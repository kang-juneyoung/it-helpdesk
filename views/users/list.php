<?php
/** @var array $users @var array $errors @var array $old @var ?array $issued */
use App\Clock;
?>
<h1>계정 관리</h1>

<?php if ($issued): ?>
<section class="card issued">
    <h2><?= e($issued['name']) ?>(<?= e($issued['username']) ?>) 임시 비밀번호</h2>
    <p class="temp-pw mono"><?= e($issued['password']) ?></p>
    <p class="muted small">이 화면을 벗어나면 다시 볼 수 없습니다. 본인에게 직접 전달하고 첫 로그인 후 바꾸도록 안내하세요.</p>
</section>
<?php endif; ?>

<details class="card" <?= $errors ? 'open' : '' ?>>
    <summary>신규 계정 만들기</summary>
    <form method="post" action="index.php?r=users" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="create">
        <div class="row">
            <label>아이디 <span class="req">*</span>
                <input type="text" name="username" maxlength="40" value="<?= e($old['username'] ?? '') ?>" placeholder="영문 소문자·숫자" required>
                <?php if (isset($errors['username'])): ?><span class="err"><?= e($errors['username']) ?></span><?php endif; ?>
            </label>
            <label>이름 <span class="req">*</span>
                <input type="text" name="name" maxlength="40" value="<?= e($old['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><span class="err"><?= e($errors['name']) ?></span><?php endif; ?>
            </label>
            <label>부서
                <input type="text" name="dept" maxlength="40" value="<?= e($old['dept'] ?? '') ?>">
            </label>
            <label>권한
                <select name="role">
                    <option value="staff" <?= ($old['role'] ?? '') !== 'admin' ? 'selected' : '' ?>>일반 직원</option>
                    <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>전산 담당</option>
                </select>
            </label>
        </div>
        <p class="muted small">비밀번호는 임시 비밀번호로 자동 발급됩니다.</p>
        <div class="actions"><button class="btn primary">계정 만들기</button></div>
    </form>
</details>

<table class="table">
    <thead><tr><th>아이디</th><th>이름</th><th>부서</th><th>권한</th><th>상태</th><th>관리</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <?php $locked = $u['locked_until'] && strtotime($u['locked_until']) > Clock::ts(); ?>
        <tr class="<?= $locked ? 'row-warn' : '' ?>">
            <td data-label="아이디" class="mono"><?= e($u['username']) ?></td>
            <td data-label="이름"><?= e($u['name']) ?></td>
            <td data-label="부서"><?= e($u['dept']) ?></td>
            <td data-label="권한"><?= $u['role'] === 'admin' ? '전산 담당' : '일반 직원' ?></td>
            <td data-label="상태">
                <?php if ($locked): ?>
                    <span class="badge bk-실패">잠김 (<?= e(date('H:i', strtotime($u['locked_until']))) ?>까지)</span>
                <?php elseif ($u['failed_count'] > 0): ?>
                    <span class="badge st-접수">실패 <?= e($u['failed_count']) ?>회</span>
                <?php else: ?>
                    <span class="badge bk-성공">정상</span>
                <?php endif; ?>
            </td>
            <td data-label="관리">
                <form method="post" action="index.php?r=users" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                    <?php if ($locked): ?>
                        <button name="op" value="unlock" class="btn small-btn">잠금 해제</button>
                    <?php endif; ?>
                    <button name="op" value="reset" class="btn small-btn">임시 비밀번호</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php /** @var ?string $error @var string $username */ ?>
<section class="login">
    <h1>사내 전산 관리</h1>
    <p class="muted">장애 접수 · 장비대장 · 백업 점검</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="index.php?r=login">
        <?= csrf_field() ?>
        <label>아이디
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </label>
        <label>비밀번호
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn primary full">로그인</button>
    </form>
    <p class="muted small">비밀번호를 5번 틀리면 10분 동안 잠깁니다.</p>
</section>

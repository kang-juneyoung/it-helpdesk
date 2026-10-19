<?php
declare(strict_types=1);

namespace Tests;

use App\Auth;
use App\Clock;
use App\ValidationException;

final class AuthTest extends DbTestCase
{
    public function testLoginSucceedsAndLogsAudit(): void
    {
        $result = $this->auth->attempt('kim', 'staff1234!', '192.168.0.31');

        $this->assertTrue($result['ok']);
        $this->assertSame('김생산', $result['user']['name']);
        $this->assertArrayNotHasKey('password_hash', $result['user']);
        $this->assertSame(1, $this->count("SELECT COUNT(*) FROM audit_logs WHERE action = 'login' AND ip = '192.168.0.31'"));
    }

    public function testPasswordIsStoredAsHashNotPlainText(): void
    {
        $hash = $this->pdo->query("SELECT password_hash FROM users WHERE username = 'kim'")->fetchColumn();

        $this->assertNotSame('staff1234!', $hash);
        $this->assertTrue(password_verify('staff1234!', $hash));
    }

    public function testWrongPasswordAndUnknownUserGiveSameMessage(): void
    {
        $wrong = $this->auth->attempt('kim', 'nope');
        $unknown = $this->auth->attempt('nobody', 'nope');

        $this->assertFalse($wrong['ok']);
        $this->assertSame(Auth::MSG_FAIL, $wrong['error']);
        $this->assertSame($wrong['error'], $unknown['error']);
    }

    public function testFiveFailuresLockAccountEvenForCorrectPassword(): void
    {
        for ($i = 0; $i < Auth::MAX_FAILS; $i++) {
            $this->auth->attempt('kim', 'wrong');
        }
        $result = $this->auth->attempt('kim', 'staff1234!');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('잠겼습니다', $result['error']);
    }

    public function testLockExpiresAfterTenMinutes(): void
    {
        for ($i = 0; $i < Auth::MAX_FAILS; $i++) {
            $this->auth->attempt('kim', 'wrong');
        }
        Clock::advance(Auth::LOCK_SECONDS + 1);

        $this->assertTrue($this->auth->attempt('kim', 'staff1234!')['ok']);
    }

    public function testSuccessfulLoginResetsFailCount(): void
    {
        $this->auth->attempt('kim', 'wrong');
        $this->auth->attempt('kim', 'wrong');
        $this->auth->attempt('kim', 'staff1234!');

        $this->assertSame(0, $this->count("SELECT failed_count FROM users WHERE username = 'kim'"));
    }

    public function testAdminUnlock(): void
    {
        for ($i = 0; $i < Auth::MAX_FAILS; $i++) {
            $this->auth->attempt('kim', 'wrong');
        }
        $this->auth->unlock((int) $this->staff['id'], $this->admin);

        $this->assertTrue($this->auth->attempt('kim', 'staff1234!')['ok']);
    }

    public function testResetPasswordIssuesWorkingTempPasswordAndOldOneStops(): void
    {
        $temp = $this->auth->resetPassword((int) $this->staff['id'], $this->admin);

        $this->assertSame(10, strlen($temp));
        $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $temp);
        $this->assertTrue($this->auth->attempt('kim', $temp)['ok']);
        $this->assertFalse($this->auth->attempt('kim', 'staff1234!')['ok']);
    }

    public function testCreateUserRejectsBadAndDuplicateUsernames(): void
    {
        try {
            $this->auth->createUser('1bad', 'abcdefgh1', '홍길동', '회계팀', 'staff');
            $this->fail('숫자로 시작하는 아이디는 거부해야 합니다.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->errors);
        }

        try {
            $this->auth->createUser('KIM', 'abcdefgh1', '김중복', '생산팀', 'staff');
            $this->fail('대소문자만 다른 중복 아이디는 거부해야 합니다.');
        } catch (ValidationException $e) {
            $this->assertSame('이미 있는 아이디입니다.', $e->errors['username']);
        }
    }
}

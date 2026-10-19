<?php
declare(strict_types=1);

namespace Tests;

use App\AuditLog;
use App\Auth;
use App\Clock;
use App\Db;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 테스트마다 메모리 SQLite DB를 새로 만들고 시각을 고정한다.
 * 운영은 MySQL이지만 쿼리는 두 DB에서 같게 동작하도록 표준 SQL만 쓴다.
 */
abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;
    protected AuditLog $audit;
    protected Auth $auth;
    protected array $admin;
    protected array $staff;
    protected array $staff2;

    protected function setUp(): void
    {
        Clock::freeze('2026-10-19 10:00:00');
        // TEST_DB_DSN 이 있으면 그 DB(MySQL)로, 없으면 메모리 SQLite로 테스트한다.
        $dsn = getenv('TEST_DB_DSN') ?: 'sqlite::memory:';
        $this->pdo = Db::connect($dsn, getenv('TEST_DB_USER') ?: null, getenv('TEST_DB_PASS') ?: null);
        if (Db::driver($this->pdo) === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['audit_logs', 'backups', 'tickets', 'assets', 'users'] as $t) {
                $this->pdo->exec("DROP TABLE IF EXISTS {$t}");
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        Db::migrate($this->pdo);
        $this->audit = new AuditLog($this->pdo);
        $this->auth = new Auth($this->pdo, $this->audit);

        $this->admin  = $this->user($this->auth->createUser('admin', 'admin1234!', '강전산', '전산실', 'admin'));
        $this->staff  = $this->user($this->auth->createUser('kim', 'staff1234!', '김생산', '생산팀', 'staff'));
        $this->staff2 = $this->user($this->auth->createUser('choi', 'staff1234!', '최회계', '회계팀', 'staff'));
    }

    protected function tearDown(): void
    {
        Clock::reset();
    }

    protected function user(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, name, dept, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    protected function count(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

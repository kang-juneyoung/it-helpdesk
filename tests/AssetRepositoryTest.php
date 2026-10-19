<?php
declare(strict_types=1);

namespace Tests;

use App\AssetRepository;
use App\TicketService;

final class AssetRepositoryTest extends DbTestCase
{
    private AssetRepository $assets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assets = new AssetRepository($this->pdo, $this->audit);
    }

    private function data(array $override = []): array
    {
        return AssetRepository::normalize($override + [
            'asset_no' => 'pc-2024-013', 'category' => 'PC', 'model' => '삼성 DM500SGA', 'user_name' => '최회계',
            'dept' => '회계팀', 'location' => '본관 2층', 'ip' => '192.168.0.34', 'os' => 'Windows 11 Pro',
            'purchased_on' => '2025-10-01', 'warranty_until' => '2026-11-30', 'status' => '사용', 'memo' => '',
        ]);
    }

    public function testNormalizeUppercasesAssetNoAndNullsEmptyDates(): void
    {
        $d = AssetRepository::normalize(['asset_no' => ' pc-2024-001 ', 'purchased_on' => '']);

        $this->assertSame('PC-2024-001', $d['asset_no']);
        $this->assertNull($d['purchased_on']);
    }

    public function testValidationCatchesFormatIpAndDateErrors(): void
    {
        $errors = $this->assets->validate($this->data([
            'asset_no' => 'PC13', 'ip' => '192.168.0.300', 'purchased_on' => '2026-02-30', 'category' => '냉장고',
        ]));

        $this->assertArrayHasKey('asset_no', $errors);
        $this->assertArrayHasKey('ip', $errors);
        $this->assertArrayHasKey('purchased_on', $errors);
        $this->assertArrayHasKey('category', $errors);
    }

    public function testWarrantyCannotBeBeforePurchase(): void
    {
        $errors = $this->assets->validate($this->data(['purchased_on' => '2026-05-01', 'warranty_until' => '2026-01-01']));

        $this->assertArrayHasKey('warranty_until', $errors);
    }

    public function testDuplicateAssetNoIsRejectedButEditingSelfIsAllowed(): void
    {
        $id = $this->assets->create($this->data(), $this->admin);

        $this->assertArrayHasKey('asset_no', $this->assets->validate($this->data()));
        $this->assertSame([], $this->assets->validate($this->data(), $id));
    }

    public function testIpConflictIgnoresSelfAndDiscardedAssets(): void
    {
        $id = $this->assets->create($this->data(), $this->admin);
        $this->assets->create($this->data(['asset_no' => 'PC-2019-001', 'status' => '폐기']), $this->admin);

        $this->assertSame([], $this->assets->ipConflicts('192.168.0.34', $id));
        $this->assertCount(1, $this->assets->ipConflicts('192.168.0.34'));
    }

    public function testSearchTreatsPercentAsLiteralText(): void
    {
        $this->assets->create($this->data(), $this->admin);
        $this->assets->create($this->data(['asset_no' => 'PR-2022-001', 'category' => '프린터', 'model' => '토너 100% 교체', 'ip' => '']), $this->admin);

        $this->assertCount(1, $this->assets->search(['q' => '100%']));
        $this->assertCount(0, $this->assets->search(['q' => '%%']));
        $this->assertCount(2, $this->assets->search(['dept' => '회계팀']));
    }

    public function testSqlInjectionTextIsJustASearchWord(): void
    {
        $this->assets->create($this->data(), $this->admin);

        $this->assertSame([], $this->assets->search(['q' => "' OR '1'='1"]));
        $this->assertSame([], $this->assets->search(['dept' => "회계팀' OR 1=1 --"]));
        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM assets'));
    }

    public function testUpdateWritesChangedFieldsToAuditLog(): void
    {
        $id = $this->assets->create($this->data(), $this->admin);
        $this->assets->update($id, $this->data(['user_name' => '홍길동', 'status' => '수리']), $this->admin);

        $detail = $this->pdo->query("SELECT detail FROM audit_logs WHERE action = 'update' ORDER BY id DESC")->fetchColumn();
        $this->assertStringContainsString('user_name: 최회계 → 홍길동', $detail);
        $this->assertStringContainsString('status: 사용 → 수리', $detail);
    }

    public function testDeleteKeepsTicketHistory(): void
    {
        $id = $this->assets->create($this->data(), $this->admin);
        $tickets = new TicketService($this->pdo, $this->audit);
        $ticketId = $tickets->create(['title' => '부팅 안 됨', 'category' => 'PC', 'priority' => '높음', 'content' => '전원 무반응', 'asset_id' => $id], $this->staff);

        $this->assets->delete($id, $this->admin);

        $this->assertNull($this->assets->find($id));
        $this->assertNull($tickets->find($ticketId)['asset_id']);
    }

    public function testWarrantyExpiringWithinDays(): void
    {
        $this->assets->create($this->data(['warranty_until' => '2026-11-10']), $this->admin);
        $this->assets->create($this->data(['asset_no' => 'PC-2025-020', 'ip' => '', 'warranty_until' => '2027-12-31']), $this->admin);
        $this->assets->create($this->data(['asset_no' => 'PC-2019-002', 'ip' => '', 'warranty_until' => '2020-01-01', 'purchased_on' => '2019-01-01', 'status' => '폐기']), $this->admin);

        $expiring = $this->assets->warrantyExpiring(60);

        $this->assertSame(['PC-2024-013'], array_column($expiring, 'asset_no'));
    }
}

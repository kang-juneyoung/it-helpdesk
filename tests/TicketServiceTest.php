<?php
declare(strict_types=1);

namespace Tests;

use App\Clock;
use App\TicketService;
use App\ValidationException;

final class TicketServiceTest extends DbTestCase
{
    private TicketService $tickets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tickets = new TicketService($this->pdo, $this->audit);
    }

    private function newTicket(array $by, string $title = '프린터 인쇄 안 됨'): int
    {
        return $this->tickets->create(['title' => $title, 'category' => '프린터', 'priority' => '보통', 'content' => '대기열에서 멈춤'], $by);
    }

    public function testCreateValidatesRequiredFields(): void
    {
        try {
            $this->tickets->create(['title' => '', 'category' => '냉장고', 'content' => ''], $this->staff);
            $this->fail('잘못된 입력을 받아들이면 안 됩니다.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title', $e->errors);
            $this->assertArrayHasKey('category', $e->errors);
            $this->assertArrayHasKey('content', $e->errors);
        }
    }

    public function testUnknownAssetIdIsIgnoredInsteadOfFailing(): void
    {
        $id = $this->tickets->create(['title' => 'PC 느림', 'category' => 'PC', 'priority' => '보통', 'content' => '부팅 5분', 'asset_id' => 999], $this->staff);

        $this->assertNull($this->tickets->find($id)['asset_id']);
    }

    public function testFullFlowRecordsTimesAndAssignee(): void
    {
        $id = $this->newTicket($this->staff);
        Clock::advance(600);
        $this->tickets->transition($id, 'start', $this->admin);
        Clock::advance(1800);
        $t = $this->tickets->transition($id, 'resolve', $this->admin, '스풀러 재시작');

        $this->assertSame('완료', $t['status']);
        $this->assertSame('2026-10-19 10:10:00', $t['started_at']);
        $this->assertSame('2026-10-19 10:40:00', $t['resolved_at']);
        $this->assertSame('강전산', $t['assignee_name']);
        $this->assertSame('스풀러 재시작', $t['resolution']);
    }

    public function testResolveRequiresResolutionText(): void
    {
        $id = $this->newTicket($this->staff);

        $this->expectException(ValidationException::class);
        $this->tickets->transition($id, 'resolve', $this->admin, '   ');
    }

    public function testQuickResolveFromReceivedAlsoSetsStartedAt(): void
    {
        $id = $this->newTicket($this->staff);
        $t = $this->tickets->transition($id, 'resolve', $this->admin, '전화로 안내');

        $this->assertNotNull($t['started_at']);
    }

    public function testStaffCannotChangeStatus(): void
    {
        $id = $this->newTicket($this->staff);

        $this->expectException(\DomainException::class);
        $this->tickets->transition($id, 'start', $this->staff);
    }

    public function testInvalidTransitionIsRejected(): void
    {
        $id = $this->newTicket($this->staff);
        $this->tickets->transition($id, 'resolve', $this->admin, '완료');

        $this->expectException(\DomainException::class);
        $this->tickets->transition($id, 'start', $this->admin);
    }

    public function testReopenClearsResolvedAt(): void
    {
        $id = $this->newTicket($this->staff);
        $this->tickets->transition($id, 'resolve', $this->admin, '용지 교체');
        $t = $this->tickets->transition($id, 'reopen', $this->admin);

        $this->assertSame('처리중', $t['status']);
        $this->assertNull($t['resolved_at']);
    }

    public function testStaffSeesOnlyOwnTickets(): void
    {
        $mine = $this->newTicket($this->staff, '내 건');
        $other = $this->newTicket($this->staff2, '남의 건');

        $this->assertSame([$mine], array_map('intval', array_column($this->tickets->list($this->staff), 'id')));
        $this->assertCount(2, $this->tickets->list($this->admin));
        $this->assertFalse($this->tickets->canView($this->tickets->find($other), $this->staff));
        $this->assertTrue($this->tickets->canView($this->tickets->find($other), $this->admin));
    }

    public function testListPutsUrgentOpenTicketsFirst(): void
    {
        $this->newTicket($this->staff, '보통 건');
        $urgent = $this->tickets->create(['title' => 'ERP 접속 불가', 'category' => 'ERP·프로그램', 'priority' => '긴급', 'content' => '전체 접속 안 됨'], $this->staff);

        $this->assertSame($urgent, (int) $this->tickets->list($this->admin, ['status' => 'open'])[0]['id']);
    }

    public function testStatsAverageResolutionMinutesThisMonth(): void
    {
        $a = $this->newTicket($this->staff);
        Clock::advance(30 * 60);
        $this->tickets->transition($a, 'resolve', $this->admin, '처리');   // 30분
        $b = $this->newTicket($this->staff);
        Clock::advance(90 * 60);
        $this->tickets->transition($b, 'resolve', $this->admin, '처리');   // 90분
        $this->newTicket($this->staff);                                     // 미처리

        $s = $this->tickets->stats();

        $this->assertSame(1, $s['open']);
        $this->assertSame(3, $s['created_this_month']);
        $this->assertSame(2, $s['resolved_this_month']);
        $this->assertSame(60, $s['avg_minutes']);
    }
}

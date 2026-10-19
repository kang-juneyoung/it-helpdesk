<?php
declare(strict_types=1);

namespace Tests;

use App\Csv;
use PHPUnit\Framework\TestCase;

/** 화면 출력(XSS)과 CSV 내보내기(수식 주입) 안전성. */
final class OutputSafetyTest extends TestCase
{
    public function testHtmlEscapeNeutralizesScriptAndQuotes(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertSame('&quot; onmouseover=&quot;x', e('" onmouseover="x'));
        $this->assertSame('', e(null));
    }

    public function testCsvCellsStartingWithFormulaCharactersAreQuoted(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", Csv::safeCell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+1+1", Csv::safeCell('+1+1'));
        $this->assertSame("'@SUM(A1)", Csv::safeCell('@SUM(A1)'));
        $this->assertSame('192.168.0.1', Csv::safeCell('192.168.0.1'));
    }

    public function testCsvHasBomForExcelAndHeaders(): void
    {
        $csv = Csv::build([['asset_no' => 'PC-2024-013', 'memo' => '=1+1']], ['asset_no' => '자산번호', 'memo' => '메모']);

        $this->assertStringStartsWith("\xEF\xBB\xBF자산번호,메모\n", $csv);
        $this->assertStringContainsString("PC-2024-013,'=1+1", $csv);
    }

    public function testMinutesLabel(): void
    {
        $this->assertSame('45분', minutes_label(45));
        $this->assertSame('2시간 5분', minutes_label(125));
        $this->assertSame('-', minutes_label(null));
    }
}

<?php
declare(strict_types=1);

namespace App;

/**
 * CSV 내보내기 (엑셀에서 한글이 깨지지 않도록 UTF-8 BOM을 붙인다).
 *
 * 셀 값이 = + - @ 로 시작하면 엑셀이 수식으로 실행할 수 있다(CSV 수식 주입).
 * 이런 값은 앞에 작은따옴표를 붙여 글자로만 보이게 한다.
 */
final class Csv
{
    public static function safeCell(mixed $value): string
    {
        $s = (string) ($value ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    }

    /** @param array<string, string> $columns 컬럼 키 => 머리글 */
    public static function build(array $rows, array $columns): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_values($columns), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($k) => self::safeCell($row[$k] ?? ''), array_keys($columns)), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}

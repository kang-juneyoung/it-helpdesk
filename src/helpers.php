<?php
declare(strict_types=1);

/* 화면에서 쓰는 전역 도우미 함수. */

use App\Clock;
use App\Session;

/** HTML 이스케이프 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 숨은 CSRF 입력칸 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

/** 분 단위 시간을 "1시간 20분" 형태로 */
function minutes_label(?int $minutes): string
{
    if ($minutes === null) {
        return '-';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}시간 {$m}분" : "{$m}분";
}

/** 날짜·시각을 짧게: 오늘이면 시각만 */
function short_time(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    return date('Y-m-d', $ts) === Clock::today() ? date('H:i', $ts) : date('m-d H:i', $ts);
}

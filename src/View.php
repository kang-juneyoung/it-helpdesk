<?php
declare(strict_types=1);

namespace App;

/**
 * 화면 출력. 모든 출력 값은 e()로 HTML 이스케이프한다 (XSS 방지).
 */
final class View
{
    public static function render(string $template, array $vars = [], string $layout = 'layout/main'): string
    {
        $content = self::partial($template, $vars);
        return self::partial($layout, $vars + ['content' => $content]);
    }

    public static function partial(string $template, array $vars = []): string
    {
        $file = dirname(__DIR__) . "/views/{$template}.php";
        if (!is_file($file)) {
            throw new \RuntimeException("화면 파일이 없습니다: {$template}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}

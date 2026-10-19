<?php
declare(strict_types=1);

/*
 * 공통 시작 파일. 웹(public/index.php), 명령줄 도구(bin/*), 테스트가 모두 이 파일을 불러온다.
 * 운영 환경에 Composer가 없어도 돌아가도록 자동 로딩을 직접 등록한다.
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

date_default_timezone_set('Asia/Seoul');
mb_internal_encoding('UTF-8');

require __DIR__ . '/helpers.php';

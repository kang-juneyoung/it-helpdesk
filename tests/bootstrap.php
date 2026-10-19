<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__ . '/' . substr($class, 6) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

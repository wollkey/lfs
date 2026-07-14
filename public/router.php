<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/api')) {
    require __DIR__.'/index.php';

    return true;
}

$file = __DIR__.$path;
if ($path !== '/' && is_file($file) && !str_ends_with($path, '.php')) {
    return false;
}

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__.'/index.html');

return true;

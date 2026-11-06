<?php
// api/index.php - Serverless Router untuk Vercel

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$rootDir = realpath(__DIR__ . '/..');

if ($uri === '/' || $uri === '') {
    chdir($rootDir);
    require $rootDir . '/index.php';
    exit;
}

$targetPath = realpath($rootDir . $uri);

if ($targetPath && str_starts_with($targetPath, $rootDir) && is_file($targetPath) && str_ends_with($targetPath, '.php')) {
    chdir($rootDir);
    require $targetPath;
    exit;
}

// Coba jika URL dipanggil tanpa ekstensi .php (contoh: /pos -> pos.php)
$withPhp = realpath($rootDir . $uri . '.php');
if ($withPhp && str_starts_with($withPhp, $rootDir) && is_file($withPhp)) {
    chdir($rootDir);
    require $withPhp;
    exit;
}

http_response_code(404);
echo "404 Not Found";

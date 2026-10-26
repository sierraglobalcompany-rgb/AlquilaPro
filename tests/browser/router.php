<?php

declare(strict_types=1);

$repo = dirname(__DIR__, 2);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$decodedPath = is_string($requestPath) ? rawurldecode($requestPath) : null;
if (!is_string($decodedPath) || str_contains($decodedPath, "\0") || str_contains($decodedPath, '\\')
    || preg_match('~(^|/)\.\.?(/|$)~', $decodedPath) === 1) {
    http_response_code(400);
    exit;
}

if (str_starts_with($decodedPath, '/assets/')) {
    $relative = substr($decodedPath, strlen('/assets/'));
    if ($relative === '' || preg_match('~[^A-Za-z0-9_./-]~', $relative) === 1) {
        http_response_code(404);
        exit;
    }
    $assetsRoot = realpath($repo . '/assets');
    $assetPath = realpath($repo . '/assets/' . $relative);
    if ($assetsRoot === false || $assetPath === false || !is_file($assetPath) || is_link($repo . '/assets/' . $relative)
        || !str_starts_with($assetPath, $assetsRoot . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit;
    }
    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $contentTypes = [
        'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'woff2' => 'font/woff2',
    ];
    if (!isset($contentTypes[$extension])) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $contentTypes[$extension]);
    header('X-Content-Type-Options: nosniff');
    readfile($assetPath);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $repo . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $repo;
require $repo . '/index.php';

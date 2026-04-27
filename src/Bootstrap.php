<?php

declare(strict_types=1);

// Define app root
define('APP_ROOT', dirname(__DIR__, 2));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Load environment variables
$envFile = APP_ROOT . '/cli/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

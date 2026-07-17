<?php
declare(strict_types=1);

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value));
    }
}

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'url' => rtrim(getenv('APP_URL') ?: 'http://localhost', '/'),
    'key' => getenv('APP_KEY') ?: '',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'opencrm',
        'user' => getenv('DB_USER') ?: 'opencrm',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'secure_session' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOL),
    'upload_max' => ((int) (getenv('UPLOAD_MAX_MB') ?: 12)) * 1024 * 1024,
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/New_York',
];

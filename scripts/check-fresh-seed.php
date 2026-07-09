<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__);
$databasePath = $basePath.'/tmp/fresh-seed-check.sqlite';
$configCachePath = $basePath.'/bootstrap/cache/config.php';

if (file_exists($configCachePath)) {
    fwrite(STDERR, "Config cache exists. Run `php artisan optimize:clear` before this check.\n");
    exit(1);
}

if (! is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0775, true);
}

if (file_exists($databasePath) && ! unlink($databasePath)) {
    fwrite(STDERR, "Could not remove old temporary database: {$databasePath}\n");
    exit(1);
}

if (! touch($databasePath)) {
    fwrite(STDERR, "Could not create temporary database: {$databasePath}\n");
    exit(1);
}

$env = [
    'APP_ENV' => 'testing',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $databasePath,
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

$status = $kernel->call('migrate:fresh', [
    '--seed' => true,
    '--force' => true,
    '--no-interaction' => true,
]);

echo $kernel->output();

if ($status !== 0) {
    fwrite(STDERR, "Fresh migration with seed failed. Temporary database kept at {$databasePath}\n");
    exit($status);
}

$minimumRows = [
    'users' => 1,
    'roles' => 1,
    'permissions' => 1,
    'personens' => 1,
    'projekts' => 1,
];

foreach ($minimumRows as $table => $minimum) {
    $count = DB::table($table)->count();

    if ($count < $minimum) {
        fwrite(STDERR, "Fresh seed check failed: table `{$table}` has {$count} rows, expected at least {$minimum}.\n");
        exit(1);
    }

    echo "Seed check: {$table} has {$count} rows.\n";
}

if (! unlink($databasePath)) {
    fwrite(STDERR, "Fresh seed check passed, but temporary database could not be removed: {$databasePath}\n");
    exit(1);
}

echo "Fresh seed check passed.\n";

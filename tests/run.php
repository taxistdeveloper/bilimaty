<?php

declare(strict_types=1);

/**
 * Test runner.
 *
 *   php tests/run.php                 unit tests (no database needed)
 *   php tests/run.php --integration   unit + integration tests (HTTP and database)
 *   php tests/run.php --filter=Access only test classes whose name contains "Access"
 *
 * Integration tests NEVER touch the working database: they use a separate database whose name
 * must end with "_test" (TEST_DB_DATABASE, default: <DB_DATABASE>_test). It is dropped and
 * created again, migrated and filled with demo data; the application is served by the PHP
 * built-in server on a free local port. TEST_DB_HOST/PORT/USERNAME/PASSWORD override DB_*.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$options = array_slice($argv, 1);
$integration = in_array('--integration', $options, true);
$filter = '';
foreach ($options as $o) {
    if (str_starts_with($o, '--filter=')) {
        $filter = substr($o, 9);
    }
}

if ($integration) {
    require __DIR__ . '/Integration/Environment.php';
    Tests\Integration\Environment::prepare($root);
}
require $root . '/bootstrap.php';
require __DIR__ . '/TestCase.php';
if ($integration) {
    require __DIR__ . '/Integration/IntegrationTestCase.php';
    require __DIR__ . '/Integration/Http.php';
}

$files = glob(__DIR__ . '/Unit/*Test.php') ?: [];
if ($integration) {
    $files = array_merge($files, glob(__DIR__ . '/Integration/*Test.php') ?: []);
}

$passed = $failed = $assertions = 0;
$failures = [];
$started = microtime(true);
foreach ($files as $file) {
    require_once $file;
    $class = 'Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
    if ($filter !== '' && !str_contains($class, $filter)) {
        continue;
    }
    echo str_pad(substr($class, 6), 44);
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $test = new $class();
        try {
            $test->setUp();
            $test->$method();
            $test->tearDown();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failed++;
            echo 'F';
            $where = $e instanceof Tests\AssertionFailed ? '' : ' [' . $e::class . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            $failures[] = substr($class, 6) . '::' . $method . "\n     " . $e->getMessage() . $where;
        }
        $assertions += $test->assertions;
    }
    echo "\n";
}

if ($integration) {
    Tests\Integration\Environment::shutdown();
}
echo "\n";
foreach ($failures as $i => $f) {
    echo ($i + 1) . ') ' . $f . "\n\n";
}
printf("%s: %d passed, %d failed, %d assertions, %.1fs\n", $failed === 0 ? 'OK' : 'FAILURES', $passed, $failed, $assertions, microtime(true) - $started);
exit($failed === 0 ? 0 : 1);

<?php

declare(strict_types=1);

/**
 * 测试入口：php tests/run.php [--suite=unit|integration|contract] [--filter=Name]
 */

require __DIR__ . '/bootstrap.php';

$suiteFilter = null;
$nameFilter = null;

foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--suite=') === 0) {
        $suiteFilter = substr($arg, 8);
    } elseif (strpos($arg, '--filter=') === 0) {
        $nameFilter = substr($arg, 9);
    }
}

$suites = array(
    'unit' => TEST_ROOT . 'unit',
    'integration' => TEST_ROOT . 'integration',
    'contract' => TEST_ROOT . 'contract',
);

$totalFailed = 0;
$totalFiles = 0;
$totalPassed = 0;

foreach ($suites as $suiteName => $dir) {
    if ($suiteFilter !== null && $suiteFilter !== $suiteName) {
        continue;
    }
    if (!is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*Test.php');
    if (!$files) {
        continue;
    }
    sort($files);

    foreach ($files as $file) {
        $basename = basename($file, '.php');
        if ($nameFilter !== null && stripos($basename, $nameFilter) === false) {
            continue;
        }

        $totalFiles++;
        echo "\n[$suiteName] $basename\n";
        echo str_repeat('-', 40) . "\n";

        ConfigStub::reset();
        $code = include $file;
        ConfigStub::reset();
        if ($code === 0) {
            $totalPassed++;
            echo "=> PASSED\n";
        } else {
            $totalFailed++;
            echo "=> FAILED (exit $code)\n";
        }
    }
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "Test files: $totalFiles, passed: $totalPassed, failed: $totalFailed\n";

exit($totalFailed > 0 ? 1 : 0);

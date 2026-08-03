<?php

declare(strict_types=1);

/**
 * 测试入口：php tests/run.php [--suite=unit|integration|contract] [--filter=Name]
 *
 * 每个 *Test.php 在独立 PHP 进程中执行，避免 SITE_DIR 等常量与静态状态跨文件污染。
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
$phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';

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

        // 直接执行脚本不会把顶层 return 作为进程退出码；用包装器显式传递。
        $runner = '$_testStatus = require $argv[1]; exit(is_int($_testStatus) ? $_testStatus : 0);';
        $cmd = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($runner) . ' ' . escapeshellarg($file);
        $code = 1;
        passthru($cmd, $code);
        $code = (int) $code;

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

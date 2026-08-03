<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers tests/run.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== 测试入口退出码传递 ===\n";

    $phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $runFile = TEST_ROOT . 'run.php';
    $runFixture = function ($returnedStatus) use ($phpBin, $runFile) {
        $fixtureName = str_replace('.', '', uniqid('RunnerExitStatusFixture', true)) . 'Test';
        $fixtureFile = TEST_ROOT . 'unit' . DIRECTORY_SEPARATOR . $fixtureName . '.php';

        try {
            $contents = "<?php\nreturn " . $returnedStatus . ";\n";
            TestAssert::same(strlen($contents), file_put_contents($fixtureFile, $contents), '临时测试文件已写入');

            $output = array();
            $exitCode = 0;
            $command = escapeshellarg($phpBin) . ' ' . escapeshellarg($runFile)
                . ' ' . escapeshellarg('--suite=unit')
                . ' ' . escapeshellarg('--filter=' . $fixtureName);
            exec($command, $output, $exitCode);

            return array($exitCode, implode("\n", $output));
        } finally {
            @unlink($fixtureFile);
        }
    };

    list($passedCode, $passedOutput) = $runFixture(0);
    TestAssert::same(0, $passedCode, '顶层 return 0 使测试入口成功');
    TestAssert::contains($passedOutput, '=> PASSED', '顶层 return 0 被子进程识别为成功');

    list($failedCode, $failedOutput) = $runFixture(1);
    TestAssert::same(1, $failedCode, '顶层 return 1 使测试入口失败');
    TestAssert::contains($failedOutput, '=> FAILED (exit 1)', '顶层 return 1 被子进程识别为失败');
});

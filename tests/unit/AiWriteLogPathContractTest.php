<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/admin/controller/content/AiController.php aiWriteLog path
 *
 * feat #260：AI 调用日志归档到 data/log/ai/Y/Ym，禁止 runtime/log 与截断创建
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== source contract: aiWriteLog archives under data/log/ai ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/content/AiController.php');
    TestAssert::false($src === false, 'AiController.php readable');
    TestAssert::contains($src, 'DATA_DIR', 'aiWriteLog writes under DATA_DIR');
    TestAssert::contains($src, '/log/ai/', 'aiWriteLog writes under data/log/ai');
    TestAssert::contains($src, "date('Y/Ym')", 'aiWriteLog archives under year/Ym directories');
    TestAssert::contains($src, 'check_dir(', 'aiWriteLog ensures directory exists');
    TestAssert::notContains($src, "RUN_PATH . '/log'", 'aiWriteLog must not write to RUN_PATH/log');
    TestAssert::notContains($src, "'ai_' . date('Ymd')", 'aiWriteLog must not use ai_Ymd.log filename');
    TestAssert::contains($src, "date('Ymd') . '.log'", 'daily file named Ymd.log');
    TestAssert::notContains($src, 'check_file(', 'aiWriteLog must not call check_file()');
    TestAssert::notContains($src, 'create_file(', 'aiWriteLog must not call create_file()');
    TestAssert::notContains($src, 'file_put_contents(', 'aiWriteLog must not use unlocked file_put_contents');
    TestAssert::true(
        strpos($src, "fopen(\$path, 'ab')") !== false || strpos($src, 'fopen($path, "ab")') !== false,
        'aiWriteLog appends with fopen ab (create without truncate)'
    );
});

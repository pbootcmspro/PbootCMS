<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ParserController::parserAfter 调用顺序不变量
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== parserAfter source order invariant ===\n";

    $src = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::false($src === false, 'ParserController source readable');

    $posAfter = strpos($src, 'function parserAfter');
    $posNext = strpos($src, 'function savePreLabel');
    TestAssert::false($posAfter === false, 'parserAfter found');
    TestAssert::false($posNext === false, 'savePreLabel found');
    TestAssert::true($posNext > $posAfter, 'savePreLabel follows parserAfter');

    $body = substr($src, $posAfter, $posNext - $posAfter);

    $posIf = strpos($body, '$this->parserIfLabel($content)');
    $posLoop = strpos($body, '$this->parserLoopLabel($content)');
    $posRestore = strpos($body, '$this->restorePreLabel($content)');

    TestAssert::false($posIf === false, 'parserIfLabel call in parserAfter');
    TestAssert::false($posLoop === false, 'parserLoopLabel call in parserAfter');
    TestAssert::false($posRestore === false, 'restorePreLabel call in parserAfter');

    TestAssert::true($posIf < $posLoop, 'parserIfLabel before parserLoopLabel');
    TestAssert::true($posLoop < $posRestore, 'parserLoopLabel before restorePreLabel');
    TestAssert::true($posIf < $posRestore, 'parserIfLabel before restorePreLabel');
});

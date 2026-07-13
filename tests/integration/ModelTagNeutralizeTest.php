<?php

declare(strict_types=1);

/**
 * @covers core/basic/Model.php 写入路径 pboot:if / pboot:sql 中和
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== Model source contract ===\n";

    $src = file_get_contents(CORE_PATH . '/basic/Model.php');
    TestAssert::false($src === false, 'model source readable');
    TestAssert::contains($src, "preg_replace_r('/pboot:if/i', 'pboot@if'", 'model source: pboot:if neutralize');
    TestAssert::contains($src, "preg_replace_r('/pboot:sql/i', 'pboot@sql'", 'model source: pboot:sql neutralize');

    echo "=== Model neutralize pattern (malicious tag fragment) ===\n";

    $fragment = "INSERT INTO t (content) VALUES ('" . PbootIfBypassPayloads::defaultMaliciousTag() . "')";
    $neutralized = preg_replace_r('/pboot:if/i', 'pboot@if', $fragment);
    $neutralized = preg_replace_r('/pboot:sql/i', 'pboot@sql', $neutralized);
    TestAssert::contains($neutralized, 'pboot@if', 'model pattern: pboot:if neutralized');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($neutralized), 'model pattern: no active tag');
});

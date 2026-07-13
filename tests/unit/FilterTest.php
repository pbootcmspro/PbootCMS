<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/helper.php filter()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== filter() neutralizes pboot:if ===\n";

    $filtered = filter('{pboot:if(1==1)}T{/pboot:if}', array());
    TestAssert::contains($filtered, 'pboot@if', 'filter: tag neutralized');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($filtered), 'filter: no active tag');

    $filteredCase = filter('{PbOoT:iF(1==1)}T{/PbOoT:iF}', array());
    TestAssert::contains($filteredCase, 'pboot@if', 'filter: case-insensitive');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($filteredCase), 'filter: case variant no active tag');

    $filteredMalicious = filter(PbootIfBypassPayloads::defaultMaliciousTag(), array());
    TestAssert::contains($filteredMalicious, 'pboot@if', 'filter: malicious tag neutralized');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($filteredMalicious), 'filter: malicious tag no active tag');
    TestAssert::contains($filteredMalicious, 'EVIL', 'filter: body preserved');

    echo "=== filter() neutralizes pboot:sql and superglobals ===\n";

    $filteredSql = filter('{pboot:sql(select 1)}', array());
    TestAssert::contains($filteredSql, 'pboot@sql', 'filter: pboot:sql neutralized');
    TestAssert::false((bool) preg_match('/pboot:sql/i', $filteredSql), 'filter: no pboot:sql left');

    $filteredGet = filter('GET[a] POST[b]', array());
    TestAssert::contains($filteredGet, 'GET@[', 'filter: GET[ neutralized');
    TestAssert::contains($filteredGet, 'POST@[', 'filter: POST[ neutralized');

    echo "=== filter() escapes XSS body ===\n";

    $raw = PbootIfBypassPayloads::fullTag('1==1', '<script>alert(1)</script>');
    $filteredXss = filter($raw, array());
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($filteredXss), 'filter xss: no active tag');
    TestAssert::notContains($filteredXss, '<script>', 'filter xss: no raw script');
    TestAssert::contains($filteredXss, '&lt;script&gt;', 'filter xss: escaped script');
});

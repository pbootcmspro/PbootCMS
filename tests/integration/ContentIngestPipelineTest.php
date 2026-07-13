<?php

declare(strict_types=1);

/**
 * @covers filter → parserIfLabel → restorePreLabel 端到端
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $h = ParserControllerHarness::create();

    echo "=== e2e filter → parserIfLabel → restorePreLabel ===\n";

    $raw = PbootIfBypassPayloads::defaultMaliciousTag();
    $step1 = filter($raw, array());
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($step1), 'e2e: after filter no active tag');

    $step2 = $h->parser->parserIfLabel($step1);
    TestAssert::same($step1, $step2, 'e2e: parserIfLabel no-op on neutralized');
    TestAssert::notContains($step2, '{pboot:if(', 'e2e: parse phase stays neutralized');

    $step3 = $h->parser->restorePreLabel($step2);
    TestAssert::contains($step3, 'pboot:if', 'e2e: literal pboot:if after restore');
    TestAssert::contains($step3, 'EVIL', 'e2e: body not executed away');
    TestAssert::false($step3 === 'EVIL', 'e2e: not true-branch output');
    TestAssert::contains($step3, '{pboot:if(', 'e2e: tag wrapper remains');

    echo "=== e2e filter/decode path ===\n";

    $readBack = decode_string(filter($raw, array()));
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($readBack), 'e2e decode: still neutralized');
    TestAssert::same($readBack, $h->parser->parserIfLabel($readBack), 'e2e decode: parser no-op');

    echo "=== e2e XSS body not unwrapped ===\n";

    $rawXss = PbootIfBypassPayloads::fullTag(PbootIfBypassPayloads::conditionDoubleQuote(), '<script>alert(1)</script>');
    $xss3 = $h->parser->restorePreLabel($h->parser->parserIfLabel(filter($rawXss, array())));
    TestAssert::notContains($xss3, '<script>', 'xss: no raw script in output');
    TestAssert::contains($xss3, '{pboot:if(', 'xss: tag wrapper remains');
});

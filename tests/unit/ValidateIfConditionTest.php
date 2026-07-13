<?php

declare(strict_types=1);

/**
 * @covers apps/home/controller/ParserController::validateIfCondition
 * @see https://github.com/pbootcmspro/PbootCMS/issues/28
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $h = ParserControllerHarness::create();

    echo "=== validateIfCondition rejects malicious conditions ===\n";
    foreach (PbootIfBypassPayloads::maliciousConditions() as $name => $cond) {
        TestAssert::false($h->isValidIfCondition($cond), "reject: $name");
    }

    echo "=== validateIfCondition allows legitimate conditions ===\n";
    foreach (PbootIfBypassPayloads::legitimateConditions() as $name => $cond) {
        TestAssert::true($h->isValidIfCondition($cond), "allow: $name");
    }

    echo "=== parserIfLabel rejects representative malicious tags ===\n";
    foreach (array(
        'concat bypass' => PbootIfBypassPayloads::conditionDoubleQuote(),
        'system()' => "system('id')",
    ) as $name => $cond) {
        $out = $h->parser->parserIfLabel(PbootIfBypassPayloads::fullTag($cond, 'EVIL'));
        TestAssert::contains($out, '{pboot:if(', "parser keeps tag: $name");
        TestAssert::false($out === 'EVIL', "parser not executed: $name");
    }

    echo "=== parserIfLabel legitimate branches ===\n";
    TestAssert::same('YES', $h->parser->parserIfLabel('{pboot:if(1==1)}YES{else}NO{/pboot:if}'), 'legitimate true branch');
    TestAssert::same('NO', $h->parser->parserIfLabel('{pboot:if(1==0)}YES{else}NO{/pboot:if}'), 'legitimate else branch');
});

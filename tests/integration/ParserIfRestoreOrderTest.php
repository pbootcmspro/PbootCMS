<?php

declare(strict_types=1);

/**
 * @covers apps/home/controller/ParserController parserIfLabel / restorePreLabel 顺序
 * @see https://github.com/pbootcmspro/PbootCMS/issues/28
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $h = ParserControllerHarness::create();
    $condition = PbootIfBypassPayloads::conditionDoubleQuote();
    $neutralized = 'SAFE' . PbootIfBypassPayloads::neutralizedTag($condition);
    $neutralizedOnly = PbootIfBypassPayloads::neutralizedTag($condition);

    echo "=== parserIfLabel before restorePreLabel (pipeline) ===\n";

    $afterIf = $h->parser->parserIfLabel($neutralized);
    TestAssert::same($neutralized, $afterIf, 'parserIfLabel ignores pboot@if');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($afterIf), 'parse phase no active tag');
    TestAssert::notContains($afterIf, '{pboot:if(', 'parse phase tag stays neutralized');

    $pipelineOut = $h->runIfPipeline($neutralized);
    TestAssert::contains($pipelineOut, '{pboot:if(', 'pipeline: literal tag after restore');
    TestAssert::contains($pipelineOut, 'EVIL', 'pipeline: body preserved');
    TestAssert::false($pipelineOut === 'EVIL', 'pipeline: not executed');
    TestAssert::contains($pipelineOut, 'SAFE', 'pipeline: prefix preserved');
    TestAssert::notContains($pipelineOut, 'pboot@if', 'pipeline: no pboot@if after restore');

    echo "=== wrong order defense-in-depth ===\n";

    $wrongOrderParsed = $h->parser->parserIfLabel($h->parser->restorePreLabel($neutralizedOnly));
    TestAssert::contains($wrongOrderParsed, 'EVIL', 'wrong order: body still in tag');
    TestAssert::contains($wrongOrderParsed, '{pboot:if(', 'wrong order: rejected tag in place');
    TestAssert::false($wrongOrderParsed === 'EVIL', 'wrong order: not executed');
});

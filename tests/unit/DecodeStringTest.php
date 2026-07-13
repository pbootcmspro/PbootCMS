<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php decode_string()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== decode_string() decode-then-neutralize ===\n";

    $escaped = escape_string('{pboot:if(1==1)}T{/pboot:if}');
    $decoded = decode_string($escaped);
    TestAssert::contains($decoded, 'pboot@if', 'decode: neutralized after entity round-trip');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($decoded), 'decode: no active tag after entity');

    $slashX22 = '{pboot:if((\x22a\x22==\x22a\x22))}T{/pboot:if}';
    $decodedX22 = decode_string($slashX22);
    TestAssert::contains($decodedX22, 'pboot@if', 'decode: neutralized after \\x22');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($decodedX22), 'decode: \\x22 path no active tag');
    TestAssert::contains($decodedX22, '"a"', 'decode: \\x22 became double-quote');

    $rawMalicious = PbootIfBypassPayloads::fullTag(PbootIfBypassPayloads::conditionSlashX22());
    $decodedMalicious = decode_string($rawMalicious);
    TestAssert::contains($decodedMalicious, 'pboot@if', 'decode: slash-x22 bypass neutralized');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($decodedMalicious), 'decode: slash-x22 bypass no active tag');
    TestAssert::contains($decodedMalicious, 'EVIL', 'decode: bypass body preserved');

    $rawEntity = PbootIfBypassPayloads::fullTag(PbootIfBypassPayloads::conditionHtmlEntityQuote());
    $decodedEntity = decode_string($rawEntity);
    TestAssert::contains($decodedEntity, 'pboot@if', 'decode: html entity path neutralized');
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($decodedEntity), 'decode: html entity no active tag');
    TestAssert::contains($decodedEntity, '"file_put_co"', 'decode: entity quotes decoded');

    $rawNumeric = PbootIfBypassPayloads::fullTag(PbootIfBypassPayloads::conditionNumericEntityQuote());
    $decodedNumeric = decode_string($rawNumeric);
    TestAssert::false(ParserControllerHarness::hasActivePbootIf($decodedNumeric), 'decode: numeric entity no active tag');
    TestAssert::contains($decodedNumeric, 'pboot@if', 'decode: numeric entity neutralized');
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php filter_area_acode()
 * @covers core/function/handle.php filter_area_domain()
 * @covers core/function/helper.php post(..., 'var') 区域编码链路
 *
 * 审计 #6 回归：区域编码与域名输入过滤（新增/修改共用）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'fixtures/security/AreaInputPayloads.php';

/**
 * 模拟 AreaController add/mod 编码入口：post('acode','var') + filter_area_acode
 *
 * @return string|false|null null=被 var 过滤拒绝；false=编码格式拒绝；string=通过
 */
function area_acode_pipeline($raw)
{
    $_POST['acode'] = $raw;
    $acode = post('acode', 'var');
    if (! $acode) {
        return null;
    }
    return filter_area_acode($acode);
}

/**
 * 模拟 AreaController add/mod 域名入口：post('domain') + filter_area_domain
 *
 * @return string|false|null 与业务一致：空为 ''；非法 false；合法主机名
 */
function area_domain_pipeline($raw)
{
    $_POST['domain'] = $raw;
    $domain = post('domain');
    if (! $domain) {
        return filter_area_domain('');
    }
    return filter_area_domain($domain);
}

return TestAssert::runSuite(function () {
    echo "=== filter_area_acode() accepts valid codes ===\n";
    foreach (AreaInputPayloads::acodeValid() as $code) {
        TestAssert::same($code, filter_area_acode($code), "acode valid: $code");
    }

    echo "=== filter_area_acode() rejects XSS ===\n";
    foreach (AreaInputPayloads::acodeXss() as $payload) {
        TestAssert::same(false, filter_area_acode($payload), 'acode xss rejected');
    }

    echo "=== filter_area_acode() rejects SQLi ===\n";
    foreach (AreaInputPayloads::acodeSqli() as $payload) {
        TestAssert::same(false, filter_area_acode($payload), 'acode sqli rejected');
    }

    echo "=== filter_area_acode() rejects underscore/space/unicode ===\n";
    foreach (AreaInputPayloads::acodeInvalidCharset() as $payload) {
        TestAssert::same(false, filter_area_acode($payload), "acode charset rejected: $payload");
    }

    echo "=== add/mod acode pipeline rejects XSS/SQLi ===\n";
    foreach (array_merge(AreaInputPayloads::acodeXss(), AreaInputPayloads::acodeSqli()) as $payload) {
        $out = area_acode_pipeline($payload);
        TestAssert::true($out === null || $out === false, 'pipeline rejects malicious acode');
    }

    echo "=== add/mod acode pipeline accepts valid ===\n";
    foreach (AreaInputPayloads::acodeValid() as $code) {
        TestAssert::same($code, area_acode_pipeline($code), "pipeline acode: $code");
    }

    echo "=== add/mod acode pipeline rejects underscore ===\n";
    TestAssert::same(false, area_acode_pipeline('cn_us'), 'pipeline rejects underscore acode');

    echo "=== filter_area_domain() accepts valid hosts ===\n";
    foreach (AreaInputPayloads::domainValidMap() as $input => $expected) {
        TestAssert::same($expected, filter_area_domain($input), "domain valid: $input");
    }

    echo "=== filter_area_domain() empty ===\n";
    TestAssert::same('', filter_area_domain(''), 'domain empty ok');
    TestAssert::same('', filter_area_domain('   '), 'domain whitespace ok');

    echo "=== filter_area_domain() rejects XSS ===\n";
    foreach (AreaInputPayloads::domainXss() as $payload) {
        TestAssert::same(false, filter_area_domain($payload), 'domain xss rejected');
    }

    echo "=== filter_area_domain() rejects SQLi ===\n";
    foreach (AreaInputPayloads::domainSqli() as $payload) {
        TestAssert::same(false, filter_area_domain($payload), 'domain sqli rejected');
    }

    echo "=== filter_area_domain() rejects invalid format ===\n";
    foreach (AreaInputPayloads::domainInvalid() as $payload) {
        if ($payload === '' || trim($payload) === '') {
            TestAssert::same('', filter_area_domain($payload), 'domain blank stays empty');
            continue;
        }
        TestAssert::same(false, filter_area_domain($payload), "domain invalid: $payload");
    }

    echo "=== add/mod domain pipeline rejects XSS/SQLi ===\n";
    foreach (array_merge(AreaInputPayloads::domainXss(), AreaInputPayloads::domainSqli()) as $payload) {
        $out = area_domain_pipeline($payload);
        TestAssert::true($out === false || $out === '', 'pipeline rejects malicious domain');
    }

    echo "=== add/mod domain pipeline accepts valid ===\n";
    foreach (AreaInputPayloads::domainValidMap() as $input => $expected) {
        TestAssert::same($expected, area_domain_pipeline($input), "pipeline domain: $input");
    }

    echo "=== PHP version note ===\n";
    TestAssert::true(PHP_VERSION_ID >= 70000, 'PHP >= 7.0 (got ' . PHP_VERSION . ')');
});

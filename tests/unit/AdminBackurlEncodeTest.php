<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php get_btn_qs() get_backurl()
 *
 * 运行：php tests/unit/AdminBackurlEncodeTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require APP_PATH . '/common/function.php';

/**
 * @param string $qs
 * @return array{backurl?:string}
 */
function admin_backurl_parse_qs($qs)
{
    if ($qs !== '' && ($qs[0] === '&' || $qs[0] === '?')) {
        $qs = substr($qs, 1);
    }
    $parsed = array();
    parse_str($qs, $parsed);
    return $parsed;
}

/**
 * 输出值段不得含未编码的 base64 特殊字符（= + /）
 *
 * @param string $out
 */
function admin_backurl_value_is_encoded($out)
{
    if (!preg_match('/^[?&]backurl=(.*)$/', $out, $m)) {
        return false;
    }
    $value = $m[1];
    return strpos($value, '=') === false
        && strpos($value, '+') === false
        && strpos($value, '/') === false;
}

return TestAssert::runSuite(function () {
    $listUrl = '/admin.php?p=/Content/index/mcode/2';
    if (!defined('URL')) {
        define('URL', $listUrl);
    }

    echo "=== get_btn_qs() encodes base64 backurl ===\n";

    $_SERVER['QUERY_STRING'] = 'p=/Content/index/mcode/2';
    $out = get_btn_qs();
    TestAssert::true(strpos($out, '&backurl=') === 0, 'get_btn_qs uses & when QUERY_STRING set');
    TestAssert::true(admin_backurl_value_is_encoded($out), 'get_btn_qs value has no bare = + /');
    $parsed = admin_backurl_parse_qs($out);
    TestAssert::same($listUrl, admin_b64_url($parsed['backurl']), 'get_btn_qs round-trips via parse_str + admin_b64_url');

    unset($_SERVER['QUERY_STRING']);
    $outQ = get_btn_qs();
    TestAssert::true(strpos($outQ, '?backurl=') === 0, 'get_btn_qs uses ? when no QUERY_STRING');
    TestAssert::true(admin_backurl_value_is_encoded($outQ), 'get_btn_qs ? form value is encoded');

    echo "=== get_backurl() re-encodes decoded backurl ===\n";

    $b64 = base64_encode($listUrl);
    $_GET = array('backurl' => $b64, 'p' => '/Content/mod/mcode/2/id/1');
    $_SERVER['QUERY_STRING'] = 'p=/Content/mod/mcode/2/id/1&backurl=' . rawurlencode($b64);
    $back = get_backurl();
    TestAssert::true(is_string($back) && strpos($back, '&backurl=') === 0, 'get_backurl uses & with p');
    TestAssert::true(admin_backurl_value_is_encoded($back), 'get_backurl value has no bare = + /');
    $parsedBack = admin_backurl_parse_qs($back);
    TestAssert::same($listUrl, admin_b64_url($parsedBack['backurl']), 'get_backurl round-trips');

    $_GET = array('backurl' => $b64);
    unset($_SERVER['QUERY_STRING']);
    $backQ = get_backurl();
    TestAssert::true(is_string($backQ) && strpos($backQ, '?backurl=') === 0, 'get_backurl uses ? without p');
    TestAssert::true(admin_backurl_value_is_encoded($backQ), 'get_backurl ? form value is encoded');

    echo "=== bare legacy backurl still decodes ===\n";

    TestAssert::same($listUrl, admin_b64_url($b64), 'admin_b64_url accepts bare = padding');
    $_GET = array();
    TestAssert::same(null, get_backurl(), 'missing backurl returns nullish');
});

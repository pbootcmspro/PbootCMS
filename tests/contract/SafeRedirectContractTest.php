<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers core/function/helper.php error() success() location() alert_location()
 * @covers apps/home/controller/MemberController.php login backurl chain
 * @covers apps/common/function.php get_btn_back()
 *
 * 运行：php tests/contract/SafeRedirectContractTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== centralized sanitize helpers exist ===\n";

    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function sanitize_redirect_url(', 'sanitize_redirect_url defined');
    TestAssert::contains($handle, 'function decode_html_entities_stable(', 'decode_html_entities_stable defined');
    TestAssert::contains($handle, 'redirect_resolve_jump_url(', 'redirect_resolve_jump_url defined');
    TestAssert::contains($handle, 'sanitize_redirect_origin_allowed', 'origin check uses parse_url path');
    TestAssert::notContains($handle, "strpos(\$jump_url, get_http_url())", 'prefix referer check removed from handle.php');

    echo "=== jump helpers wired before all output paths ===\n";

    $helper = file_get_contents(CORE_PATH . '/function/helper.php');
    TestAssert::false($helper === false, 'helper.php readable');
    TestAssert::contains($helper, 'redirect_resolve_jump_url($jump_url)', 'error/success use redirect_resolve_jump_url');
    TestAssert::contains($helper, '$url = redirect_resolve_jump_url($url);', 'location/alert_location use redirect_resolve_jump_url');
    TestAssert::notContains($helper, "strpos(\$jump_url, get_http_url())", 'prefix referer check removed from helper.php');
    TestAssert::notContains($helper, "strpos(\$url, get_http_url())", 'prefix referer check removed from helper location paths');

    echo "=== member login backurl chain unchanged at controller, sanitized at helper ===\n";

    $member = file_get_contents(APP_PATH . '/home/controller/MemberController.php');
    TestAssert::contains($member, "alert_location('登录成功！', \$backurl, 1);", 'MemberController passes backurl to alert_location');
    $parser = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::contains($parser, 'Url::home(\'member/login\', null, "backurl=" . urlencode($url))', 'ParserController propagates backurl to login AJAX url');

    echo "=== admin get_btn_back sanitized ===\n";

    $common = file_get_contents(APP_PATH . '/common/function.php');
    TestAssert::contains($common, 'sanitize_redirect_url($url, url(', 'get_btn_back sanitizes href');
    TestAssert::contains($common, 'htmlspecialchars($url, ENT_QUOTES', 'get_btn_back escapes href');
});

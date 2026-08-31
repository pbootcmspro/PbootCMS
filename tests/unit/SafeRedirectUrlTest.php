<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php sanitize_redirect_url()
 * @covers core/function/handle.php redirect_resolve_jump_url()
 *
 * 运行：php tests/unit/SafeRedirectUrlTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @param array<string, mixed> $server
 */
function safe_redirect_setup_server(array $server = array())
{
    $defaults = array(
        'HTTP_HOST' => 'site.test',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    );
    foreach (array_merge($defaults, $server) as $key => $value) {
        $_SERVER[$key] = $value;
    }
}

return TestAssert::runSuite(function () {
    ConfigStub::reset();
    ConfigStub::set(array());
    safe_redirect_setup_server();

    echo "=== reject external and dangerous schemes ===\n";

    TestAssert::same('/', sanitize_redirect_url('https://evil.test/phishing'), 'external https rejected');
    TestAssert::same('/', sanitize_redirect_url('http://evil.test/'), 'external http rejected');
    TestAssert::same('/', sanitize_redirect_url('javascript:alert(1)'), 'javascript: rejected');
    TestAssert::same('/', sanitize_redirect_url('JaVaScRiPt:alert(1)'), 'mixed-case javascript rejected');
    TestAssert::same('/', sanitize_redirect_url("javascript:eval(name)"), 'javascript:eval rejected');
    TestAssert::same('/', sanitize_redirect_url('data:text/html,<script>alert(1)</script>'), 'data: rejected');
    TestAssert::same('/', sanitize_redirect_url('vbscript:msgbox(1)'), 'vbscript: rejected');
    TestAssert::same('/', sanitize_redirect_url('//evil.test/path'), 'protocol-relative external rejected');
    TestAssert::same('/', sanitize_redirect_url("https://site.test.evil.test/"), 'suffix host spoof rejected');
    TestAssert::same('/', sanitize_redirect_url('https://site.test@evil.test/'), 'userinfo @ bypass rejected');
    TestAssert::same('/', sanitize_redirect_url("http://site.test\x07.evil/"), 'control char rejected');

    echo "=== allow safe relative paths ===\n";

    TestAssert::same('/member/ucenter', sanitize_redirect_url('/member/ucenter'), 'root-relative allowed');
    TestAssert::same('?member/ucenter', sanitize_redirect_url('?member/ucenter'), 'query route allowed');
    TestAssert::same('member/ucenter', sanitize_redirect_url('member/ucenter'), 'plain relative allowed');
    TestAssert::same('/cms/index.php?p=/home/index', sanitize_redirect_url('/cms/index.php?p=/home/index'), 'subdir path allowed');

    echo "=== allow same-origin absolute URL ===\n";

    TestAssert::same(
        'http://site.test/member/ucenter',
        sanitize_redirect_url('http://site.test/member/ucenter'),
        'same host http allowed'
    );

    safe_redirect_setup_server(array(
        'HTTP_HOST' => 'site.test',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
    ));

    TestAssert::same(
        'https://site.test/member/ucenter',
        sanitize_redirect_url('https://site.test/member/ucenter'),
        'same host https allowed'
    );

    TestAssert::same('/', sanitize_redirect_url('http://site.test/member/ucenter'), 'http on https request rejected');

    echo "=== configured alternate hosts ===\n";

    ConfigStub::set(array(
        'wap_domain' => 'm.site.test',
        'main_domain' => 'www.site.test',
        'lgs' => array(
            array('acode' => 'en', 'domain' => 'en.site.test'),
        ),
    ));
    safe_redirect_setup_server(array(
        'HTTP_HOST' => 'site.test',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    ));

    TestAssert::same(
        'https://m.site.test/',
        sanitize_redirect_url('https://m.site.test/'),
        'wap_domain whitelist allowed'
    );
    TestAssert::same(
        'http://en.site.test/page',
        sanitize_redirect_url('http://en.site.test/page'),
        'language bind domain allowed'
    );
    TestAssert::same('/', sanitize_redirect_url('https://other.site.test/'), 'non-whitelisted host rejected');
    TestAssert::same('/', sanitize_redirect_url('https://m.site.test:8443/admin'), 'whitelist non-default port rejected');
    TestAssert::same('/', sanitize_redirect_url('http://m.site.test:8080/'), 'whitelist non-default http port rejected');

    echo "=== relative URL with @ in query ===\n";

    safe_redirect_setup_server(array(
        'HTTP_HOST' => 'site.test',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    ));
    ConfigStub::set(array());

    TestAssert::same(
        '/search?email=user@example.com',
        sanitize_redirect_url('/search?email=user@example.com'),
        '@ in query string allowed'
    );
    TestAssert::same('/', sanitize_redirect_url('user@evil.test/path'), 'relative userinfo rejected');

    echo "=== IPv6 same-origin absolute URL ===\n";

    safe_redirect_setup_server(array(
        'HTTP_HOST' => '[2001:db8::1]',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    ));

    TestAssert::same(
        'http://[2001:db8::1]/admin',
        sanitize_redirect_url('http://[2001:db8::1]/admin'),
        'IPv6 same-origin absolute allowed'
    );

    safe_redirect_setup_server(array(
        'HTTP_HOST' => '[2001:db8::1]:8443',
        'SERVER_PORT' => '8443',
        'HTTPS' => 'on',
    ));

    TestAssert::same('/', sanitize_redirect_url('http://[2001:db8::1]/admin'), 'IPv6 scheme mismatch rejected');

    echo "=== redirect_resolve_jump_url Referer handling ===\n";

    safe_redirect_setup_server(array(
        'HTTP_HOST' => 'site.test',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    ));
    ConfigStub::set(array());

    $_SERVER['HTTP_REFERER'] = 'http://site.test.evil.test/page';
    TestAssert::same('/', redirect_resolve_jump_url('-1'), 'Referer prefix spoof rejected');

    $_SERVER['HTTP_REFERER'] = 'http://site.test@evil.test/page';
    TestAssert::same('/', redirect_resolve_jump_url('-1'), 'Referer userinfo spoof rejected');

    $_SERVER['HTTP_REFERER'] = 'http://site.test/safe';
    TestAssert::same('http://site.test/safe', redirect_resolve_jump_url('-1'), 'safe Referer allowed (string -1)');
    TestAssert::same('http://site.test/safe', redirect_resolve_jump_url(-1), 'safe Referer allowed (int -1)');
    TestAssert::same('http://site.test/safe', redirect_resolve_jump_url(' -1 '), 'safe Referer allowed (trimmed -1)');

    unset($_SERVER['HTTP_REFERER']);
    TestAssert::same(null, redirect_resolve_jump_url('-1'), 'missing Referer returns null (string -1)');
    TestAssert::same(null, redirect_resolve_jump_url(-1), 'missing Referer returns null (int -1)');

    TestAssert::same('/', redirect_resolve_jump_url('javascript:alert(1)'), 'explicit javascript resolved to fallback');

    echo "=== reject HTML entity encoded bypass ===\n";

    TestAssert::same('/', sanitize_redirect_url('javascript&#58;alert(1)'), 'entity-encoded javascript rejected');
    TestAssert::same('/', sanitize_redirect_url('javascript&#x3a;alert(1)'), 'hex entity javascript rejected');
    TestAssert::same('/', sanitize_redirect_url('JaVaScRiPt&#58;alert(1)'), 'mixed-case entity javascript rejected');
    TestAssert::same('/', sanitize_redirect_url('&#47;&#47;evil.test/path'), 'entity-encoded protocol-relative rejected');
    TestAssert::same('/', sanitize_redirect_url('&#106;avascript:alert(1)'), 'entity-encoded j in javascript rejected');
    TestAssert::same('/', sanitize_redirect_url('javascript&amp;#58;alert(1)'), 'double entity javascript rejected');
    TestAssert::same('/', sanitize_redirect_url('javascript&amp;amp;#58;alert(1)'), 'triple entity javascript rejected');

    echo "=== reject entity-decoded leading whitespace bypass ===\n";

    TestAssert::same('/', sanitize_redirect_url('&#32;//evil.test/path'), 'decimal space entity before // rejected');
    TestAssert::same('/', sanitize_redirect_url('&#x20;//evil.test/path'), 'hex space entity before // rejected');
    TestAssert::same('/', sanitize_redirect_url('&#32;&#32;//evil.test/path'), 'double space entity before // rejected');
    TestAssert::same('/', sanitize_redirect_url('&amp;#32;//evil.test/path'), 'double-encoded space entity before // rejected');
    TestAssert::same('/', sanitize_redirect_url('&#09;//evil.test/path'), 'tab entity before // rejected');
    TestAssert::same('/', sanitize_redirect_url('&#10;//evil.test/path'), 'LF entity before // rejected');

    echo "=== base64-decoded admin backurl cannot bypass ===\n";

    $encoded = base64_encode('https://evil.test/admin');
    $decoded = base64_decode($encoded);
    TestAssert::same('/', sanitize_redirect_url($decoded), 'decoded external url rejected');

    ConfigStub::reset();
});

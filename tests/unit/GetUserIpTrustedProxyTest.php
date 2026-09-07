<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers get_user_ip()
 * @covers get_user_ip_peer()
 * @covers is_forwarded_public_ipv4()
 * @covers resolve_forwarded_client_ipv4()
 *
 * fix #199 回归：默认只信 REMOTE_ADDR；配置 trusted_proxies 后才解析转发头
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function get_user_ip_reset_server()
{
    unset(
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_X_FORWARDED_FOR'],
        $_SERVER['HTTP_X_REAL_IP'],
        $_SERVER['HTTP_CLIENT_IP']
    );
}

/**
 * @param array<string, string> $server
 * @param string[]|null $trusted
 */
function get_user_ip_setup(array $server, $trusted = null)
{
    get_user_ip_reset_server();
    foreach ($server as $k => $v) {
        $_SERVER[$k] = $v;
    }
    ConfigStub::reset();
    if ($trusted !== null) {
        ConfigStub::set(array('trusted_proxies' => implode(',', $trusted)));
    } else {
        ConfigStub::set(array('trusted_proxies' => ''));
    }
}

return TestAssert::runSuite(function () {
    echo "=== no trusted proxy: ignore all forwarded headers ===\n";
    get_user_ip_setup(array(
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    ));
    TestAssert::same('203.0.113.10', get_user_ip(), 'xff ignored without trusted_proxies');

    get_user_ip_setup(array(
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_CLIENT_IP' => '1.2.3.4',
    ));
    TestAssert::same('203.0.113.10', get_user_ip(), 'client-ip ignored');

    echo "=== trusted proxy: parse xff / x-real-ip ===\n";
    get_user_ip_setup(array(
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
    ), array('10.0.0.1'));
    TestAssert::same('203.0.113.8', get_user_ip(), 'single-hop xff');

    get_user_ip_setup(array(
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.8',
    ), array('10.0.0.1'));
    TestAssert::same('203.0.113.8', get_user_ip(), 'right-to-left strip, not leftmost spoof');

    get_user_ip_setup(array(
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_REAL_IP' => '203.0.113.8',
    ), array('10.0.0.1'));
    TestAssert::same('203.0.113.8', get_user_ip(), 'x-real-ip fallback');

    get_user_ip_setup(array(
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
    ), array('10.0.0.0/8'));
    TestAssert::same('203.0.113.8', get_user_ip(), 'cidr trusted proxy');

    echo "=== non-trusted source: spoof blocked ===\n";
    get_user_ip_setup(array(
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    ), array('10.0.0.1'));
    TestAssert::same('203.0.113.10', get_user_ip(), 'xff from untrusted peer ignored');

    echo "=== reject private/reserved/loopback from forwarded headers ===\n";
    foreach (array('192.168.1.2', '127.0.0.1', '169.254.1.1', '0.0.0.0', '10.0.0.5') as $bad) {
        get_user_ip_setup(array(
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => $bad,
        ), array('10.0.0.1'));
        TestAssert::same('10.0.0.1', get_user_ip(), "reject forwarded $bad, fallback peer");
    }

    echo "=== do not skip untrusted private/malformed hops ===\n";
    // 伪造公网在左、不可信 hop 在右：不可越过；含 PoC 192.168.1.2
    foreach (array(
        '192.168.1.2' => 'private-poc',
        '127.0.0.1' => 'loopback',
        '169.254.1.1' => 'link-local',
        '0.0.0.0' => 'zero',
        '10.0.0.5' => 'private',
        'not-an-ip' => 'malformed',
        '::1' => 'ipv6-literal',
    ) as $hop => $label) {
        get_user_ip_setup(array(
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => "6.6.6.6, $hop",
        ), array('10.0.0.1'));
        TestAssert::same('10.0.0.1', get_user_ip(), "reject chain across untrusted $label hop");
    }

    echo "=== peer normalization ===\n";
    get_user_ip_setup(array('REMOTE_ADDR' => '::1'));
    TestAssert::same('127.0.0.1', get_user_ip(), '::1 to 127.0.0.1');

    get_user_ip_setup(array('REMOTE_ADDR' => '2001:db8::1'));
    TestAssert::same('0.0.0.0', get_user_ip(), 'ipv6 peer without trusting headers');

    get_user_ip_setup(array(
        'REMOTE_ADDR' => '2001:db8::1',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    ), array('10.0.0.1'));
    TestAssert::same('0.0.0.0', get_user_ip(), 'ipv6 peer never trusts xff');

    echo "=== is_forwarded_public_ipv4 ===\n";
    TestAssert::true(is_forwarded_public_ipv4('203.0.113.8'), 'public ipv4 accepted');
    TestAssert::false(is_forwarded_public_ipv4('192.168.1.1'), 'private rejected');
    TestAssert::false(is_forwarded_public_ipv4('127.0.0.1'), 'loopback rejected');
    TestAssert::false(is_forwarded_public_ipv4('0.0.0.0'), 'zero rejected');

    echo "=== ip2long compatibility ===\n";
    get_user_ip_setup(array(
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
    ), array('10.0.0.1'));
    $dep = 0;
    set_error_handler(static function ($errno) use (&$dep) {
        if ($errno === E_NOTICE || $errno === E_DEPRECATED || $errno === E_WARNING) {
            $dep++;
        }
    });
    $long = ip2long(get_user_ip());
    restore_error_handler();
    TestAssert::same(0, $dep, 'ip2long(get_user_ip()) no notices');
    TestAssert::true($long !== false, 'ip2long returns int');
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/remote_fetch.php
 *
 * Issue #200 — UEditor 远程抓图 SSRF 校验矩阵（无外网请求）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (! function_exists('remote_fetch_is_blocked_ip')) {
    require CORE_PATH . '/function/remote_fetch.php';
}
if (! function_exists('svgz_decompress_limited_string')) {
    require CORE_PATH . '/function/file.php';
}

$allowFiles = array('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp', '.svg', '.svgz', '.avif');

return TestAssert::runSuite(function () use ($allowFiles) {
    echo "=== remote_fetch_is_blocked_ip ===\n";

    TestAssert::true(remote_fetch_is_blocked_ip('127.0.0.1'), '127.0.0.1 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('10.0.0.1'), '10.0.0.0/8 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('192.168.1.1'), '192.168.0.0/16 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('172.16.0.1'), '172.16.0.0/12 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('169.254.169.254'), '169.254.169.254 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('100.64.0.1'), '100.64.0.0/10 CGNAT blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('100.127.255.254'), '100.64.0.0/10 upper bound blocked');
    TestAssert::false(remote_fetch_is_blocked_ip('100.128.0.1'), '100.128.0.1 outside CGNAT allowed');
    TestAssert::true(remote_fetch_is_blocked_ip('::ffff:100.64.0.1'), 'IPv4-mapped CGNAT blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('0.0.0.0'), '0.0.0.0 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('::1'), '::1 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('fe80::1'), 'fe80::/10 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('fc00::1'), 'fc00::/7 blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('fd12:3456:7890::1'), 'fd ULA blocked');
    TestAssert::true(remote_fetch_is_blocked_ip('::ffff:127.0.0.1'), 'IPv4-mapped loopback blocked');

    TestAssert::false(remote_fetch_is_blocked_ip('8.8.8.8'), '8.8.8.8 allowed');
    TestAssert::false(remote_fetch_is_blocked_ip('1.1.1.1'), '1.1.1.1 allowed');

    echo "=== remote_fetch_validate_image_url rejects dangerous targets ===\n";

    $rejectUrls = array(
        'file:///etc/passwd' => 'invalid_scheme',
        'gopher://127.0.0.1/x' => 'invalid_scheme',
        'dict://127.0.0.1:11211/stat' => 'invalid_scheme',
        'http://127.0.0.1/a.png' => 'invalid_ip',
        'http://localhost/a.png' => 'invalid_ip',
        'http://[::1]/a.png' => 'invalid_ip',
        'http://169.254.169.254/latest/meta-data' => 'invalid_ip',
        'http://10.0.0.1/x.png' => 'invalid_ip',
        'http://192.168.0.1/x.png' => 'invalid_ip',
        'http://0.0.0.0/x.png' => 'invalid_ip',
        'http://100.64.0.1/x.png' => 'invalid_ip',
        'http://user@8.8.8.8/x.png' => 'invalid_url',
        'http://example.com/x.txt' => 'invalid_type',
        'not-a-url' => 'invalid_url',
    );

    foreach ($rejectUrls as $url => $expectErr) {
        $result = remote_fetch_validate_image_url($url, $allowFiles);
        TestAssert::false($result, "reject: $url");
        TestAssert::same($expectErr, remote_fetch_last_error(), "error code for: $url");
    }

    echo "=== remote_fetch_validate_image_url accepts well-formed public URL shape ===\n";

    // 仅校验 URL 形态与扩展名；解析依赖 DNS，example.com 通常解析到公网
    $parsed = remote_fetch_validate_image_url('http://example.com/path/photo.png', $allowFiles);
    if ($parsed === false && remote_fetch_last_error() === 'invalid_ip') {
        echo "SKIP: example.com did not resolve to a public IP in this environment\n";
    } else {
        TestAssert::true(is_array($parsed), 'example.com png URL validates when DNS is public');
        if (is_array($parsed)) {
            TestAssert::same('.png', $parsed['ext'], 'ext extracted from path');
            TestAssert::same('example.com', $parsed['host'], 'host normalized');
            TestAssert::false(remote_fetch_is_blocked_ip($parsed['ip']), 'pinned ip is public');
        }
    }

    echo "=== remote_fetch_content_type_matches ===\n";

    TestAssert::true(remote_fetch_content_type_matches('image/png', '.png'), 'png ctype');
    TestAssert::true(remote_fetch_content_type_matches('image/jpeg', '.jpg'), 'jpg ctype');
    TestAssert::false(remote_fetch_content_type_matches('text/html', '.png'), 'html rejected');
    TestAssert::false(remote_fetch_content_type_matches('application/octet-stream', '.png'), 'octet-stream rejected');

    echo "=== remote_fetch_verify_image_body ===\n";

    $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    TestAssert::true(remote_fetch_verify_image_body($png1x1, '.png'), 'valid 1x1 png');
    TestAssert::false(remote_fetch_verify_image_body('<html></html>', '.png'), 'html not png');
    TestAssert::true(remote_fetch_verify_image_body('<svg xmlns="http://www.w3.org/2000/svg"></svg>', '.svg'), 'minimal svg');
    TestAssert::false(remote_fetch_verify_image_body('not svg', '.svg'), 'non-svg rejected');

    echo "=== remote_fetch_verify_image_body SVGZ limited decompress ===\n";

    $svgPayload = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
    $svgzOk = gzencode($svgPayload, 9);
    TestAssert::true(is_string($svgzOk) && $svgzOk !== '', 'svgz fixture encoded');
    TestAssert::true(remote_fetch_verify_image_body($svgzOk, '.svgz', 65536), 'valid svgz within limit');

    $svgzBomb = gzencode(str_repeat('A', 50000), 9);
    TestAssert::false(remote_fetch_verify_image_body($svgzBomb, '.svgz', 4096), 'svgz gzip bomb rejected by limit');

    echo "=== remote_fetch_http_get_pinned uses pinned IP (no rebinding to loopback) ===\n";

    if (! function_exists('curl_init')) {
        echo "SKIP: curl extension missing\n";
    } else {
        // 127.0.0.1 为 blocked，但此处强制 pin 到 127.0.0.1 验证 CURLOPT_RESOLVE 生效；
        // 若 pin 到 8.8.8.8 而 host 为 127.0.0.1 则连接应失败（非标准 HTTP）
        $resp = remote_fetch_http_get_pinned(
            'http://rebind.test/robots.txt',
            'rebind.test',
            80,
            '127.0.0.1',
            4096
        );
        // 预期：要么连不上(dead_link)，要么拿到 127.0.0.1 的响应；不应 silent 成功到公网
        if ($resp === false) {
            TestAssert::same('dead_link', remote_fetch_last_error(), 'pinned loopback fetch fails safely');
        } else {
            TestAssert::true(is_array($resp), 'pinned fetch returned array when loopback responded');
        }
    }
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php filter_iframe_*()
 * @covers core/function/handle.php filter_iframe_extract_src()
 *
 * 运行：
 *   php tests/unit/IframeWhitelistTest.php
 *   php tests/run.php --filter=Iframe
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $wl = function (array $hosts) {
        $map = array();
        foreach ($hosts as $host) {
            $map[$host] = true;
        }
        return $map;
    };

    echo "=== filter_iframe_normalize_host() ===\n";

    TestAssert::same('example.com', filter_iframe_normalize_host('example.com'), 'normalize: literal host');
    TestAssert::same('example.com', filter_iframe_normalize_host('Example.COM'), 'normalize: lowercase');
    TestAssert::same('player.bilibili.com', filter_iframe_normalize_host('https://player.bilibili.com/path'), 'normalize: URL to host');
    TestAssert::same('*.example.com', filter_iframe_normalize_host('*.Example.COM'), 'normalize: wildcard');
    TestAssert::same('*.example.com', filter_iframe_normalize_host('.example.com'), 'normalize: cookie domain');
    TestAssert::same('', filter_iframe_normalize_host('*example.com'), 'normalize: invalid wildcard without dot');
    TestAssert::same('', filter_iframe_normalize_host('*.'), 'normalize: invalid wildcard empty base');
    TestAssert::same('', filter_iframe_normalize_host('*.com'), 'normalize: reject single-label wildcard');
    TestAssert::same('', filter_iframe_normalize_host('*.localhost'), 'normalize: reject localhost wildcard');
    TestAssert::same('sub.example.com', filter_iframe_normalize_host('sub.example.com'), 'normalize: subdomain literal');

    echo "=== filter_iframe_host_in_whitelist() ===\n";

    TestAssert::true(filter_iframe_host_in_whitelist('example.com', $wl(array('example.com'))), 'whitelist: exact match');
    TestAssert::false(filter_iframe_host_in_whitelist('sub.example.com', $wl(array('example.com'))), 'whitelist: no subdomain without wildcard');
    TestAssert::true(filter_iframe_host_in_whitelist('sub.example.com', $wl(array('*.example.com'))), 'whitelist: wildcard subdomain');
    TestAssert::true(filter_iframe_host_in_whitelist('a.b.example.com', $wl(array('*.example.com'))), 'whitelist: deep subdomain');
    TestAssert::false(filter_iframe_host_in_whitelist('example.com', $wl(array('*.example.com'))), 'whitelist: wildcard excludes bare domain');
    TestAssert::false(filter_iframe_host_in_whitelist('notexample.com', $wl(array('*.example.com'))), 'whitelist: no false positive suffix');
    TestAssert::false(filter_iframe_host_in_whitelist('evil.example.com.evil', $wl(array('*.example.com'))), 'whitelist: no false positive prefix');
    TestAssert::true(filter_iframe_host_in_whitelist('sub.example.com', $wl(array('example.com', '*.example.com'))), 'whitelist: mixed');

    echo "=== filter_iframe_rebuild() ===\n";

    $wildcardWl = $wl(array('*.bilibili.com'));
    $out = filter_iframe_rebuild('src="//player.bilibili.com/player.html"', $wildcardWl);
    TestAssert::true($out !== '', 'rebuild: wildcard subdomain allowed');
    TestAssert::contains($out, 'player.bilibili.com', 'rebuild: preserves src host');
    TestAssert::same('', filter_iframe_rebuild('src="//bilibili.com/"', $wildcardWl), 'rebuild: rejects bare domain');

    $exactWl = $wl(array('example.com'));
    TestAssert::true(filter_iframe_rebuild('src="//example.com/embed"', $exactWl) !== '', 'rebuild: exact match');
    TestAssert::same('', filter_iframe_rebuild('src="//sub.example.com/embed"', $exactWl), 'rebuild: exact does not match subdomain');

    $subWl = $wl(array('sub.example.com'));
    TestAssert::true(filter_iframe_rebuild('src="//sub.example.com/embed"', $subWl) !== '', 'rebuild: subdomain exact match');
    TestAssert::same('', filter_iframe_rebuild('src="//example.com/embed"', $subWl), 'rebuild: subdomain exact does not match parent');

    echo "=== filter_iframe_extract_src() ===\n";

    TestAssert::same(
        'https://example.com/embed',
        filter_iframe_extract_src('src=https://example.com/embed'),
        'extract_src: unquoted'
    );
    TestAssert::same(
        'https://example.com/embed',
        filter_iframe_extract_src("src='https://example.com/embed'"),
        'extract_src: single quoted'
    );
    TestAssert::same(
        'https://example.com/embed',
        filter_iframe_extract_src('src="https://example.com/embed"'),
        'extract_src: double quoted'
    );
    TestAssert::same(
        'https://example.com/embed',
        filter_iframe_extract_src('src="javascript:alert(1)" src=https://example.com/embed'),
        'extract_src: last unquoted wins'
    );
    TestAssert::same(
        'javascript:alert(1)',
        filter_iframe_extract_src('src=https://example.com/embed src="javascript:alert(1)"'),
        'extract_src: last quoted wins'
    );
    TestAssert::same('', filter_iframe_extract_src('src='), 'extract_src: empty unquoted value');
    TestAssert::same('', filter_iframe_extract_src(''), 'extract_src: empty attrs');
    TestAssert::same('', filter_iframe_extract_src('width=560'), 'extract_src: no src attr');
    TestAssert::same(
        'https://imgpai.thepaper.cn/x.jpg?x-oss-process=image/resize,w_1024',
        filter_iframe_extract_src(
            'src=https://imgpai.thepaper.cn/x.jpg?x-oss-process=image/resize,w_1024'
        ),
        'extract_src: unquoted URL with query equals'
    );
    TestAssert::same(
        'https://imgpai.thepaper.cn/x.jpg?x-oss-process=image/resize,w_1024',
        filter_iframe_extract_src(
            'src="https://evil.com" src=https://imgpai.thepaper.cn/x.jpg?x-oss-process=image/resize,w_1024'
        ),
        'extract_src: last unquoted with query equals wins'
    );

    echo "=== filter_iframe_rebuild() unquoted src ===\n";

    $unquotedOut = filter_iframe_rebuild('src=https://example.com/embed', $exactWl);
    TestAssert::true($unquotedOut !== '', 'rebuild: unquoted single src whitelisted');
    TestAssert::contains($unquotedOut, 'example.com', 'rebuild: unquoted preserves host');

    $unquotedDup = filter_iframe_rebuild(
        'src=javascript:alert(1) src=https://example.com/embed',
        $exactWl
    );
    TestAssert::true($unquotedDup !== '', 'rebuild: unquoted duplicate src last good wins');
    TestAssert::contains($unquotedDup, 'example.com', 'rebuild: unquoted dup last good host');

    $queryEquals = filter_iframe_rebuild(
        'src=https://example.com/x.jpg?x-oss-process=image/resize,w_1024',
        $exactWl
    );
    TestAssert::true($queryEquals !== '', 'rebuild: unquoted src with query equals');
    TestAssert::contains(
        $queryEquals,
        'x.jpg?x-oss-process=image/resize,w_1024',
        'rebuild: preserves query string with equals'
    );

    echo "=== filter_html_extract_iframe_hosts() ===\n";

    $hosts = filter_html_extract_iframe_hosts(
        '<p>x</p><iframe src="https://Player.Example.COM/v"></iframe><iframe SRC="//video.cdn.example.com/play"></iframe>'
    );
    TestAssert::true(in_array('player.example.com', $hosts, true), 'extract: https host lowercase');
    TestAssert::true(in_array('video.cdn.example.com', $hosts, true), 'extract: protocol-relative');
    TestAssert::same(2, count($hosts), 'extract: dedupe count');
    TestAssert::same(
        array('player.example.com'),
        filter_html_extract_iframe_hosts('<iframe src="https://player.example.com:8443/play"></iframe>'),
        'extract: strips port'
    );
    TestAssert::same(
        array(),
        filter_html_extract_iframe_hosts('<iframe src="javascript:alert(1)"></iframe>'),
        'extract: rejects javascript'
    );
    TestAssert::same(
        array(),
        filter_html_extract_iframe_hosts('<iframe src="data:text/html,x"></iframe>'),
        'extract: rejects data'
    );

    TestAssert::same(
        array('example.com'),
        filter_html_extract_iframe_hosts('<iframe src=https://example.com/embed></iframe>'),
        'extract: unquoted single src'
    );
    TestAssert::same(
        array('example.com'),
        filter_html_extract_iframe_hosts(
            '<iframe src=javascript:alert(1) src=https://example.com/embed></iframe>'
        ),
        'extract: unquoted duplicate src last good wins'
    );
    TestAssert::same(
        array('imgpai.thepaper.cn'),
        filter_html_extract_iframe_hosts(
            '<iframe src=https://imgpai.thepaper.cn/x.jpg?x-oss-process=image/resize,w_1024></iframe>'
        ),
        'extract: unquoted src with query equals host'
    );

    echo "=== extract after post() escape round-trip ===\n";

    $raw = '<iframe src="https://player.bilibili.com/video/xxx" width="560" height="315"></iframe>';
    $escaped = addslashes(htmlspecialchars(trim($raw), ENT_QUOTES, 'UTF-8'));
    TestAssert::same(array(), filter_html_extract_iframe_hosts($escaped), 'extract: escaped content empty');
    TestAssert::same(
        array('player.bilibili.com'),
        filter_html_extract_iframe_hosts(decode_string($escaped)),
        'extract: after decode_string like sync_iframe_whitelist_from_content'
    );

    echo "=== filter_iframe_whitelist_filter_new_hosts() / merge_exact_hosts() ===\n";

    TestAssert::same(
        array('player.example.com'),
        filter_iframe_whitelist_filter_new_hosts(array('player.example.com'), 'map.baidu.com'),
        'filter new: exact host'
    );
    TestAssert::same(
        array(),
        filter_iframe_whitelist_filter_new_hosts(array('sub.example.com'), '*.example.com'),
        'filter new: covered by wildcard'
    );
    TestAssert::same(
        array(),
        filter_iframe_whitelist_filter_new_hosts(array('sub.example.com'), 'sub.example.com'),
        'filter new: covered by exact'
    );

    $merge1 = filter_iframe_whitelist_merge_exact_hosts('map.baidu.com', array('player.example.com', 'player.example.com'));
    TestAssert::true($merge1['added'], 'merge: adds new');
    TestAssert::same('map.baidu.com,player.example.com', $merge1['value'], 'merge: exact not wildcard');
    TestAssert::same(array('player.example.com'), $merge1['added_hosts'], 'merge: added_hosts');

    $merge2 = filter_iframe_whitelist_merge_exact_hosts('*.example.com', array('sub.example.com'));
    TestAssert::false($merge2['added'], 'merge: skip wildcard-covered');
    TestAssert::same('*.example.com', $merge2['value'], 'merge: unchanged when covered');

    $merge3 = filter_iframe_whitelist_merge_exact_hosts('', array('github.com'));
    TestAssert::true($merge3['added'], 'merge: into empty');
    TestAssert::same('github.com', $merge3['value'], 'merge: exact bare host');
});

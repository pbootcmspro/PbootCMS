<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php get_indexnow_msg() filter_indexnow_urls() build_indexnow_url() indexnow_key_location_covers_urls() post_indexnow()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');

require APP_PATH . '/common/function.php';

function indexnow_rs($code, $errno = 0, $error = '')
{
    return array(
        'code' => $code,
        'errno' => $errno,
        'error' => $error
    );
}

return TestAssert::runSuite(function () {
    echo "=== post_indexnow() TLS 校验 ===\n";

    $source = file_get_contents(APP_PATH . '/common/function.php');
    TestAssert::contains($source, 'CURLOPT_SSL_VERIFYHOST => 2', 'IndexNow启用TLS主机名校验');
    TestAssert::notContains($source, 'CURLOPT_SSL_VERIFYHOST => 0', 'IndexNow不得关闭TLS主机名校验');

    echo "=== get_indexnow_msg() 成功状态 ===\n";

    $ok = get_indexnow_msg(indexnow_rs(200), 3);
    TestAssert::contains($ok, '成功推送3条', '200提示含推送条数');

    $accepted = get_indexnow_msg(indexnow_rs(202), 5);
    TestAssert::contains($accepted, '已接收5条', '202提示含推送条数');
    TestAssert::contains($accepted, '密钥待校验', '202说明密钥待校验');

    echo "=== get_indexnow_msg() 失败状态可读 ===\n";

    $codes = array(
        400 => '格式错误',
        403 => '密钥无效',
        422 => '不属于本站点',
        429 => '过于频繁'
    );
    foreach ($codes as $code => $keyword) {
        $msg = get_indexnow_msg(indexnow_rs($code), 1);
        TestAssert::contains($msg, $keyword, $code . '有针对性提示');
        TestAssert::notContains($msg, '未知', $code . '不是笼统的未知错误');
    }

    TestAssert::contains(get_indexnow_msg(indexnow_rs(0), 1), '未获取到接口响应', '无响应提示');
    TestAssert::contains(get_indexnow_msg(indexnow_rs(500), 1), '500', '未覆盖状态码回显原始码');

    echo "=== get_indexnow_msg() 网络与证书错误 ===\n";

    $curl = get_indexnow_msg(indexnow_rs(0, 60, 'SSL certificate problem: unable to get "local" issuer certificate'), 1);
    TestAssert::contains($curl, 'SSL certificate problem', 'curl错误原文透出');
    TestAssert::contains($curl, 'HTTPS证书', '给出证书排查方向');
    // 提示语最终拼进JS弹窗字符串，带引号会截断脚本
    TestAssert::notContains($curl, '"', '提示语不含双引号');
    TestAssert::notContains($curl, "'", '提示语不含单引号');

    // curl 错误优先于状态码，避免证书失败被当成接口拒绝
    $curlFirst = get_indexnow_msg(indexnow_rs(403, 60, 'SSL error'), 1);
    TestAssert::contains($curlFirst, '请求失败', 'curl错误优先于状态码');

    echo "=== filter_indexnow_urls() 去重与同域约束 ===\n";

    TestAssert::same(array(), filter_indexnow_urls(array()), '空数组原样返回');
    TestAssert::same(array(), filter_indexnow_urls(array('/a.html')), '相对地址无host时丢弃');

    TestAssert::same(
        array(
            'https://www.example.com/a.html',
            'https://www.example.com/b.html'
        ),
        filter_indexnow_urls(array(
            'https://www.example.com/a.html',
            'https://www.example.com/b.html',
            'https://www.example.com/a.html'
        )),
        '重复地址只保留一条'
    );

    // 混入其它域名会导致整批被接口拒绝
    TestAssert::same(
        array(
            'https://www.example.com/a.html',
            'https://www.example.com/b.html'
        ),
        filter_indexnow_urls(array(
            'https://www.example.com/a.html',
            'https://evil.example.org/x.html',
            'https://www.example.com/b.html'
        )),
        '异域地址被剔除'
    );

    TestAssert::same(
        array(
            'https://www.example.com/a.html'
        ),
        filter_indexnow_urls(array(
            'https://www.example.com/a.html',
            'https://www.example.com@evil.example.org/x.html'
        )),
        '伪装成本站前缀的地址被剔除'
    );

    // 以服务端 host 为准：即使首条被篡改为异域，仍只保留期望域
    TestAssert::same(
        array(
            'https://www.example.com/b.html'
        ),
        filter_indexnow_urls(array(
            'https://evil.example.org/x.html',
            'https://www.example.com/b.html'
        ), 'www.example.com'),
        'expected_host 丢弃首条异域并保留本站'
    );
    TestAssert::same(
        array(),
        filter_indexnow_urls(array(
            'https://evil.example.org/x.html'
        ), 'www.example.com'),
        'expected_host 下全异域则空'
    );

    echo "=== build_indexnow_url() 域名绑定不重复拼接 ===\n";

    TestAssert::same(
        'https://admin.example.com/news/1.html',
        build_indexnow_url('https://admin.example.com', '/news/1.html'),
        '相对路径前补域名'
    );
    TestAssert::same(
        'https://bound.example.com/news/1.html',
        build_indexnow_url('https://admin.example.com', 'https://bound.example.com/news/1.html'),
        '已带协议的绝对地址不再拼接'
    );
    TestAssert::same(
        'http://bound.example.com/a',
        build_indexnow_url('https://admin.example.com', 'http://bound.example.com/a'),
        'http绝对地址不再拼接'
    );
    TestAssert::same(
        'https://admin.example.comhttpfoo',
        build_indexnow_url('https://admin.example.com', 'httpfoo'),
        'httpfoo 不当作绝对地址'
    );

    echo "=== indexnow_key_location_covers_urls() 目录覆盖 ===\n";

    TestAssert::true(
        indexnow_key_location_covers_urls(
            'http://dev.example.com/key.txt',
            array('http://dev.example.com/?a.html', 'http://dev.example.com/news/1.html')
        ),
        '根目录密钥覆盖站点 URL'
    );
    TestAssert::false(
        indexnow_key_location_covers_urls(
            'http://dev.example.com/data/key.txt',
            array('http://dev.example.com/?a.html')
        ),
        'data 子目录密钥盖不住根路径 URL'
    );
    TestAssert::true(
        indexnow_key_location_covers_urls(
            'http://dev.example.com/data/key.txt',
            array('http://dev.example.com/data/a.html')
        ),
        '子目录密钥可覆盖同目录 URL'
    );
});

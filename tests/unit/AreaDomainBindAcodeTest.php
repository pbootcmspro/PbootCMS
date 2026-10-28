<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php match_area_bind_acode()
 *
 * 多语言二级域名绑定回归：绑定域名与请求域名两侧规范化后再比较。
 * 原实现用 $value['domain'] == get_http_host() 直接相等，库中旧数据带
 * http:// 、尾部斜杠或大写时匹配不上，绑定域名的语言会退回默认语言。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 区域配置映射，模拟 cache_config() 写入的 lgs
 *
 * @param array<string,string> $domains acode => 绑定域名
 * @return array<string,array<string,string>>
 */
function area_bind_lgs(array $domains)
{
    $lgs = array();
    foreach ($domains as $acode => $domain) {
        $lgs[$acode] = array(
            'acode' => $acode,
            'domain' => $domain,
            'name' => $acode
        );
    }
    return $lgs;
}

return TestAssert::runSuite(function () {
    $lgs = area_bind_lgs(array(
        'cn' => 'www.example.com',
        'en' => 'en.example.com',
        'ru' => 'ru.example.com'
    ));

    echo "=== 裸主机名精确匹配 ===\n";
    TestAssert::same('cn', match_area_bind_acode($lgs, 'www.example.com'), '默认区域命中');
    TestAssert::same('en', match_area_bind_acode($lgs, 'en.example.com'), '英文二级域名命中');
    TestAssert::same('ru', match_area_bind_acode($lgs, 'ru.example.com'), '俄文二级域名命中');

    echo "=== 库中域名为旧格式时仍命中 ===\n";
    $scheme = area_bind_lgs(array(
        'cn' => 'www.example.com',
        'en' => 'http://en.example.com'
    ));
    TestAssert::same('en', match_area_bind_acode($scheme, 'en.example.com'), '带 http:// 前缀');
    $https = area_bind_lgs(array(
        'cn' => 'www.example.com',
        'en' => 'https://en.example.com/'
    ));
    TestAssert::same('en', match_area_bind_acode($https, 'en.example.com'), '带 https:// 与尾部斜杠');
    $upper = area_bind_lgs(array(
        'cn' => 'www.example.com',
        'en' => 'EN.Example.COM'
    ));
    TestAssert::same('en', match_area_bind_acode($upper, 'en.example.com'), '库中大写主机名');

    echo "=== 请求主机名异常形态仍命中 ===\n";
    TestAssert::same('en', match_area_bind_acode($lgs, 'EN.EXAMPLE.COM'), '请求头大写');
    TestAssert::same('en', match_area_bind_acode($lgs, 'en.example.com:8080'), '请求头带端口');
    TestAssert::same('en', match_area_bind_acode($lgs, 'en.example.com.'), '请求头带尾部点');
    TestAssert::same('en', match_area_bind_acode($lgs, ' en.example.com '), '请求头带空白');

    echo "=== 未命中返回 null ===\n";
    TestAssert::same(null, match_area_bind_acode($lgs, 'other.example.com'), '未绑定的域名');
    TestAssert::same(null, match_area_bind_acode($lgs, ''), '空主机名');
    TestAssert::same(null, match_area_bind_acode($lgs, 'http://en.example.com/path'), '带路径的非法主机名');

    echo "=== 空绑定域名的区域被跳过 ===\n";
    $partial = area_bind_lgs(array(
        'cn' => '',
        'en' => 'en.example.com'
    ));
    TestAssert::same(null, match_area_bind_acode($partial, 'www.example.com'), '未填域名不参与匹配');
    TestAssert::same('en', match_area_bind_acode($partial, 'en.example.com'), '已填域名照常匹配');

    echo "=== 单区域与非法配置不绑定 ===\n";
    $single = area_bind_lgs(array(
        'cn' => 'www.example.com'
    ));
    TestAssert::same(null, match_area_bind_acode($single, 'www.example.com'), '仅一个区域时跳过');
    TestAssert::same(null, match_area_bind_acode(null, 'en.example.com'), 'lgs 为 null');
    TestAssert::same(null, match_area_bind_acode('', 'en.example.com'), 'lgs 非数组');

    echo "=== 区域数据为对象时兼容 ===\n";
    $objects = array();
    foreach ($lgs as $acode => $value) {
        $objects[$acode] = (object) $value;
    }
    TestAssert::same('en', match_area_bind_acode($objects, 'en.example.com'), '对象形式的区域数据');

    echo "=== 原实现的字符串相等会漏匹配 ===\n";
    TestAssert::false('http://en.example.com' == 'en.example.com', '旧比较方式确实不相等');
});

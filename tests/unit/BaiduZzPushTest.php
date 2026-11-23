<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php baidu_zz_should_push() get_baidu_zz_result() post_baidu()
 *
 *   php tests/run.php --suite=unit --filter=BaiduZzPush
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');

require APP_PATH . '/common/function.php';

function baidu_zz_fixture($json)
{
    return json_decode($json);
}

return TestAssert::runSuite(function () {
    echo "=== post_baidu() 超时默认值 ===\n";

    $ref = new ReflectionFunction('post_baidu');
    $params = $ref->getParameters();
    TestAssert::same(4, count($params), 'post_baidu has 4 parameters');
    TestAssert::same(30, $params[2]->getDefaultValue(), 'connect_timeout default 30');
    TestAssert::same(90, $params[3]->getDefaultValue(), 'timeout default 90');

    echo "=== baidu_zz_should_push() 边界 ===\n";

    $now = strtotime('2026-11-19 12:00:00');
    TestAssert::false(baidu_zz_should_push('', 1, 1, '', '2026-11-19 11:00:00', $now), 'empty token');
    TestAssert::false(baidu_zz_should_push('tok', 0, 1, '', '2026-11-19 11:00:00', $now), 'unchecked');
    TestAssert::false(baidu_zz_should_push('tok', 1, 0, '', '2026-11-19 11:00:00', $now), 'hidden status');
    TestAssert::false(baidu_zz_should_push('tok', 1, 1, 'https://x.com', '2026-11-19 11:00:00', $now), 'outlink');
    TestAssert::true(baidu_zz_should_push('tok', 1, 1, '  ', '2026-11-19 11:00:00', $now), 'whitespace-only outlink allowed');
    TestAssert::false(baidu_zz_should_push('tok', 1, 1, '', '2026-11-19 13:00:00', $now), 'future date');
    TestAssert::false(baidu_zz_should_push('tok', 1, 1, '', 'not-a-date', $now), 'unparseable date');
    TestAssert::false(baidu_zz_should_push('tok', 1, 1, '', '', $now), 'empty date');
    TestAssert::true(baidu_zz_should_push('tok', 1, 1, '', '2026-11-19 12:00:00', $now), 'equal to now');
    TestAssert::true(baidu_zz_should_push('tok', 1, 1, '', '2026-11-19 11:00:00', $now), 'past date');

    echo "=== get_baidu_zz_result() 各分支 ===\n";

    $nullRs = get_baidu_zz_result(null);
    TestAssert::false($nullRs['ok'], 'null not ok');
    TestAssert::same('百度推送失败：连接超时或无响应', $nullRs['msg'], 'null fixed msg');

    $scalarRs = get_baidu_zz_result('oops');
    TestAssert::false($scalarRs['ok'], 'scalar not ok');
    TestAssert::same('百度推送失败：连接超时或无响应', $scalarRs['msg'], 'scalar fixed msg');

    $ok = get_baidu_zz_result(baidu_zz_fixture('{"success":1,"remain":99}'));
    TestAssert::true($ok['ok'], 'success>0 ok');
    TestAssert::same('已推送百度普通收录，今日剩余 99 条', $ok['msg'], 'success remain msg');

    $auth = get_baidu_zz_result(baidu_zz_fixture('{"error":401,"message":"token is not valid"}'));
    TestAssert::false($auth['ok'], '401 not ok');
    TestAssert::same('百度推送失败：token 无效', $auth['msg'], '401 fixed msg');
    TestAssert::notContains($auth['msg'], 'token is not valid', '401 no raw message');

    $site = get_baidu_zz_result(baidu_zz_fixture('{"error":400,"message":"site error"}'));
    TestAssert::same('百度推送失败：站点未在百度验证，或域名/协议与验证站点不一致', $site['msg'], 'site error msg');

    $quota = get_baidu_zz_result(baidu_zz_fixture('{"error":400,"message":"over quota"}'));
    TestAssert::same('百度推送失败：今日额度已用完', $quota['msg'], 'over quota msg');

    $zero = get_baidu_zz_result(baidu_zz_fixture('{"success":0,"remain":10}'));
    TestAssert::false($zero['ok'], 'success=0 not ok');
    TestAssert::same('百度推送失败：地址未被接收', $zero['msg'], 'success=0 msg');

    $badSite = get_baidu_zz_result(baidu_zz_fixture('{"success":1,"not_same_site":["http://x"]}'));
    TestAssert::false($badSite['ok'], 'not_same_site not ok');
    TestAssert::same('百度推送失败：地址未被接收', $badSite['msg'], 'not_same_site msg');

    $invalid = get_baidu_zz_result(baidu_zz_fixture('{"success":1,"not_valid":["http://x"]}'));
    TestAssert::false($invalid['ok'], 'not_valid not ok');
    TestAssert::same('百度推送失败：地址未被接收', $invalid['msg'], 'not_valid msg');

    $other = get_baidu_zz_result(baidu_zz_fixture('{"error":500,"message":"internal error"}'));
    TestAssert::same('百度推送失败：接口错误（500）', $other['msg'], 'other error code');
    TestAssert::notContains($other['msg'], 'internal error', 'other error no raw message');

    $unknown = get_baidu_zz_result(baidu_zz_fixture('{"foo":1}'));
    TestAssert::false($unknown['ok'], 'unknown shape not ok');
    TestAssert::same('百度推送失败：连接超时或无响应', $unknown['msg'], 'unknown shape msg');

    foreach (array($nullRs, $auth, $site, $quota, $zero, $other, $ok) as $rs) {
        TestAssert::notContains($rs['msg'], '"', 'msg has no double quote');
        TestAssert::notContains($rs['msg'], "'", 'msg has no single quote');
    }
});

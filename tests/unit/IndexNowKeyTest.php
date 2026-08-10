<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php check_indexnow_key() make_indexnow_key() get_indexnow_key_file() get_indexnow_key_location() save_indexnow_key_file() resolve_indexnow_local_key_file() resolve_indexnow_key_location() indexnow_key_location_file_ready() is_indexnow_remote_key_location() indexnow_location_basename_ok() read_indexnow_key_from_local_location() write_indexnow_key_to_local_location()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

// 根目录部署
define('SITE_DIR', '');

require APP_PATH . '/common/function.php';

return TestAssert::runSuite(function () {
    echo "=== check_indexnow_key() 格式边界 ===\n";

    TestAssert::true(check_indexnow_key('abcd1234'), '8位合法');
    TestAssert::true(check_indexnow_key(str_repeat('a', 128)), '128位合法');
    TestAssert::true(check_indexnow_key('a-B-9-c-D-1'), '连字符合法');
    TestAssert::false(check_indexnow_key('abc1234'), '7位过短');
    TestAssert::false(check_indexnow_key(str_repeat('a', 129)), '129位过长');
    TestAssert::false(check_indexnow_key('abcd_1234'), '下划线非法');
    TestAssert::false(check_indexnow_key('abcd 1234'), '空格非法');
    TestAssert::false(check_indexnow_key('密钥密钥密钥密钥'), '中文非法');
    TestAssert::false(check_indexnow_key(''), '空串非法');
    TestAssert::false(check_indexnow_key(null), 'null非法');

    echo "=== make_indexnow_key() 生成结果自洽 ===\n";

    $key = make_indexnow_key();
    TestAssert::same(32, strlen($key), '生成32位密钥');
    TestAssert::true(check_indexnow_key($key), '生成的密钥通过校验');
    TestAssert::true($key !== make_indexnow_key(), '两次生成不重复');

    echo "=== get_indexnow_key_file() 落在部署根目录 ===\n";

    $file = get_indexnow_key_file('abcd1234');
    TestAssert::same(rtrim(ROOT_PATH, '/\\') . '/abcd1234.txt', $file, '密钥文件路径');
    TestAssert::notContains($file, '//', '路径无重复斜杠');

    echo "=== get_indexnow_key_location() 根目录部署推导 ===\n";

    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $_SERVER['SERVER_PORT'] = '80';
    unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_CLIENT_SCHEME']);

    TestAssert::same(
        'http://www.example.com/abcd1234.txt',
        get_indexnow_key_location('abcd1234'),
        '留空时按根目录推导'
    );

    $_SERVER['HTTPS'] = 'on';
    TestAssert::same(
        'https://www.example.com/abcd1234.txt',
        get_indexnow_key_location('abcd1234'),
        'HTTPS站点推导'
    );

    echo "=== save_indexnow_key_file() 写入与幂等 ===\n";

    $testKey = 'testindexnowkeyfile01';
    $testFile = get_indexnow_key_file($testKey);
    if (file_exists($testFile)) {
        @unlink($testFile);
    }

    TestAssert::false(save_indexnow_key_file('bad_key'), '非法密钥不写文件');
    TestAssert::false(file_exists(get_indexnow_key_file('bad_key')), '非法密钥无残留文件');

    TestAssert::true(save_indexnow_key_file($testKey), '首次写入成功');
    TestAssert::same($testKey, trim((string) file_get_contents($testFile)), '文件内容即密钥本身');
    TestAssert::true(save_indexnow_key_file($testKey), '重复写入成功');

    @unlink($testFile);
    TestAssert::false(file_exists($testFile), '测试文件已清理');

    echo "=== resolve / read / write 三类密钥地址 ===\n";

    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $_SERVER['SERVER_PORT'] = '80';
    unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_CLIENT_SCHEME']);

    $locKey = 'testindexnowkeyloc01';
    $locRel = 'static/testindexnowkeyloc01.txt';
    $locFile = rtrim(ROOT_PATH, '/\\') . '/' . $locRel;
    if (file_exists($locFile)) {
        @unlink($locFile);
    }
    @file_put_contents($locFile, $locKey);

    TestAssert::same($locFile, resolve_indexnow_local_key_file('/' . $locRel), '相对路径可解析');
    TestAssert::same($locFile, resolve_indexnow_local_key_file($locRel), '相对路径无前导斜杠可解析');
    TestAssert::same(
        $locFile,
        resolve_indexnow_local_key_file('http://www.example.com/' . $locRel),
        '本站绝对URL可解析'
    );
    TestAssert::same('', resolve_indexnow_local_key_file('https://cdn.example.com/' . $locRel), '远程非本站不可解析');
    TestAssert::same('', resolve_indexnow_local_key_file('../etc/passwd'), '路径穿越拒绝');

    TestAssert::false(is_indexnow_remote_key_location('/' . $locRel), '相对路径非远程');
    TestAssert::false(is_indexnow_remote_key_location('http://www.example.com/' . $locRel), '本站URL非远程');
    TestAssert::true(is_indexnow_remote_key_location('https://cdn.example.com/' . $locRel), 'CDN为远程');

    TestAssert::same($locKey, read_indexnow_key_from_local_location('/' . $locRel), '相对路径可读回填');
    TestAssert::same($locKey, read_indexnow_key_from_local_location('http://www.example.com/' . $locRel), '本站URL可读回填');
    TestAssert::same('', read_indexnow_key_from_local_location('https://cdn.example.com/' . $locRel), '远程不自动读取');

    TestAssert::true(indexnow_location_basename_ok($locKey, '/' . $locRel), 'basename 与密钥一致');
    TestAssert::false(indexnow_location_basename_ok($locKey, '/index.php'), 'index.php basename 非法');
    TestAssert::false(indexnow_location_basename_ok($locKey, '/static/otherkey.txt'), '其它密钥文件名非法');

    // 合法写入：目标文件名必须为 {key}.txt
    $newKey = 'testindexnowkeyloc02';
    $newRel = 'static/' . $newKey . '.txt';
    $newFile = rtrim(ROOT_PATH, '/\\') . '/' . $newRel;
    if (file_exists($newFile)) {
        @unlink($newFile);
    }
    TestAssert::true(write_indexnow_key_to_local_location($newKey, '/' . $newRel), '改密钥同步写相对路径文件');
    TestAssert::same($newKey, trim((string) file_get_contents($newFile)), '相对路径文件内容已更新');
    TestAssert::true(write_indexnow_key_to_local_location($newKey, 'http://www.example.com/' . $newRel), '本站URL同步写入（幂等）');
    TestAssert::same($newKey, trim((string) file_get_contents($newFile)), '本站URL对应文件内容保持密钥');
    TestAssert::false(write_indexnow_key_to_local_location($newKey, 'https://cdn.example.com/' . $newRel), '远程地址不代写');

    // 文件名与密钥不一致时禁止写入（防覆盖 index.php 等）
    $probeRel = 'static/indexnow_overwrite_probe.php';
    $probeFile = rtrim(ROOT_PATH, '/\\') . '/' . $probeRel;
    $probeOrig = "<?php // indexnow-probe-do-not-overwrite\n";
    @file_put_contents($probeFile, $probeOrig);
    TestAssert::false(write_indexnow_key_to_local_location($newKey, '/' . $probeRel), '非 txt 目标拒绝写入');
    TestAssert::same($probeOrig, (string) file_get_contents($probeFile), '探针文件内容未被改动');
    TestAssert::false(write_indexnow_key_to_local_location($newKey, '/index.php'), '拒绝写入 /index.php');
    $indexPhp = rtrim(ROOT_PATH, '/\\') . '/index.php';
    if (is_file($indexPhp)) {
        $indexBefore = (string) file_get_contents($indexPhp);
        TestAssert::false(write_indexnow_key_to_local_location($newKey, 'http://www.example.com/index.php'), '本站URL指向index.php亦拒绝');
        TestAssert::same($indexBefore, (string) file_get_contents($indexPhp), 'index.php 内容未被改动');
    }
    // 文件名是旧密钥、内容要写成新密钥：拒绝
    TestAssert::false(write_indexnow_key_to_local_location($newKey, '/' . $locRel), '旧文件名写新密钥被拒');
    TestAssert::same($locKey, trim((string) file_get_contents($locFile)), '旧密钥文件内容未被改动');
    // 内容是合法密钥但文件名不是 {key}.txt：不回填
    @file_put_contents($probeFile, $newKey);
    TestAssert::same('', read_indexnow_key_from_local_location('/' . $probeRel), '文件名非{key}.txt不回填');

    echo "=== resolve_indexnow_key_location() 跨域回退且不写文件 ===\n";

    $resolveKey = 'testindexnowresolve01';
    $resolveFile = get_indexnow_key_file($resolveKey);
    if (file_exists($resolveFile)) {
        @unlink($resolveFile);
    }
    $resolved = resolve_indexnow_key_location(
        $resolveKey,
        'https://cdn.example.com/keys/' . $resolveKey . '.txt',
        array('http://www.example.com/a.html')
    );
    TestAssert::same(
        get_indexnow_key_location($resolveKey, ''),
        $resolved,
        '跨域配置回退到本站根目录密钥地址'
    );
    TestAssert::false(file_exists($resolveFile), 'resolve 不产生写文件副作用');
    TestAssert::false(
        indexnow_key_location_file_ready($resolveKey, $resolved),
        '根目录密钥文件缺失时 file_ready 为 false'
    );
    TestAssert::true(save_indexnow_key_file($resolveKey), '补写根目录密钥文件');
    TestAssert::true(
        indexnow_key_location_file_ready($resolveKey, $resolved),
        '根目录密钥文件就绪后 file_ready 为 true'
    );
    @unlink($resolveFile);

    @unlink($probeFile);
    @unlink($newFile);
    @unlink($locFile);
    TestAssert::false(file_exists($locFile), '本地测试密钥文件已清理');
});

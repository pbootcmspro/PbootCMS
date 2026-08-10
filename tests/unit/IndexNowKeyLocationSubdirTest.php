<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php get_indexnow_key_location() resolve_indexnow_local_key_file()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

// 二级目录部署
define('SITE_DIR', '/cms');

require APP_PATH . '/common/function.php';

return TestAssert::runSuite(function () {
    echo "=== get_indexnow_key_location() 二级目录部署 ===\n";

    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $_SERVER['SERVER_PORT'] = '80';
    unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_CLIENT_SCHEME']);

    // 密钥文件随部署目录走，须带上子目录，否则引擎按根目录取文件必然404
    TestAssert::same(
        'http://www.example.com/cms/abcd1234.txt',
        get_indexnow_key_location('abcd1234'),
        '留空时带上部署子目录'
    );

    echo "=== get_indexnow_key_location() 显式配置优先 ===\n";

    TestAssert::same(
        'https://cdn.example.com/keys/abcd1234.txt',
        get_indexnow_key_location('abcd1234', 'https://cdn.example.com/keys/abcd1234.txt'),
        '已配置时直接返回配置值'
    );

    echo "=== resolve_indexnow_local_key_file() 二级目录剥 SITE_DIR ===\n";

    $rel = 'static/testindexnowsubdir01.txt';
    $file = rtrim(ROOT_PATH, '/\\') . '/' . $rel;
    TestAssert::same($file, resolve_indexnow_local_key_file('/' . $rel), '相对路径不带SITE_DIR');
    TestAssert::same(
        $file,
        resolve_indexnow_local_key_file('http://www.example.com/cms/' . $rel),
        '本站URL带SITE_DIR前缀可剥除'
    );
    TestAssert::same(
        $file,
        resolve_indexnow_local_key_file('/cms/' . $rel),
        '相对路径误带SITE_DIR亦可剥除'
    );
});

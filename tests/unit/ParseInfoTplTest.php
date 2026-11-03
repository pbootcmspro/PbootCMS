<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers parse_info_tpl() 错误页 PHP/数据库环境占位符与 SERVER_SOFTWARE 缺失兼容
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', '3.2.23');
defined('RELEASE_TIME') || define('RELEASE_TIME', '20260825');

require_once CORE_PATH . '/basic/Config.php';
require_once TEST_ROOT . 'support/ConfigStub.php';

return TestAssert::runSuite(function () {
    echo "=== parse_info_tpl replaces phpversion without SERVER_SOFTWARE warning ===\n";

    unset($_SERVER['SERVER_SOFTWARE']);
    db_env_reset();
    ConfigStub::set(array(
        'database' => array()
    ));

    $out = parse_info_tpl(CORE_PATH . '/template/error.html', 'probe error message');

    TestAssert::contains($out, 'PHP版本：' . PHP_VERSION, 'output contains replaced PHP version');
    TestAssert::contains($out, '程序版本：3.2.23-20260825', 'output contains app version');
    TestAssert::notContains($out, '{phpversion}', 'phpversion placeholder fully replaced');
    TestAssert::notContains($out, '{appversion}', 'appversion placeholder fully replaced');
    TestAssert::contains($out, 'probe error message', 'error message preserved');
    TestAssert::notContains($out, '{dbtype}', 'dbtype placeholder replaced');
    TestAssert::notContains($out, '{dbdriver}', 'dbdriver placeholder replaced');
    TestAssert::notContains($out, '{dbversion}', 'dbversion placeholder replaced');
    TestAssert::contains($out, '数据库：未识别', 'no config → 未识别 type');
    TestAssert::contains($out, '驱动：未识别', 'no config → 未识别 driver');
    TestAssert::contains($out, '版本：未获取', 'no version → 未获取');

    echo "=== parse_info_tpl db env with known driver and escaped version ===\n";

    db_env_reset();
    ConfigStub::set(array(
        'database' => array(
            'type' => 'mysqli'
        )
    ));
    $store = &db_env_store();
    $store['master'] = '10.11.6-MariaDB<script>';
    $before = db_env_state();
    $out2 = parse_info_tpl(CORE_PATH . '/template/error.html', 'db probe');
    $after = db_env_state();

    TestAssert::contains($out2, '数据库：MySQL', 'mysqli maps to MySQL');
    TestAssert::contains($out2, '驱动：mysqli', 'shows configured driver');
    TestAssert::contains($out2, '版本：10.11.6-MariaDB&lt;script&gt;', 'version escaped');
    TestAssert::notContains($out2, '<script>', 'raw script not in HTML');
    TestAssert::same($before['noted'], $after['noted'], 'render does not note');
    TestAssert::same($before['reads'], $after['reads'], 'render does not read connection');

    echo "=== parse_info_tpl unknown config not echoed ===\n";

    db_env_reset();
    ConfigStub::set(array(
        'database' => array(
            'type' => '<b>evil</b>'
        )
    ));
    $out3 = parse_info_tpl(CORE_PATH . '/template/error.html', 'unknown cfg');
    TestAssert::contains($out3, '数据库：未识别', 'unknown type → 未识别');
    TestAssert::contains($out3, '驱动：未识别', 'unknown driver → 未识别');
    TestAssert::notContains($out3, '<b>evil</b>', 'raw config not echoed');
    TestAssert::notContains($out3, '&lt;b&gt;evil&lt;/b&gt;', 'escaped evil config not echoed either');

    echo "=== parse_info_tpl old template without db placeholders ===\n";

    db_env_reset();
    ConfigStub::set(array(
        'database' => array(
            'type' => 'sqlite'
        )
    ));
    $store = &db_env_store();
    $store['master'] = '3.45.1';
    $tmp = sys_get_temp_dir() . '/pboot_parse_info_' . getmypid() . '.html';
    file_put_contents($tmp, '<p>{info}</p><p>程序版本：{appversion}　PHP版本：{phpversion}</p>');
    $before2 = db_env_state();
    $out4 = parse_info_tpl($tmp, 'legacy tpl');
    $after2 = db_env_state();
    @unlink($tmp);

    TestAssert::contains($out4, 'legacy tpl', 'legacy message kept');
    TestAssert::notContains($out4, '数据库：', 'legacy template has no db line');
    TestAssert::same($before2['noted'], $after2['noted'], 'legacy render does not note');
    TestAssert::same($before2['reads'], $after2['reads'], 'legacy render does not read');
    TestAssert::same('3.45.1', db_env_state()['master'], 'legacy render leaves metadata intact');

    echo "=== parse_info_tpl version truncate and pin/block ===\n";

    db_env_reset();
    ConfigStub::set(array(
        'database' => array(
            'type' => 'pdo_sqlite'
        )
    ));
    $long = str_repeat('A', 80);
    TestAssert::same(64, strlen(db_env_sanitize_version($long)), 'sanitize truncates to 64');
    $store = &db_env_store();
    $store['master'] = 'master-v';
    $store['slave'] = 'slave-v';
    db_env_pin('slave');
    $fmt = db_env_format();
    TestAssert::same('SQLite', html_entity_decode($fmt['type'], ENT_QUOTES, 'UTF-8'), 'pdo_sqlite type');
    TestAssert::same('pdo_sqlite', $fmt['driver'], 'pdo_sqlite driver');
    TestAssert::same('slave-v', $fmt['version'], 'pin uses slave version');
    db_env_block();
    $fmt2 = db_env_format();
    TestAssert::same('未获取', $fmt2['version'], 'block forces 未获取');
    TestAssert::same('master-v', db_env_state()['master'], 'block keeps stored master');
});

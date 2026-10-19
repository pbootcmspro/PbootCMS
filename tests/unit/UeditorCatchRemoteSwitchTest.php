<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers ueditor_catch_remote_enabled()
 *
 * 配置缓存缺失时必须回源 ay_config：只有明确为 0 才禁用。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

/**
 * @param string|null $cacheValue null 表示缓存中没有这一项
 * @param string|null $dbValue null 表示 ay_config 中没有这一行
 */
function catch_remote_prepare($path, $cacheValue, $dbValue)
{
    db_reset_sqlite_singleton();
    $sqlite = new SQLite3($path);
    $sqlite->exec("DELETE FROM ay_config WHERE name='ueditor_catch_remote'");
    if ($dbValue !== null) {
        $stmt = $sqlite->prepare('INSERT INTO ay_config (name, value) VALUES (?, ?)');
        $stmt->bindValue(1, 'ueditor_catch_remote', SQLITE3_TEXT);
        $stmt->bindValue(2, $dbValue, SQLITE3_TEXT);
        $stmt->execute();
    }
    $sqlite->close();

    $prop = db_config_reflection();
    $configs = $prop->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    if ($cacheValue === null) {
        unset($configs['ueditor_catch_remote']);
    } else {
        $configs['ueditor_catch_remote'] = $cacheValue;
    }
    $prop->setValue(null, $configs);
}

return TestAssert::runSuite(function () {
    echo "=== ueditor_catch_remote_enabled reads ay_config when cache misses ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "sqlite3 extension missing, skip\n";
        return;
    }

    $path = db_boot_sqlite('ueditor_catch_remote');
    TestAssert::true(is_string($path) && $path !== '', 'sqlite boot');
    if (! is_string($path) || $path === '') {
        return;
    }

    $sqlite = new SQLite3($path);
    $sqlite->exec("CREATE TABLE ay_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        value TEXT NOT NULL DEFAULT ''
    )");
    $sqlite->close();

    try {
        catch_remote_prepare($path, null, null);
        TestAssert::true(ueditor_catch_remote_enabled(), 'missing cache and missing row enables');

        catch_remote_prepare($path, null, '0');
        TestAssert::false(ueditor_catch_remote_enabled(), 'missing cache with db 0 disables');

        catch_remote_prepare($path, null, '1');
        TestAssert::true(ueditor_catch_remote_enabled(), 'missing cache with db 1 enables');

        catch_remote_prepare($path, null, '');
        TestAssert::true(ueditor_catch_remote_enabled(), 'missing cache with empty db value enables');

        catch_remote_prepare($path, '0', '1');
        TestAssert::false(ueditor_catch_remote_enabled(), 'cache 0 disables without reading the enabled db row');
    } finally {
        db_reset_sqlite_singleton();
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

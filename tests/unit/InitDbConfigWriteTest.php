<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\admin\controller\IndexController::buildDbConfig
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    if (! class_exists('app\\admin\\controller\\IndexController', false)) {
        require_once APP_PATH . '/admin/controller/IndexController.php';
    }
    $ref = new ReflectionClass('app\\admin\\controller\\IndexController');
    $obj = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('buildDbConfig');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }

    $passwd = 'a\'b"c\\d$e';
    $cfg = array(
        'keep' => 'yes',
        'database' => array(
            'type' => 'sqlite',
            'host' => '127.0.0.1',
            'user' => 'pboot',
            'passwd' => 'old',
            'port' => '3306',
            'dbname' => '/data/pbootcms.db',
            'prefix' => 'ay_',
            'charset' => 'utf8'
        )
    );
    $php = $method->invoke($obj, $cfg, array(
        'host' => 'db.example.com',
        'user' => 'root',
        'passwd' => $passwd,
        'port' => 3306,
        'dbname' => 'pboot_init'
    ));

    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_db_cfg_' . getmypid() . '.php';
    TestAssert::true(file_put_contents($file, $php) !== false, 'config php written');
    $loaded = require $file;
    @unlink($file);

    echo "=== var_export 原样还原 ===\n";
    TestAssert::same('mysqli', $loaded['database']['type'], 'type is mysqli');
    TestAssert::same('db.example.com', $loaded['database']['host'], 'host replaced');
    TestAssert::same('root', $loaded['database']['user'], 'user replaced');
    TestAssert::same($passwd, $loaded['database']['passwd'], 'password round-trips');
    TestAssert::same('3306', $loaded['database']['port'], 'port stored as string');
    TestAssert::same('pboot_init', $loaded['database']['dbname'], 'dbname replaced');
    TestAssert::same('ay_', $loaded['database']['prefix'], 'prefix kept');
    TestAssert::same('utf8', $loaded['database']['charset'], 'charset kept');
    TestAssert::same('yes', $loaded['keep'], 'unrelated key kept');
});

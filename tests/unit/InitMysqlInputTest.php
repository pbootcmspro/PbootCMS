<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\admin\controller\IndexController::checkMysqlInput
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function init_input_call(array $db): string
{
    if (! class_exists('app\\admin\\controller\\IndexController', false)) {
        require_once APP_PATH . '/admin/controller/IndexController.php';
    }
    $ref = new ReflectionClass('app\\admin\\controller\\IndexController');
    $obj = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('checkMysqlInput');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    $result = $method->invoke($obj, $db);
    return (string) $result;
}

function init_field_call(string $username, string $realname): string
{
    if (! class_exists('app\\admin\\controller\\IndexController', false)) {
        require_once APP_PATH . '/admin/controller/IndexController.php';
    }
    $ref = new ReflectionClass('app\\admin\\controller\\IndexController');
    $obj = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('adminFieldError');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return (string) $method->invoke($obj, $username, $realname);
}

function init_input_db(array $over): array
{
    return array_merge(array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'passwd' => '',
        'dbname' => 'pboot_init'
    ), $over);
}

return TestAssert::runSuite(function () {
    echo "=== 合法输入 ===\n";
    TestAssert::same('', init_input_call(init_input_db(array())), 'defaults pass');
    TestAssert::same('', init_input_call(init_input_db(array('host' => 'localhost'))), 'localhost passes');
    TestAssert::same('', init_input_call(init_input_db(array('host' => '::1'))), 'ipv6 host passes');
    TestAssert::same('', init_input_call(init_input_db(array('host' => str_repeat('a', 255)))), 'host length 255 passes');
    TestAssert::same('', init_input_call(init_input_db(array('port' => 1))), 'port 1 passes');
    TestAssert::same('', init_input_call(init_input_db(array('port' => 65535))), 'port 65535 passes');
    TestAssert::same('', init_input_call(init_input_db(array('port' => '3306'))), 'numeric port string passes');
    TestAssert::same('', init_input_call(init_input_db(array('user' => 'a@b_c-1'))), 'user charset passes');
    TestAssert::same('', init_input_call(init_input_db(array('user' => str_repeat('a', 32)))), 'user length 32 passes');
    TestAssert::same('', init_input_call(init_input_db(array('dbname' => 'pboot_cms-1'))), 'dbname charset passes');
    TestAssert::same('', init_input_call(init_input_db(array('dbname' => str_repeat('a', 64)))), 'dbname length 64 passes');
    TestAssert::same('', init_input_call(init_input_db(array('passwd' => 'a\'b"c\\d$e'))), 'password punctuation passes');
    TestAssert::same('', init_input_call(init_input_db(array('passwd' => str_repeat('a', 128)))), 'password length 128 passes');

    echo "=== 边界拒绝 ===\n";
    TestAssert::true(init_input_call(init_input_db(array('host' => ''))) !== '', 'empty host rejected');
    TestAssert::true(init_input_call(init_input_db(array('host' => 'bad host'))) !== '', 'host space rejected');
    TestAssert::true(init_input_call(init_input_db(array('host' => 'db_host'))) !== '', 'host underscore rejected');
    TestAssert::true(init_input_call(init_input_db(array('host' => str_repeat('a', 256)))) !== '', 'host length 256 rejected');
    TestAssert::true(init_input_call(init_input_db(array('port' => 0))) !== '', 'port 0 rejected');
    TestAssert::true(init_input_call(init_input_db(array('port' => 65536))) !== '', 'port 65536 rejected');
    TestAssert::true(init_input_call(init_input_db(array('port' => '3306abc'))) !== '', 'port suffix rejected');
    TestAssert::true(init_input_call(init_input_db(array('user' => ''))) !== '', 'empty user rejected');
    TestAssert::true(init_input_call(init_input_db(array('user' => 'bad user'))) !== '', 'user space rejected');
    TestAssert::true(init_input_call(init_input_db(array('user' => str_repeat('a', 33)))) !== '', 'user length 33 rejected');
    TestAssert::true(init_input_call(init_input_db(array('dbname' => ''))) !== '', 'empty dbname rejected');
    TestAssert::true(init_input_call(init_input_db(array('dbname' => 'a.b'))) !== '', 'dbname dot rejected');
    TestAssert::true(init_input_call(init_input_db(array('dbname' => str_repeat('a', 65)))) !== '', 'dbname length 65 rejected');
    TestAssert::true(init_input_call(init_input_db(array('passwd' => str_repeat('a', 129)))) !== '', 'password length 129 rejected');
    TestAssert::true(init_input_call(init_input_db(array('passwd' => "a\nb"))) !== '', 'password newline rejected');
    TestAssert::true(init_input_call(init_input_db(array('passwd' => "a\x00b"))) !== '', 'password nul rejected');

    echo "=== 管理员字段按入库长度限制在 30 ===\n";
    TestAssert::same('', init_field_call(str_repeat('a', 30), ''), 'username length 30 passes');
    TestAssert::same('', init_field_call('admin', str_repeat('名', 30)), 'realname length 30 passes');
    TestAssert::true(init_field_call(str_repeat('a', 31), 'admin') !== '', 'username length 31 rejected');
    TestAssert::true(init_field_call('admin', str_repeat('a', 27) . '&lt;') !== '', 'escaped realname over 30 rejected');
    TestAssert::same('', init_field_call('admin', str_repeat('a', 26) . '&lt;'), 'escaped realname at 30 passes');
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Model.php whereCompare()
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';

/**
 * @param \core\basic\Model $model
 * @return array
 */
function where_compare_binds($model)
{
    $prop = (new ReflectionClass('core\\basic\\Model'))->getProperty('bindParams');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return $prop->getValue($model);
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function where_compare_run_probe($mode)
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'model_where_compare_probe.php';
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $mode)));

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (! is_resource($proc) && ! is_object($proc))) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exit_code' => (int) proc_close($proc)
    );
}

class WhereCompareFakeDriver
{
    public $lastSql = '';

    public $lastParams = array();

    public function amd($sql, $params = array())
    {
        $this->lastSql = $sql;
        $this->lastParams = $params;
        return true;
    }
}

return TestAssert::runSuite(function () {
    echo "=== whereCompare SQL and bind order ===\n";

    $model = new \core\basic\Model();
    $sql = $model->table('ay_member')
        ->where(array(
            'username' => 'ada',
            'useremail' => 'ada',
            'usermobile' => 'ada'
        ), 'OR')
        ->whereCompare('id', '<>', 9)
        ->select(false);
    $binds = where_compare_binds($model);
    TestAssert::contains($sql, '`username`=?', 'OR username placeholder');
    TestAssert::contains($sql, '`useremail`=?', 'OR useremail placeholder');
    TestAssert::contains($sql, '`usermobile`=?', 'OR usermobile placeholder');
    TestAssert::contains($sql, 'AND(`id`<>?)', 'exclude id compare group');
    TestAssert::same(array('ada', 'ada', 'ada', 9), $binds, 'three account then exclude id');

    $eq = new \core\basic\Model();
    $eqSql = $eq->table('ay_member')->whereCompare('a.id', '=', 3)->select(false);
    TestAssert::contains($eqSql, 'WHERE(`a`.`id`=?)', 'aliased equality');
    TestAssert::same(array(3), where_compare_binds($eq), 'equality bind');

    echo "=== invalid args reject without leftover binds ===\n";

    foreach (array(
        'bad_operator' => '不支持的比较运算符',
        'bad_field_func' => '比较字段不合法',
        'bad_field_semi' => '比较字段不合法',
        'bad_value_array' => '比较值类型不合法',
        'bad_connect' => '条件连接符不合法'
    ) as $mode => $msg) {
        $r = where_compare_run_probe($mode);
        TestAssert::false(strpos($r['stdout'], 'UNEXPECTED_OK') !== false, $mode . ' must not succeed');
        TestAssert::true(strpos($r['stdout'], $msg) !== false, $mode . ' message');
    }

    echo "=== array UPDATE SET binds before WHERE ===\n";

    $upd = new \core\basic\Model();
    $driver = new WhereCompareFakeDriver();
    $dbProp = (new ReflectionClass('core\\basic\\Model'))->getProperty('dbDriver');
    if (PHP_VERSION_ID < 80100) {
        $dbProp->setAccessible(true);
    }
    $dbProp->setValue($upd, $driver);
    $upd->table('ay_member')->where(array(
        'id' => 7
    ))->update(array(
        'status' => 0,
        'nickname' => 'n'
    ));
    TestAssert::same(array(0, 'n', 7), $driver->lastParams, 'SET then WHERE binds');
    TestAssert::contains($driver->lastSql, '`status`=?', 'status set placeholder');
    TestAssert::contains($driver->lastSql, '`id`=?', 'id where placeholder');
});

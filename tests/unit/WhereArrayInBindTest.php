<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Model.php where()
 *
 * 数组值为 IN 绑定；标量等值、模糊匹配与整数键原句保持不变。
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
function where_in_binds($model)
{
    $prop = (new ReflectionClass('core\\basic\\Model'))->getProperty('bindParams');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return $prop->getValue($model);
}

/**
 * @param mixed $where
 * @param string $inConnect
 * @param bool $fuzzy
 * @return array{0:string,1:array}
 */
function where_in_sql($where, $inConnect = 'AND', $fuzzy = false)
{
    $model = new \core\basic\Model();
    $sql = $model->table('ay_content a')->where($where, $inConnect, 'AND', $fuzzy)->select(false);
    return array($sql, where_in_binds($model));
}

return TestAssert::runSuite(function () {
    echo "=== array value: multi / single / empty IN ===\n";

    list($multiSql, $multiBind) = where_in_sql(array(
        'a.scode' => array('1', '2', '3')
    ));
    TestAssert::contains($multiSql, '`a`.`scode` IN (?,?,?)', 'multi: IN placeholders');
    TestAssert::same(array('1', '2', '3'), $multiBind, 'multi: bind order follows array');

    list($singleSql, $singleBind) = where_in_sql(array(
        'gid' => array('5')
    ));
    TestAssert::contains($singleSql, '`gid` IN (?)', 'single: still IN not equality');
    TestAssert::same(array('5'), $singleBind, 'single: one bind');

    list($emptySql, $emptyBind) = where_in_sql(array(
        'a.scode' => array()
    ));
    TestAssert::contains($emptySql, '1=0', 'empty: always false');
    TestAssert::notContains($emptySql, 'IN (', 'empty: no IN list');
    TestAssert::same(array(), $emptyBind, 'empty: no bind');

    echo "=== IN OR equality keeps placeholder order ===\n";

    list($orSql, $orBind) = where_in_sql(array(
        'a.scode' => array('1', '2'),
        'a.subscode' => '1,2'
    ), 'OR');
    TestAssert::contains($orSql, '`a`.`scode` IN (?,?)', 'tree: scode IN');
    TestAssert::contains($orSql, '`a`.`subscode`=?', 'tree: subscode equality');
    TestAssert::contains($orSql, 'OR', 'tree: OR connector');
    TestAssert::same(array('1', '2', '1,2'), $orBind, 'tree: IN values then subscode');

    $model = new \core\basic\Model();
    $mixedSql = $model->table('ay_content a')
        ->where(array(
            'a.scode' => array(),
            'a.subscode' => '9'
        ), 'OR')
        ->where(array(
            'a.status' => '1'
        ))
        ->select(false);
    $mixedBind = where_in_binds($model);
    TestAssert::contains($mixedSql, '1=0', 'empty arm stays false');
    TestAssert::same(array('9', '1'), $mixedBind, 'empty IN does not shift later binds');

    echo "=== scalar paths unchanged ===\n";

    list($eqSql, $eqBind) = where_in_sql(array(
        'a.status' => '1'
    ));
    TestAssert::contains($eqSql, '`a`.`status`=?', 'scalar: equality placeholder');
    TestAssert::notContains($eqSql, 'IN (', 'scalar: not IN');
    TestAssert::same(array('1'), $eqBind, 'scalar: one bind');

    list($accountSql, $accountBind) = where_in_sql(array(
        'username' => 'ada',
        'useremail' => 'ada',
        'usermobile' => 'ada'
    ), 'OR');
    TestAssert::contains($accountSql, '`username`=?', 'account OR: username');
    TestAssert::contains($accountSql, '`useremail`=?', 'account OR: email');
    TestAssert::contains($accountSql, '`usermobile`=?', 'account OR: mobile');
    TestAssert::same(array('ada', 'ada', 'ada'), $accountBind, 'account OR: three scalar binds');

    list($fuzzySql, $fuzzyBind) = where_in_sql(array(
        'a.title' => 'hi'
    ), 'AND', true);
    TestAssert::contains($fuzzySql, '`a`.`title` like ?', 'fuzzy scalar: like placeholder');
    TestAssert::same(array('%hi%'), $fuzzyBind, 'fuzzy scalar: wrapped bind');

    list($rawSql, $rawBind) = where_in_sql(array(
        'a.status=1'
    ));
    TestAssert::contains($rawSql, 'a.status=1', 'int key: raw clause kept');
    TestAssert::same(array(), $rawBind, 'int key: no bind');
});

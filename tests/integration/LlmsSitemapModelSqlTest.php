<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers app\home\model\SitemapModel::getLlmsSorts() getLlmsSortContent()
 *
 * 走真实 ORM 链式调用生成 SQL（以假驱动拦截执行），校验字段、绑定、
 * 排序与条数上限确实落到语句里，且不出现拼接的用户输入。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require APP_PATH . '/common/function.php';
require APP_PATH . '/home/model/SitemapModel.php';

/** 假数据库驱动：只记录语句与绑定参数，不连接数据库 */
final class LlmsFakeDbDriver
{
    public $sql = '';

    public $bind = array();

    public function all($sql, $type = null, $bind = array())
    {
        $this->sql = $sql;
        $this->bind = is_array($bind) ? $bind : array();
        return array();
    }
}

return TestAssert::runSuite(function () {
    ConfigStub::reset();
    ConfigStub::set(array(
        'database' => array(
            'type' => 'mysqli',
            'prefix' => 'ay_'
        ),
        'lgs' => array(
            'cn' => array(
                'acode' => 'cn'
            )
        )
    ));
    $_COOKIE['lg'] = 'cn';

    $driverProp = (new ReflectionClass('core\\basic\\Model'))->getProperty('dbDriver');
    if (PHP_VERSION_ID < 80100) {
        $driverProp->setAccessible(true);
    }

    $run = function ($method, array $args) use ($driverProp) {
        $model = new \app\home\model\SitemapModel();
        $driver = new LlmsFakeDbDriver();
        $driverProp->setValue($model, $driver);
        call_user_func_array(array($model, $method), $args);
        return $driver;
    };

    echo "=== getLlmsSorts() 语句结构 ===\n";

    $sorts = $run('getLlmsSorts', array());
    TestAssert::contains($sorts->sql, 'FROM ay_content_sort a', '主表为栏目表');
    TestAssert::contains($sorts->sql, 'LEFT JOIN ay_model b ON a.mcode=b.mcode', '关联模型表取栏目类型');
    foreach (array('a.scode', 'a.name', 'a.outlink', 'a.description', 'b.type', 'b.urlname') as $field) {
        TestAssert::contains($sorts->sql, $field, '选出字段 ' . $field);
    }
    TestAssert::contains($sorts->sql, '`a`.`status`=?', '启用状态走占位符');
    TestAssert::contains($sorts->sql, '`a`.`acode`=?', '语言过滤走占位符');
    TestAssert::same(array(1, 'cn'), $sorts->bind, '绑定值为状态与当前语言');
    TestAssert::contains($sorts->sql, 'ORDER BY', '按栏目树排序');

    echo "=== getLlmsSortContent() 语句结构 ===\n";

    $contents = $run('getLlmsSortContent', array('2', 5));
    TestAssert::contains($contents->sql, 'FROM ay_content a', '主表为内容表');
    foreach (array('a.title', 'a.outlink', 'a.description', 'b.scode') as $field) {
        TestAssert::contains($contents->sql, $field, '选出字段 ' . $field);
    }
    TestAssert::contains($contents->sql, '`a`.`scode`=?', '栏目条件走占位符');
    TestAssert::contains($contents->sql, '`c`.`type`=?', '仅取列表模型内容');
    TestAssert::notContains($contents->sql, "a.scode='2'", '栏目编码未被拼接进语句');
    TestAssert::same(array('2', 1, 2), $contents->bind, '绑定值为栏目、审核状态与模型类型');
    TestAssert::contains($contents->sql, 'LIMIT 5', '条数上限落到语句');
    TestAssert::contains($contents->sql, 'a.date<', '未到发布时间的内容被排除');
    TestAssert::contains($contents->sql, 'ORDER BY', '按置顶与发布时间排序');
    TestAssert::contains($contents->sql, '`a`.`istop` DESC', '置顶优先');
    TestAssert::contains($contents->sql, '`a`.`date` DESC', '发布时间倒序');

    echo "=== 条数上限非法时不触库 ===\n";

    $model = new \app\home\model\SitemapModel();
    $driver = new LlmsFakeDbDriver();
    $driverProp->setValue($model, $driver);
    TestAssert::same(array(), $model->getLlmsSortContent('2', 0), '条数为0直接返回空数组');
    TestAssert::same('', $driver->sql, '条数为0不产生查询');

    echo "=== 注入型栏目编码只会成为绑定值 ===\n";

    $evil = $run('getLlmsSortContent', array("2' OR '1'='1", 3));
    TestAssert::notContains($evil->sql, "OR '1'='1", '注入片段不进入语句');
    TestAssert::same("2' OR '1'='1", $evil->bind[0], '注入片段仅作为绑定值');

    ConfigStub::reset();
});

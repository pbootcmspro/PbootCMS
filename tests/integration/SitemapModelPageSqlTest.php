<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers app\home\model\SitemapModel::countOutputContent
 * @covers app\home\model\SitemapModel::getContentPage
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
final class SitemapFakeDbDriver
{
    public $sql = '';

    public $bind = array();

    public function all($sql, $type = null, $bind = array())
    {
        $this->sql = $sql;
        $this->bind = is_array($bind) ? $bind : array();
        return array();
    }

    public function one($sql, $type = null, $bind = array())
    {
        $this->sql = $sql;
        $this->bind = is_array($bind) ? $bind : array();
        return array(0);
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
        $driver = new SitemapFakeDbDriver();
        $driverProp->setValue($model, $driver);
        call_user_func_array(array($model, $method), $args);
        return $driver;
    };

    $now = '2020-01-02 03:04:05';

    echo "=== countOutputContent() 语句结构 ===\n";

    $count = $run('countOutputContent', array($now));
    TestAssert::contains($count->sql, 'COUNT(*)', '统计使用 COUNT');
    TestAssert::contains($count->sql, 'FROM ay_content a', '主表为内容表');
    TestAssert::contains($count->sql, 'LEFT JOIN ay_content_sort b ON a.scode=b.scode', '关联栏目');
    TestAssert::contains($count->sql, 'LEFT JOIN ay_model c ON b.mcode=c.mcode', '关联模型');
    TestAssert::contains($count->sql, '`a`.`status`=?', '内容启用走占位符');
    TestAssert::contains($count->sql, '`b`.`status`=?', '栏目启用走占位符');
    TestAssert::contains($count->sql, '`b`.`acode`=?', '语言过滤走占位符');
    TestAssert::contains($count->sql, '`c`.`type`=?', '仅列表模型');
    TestAssert::contains($count->sql, "a.outlink=''", '排除外链内容');
    TestAssert::contains($count->sql, "b.outlink=''", '排除外链栏目下的内容');
    TestAssert::contains($count->sql, "a.date<'" . $now . "'", '未到发布时间的内容被排除');
    TestAssert::notContains($count->sql, 'ORDER BY', '计数不排序');
    TestAssert::notContains($count->sql, 'LIMIT', '计数不分页');
    TestAssert::same(array(1, 1, 'cn', 2), $count->bind, '绑定值为内容状态、栏目状态、语言与模型类型');

    echo "=== getContentPage() 按 id 升序分页 ===\n";

    $page = $run('getContentPage', array($now, 149974, 50000));
    TestAssert::contains($page->sql, 'ORDER BY `a`.`id` ASC', '内容按 id 升序');
    TestAssert::contains($page->sql, 'LIMIT 149974,50000', 'MySQL 深分页偏移');
    TestAssert::contains($page->sql, 'a.filename', '选出内容地址字段');
    TestAssert::contains($page->sql, 'b.filename as sortfilename', '选出栏目文件名');
    TestAssert::notContains($page->sql, 'page', '页码不进入语句');
    TestAssert::same(array(1, 1, 'cn', 2), $page->bind, '分页查询绑定值与计数一致');

    echo "=== SQLite 分页语法 ===\n";

    ConfigStub::set(array(
        'database' => array(
            'type' => 'sqlite',
            'prefix' => 'ay_'
        )
    ));
    $sqlite = $run('getContentPage', array($now, 10, 20));
    TestAssert::contains($sqlite->sql, 'LIMIT 20 OFFSET 10', 'SQLite 使用 OFFSET');

    echo "=== 非法页窗口与非法时间不把外部输入拼进语句 ===\n";

    $model = new \app\home\model\SitemapModel();
    $driver = new SitemapFakeDbDriver();
    $driverProp->setValue($model, $driver);
    TestAssert::same(array(), $model->getContentPage($now, 0, 0), '条数为 0 返回空数组');
    TestAssert::same('', $driver->sql, '条数为 0 不产生查询');
    TestAssert::same(array(), $model->getContentPage($now, -1, 10), '负偏移返回空数组');
    TestAssert::same('', $driver->sql, '负偏移不产生查询');

    $evil = $run('countOutputContent', array("2020-01-02 03:04:05' OR '1'='1"));
    TestAssert::notContains($evil->sql, "OR '1'='1", '注入片段不进入语句');
    TestAssert::contains($evil->sql, 'a.date<', '非法时间改用当前时间比较');

    ConfigStub::reset();
});

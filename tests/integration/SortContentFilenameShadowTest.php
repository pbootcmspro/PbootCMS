<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/home/model/ParserModel.php getSort()
 * @covers apps/home/model/ParserModel.php getContent()
 *
 * 栏目与内容同名时的路由遮蔽：IndexController 先试 getSort($path)，
 * 命中即走列表页，不再试 getContent()，该内容永久访问不到。
 *
 * 这是后台交叉查重（checkContentFilename / checkSortFilename）的依据：
 * 本测试锁住遮蔽这一事实，保证查重被移除时有测试报警。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/home/model/ParserModel.php';

use app\home\model\ParserModel;

if (! function_exists('get_lg')) {
    function get_lg()
    {
        return 'cn';
    }
}

/**
 * 建库：栏目 about 与内容 about 同名并存（模拟交叉查重缺失时的存量数据）
 *
 * @return string|null
 */
function shadow_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_shadow_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $dir = dirname($sqlitePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_model (
        id INTEGER PRIMARY KEY,
        mcode TEXT NOT NULL DEFAULT \'\',
        type INTEGER NOT NULL DEFAULT 2,
        urlname TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_content_sort (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        mcode TEXT NOT NULL DEFAULT \'\',
        pcode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        outlink TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        pic TEXT NOT NULL DEFAULT \'\',
        gid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        subscode TEXT NOT NULL DEFAULT \'\',
        title TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        date TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        gid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE TABLE ay_content_ext (
        extid INTEGER PRIMARY KEY,
        contentid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE TABLE ay_member_group (
        id INTEGER PRIMARY KEY,
        gcode TEXT NOT NULL DEFAULT \'\'
    )');

    $sqlite->exec("INSERT INTO ay_model (id, mcode, type, urlname, name) VALUES (1, '1', 2, 'news', 'News')");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (1, 'cn', '1', '', 'c1', '关于我们', 'about', 1)");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (2, 'cn', '1', '', 'c2', '新闻', 'news-list', 1)");
    // 与栏目 about 同名的内容：路由永远到不了它
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (10, 'cn', 'c2', '被遮蔽的文章', 'about', 1, '2000-01-01 00:00:00')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (11, 'cn', 'c2', '正常文章', 'normal', 1, '2000-01-01 00:00:00')");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

return TestAssert::runSuite(function () {
    $sqlitePath = shadow_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }
    $model = new ParserModel();

    echo "=== 同名时栏目遮蔽内容 ===\n";
    // IndexController::_empty 的匹配顺序：先 getSort($path)，命中就走列表页
    $sort = $model->getSort('about');
    TestAssert::true(is_object($sort), 'about 命中栏目');
    TestAssert::same('c1', $sort->scode, 'about 解析为栏目 c1');

    $data = $model->getContent('about');
    TestAssert::true(is_object($data), 'about 本身也能匹配到内容');
    TestAssert::same(10, (int) $data->id, '被遮蔽的内容确实存在');
    // 两者都能单独命中，但路由先试栏目，所以 id=10 这篇永久访问不到。
    // 后台交叉查重就是为了阻止这种数据被创建出来。

    echo "=== 未撞名时各自可达 ===\n";
    TestAssert::false((bool) $model->getSort('normal'), 'normal 不是栏目');
    $data = $model->getContent('normal');
    TestAssert::true(is_object($data), 'normal 命中内容');
    TestAssert::same(11, (int) $data->id, 'normal 解析为内容 11');

    TestAssert::false((bool) $model->getContent('news-list'), 'news-list 不是内容');
    $sort = $model->getSort('news-list');
    TestAssert::true(is_object($sort), 'news-list 命中栏目');
    TestAssert::same('c2', $sort->scode, 'news-list 解析为栏目 c2');

    @unlink($sqlitePath);
});

<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/home/model/ParserModel.php getSort()
 * @covers apps/home/model/ParserModel.php getContent()
 *
 * 多语言同名 URL 回归：不同语言允许栏目/内容使用相同 filename，
 * 路由须优先命中当前语言，当前语言没有时才跨语言兜底。
 *
 * 原实现只按 scode OR filename 匹配且不限语言，find() 取第一行，
 * en 站访问 /about 会命中 id 更小的 cn 行，随后 switchLg() 把语言切回中文。
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

$GLOBALS['mlang_current_lg'] = 'cn';

if (! function_exists('get_lg')) {
    function get_lg()
    {
        return $GLOBALS['mlang_current_lg'];
    }
}

/**
 * 建库：cn/en/ru 三个语言各有一个 filename 为 about 的栏目与内容，
 * ru 额外有一个只在 ru 下存在的 filename，用于验证跨语言兜底
 *
 * @return string|null
 */
function mlang_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_mlang_dup_' . getmypid() . '.db';
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
    // 三个语言各有一个 filename=about 的栏目，id 递增，cn 最小
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (1, 'cn', '1', '', 'c1', '关于我们', 'about', 1)");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (2, 'en', '1', '', 'c2', 'About Us', 'about', 1)");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (3, 'ru', '1', '', 'c3', 'O nas', 'about', 1)");
    // 只在 ru 下存在的栏目，用于验证跨语言兜底
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, status) VALUES (4, 'ru', '1', '', 'c4', 'Dostavka', 'dostavka', 1)");
    // 三个语言各有一个 filename=hello 的内容
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (10, 'cn', 'c1', '你好', 'hello', 1, '2000-01-01 00:00:00')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (11, 'en', 'c2', 'Hello', 'hello', 1, '2000-01-01 00:00:00')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (12, 'ru', 'c3', 'Privet', 'hello', 1, '2000-01-01 00:00:00')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title, filename, status, date) VALUES (13, 'ru', 'c3', 'Tolko ru', 'tolko-ru', 1, '2000-01-01 00:00:00')");
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
    $sqlitePath = mlang_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }
    $model = new ParserModel();

    echo "=== 同名栏目按当前语言命中 ===\n";
    foreach (array('cn' => 'c1', 'en' => 'c2', 'ru' => 'c3') as $lg => $scode) {
        $GLOBALS['mlang_current_lg'] = $lg;
        $sort = $model->getSort('about');
        TestAssert::true(is_object($sort), "getSort about hits under $lg");
        TestAssert::same($scode, $sort->scode, "about resolves to $lg sort");
        TestAssert::same($lg, $sort->acode, "about acode is $lg");
    }

    echo "=== 同名内容按当前语言命中 ===\n";
    foreach (array('cn' => 10, 'en' => 11, 'ru' => 12) as $lg => $id) {
        $GLOBALS['mlang_current_lg'] = $lg;
        $data = $model->getContent('hello');
        TestAssert::true(is_object($data), "getContent hello hits under $lg");
        TestAssert::same($id, (int) $data->id, "hello resolves to $lg content");
        TestAssert::same($lg, $data->acode, "hello acode is $lg");
    }

    echo "=== 当前语言没有时跨语言兜底 ===\n";
    $GLOBALS['mlang_current_lg'] = 'cn';
    $sort = $model->getSort('dostavka');
    TestAssert::true(is_object($sort), 'cn 下仍能取到仅 ru 存在的栏目');
    TestAssert::same('ru', $sort->acode, '兜底命中 ru 栏目');
    $data = $model->getContent('tolko-ru');
    TestAssert::true(is_object($data), 'cn 下仍能取到仅 ru 存在的内容');
    TestAssert::same('ru', $data->acode, '兜底命中 ru 内容');

    echo "=== scode 查询不受语言影响 ===\n";
    $GLOBALS['mlang_current_lg'] = 'en';
    $sort = $model->getSort('c1');
    TestAssert::true(is_object($sort), 'en 下按 scode 取 cn 栏目');
    TestAssert::same('cn', $sort->acode, 'scode 精确匹配跨语言可达');
    $data = $model->getContent('10');
    TestAssert::true(is_object($data), 'en 下按 id 取 cn 内容');
    TestAssert::same(10, (int) $data->id, 'id 精确匹配跨语言可达');

    echo "=== 不存在的名称返回假值 ===\n";
    $GLOBALS['mlang_current_lg'] = 'cn';
    TestAssert::false((bool) $model->getSort('nope'), 'getSort 未命中');
    TestAssert::false((bool) $model->getContent('nope'), 'getContent 未命中');

    @unlink($sqlitePath);
});

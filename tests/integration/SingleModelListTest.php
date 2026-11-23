<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/admin/model/content/SingleModel.php
 *
 * 后台单页列表与搜索每个栏目只保留 id 最大的一条，同名单页栏目不合并，列表模型内容不混入。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/admin/model/content/SingleModel.php';

use app\admin\model\content\SingleModel;

/**
 * @return string|null
 */
function single_list_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_single_list_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_model (
        id INTEGER PRIMARY KEY,
        mcode TEXT NOT NULL DEFAULT \'\',
        type INTEGER NOT NULL DEFAULT 2,
        urlname TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_content_sort (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        mcode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        title TEXT NOT NULL DEFAULT \'\',
        date TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        visits INTEGER NOT NULL DEFAULT 0,
        ico TEXT NOT NULL DEFAULT \'\',
        pics TEXT NOT NULL DEFAULT \'\',
        outlink TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec("INSERT INTO ay_model (id, mcode, type, urlname) VALUES (1, '1', 1, 'about'), (2, '2', 2, 'news')");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, scode, name) VALUES
        (1, 'cn', '1', '1', '公司简介'),
        (2, 'cn', '1', '11', '联系我们'),
        (3, 'cn', '1', '12', '联系我们'),
        (4, 'cn', '2', '2', '新闻中心')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, title) VALUES
        (1, 'cn', '1', '公司简介旧'),
        (18, 'cn', '1', '公司简介中'),
        (19, 'cn', '1', '公司简介新'),
        (20, 'cn', '11', '联系方式'),
        (21, 'cn', '12', '联系方式二'),
        (30, 'cn', '2', '公司新闻')");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

/**
 * @param array $rows
 * @return array
 */
function single_list_keys($rows)
{
    $keys = array();
    foreach ($rows as $row) {
        $keys[] = $row->scode . ':' . $row->id;
    }
    return $keys;
}

return TestAssert::runSuite(function () {
    $sqlitePath = single_list_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    echo "=== SingleModel getList dedup by scode ===\n";

    $_SESSION = array('acode' => 'cn');
    $model = new SingleModel();
    TestAssert::same(array('12:21', '11:20', '1:19'), single_list_keys($model->getList('1')), 'list keeps latest row per scode');
    TestAssert::same(array('1:19'), single_list_keys($model->getList('1', 'a.title', '公司')), 'title search returns latest match, excludes list model');
    TestAssert::same(array('12:21', '11:20'), single_list_keys($model->getList('1', 'b.name', '联系')), 'same-name sorts are not merged');

    @unlink($sqlitePath);
});

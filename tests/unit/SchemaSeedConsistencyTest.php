<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers static/backup/sql/pbootcms.sql 与 data/pbootcms.db 的表、列、菜单种子一致
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $dump = file_get_contents(ROOT_PATH . 'static/backup/sql/pbootcms.sql');
    TestAssert::false($dump === false, 'pbootcms.sql readable');
    preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=/s', $dump, $creates, PREG_SET_ORDER);
    $sqlTables = array();
    foreach ($creates as $create) {
        $cols = array();
        foreach (explode("\n", $create[2]) as $line) {
            if (preg_match('/^\s*`([^`]+)`/', $line, $match)) {
                $cols[] = $match[1];
            }
        }
        sort($cols);
        $sqlTables[$create[1]] = $cols;
    }

    $pdo = new PDO('sqlite:' . ROOT_PATH . 'data/pbootcms.db', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $sqliteTables = array();
    $tableRows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tableRows as $name) {
        $cols = array();
        foreach ($pdo->query('PRAGMA table_info(' . $name . ')') as $col) {
            $cols[] = $col['name'];
        }
        sort($cols);
        $sqliteTables[$name] = $cols;
    }

    echo "=== 表集合一致 ===\n";
    $sqlNames = array_keys($sqlTables);
    $sqliteNames = array_keys($sqliteTables);
    sort($sqlNames);
    sort($sqliteNames);
    TestAssert::same($sqlNames, $sqliteNames, 'table name sets match');
    TestAssert::false(in_array('demo_pbootcms', $sqliteNames, true), 'sqlite has no demo_pbootcms');

    echo "=== 列名集合一致 ===\n";
    foreach ($sqlNames as $name) {
        TestAssert::same($sqlTables[$name], $sqliteTables[$name], $name . ' columns match');
    }
    TestAssert::false(in_array('telephone', $sqlTables['ay_member'], true), 'ay_member has no telephone');
    TestAssert::false(in_array('email', $sqlTables['ay_member'], true), 'ay_member has no email');

    echo "=== 菜单种子按 mcode 一致 ===\n";
    $sqliteMenus = array();
    foreach ($pdo->query('SELECT mcode,pcode,name,url,sorting,status,shortcut,ico FROM ay_menu') as $row) {
        $sqliteMenus[$row['mcode']] = $row['pcode'] . '|' . $row['name'] . '|' . $row['url'] . '|' . $row['sorting'] . '|' . $row['status'] . '|' . $row['shortcut'] . '|' . $row['ico'];
    }
    preg_match_all("/^\('(\d+)',\s*'(M[^']+)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'(\d+)',\s*'([01])',\s*'([01])',\s*'([^']*)'/m", $dump, $menus, PREG_SET_ORDER);
    $sqlMenus = array();
    foreach ($menus as $row) {
        $sqlMenus[$row[2]] = $row[3] . '|' . $row[4] . '|' . $row[5] . '|' . $row[6] . '|' . $row[7] . '|' . $row[8] . '|' . $row[9];
    }
    ksort($sqliteMenus);
    ksort($sqlMenus);
    TestAssert::same($sqlMenus, $sqliteMenus, 'ay_menu natural keys match');
    TestAssert::false(isset($sqlMenus['M113']), 'SQL menu has no M113');
    TestAssert::true(isset($sqlMenus['M1101']), 'both sides can see 图片清理');
    TestAssert::same('M110|公司信息|/admin/Company/index|302|1|1|fa-copyright', $sqliteMenus['M112'], 'company menu is M112');

    echo "=== 菜单动作种子按 mcode 一致 ===\n";
    $sqliteActions = array();
    foreach ($pdo->query('SELECT mcode,action FROM ay_menu_action') as $row) {
        $sqliteActions[] = $row['mcode'] . '|' . $row['action'];
    }
    TestAssert::true(preg_match('/INSERT INTO `ay_menu_action` [^;]*;/', $dump, $block) === 1, 'ay_menu_action insert located');
    preg_match_all("/\('\d+',\s*'(M[^']+)',\s*'([^']*)'\)/", $block[0], $actions, PREG_SET_ORDER);
    $sqlActions = array();
    foreach ($actions as $row) {
        $sqlActions[] = $row[1] . '|' . $row[2];
    }
    sort($sqliteActions);
    sort($sqlActions);
    TestAssert::true(count($sqlActions) > 0, 'SQL menu actions parsed');
    TestAssert::same($sqlActions, $sqliteActions, 'ay_menu_action natural keys match');

    echo "=== 默认管理员仍是初始口令 ===\n";
    $password = $pdo->query("SELECT password FROM ay_user WHERE ucode='10001'")->fetchColumn();
    TestAssert::same('14e1b600b1fd579f47433b88e8d85291', $password, 'ucode 10001 keeps the package password hash');
});

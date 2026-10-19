<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\admin\controller\IndexController::home 不再改名未被配置使用的默认库
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $source = file_get_contents(APP_PATH . '/admin/controller/IndexController.php');
    $start = strpos($source, 'public function home()');
    $end = strpos($source, 'public function login()');
    TestAssert::true($start !== false && $end !== false && $end > $start, 'home(): method located');
    $home = substr($source, $start, $end - $start);

    echo "=== unused default database is not renamed ===\n";
    TestAssert::notContains($home, "rename(ROOT_PATH . '/data/pbootcms.db'", 'home(): no rename of data/pbootcms.db');
    TestAssert::notContains($home, 'rename(', 'home(): no rename call');

    echo "=== default-name sqlite site keeps modDB and warning ===\n";
    TestAssert::contains($home, "strpos(\$this->config('database.dbname'), 'pbootcms') !== false", 'home(): default dbname check retained');
    TestAssert::contains($home, "get_user_ip() != '127.0.0.1' && \$this->modDB()", 'home(): auto modDB retained');
    TestAssert::contains($home, "get('action') == 'moddb'", 'home(): manual moddb entry retained');
    TestAssert::contains($home, "\$this->assign('dbsecurity', \$dbsecurity)", 'home(): dbsecurity warning retained');
});

<?php

/**
 * 探测：请求前已定义 PAGE 时，Paging::limit() 仍应注入 pagebar 等模板变量。
 * 由 PagingRepeatLimitTest 在独立进程中调用。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/view/View.php';
require_once CORE_PATH . '/view/Paging.php';

if (! defined('RUN_PATH')) {
    define('RUN_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_paging_probe_' . getmypid());
}
if (! defined('APP_VIEW_PATH')) {
    define('APP_VIEW_PATH', RUN_PATH . '/view');
}
if (! defined('URL')) {
    define('URL', '/list/');
}
if (! defined('M')) {
    define('M', 'home');
}
if (! defined('C')) {
    define('C', 'List');
}
if (! defined('SITE_INDEX_DIR')) {
    define('SITE_INDEX_DIR', '');
}

error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr) {
    if (! (error_reporting() & $errno)) {
        return false;
    }
    echo 'PHP_ERROR:' . $errstr . "\n";
    exit(1);
});
ConfigStub::reset();
ConfigStub::set(array(
    'pagenum' => 5
));
$_GET = array(
    'page' => '2'
);
$_SERVER['QUERY_STRING'] = 'page=2';

define('PAGE', 1);

$paging = \core\view\Paging::getInstance();
$paging->page = 1;
$paging->pageSize = 10;
$paging->start = 1;
$limit = $paging->limit(100, true);

$pagebar = get_var('pagebar');
$pagestatus = get_var('pagestatus');
$pagerows = get_var('pagerows');

$ok = true;
$lines = array();

if ($limit !== '10,10') {
    $ok = false;
    $lines[] = 'LIMIT_FAIL:' . $limit;
} else {
    $lines[] = 'LIMIT_OK';
}

if (! is_string($pagebar) || strpos($pagebar, '共100条 当前2/10页') === false) {
    $ok = false;
    $lines[] = 'PAGEBAR_FAIL';
} else {
    $lines[] = 'PAGEBAR_OK';
}

if ($pagestatus !== '共100条 当前2/10页') {
    $ok = false;
    $lines[] = 'PAGESTATUS_FAIL:' . (string) $pagestatus;
} else {
    $lines[] = 'PAGESTATUS_OK';
}

if ((int) $pagerows !== 100) {
    $ok = false;
    $lines[] = 'PAGEROWS_FAIL:' . (string) $pagerows;
} else {
    $lines[] = 'PAGEROWS_OK';
}

if (! defined('PAGECOUNT') || (float) PAGECOUNT !== 10.0) {
    $ok = false;
    $lines[] = 'PAGECOUNT_FAIL';
} else {
    $lines[] = 'PAGECOUNT_OK';
}

if ((int) PAGE !== 1) {
    $ok = false;
    $lines[] = 'PAGE_CONST_FAIL:' . (string) PAGE;
} else {
    $lines[] = 'PAGE_CONST_OK';
}

echo implode("\n", $lines) . "\n";
exit($ok ? 0 : 1);

<?php

/**
 * 活体探测：真实库上对比 scode 末尾空段与去掉空段后的 getList/getSortRows/CmsModel。
 *
 * 用法: php tests/scripts/scode_trailing_probe.php
 */

$root = dirname(dirname(__DIR__));
define('TEST_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ROOT_PATH', str_replace('\\', '/', $root) . '/');
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('RUN_PATH', ROOT_PATH . 'runtime');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require TEST_ROOT . 'support/DatabaseTestSupport.php';
require CORE_PATH . '/basic/Model.php';
require APP_PATH . '/common/VisitsCounter.php';
require APP_PATH . '/home/model/ParserModel.php';
require APP_PATH . '/api/model/CmsModel.php';

$configFile = ROOT_PATH . 'config/database.php';
$dbConfig = require $configFile;
if (! is_array($dbConfig) || ! isset($dbConfig['database'])) {
    fwrite(STDERR, "database config missing\n");
    exit(1);
}
db_set_database_config($dbConfig['database']);
if (isset($dbConfig['database']['type']) && strpos($dbConfig['database']['type'], 'sqlite') !== false) {
    db_reset_sqlite_singleton();
} else {
    db_reset_mysqli_singleton();
}

if (! function_exists('get_lg')) {
    function get_lg()
    {
        return 'cn';
    }
}

use app\home\model\ParserModel;
use app\api\model\CmsModel;

/**
 * @param mixed $rows
 * @return array
 */
function scode_probe_ids($rows)
{
    $out = array();
    if (! is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        $out[] = (int) $row->id;
    }
    sort($out);
    return $out;
}

$fail = 0;
echo 'PHP ' . PHP_VERSION . PHP_EOL;
echo 'DB type=' . (isset($dbConfig['database']['type']) ? $dbConfig['database']['type'] : '?') . PHP_EOL;

$pm = new ParserModel();
$cm = new CmsModel();

$pairs = array(
    array('2', '2,'),
    array('2', '2, '),
    array('2', ',2'),
    array('2,5', '2,5,'),
    array('2,5', '2,5, '),
);

echo "=== ParserModel ===\n";
foreach ($pairs as $pair) {
    $a = $pair[0];
    $b = $pair[1];
    $listA = scode_probe_ids($pm->getList($a, 200, 'a.id ASC'));
    $listB = scode_probe_ids($pm->getList($b, 200, 'a.id ASC'));
    $rowsA = $pm->getSortRows($a);
    $rowsB = $pm->getSortRows($b);
    $ok = ($listA === $listB && $rowsA === $rowsB);
    if (! $ok) {
        $fail++;
    }
    echo ($ok ? 'OK' : 'FAIL') . " [$a] vs [$b] list=" . count($listA) . '/' . count($listB) . " rows=$rowsA/$rowsB\n";
}

echo "=== CmsModel getList ===\n";
foreach ($pairs as $pair) {
    $a = $pair[0];
    $b = $pair[1];
    try {
        $listA = scode_probe_ids($cm->getList('cn', $a, 200, 'a.id ASC'));
        $listB = scode_probe_ids($cm->getList('cn', $b, 200, 'a.id ASC'));
        $ok = ($listA === $listB);
        if (! $ok) {
            $fail++;
        }
        echo ($ok ? 'OK' : 'FAIL') . " [$a] vs [$b] list=" . count($listA) . '/' . count($listB) . "\n";
    } catch (Exception $e) {
        $fail++;
        echo 'FAIL [' . $b . '] EXCEPTION ' . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        $fail++;
        echo 'FAIL [' . $b . '] EXCEPTION ' . $e->getMessage() . "\n";
    }
}

echo ($fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n");
exit($fail ? 1 : 0);

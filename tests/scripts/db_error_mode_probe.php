<?php

declare(strict_types=1);

/**
 * 数据库错误模式独立进程探测
 *
 * 用法: php tests/scripts/db_error_mode_probe.php <mode> [args...]
 *
 * mode:
 *   sql_error_hard          非 failSoft 错误 SQL → 应走全局 error()+exit
 *   sql_error_soft          failSoft 下错误 SQL → amd=0 且进程存活
 *   sql_error_after_soft    setFailSoft(false) 后应回到 hard
 *   sql_error_public        argv2=0|1(debug) [argv3=ajax] 故意坏 SQL，测对外文案与 error_log
 *   failsoft_residual_exit  模拟 withFailSoftDb：try/finally + error()/exit，shutdown 打印残留
 *   failsoft_open_then_exit 仅 setFailSoft(true) 无 finally 再 error()，shutdown 打印残留
 *   conn_mysqli             错凭据连 mysqli
 *   conn_pdo_mysql          错凭据连 pdo_mysql
 *   conn_pdo_sqlite         不可写 pdo_sqlite 路径
 *   mysqli_report_mode      调用 Mysqli::conn 后打印 report_mode
 *   pdo_errmode             成功连 pdo_sqlite 后打印 ATTR_ERRMODE
 */

ini_set('display_errors', '0');
$errorLevel = E_ALL;
if (defined('E_STRICT') && PHP_VERSION_ID < 80400) {
    $errorLevel = $errorLevel & ~E_STRICT;
}
if (defined('E_DEPRECATED')) {
    $errorLevel = $errorLevel & ~E_DEPRECATED;
}
error_reporting($errorLevel);

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if ($mode === '') {
    fwrite(STDERR, "missing mode\n");
    exit(2);
}

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('RUN_PATH', sys_get_temp_dir() . '/pboot_db_probe_' . getmypid());
// error()/parse_info_tpl 模板替换所需常量（探测进程不启 Kernel）
defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', 'test');
defined('RELEASE_TIME') || define('RELEASE_TIME', '0');
if (! is_dir(RUN_PATH)) {
    @mkdir(RUN_PATH, 0777, true);
}

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Basic.php';
require CORE_PATH . '/database/Builder.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

require dirname(__DIR__) . '/support/DatabaseTestSupport.php';

use core\basic\Config as AppConfig;

function probe_print($key, $value)
{
    echo $key . '=' . $value . "\n";
}

function probe_reflect_failsoft($class)
{
    $depth = db_failsoft_depth($class);
    $on = db_failsoft_enabled($class) ? '1' : '0';
    probe_print('FAILSOFT_DEPTH', (string) $depth);
    probe_print('FAILSOFT_ON', $on);
}

function probe_set_debug($enabled)
{
    $prop = db_config_reflection();
    $configs = $prop->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    $configs['debug'] = (bool) $enabled;
    $prop->setValue(null, $configs);
}

function probe_boot_sqlite_for_sql()
{
    $path = db_boot_sqlite('probe_' . getmypid());
    if ($path === null) {
        probe_print('SKIP', 'sqlite3 unavailable');
        exit(0);
    }
    require_once CORE_PATH . '/database/Sqlite.php';
    return \core\database\Sqlite::getInstance();
}

/** 故意带表名/列名/函数名的坏 SQL，便于断言不外泄 */
function probe_secret_bad_sql()
{
    return 'UPDATE ay_secret_table SET supersecret_col = bogusfn(1)';
}

switch ($mode) {
    case 'sql_error_hard':
        $db = probe_boot_sqlite_for_sql();
        probe_set_debug(false);
        \core\database\Sqlite::setFailSoft(false);
        // 避免 error_log 落到 stderr 污染对外文案断言
        ini_set('log_errors', '1');
        ini_set('error_log', sys_get_temp_dir() . '/pboot_sql_hard_' . getmypid() . '.log');
        // 应 error()+exit，下面两行不应执行
        $db->amd('UPDATE ay_notable_xyz SET x=1');
        probe_print('UNEXPECTED_SURVIVE', '1');
        exit(0);

    case 'sql_error_soft':
        $db = probe_boot_sqlite_for_sql();
        \core\database\Sqlite::setFailSoft(true);
        $amd = $db->amd('UPDATE ay_notable_xyz SET x=1');
        probe_print('AMD', (string) (int) $amd);
        probe_print('HAD_ERROR', \core\database\Sqlite::hadFailSoftError() ? '1' : '0');
        \core\database\Sqlite::setFailSoft(false);
        probe_print('SURVIVED', '1');
        exit(0);

    case 'sql_error_after_soft':
        $db = probe_boot_sqlite_for_sql();
        probe_set_debug(false);
        ini_set('log_errors', '1');
        ini_set('error_log', sys_get_temp_dir() . '/pboot_sql_after_' . getmypid() . '.log');
        \core\database\Sqlite::setFailSoft(true);
        $db->amd('UPDATE ay_notable_xyz SET x=1');
        \core\database\Sqlite::setFailSoft(false);
        // 复位后应再次硬失败退出
        $db->amd('UPDATE ay_notable_xyz SET x=1');
        probe_print('UNEXPECTED_SURVIVE', '1');
        exit(0);

    case 'sql_error_public':
        $debugOn = isset($argv[2]) && (string) $argv[2] === '1';
        $asAjax = isset($argv[3]) && (string) $argv[3] === 'ajax';
        $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_sql_err_' . getmypid() . '.log';
        @unlink($logFile);
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        probe_print('LOG_FILE', $logFile);
        probe_print('DEBUG', $debugOn ? '1' : '0');
        probe_print('AJAX', $asAjax ? '1' : '0');

        register_shutdown_function(function () use ($logFile) {
            if (is_file($logFile)) {
                $body = (string) file_get_contents($logFile);
                // 单行便于父进程解析
                probe_print('LOG_HAS_PREFIX', strpos($body, '[PbootCMS][sqlite]') !== false ? '1' : '0');
                probe_print('LOG_HAS_STMT', strpos($body, '语句：') !== false ? '1' : '0');
                probe_print('LOG_HAS_SECRET_TABLE', strpos($body, 'ay_secret_table') !== false ? '1' : '0');
                probe_print('LOG_HAS_BOGUS', strpos($body, 'bogusfn') !== false ? '1' : '0');
            } else {
                probe_print('LOG_HAS_PREFIX', '0');
                probe_print('LOG_HAS_STMT', '0');
                probe_print('LOG_HAS_SECRET_TABLE', '0');
                probe_print('LOG_HAS_BOGUS', '0');
            }
            probe_print('SHUTDOWN', '1');
        });

        if ($asAjax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }

        $db = probe_boot_sqlite_for_sql();
        // 先建表，使错误落在 bogusfn（无表时 SQLite 只会报 no such table）
        $ref = new ReflectionObject($db);
        $masterProp = $ref->getProperty('master');
        if (PHP_VERSION_ID < 80100) {
            $masterProp->setAccessible(true);
        }
        // 触发连接
        $db->query('SELECT 1', 'slave');
        $master = $masterProp->getValue($db);
        if ($master instanceof SQLite3) {
            $master->exec('CREATE TABLE IF NOT EXISTS ay_secret_table (supersecret_col INTEGER)');
            $master->exec('commit;');
        }
        probe_set_debug($debugOn);
        \core\database\Sqlite::setFailSoft(false);
        $db->amd(probe_secret_bad_sql());
        probe_print('UNEXPECTED_SURVIVE', '1');
        exit(0);

    case 'failsoft_residual_exit':
        // 显式契约：error()/exit() 经 zend_bailout 离开，try/finally 不会执行
        require_once CORE_PATH . '/database/Sqlite.php';
        $class = 'core\\database\\Sqlite';
        register_shutdown_function(function () use ($class) {
            probe_reflect_failsoft($class);
            probe_print('SHUTDOWN', '1');
        });
        \core\database\Sqlite::setFailSoft(true);
        try {
            error('forced_exit_for_failsoft_residual_test');
        } finally {
            \core\database\Sqlite::setFailSoft(false);
        }
        exit(0);

    case 'failsoft_open_then_exit':
        // 契约：无 finally 保护时 exit 会留下开启态（显式文档化）
        require_once CORE_PATH . '/database/Sqlite.php';
        $class = 'core\\database\\Sqlite';
        register_shutdown_function(function () use ($class) {
            probe_reflect_failsoft($class);
            probe_print('SHUTDOWN', '1');
        });
        \core\database\Sqlite::setFailSoft(true);
        error('forced_exit_without_finally');
        exit(0);

    case 'conn_mysqli':
        if (! extension_loaded('mysqli')) {
            probe_print('SKIP', 'mysqli unavailable');
            exit(0);
        }
        require_once CORE_PATH . '/database/Mysqli.php';
        db_reset_mysqli_singleton();
        // 故意错凭据；可达主机上应走友好错误页，而非未捕获 mysqli_sql_exception
        $cfg = array(
            'host' => '127.0.0.1',
            'user' => 'pboot_wrong_user_' . getmypid(),
            'passwd' => 'definitely_wrong_password',
            'dbname' => 'pboot_nonexistent_db',
            'port' => 3306
        );
        try {
            \core\database\Mysqli::getInstance()->conn($cfg);
            probe_print('UNEXPECTED_SURVIVE', '1');
            exit(0);
        } catch (\Throwable $e) {
            probe_print('UNCAUGHT', get_class($e));
            probe_print('MSG', $e->getMessage());
            exit(3);
        }

    case 'conn_pdo_mysql':
        if (! extension_loaded('pdo_mysql')) {
            probe_print('SKIP', 'pdo_mysql unavailable');
            exit(0);
        }
        require_once CORE_PATH . '/database/Pdo.php';
        db_reset_pdo_singleton();
        db_set_database_config(array(
            'type' => 'pdo_mysql',
            'host' => '127.0.0.1',
            'user' => 'pboot_wrong_user_' . getmypid(),
            'passwd' => 'definitely_wrong_password',
            'dbname' => 'pboot_nonexistent_db',
            'port' => 3306
        ));
        try {
            \core\database\Pdo::getInstance()->conn(AppConfig::get('database'));
            probe_print('UNEXPECTED_SURVIVE', '1');
            exit(0);
        } catch (\Throwable $e) {
            probe_print('UNCAUGHT', get_class($e));
            probe_print('MSG', $e->getMessage());
            exit(3);
        }

    case 'conn_pdo_sqlite':
        if (! extension_loaded('pdo_sqlite')) {
            probe_print('SKIP', 'pdo_sqlite unavailable');
            exit(0);
        }
        require_once CORE_PATH . '/database/Pdo.php';
        db_reset_pdo_singleton();
        // 父路径是普通文件，无法在其下建库 → PDOException → error()
        $blockerRel = '/data/pdo_sqlite_block_' . getmypid();
        $blockerAbs = ROOT_PATH . $blockerRel;
        @unlink($blockerAbs);
        if (! is_dir(dirname($blockerAbs))) {
            mkdir(dirname($blockerAbs), 0777, true);
        }
        file_put_contents($blockerAbs, 'not-a-dir');
        db_set_database_config(array(
            'type' => 'pdo_sqlite',
            'dbname' => $blockerRel . '/child.db'
        ));
        try {
            \core\database\Pdo::getInstance()->conn(AppConfig::get('database'));
            probe_print('UNEXPECTED_SURVIVE', '1');
            @unlink($blockerAbs);
            exit(0);
        } catch (\Throwable $e) {
            @unlink($blockerAbs);
            probe_print('UNCAUGHT', get_class($e));
            probe_print('MSG', $e->getMessage());
            exit(3);
        }

    case 'mysqli_report_mode':
        if (! extension_loaded('mysqli') || ! function_exists('mysqli_report')) {
            probe_print('SKIP', 'mysqli_report unavailable');
            exit(0);
        }
        require_once CORE_PATH . '/database/Mysqli.php';
        db_reset_mysqli_singleton();
        $cfg = array(
            'host' => '127.0.0.1',
            'user' => 'pboot_wrong_user_' . getmypid(),
            'passwd' => 'x',
            'dbname' => 'x',
            'port' => 3306
        );
        register_shutdown_function(function () {
            $driver = new \mysqli_driver();
            probe_print('REPORT_MODE', (string) (int) $driver->report_mode);
            probe_print('MYSQLI_REPORT_OFF', (string) (int) MYSQLI_REPORT_OFF);
            probe_print('SHUTDOWN', '1');
        });
        try {
            \core\database\Mysqli::getInstance()->conn($cfg);
        } catch (\Throwable $e) {
            probe_print('UNCAUGHT', get_class($e));
            exit(3);
        }
        exit(0);

    case 'pdo_errmode':
        $path = db_boot_pdo_sqlite('errmode_' . getmypid());
        if ($path === null) {
            probe_print('SKIP', 'pdo_sqlite unavailable');
            exit(0);
        }
        require_once CORE_PATH . '/database/Pdo.php';
        $conn = \core\database\Pdo::getInstance()->conn(AppConfig::get('database'));
        probe_print('ERRMODE', (string) (int) $conn->getAttribute(PDO::ATTR_ERRMODE));
        probe_print('ERRMODE_SILENT', (string) (int) PDO::ERRMODE_SILENT);
        probe_print('SURVIVED', '1');
        exit(0);

    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}

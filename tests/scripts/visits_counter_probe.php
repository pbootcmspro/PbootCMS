<?php

declare(strict_types=1);

/**
 * 独立进程探测 VisitsCounter::incr() 的真实并发行为
 *
 * 用法:
 *   php tests/scripts/visits_counter_probe.php <content_id> <run_path> [times] [mode] [flush_file]
 * mode:
 *   delta  — 仅本地累加，不触发回写（默认，阈值抬高）
 *   flush  — 真实触发阈值回写，把增量写入 flush_file（多进程刷盘压测）
 * 输出 JSON: {"ok":bool,"times":int}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$contentId = (int) $argv[1];
$runPath = $argv[2];
$times = isset($argv[3]) ? max(1, (int) $argv[3]) : 1;
$mode = isset($argv[4]) ? (string) $argv[4] : 'delta';
$flushFile = isset($argv[5]) ? (string) $argv[5] : '';

$sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $runPath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('RUN_PATH', $runPath);

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/file.php';
require APP_PATH . '/common/VisitsCounter.php';

\app\common\VisitsCounter::disableRecoveryFlush();

if ($mode === 'flush') {
    // 低阈值，让子进程真实走 markSending / commitFlush / settle
    \app\common\VisitsCounter::setBatchThreshold(5);
    \app\common\VisitsCounter::setFlushInterval(1);
    \app\common\VisitsCounter::disableShutdownFlush();
    if ($flushFile !== '') {
        \app\common\VisitsCounter::setFlushHandler(function ($id, $pending) use ($flushFile) {
            $pending = (int) $pending;
            if ($pending <= 0) {
                return true;
            }
            $fp = @fopen($flushFile, 'c+');
            if ($fp === false) {
                return false;
            }
            if (! flock($fp, LOCK_EX)) {
                fclose($fp);
                return false;
            }
            $raw = stream_get_contents($fp);
            $total = is_numeric($raw) ? (int) $raw : 0;
            $total += $pending;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) $total);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        });
    }
} else {
    // 探测进程未加载数据库配置，只验证热路径计数，不参与任何回写
    \app\common\VisitsCounter::disableShutdownFlush();
    \app\common\VisitsCounter::setBatchThreshold(100000);
    \app\common\VisitsCounter::setFlushInterval(100000);
}

$ok = true;
for ($i = 0; $i < $times; $i++) {
    if (! \app\common\VisitsCounter::incr($contentId)) {
        $ok = false;
        break;
    }
}

if ($mode === 'flush') {
    // 把未达阈值的残留一并归入，保证子进程退出前本地不留 delta
    if (! \app\common\VisitsCounter::flushAll()) {
        $ok = false;
    }
}

echo json_encode(array('ok' => $ok, 'times' => $times));
exit(0);

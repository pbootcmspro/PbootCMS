<?php

/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 * @date 2017年10月24日
 *  系统日志文件驱动（归档至 data/log/system，受 data 访问拒绝保护）
 */
namespace core\log;

class LogFile implements Builder
{

    protected static $logFile;

    private function __construct()
    {}

    public static function getInstance()
    {
        if (! self::$logFile) {
            self::$logFile = new self();
        }
        return self::$logFile;
    }

    public function write($content, $level = "info", $username = null)
    {
        $dir = DOC_PATH . DATA_DIR . '/log/system/' . date('Y/Ym');
        for ($i = 0; $i < 8; $i ++) {
            if (check_dir($dir, true)) {
                break;
            }
            usleep(2000);
        }
        if (! is_dir($dir)) {
            return false;
        }
        $logfile = $dir . '/' . date('Ymd') . '.log';
        $username = $username ?: session('username') ?: 'system';
        $string = $level . ' ' . $content . ' ' . get_user_ip() . ' ' . get_user_os() . ' ' . get_user_bs() . ' ' . $username . ' ' . get_datetime() . PHP_EOL;
        return $this->appendWithLock($logfile, $string);
    }

    public function error($content)
    {
        return $this->write($content, 'error');
    }

    public function info($content)
    {
        return $this->write($content, 'info');
    }

    private function appendWithLock($path, $content)
    {
        $fp = @fopen($path, 'ab');
        if (! $fp) {
            return false;
        }
        if (! flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        $result = fwrite($fp, $content);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result !== false;
    }
}

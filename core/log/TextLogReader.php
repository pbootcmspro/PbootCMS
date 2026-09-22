<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 */
namespace core\log;

class TextLogReader
{

    const MAX_LINES = 2000;

    const MAX_BYTES = 2097152;

    const MIN_YEAR = 1970;

    const MAX_YEAR = 2100;

    private static $types = array(
        'spider' => 'log/spider',
        'ai' => 'log/ai'
    );

    public static function isValidType($type)
    {
        return isset(self::$types[$type]);
    }

    public static function resolveBase($type, $baseDir = null)
    {
        if ($baseDir !== null && $baseDir !== '') {
            $normalized = self::normalizePath($baseDir);
            $real = realpath($baseDir);
            return $real !== false ? self::normalizePath($real) : $normalized;
        }
        if (! self::isValidType($type)) {
            return '';
        }
        $doc = defined('DOC_PATH') ? DOC_PATH : '';
        $data = defined('DATA_DIR') ? DATA_DIR : '/data';
        $full = self::normalizePath($doc . $data . '/' . self::$types[$type]);
        $real = realpath($full);
        return $real !== false ? self::normalizePath($real) : $full;
    }

    public static function isValidYear($year)
    {
        $year = (int) $year;
        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }

    public static function isValidMonth($month)
    {
        $month = (int) $month;
        return $month >= 1 && $month <= 12;
    }

    public static function isValidDay($year, $month, $day)
    {
        return checkdate((int) $month, (int) $day, (int) $year);
    }

    public static function monthDir($type, $year, $month, $baseDir = null)
    {
        if (! self::isValidType($type) || ! self::isValidYear($year) || ! self::isValidMonth($month)) {
            return false;
        }
        $year = (int) $year;
        $month = (int) $month;
        return self::resolveBase($type, $baseDir) . '/' . sprintf('%04d/%04d%02d', $year, $year, $month);
    }

    public static function dayFile($type, $year, $month, $day, $baseDir = null)
    {
        if (! self::isValidDay($year, $month, $day)) {
            return false;
        }
        $dir = self::monthDir($type, $year, $month, $baseDir);
        if ($dir === false) {
            return false;
        }
        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;
        $file = $dir . '/' . sprintf('%04d%02d%02d', $year, $month, $day) . '.log';
        if (! self::isInsideBase($file, self::resolveBase($type, $baseDir))) {
            return false;
        }
        return $file;
    }

    public static function listYears($type, $baseDir = null)
    {
        if (! self::isValidType($type)) {
            return array(
                (int) date('Y')
            );
        }
        $base = self::resolveBase($type, $baseDir);
        $years = array();
        if (is_dir($base)) {
            $dirs = glob($base . '/[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR);
            if ($dirs) {
                foreach ($dirs as $dir) {
                    $y = (int) basename($dir);
                    if (self::isValidYear($y) && self::isInsideBase($dir, $base)) {
                        $years[] = $y;
                    }
                }
            }
        }
        $years[] = (int) date('Y');
        $years = array_values(array_unique($years));
        rsort($years, SORT_NUMERIC);
        return $years;
    }

    public static function listDays($type, $year, $month, $baseDir = null)
    {
        $dir = self::monthDir($type, $year, $month, $baseDir);
        if ($dir === false || ! is_dir($dir)) {
            return array();
        }
        $base = self::resolveBase($type, $baseDir);
        if (! self::isInsideBase($dir, $base)) {
            return array();
        }
        $files = glob($dir . '/*.log');
        if (! $files) {
            return array();
        }
        $days = array();
        $year = (int) $year;
        $month = (int) $month;
        foreach ($files as $file) {
            $name = basename($file);
            if (! preg_match('/^(\d{8})\.log$/', $name, $matches)) {
                continue;
            }
            $ymd = $matches[1];
            $y = (int) substr($ymd, 0, 4);
            $m = (int) substr($ymd, 4, 2);
            $d = (int) substr($ymd, 6, 2);
            if ($y !== $year || $m !== $month || ! checkdate($m, $d, $y)) {
                continue;
            }
            if (! self::isInsideBase($file, $base)) {
                continue;
            }
            $days[] = $d;
        }
        $days = array_values(array_unique($days));
        rsort($days, SORT_NUMERIC);
        return $days;
    }

    public static function readTail($type, $file, $limit = null, $baseDir = null, $maxBytes = null)
    {
        if ($limit === null) {
            $limit = self::MAX_LINES;
        }
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = self::MAX_LINES;
        }
        if ($maxBytes === null) {
            $maxBytes = self::MAX_BYTES;
        }
        $maxBytes = (int) $maxBytes;
        if ($maxBytes < 1) {
            $maxBytes = self::MAX_BYTES;
        }

        $result = array(
            'lines' => array(),
            'truncated' => false,
            'exists' => false,
            'shown' => 0
        );
        if (! self::isValidType($type) || $file === false || $file === null || $file === '') {
            return $result;
        }

        $base = self::resolveBase($type, $baseDir);
        $file = str_replace('\\', '/', $file);
        if (! self::isInsideBase($file, $base)) {
            return $result;
        }
        if (! is_file($file) || ! is_readable($file)) {
            return $result;
        }

        $fp = @fopen($file, 'rb');
        if (! $fp) {
            return $result;
        }

        $stat = fstat($fp);
        $size = isset($stat['size']) ? (int) $stat['size'] : 0;
        if ($size <= 0) {
            fclose($fp);
            return $result;
        }
        $result['exists'] = true;

        $chunk = 8192;
        $pieces = array();
        $found = 0;
        $bytes = 0;
        $pos = $size;
        while ($pos > 0 && $found <= $limit && $bytes < $maxBytes) {
            $read = ($pos >= $chunk) ? $chunk : $pos;
            $pos -= $read;
            if (fseek($fp, $pos) !== 0) {
                break;
            }
            $piece = fread($fp, $read);
            if ($piece === false || $piece === '') {
                break;
            }
            $pieces[] = $piece;
            $found += substr_count($piece, "\n");
            $bytes += strlen($piece);
        }
        $reachedStart = ($pos <= 0);
        $byteCapped = (! $reachedStart) && ($bytes >= $maxBytes);
        fclose($fp);

        $data = implode('', array_reverse($pieces));
        $data = str_replace("\r\n", "\n", $data);
        $data = str_replace("\r", "\n", $data);
        $parts = explode("\n", $data);
        if ($parts && end($parts) === '') {
            array_pop($parts);
        }
        if (! $reachedStart && count($parts) > 1) {
            array_shift($parts);
        }

        $truncated = (! $reachedStart) || (count($parts) > $limit) || $byteCapped;
        if (count($parts) > $limit) {
            $parts = array_slice($parts, -$limit);
        }
        $result['lines'] = $parts;
        $result['truncated'] = $truncated;
        $result['shown'] = count($parts);
        return $result;
    }

    public static function parseLine($type, $line)
    {
        if ($type === 'ai') {
            return self::parseAiLine($line);
        }
        return self::parseSpiderLine($line);
    }

    public static function parseLines($type, array $lines)
    {
        $rows = array();
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = self::parseLine($type, $line);
        }
        return $rows;
    }

    private static function parseSpiderLine($line)
    {
        $cols = explode("\t", (string) $line, 7);
        while (count($cols) < 7) {
            $cols[] = '';
        }
        $keys = array(
            'time',
            'spider',
            'url',
            'ip',
            'os',
            'browser',
            'ua'
        );
        $row = array();
        foreach ($keys as $i => $key) {
            $row[$key] = htmlspecialchars($cols[$i], ENT_QUOTES, 'UTF-8');
        }
        return $row;
    }

    private static function parseAiLine($line)
    {
        $line = (string) $line;
        $row = array(
            'time' => '',
            'action' => '',
            'provider' => '',
            'user' => '',
            'detail' => ''
        );
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[([^\]]+)\] provider=(\S+) user=(\S+)(?: (.*))?$/', $line, $m)) {
            $row['time'] = $m[1];
            $row['action'] = $m[2];
            $row['provider'] = $m[3];
            $row['user'] = $m[4];
            $row['detail'] = isset($m[5]) ? $m[5] : '';
        } else {
            $row['detail'] = $line;
        }
        foreach ($row as $key => $value) {
            $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        return $row;
    }

    private static function normalizePath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('#/+#', '/', $path);
        return rtrim($path, '/');
    }

    public static function isInsideBase($path, $baseDir)
    {
        $base = self::normalizePath($baseDir);
        $realBase = realpath($baseDir);
        if ($realBase !== false) {
            $base = self::normalizePath($realBase);
        }
        $realPath = realpath($path);
        if ($realPath !== false) {
            $check = self::normalizePath($realPath);
        } else {
            $check = self::normalizePath($path);
            if (preg_match('#(?:^|/)\.\.(?:/|$)#', $check)) {
                return false;
            }
        }
        if ($check === $base) {
            return true;
        }
        return strpos($check . '/', $base . '/') === 0;
    }
}

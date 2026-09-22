<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/log/TextLogReader.php
 *
 * feat #260：通用文本日志读取器（蜘蛛 / AI）路径、截断、解析与转义
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/log/TextLogReader.php';

function textlog_reader_rmdir($dir)
{
    if (! is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            textlog_reader_rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function textlog_reader_tmp($prefix)
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . mt_rand(10000, 99999);
    if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        throw new RuntimeException('cannot create tmp dir');
    }
    return $dir;
}

return TestAssert::runSuite(function () {
    $spiderBase = textlog_reader_tmp('pboot_textlog_spider_');
    $aiBase = textlog_reader_tmp('pboot_textlog_ai_');
    try {
        echo "=== type whitelist ===\n";

        TestAssert::true(\core\log\TextLogReader::isValidType('spider'), 'spider type ok');
        TestAssert::true(\core\log\TextLogReader::isValidType('ai'), 'ai type ok');
        TestAssert::false(\core\log\TextLogReader::isValidType('system'), 'system type rejected');
        TestAssert::false(\core\log\TextLogReader::isValidType('../'), '../ type rejected');
        TestAssert::same(false, \core\log\TextLogReader::monthDir('system', 2026, 9, $spiderBase), 'invalid type monthDir false');

        echo "=== dayFile: integer date builds Y/Ym/Ymd.log ===\n";

        $file = \core\log\TextLogReader::dayFile('spider', 2026, 9, 16, $spiderBase);
        TestAssert::true(is_string($file), 'valid date returns path');
        $suffix = '/2026/202609/20260916.log';
        TestAssert::true(
            is_string($file) && substr($file, -strlen($suffix)) === $suffix,
            'path ends with Y/Ym/Ymd.log'
        );
        TestAssert::true(
            strpos($file, '/../') === false && strpos($file, '\\..\\') === false,
            'constructed path has no parent segment'
        );

        echo "=== dayFile/monthDir: reject invalid params ===\n";

        TestAssert::false(\core\log\TextLogReader::isValidYear((int) '../'), '(int) ../ is not a valid year');
        TestAssert::same(false, \core\log\TextLogReader::dayFile('spider', (int) '../', 9, 16, $spiderBase), '../ year rejected');
        TestAssert::same(false, \core\log\TextLogReader::dayFile('spider', (int) 'abc', 9, 16, $spiderBase), 'non-numeric year rejected');
        TestAssert::same(false, \core\log\TextLogReader::monthDir('spider', 2026, 13, $spiderBase), 'month 13 rejected');
        TestAssert::same(false, \core\log\TextLogReader::monthDir('spider', 2026, 0, $spiderBase), 'month 0 rejected');
        TestAssert::same(false, \core\log\TextLogReader::dayFile('spider', 2026, 9, 32, $spiderBase), 'day 32 rejected');
        TestAssert::same(false, \core\log\TextLogReader::dayFile('spider', 2026, 2, 30, $spiderBase), 'Feb 30 rejected');
        TestAssert::same(false, \core\log\TextLogReader::monthDir('spider', 0, 1, $spiderBase), 'year 0 rejected');
        TestAssert::false(
            \core\log\TextLogReader::isInsideBase($spiderBase . '/../passwd', $spiderBase),
            'parent path is outside base'
        );

        echo "=== listDays: only current month Ymd.log ===\n";

        $monthDir = \core\log\TextLogReader::monthDir('spider', 2026, 9, $spiderBase);
        TestAssert::true(is_string($monthDir), 'month dir path');
        TestAssert::true(mkdir($monthDir, 0777, true), 'create month dir');
        file_put_contents($monthDir . '/20260901.log', "a\n");
        file_put_contents($monthDir . '/20260916.log', "b\n");
        file_put_contents($monthDir . '/foo.log', "nope\n");
        file_put_contents($monthDir . '/20260831.log', "other month\n");
        $days = \core\log\TextLogReader::listDays('spider', 2026, 9, $spiderBase);
        TestAssert::same(array(16, 1), $days, 'days desc and ignore junk names');
        TestAssert::same(array(), \core\log\TextLogReader::listDays('spider', 2026, 8, $spiderBase), 'empty month is empty array');

        echo "=== parseLine spider: 7 cols and HTML escape ===\n";

        $row = \core\log\TextLogReader::parseLine(
            'spider',
            "2026-09-16 01:02:03\tBaidu\t/news\t1.1.1.1\tLinux\tChrome\t<script>alert(1)</script>"
        );
        TestAssert::same('2026-09-16 01:02:03', $row['time'], 'time column');
        TestAssert::same('Baidu', $row['spider'], 'spider column');
        TestAssert::same('/news', $row['url'], 'url column');
        TestAssert::same('1.1.1.1', $row['ip'], 'ip column');
        TestAssert::same('Linux', $row['os'], 'os column');
        TestAssert::same('Chrome', $row['browser'], 'browser column');
        TestAssert::same('&lt;script&gt;alert(1)&lt;/script&gt;', $row['ua'], 'ua escaped');
        TestAssert::notContains($row['ua'], '<script>', 'raw script tag not kept');

        $short = \core\log\TextLogReader::parseLine('spider', "only\ttwo");
        TestAssert::same(7, count($short), 'short line padded to 7');
        TestAssert::same('', $short['ua'], 'missing ua is empty');

        echo "=== parseLine ai: 5 cols, unmatched to detail ===\n";

        $aiRow = \core\log\TextLogReader::parseLine(
            'ai',
            '2026-09-17 12:00:00 [generate] provider=deepseek user=admin title=<b>Hi</b>'
        );
        TestAssert::same('2026-09-17 12:00:00', $aiRow['time'], 'ai time');
        TestAssert::same('generate', $aiRow['action'], 'ai action');
        TestAssert::same('deepseek', $aiRow['provider'], 'ai provider');
        TestAssert::same('admin', $aiRow['user'], 'ai user');
        TestAssert::same('title=&lt;b&gt;Hi&lt;/b&gt;', $aiRow['detail'], 'ai detail escaped');

        $raw = \core\log\TextLogReader::parseLine('ai', '<script>alert(1)</script>');
        TestAssert::same('&lt;script&gt;alert(1)&lt;/script&gt;', $raw['detail'], 'unmatched line escaped');
        TestAssert::same('', $raw['action'], 'unmatched action empty');

        echo "=== readTail: truncate past limit, exact limit no flag ===\n";

        $big = $monthDir . '/20260916.log';
        $buf = '';
        for ($i = 1; $i <= 2001; $i++) {
            $buf .= 'line-' . $i . "\n";
        }
        file_put_contents($big, $buf);
        $read = \core\log\TextLogReader::readTail('spider', $big, 2000, $spiderBase);
        TestAssert::true($read['exists'], 'file exists');
        TestAssert::true($read['truncated'], 'truncated flag');
        TestAssert::same(2000, count($read['lines']), 'keep 2000 lines');
        TestAssert::same(2000, $read['shown'], 'shown matches lines');
        TestAssert::same('line-2', $read['lines'][0], 'first kept is line 2');
        TestAssert::same('line-2001', $read['lines'][1999], 'last kept is file tail');

        $exact = $monthDir . '/20260903.log';
        $exactBuf = '';
        for ($i = 1; $i <= 2000; $i++) {
            $exactBuf .= 'exact-' . $i . "\n";
        }
        file_put_contents($exact, $exactBuf);
        $exactRead = \core\log\TextLogReader::readTail('spider', $exact, 2000, $spiderBase);
        TestAssert::true($exactRead['exists'], 'exact file exists');
        TestAssert::false($exactRead['truncated'], 'exactly 2000 lines not truncated');
        TestAssert::same(2000, $exactRead['shown'], 'exact shown is 2000');

        $smallFile = $monthDir . '/20260901.log';
        file_put_contents($smallFile, "one\ntwo\nthree\n");
        $small = \core\log\TextLogReader::readTail('spider', $smallFile, 2000, $spiderBase);
        TestAssert::false($small['truncated'], 'small file not truncated');
        TestAssert::same(array('one', 'two', 'three'), $small['lines'], 'small file all lines');
        TestAssert::same(3, $small['shown'], 'small shown is 3');

        $missing = \core\log\TextLogReader::readTail('spider', $spiderBase . '/2026/202609/20260999.log', 2000, $spiderBase);
        TestAssert::false($missing['exists'], 'missing file exists=false');
        TestAssert::same(array(), $missing['lines'], 'missing file no lines');

        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_text_outside_' . mt_rand(1000, 9999) . '.log';
        file_put_contents($outside, "secret\n");
        $blocked = \core\log\TextLogReader::readTail('spider', $outside, 2000, $spiderBase);
        TestAssert::false($blocked['exists'], 'outside file not read');
        TestAssert::same(array(), $blocked['lines'], 'outside file yields no lines');
        @unlink($outside);

        $huge = $monthDir . '/20260902.log';
        file_put_contents($huge, str_repeat('x', 20000));
        $capped = \core\log\TextLogReader::readTail('spider', $huge, 2000, $spiderBase, 8192);
        TestAssert::true($capped['exists'], 'no-newline file exists');
        TestAssert::true($capped['truncated'], 'byte cap truncates');
        TestAssert::true(count($capped['lines']) <= 1, 'no-newline yields at most one line');
        TestAssert::same(count($capped['lines']), $capped['shown'], 'byte-cap shown matches lines');
        $cappedLen = isset($capped['lines'][0]) ? strlen($capped['lines'][0]) : 0;
        TestAssert::true($cappedLen > 0 && $cappedLen <= 8192, 'capped payload stays within maxBytes');

        $exactBytes = $monthDir . '/20260904.log';
        file_put_contents($exactBytes, str_repeat('y', 4096));
        $exactByteRead = \core\log\TextLogReader::readTail('spider', $exactBytes, 2000, $spiderBase, 4096);
        TestAssert::true($exactByteRead['exists'], 'exact-byte file exists');
        TestAssert::false($exactByteRead['truncated'], 'exactly maxBytes from start is not truncated');

        $emptyFile = $monthDir . '/20260905.log';
        file_put_contents($emptyFile, '');
        $emptyRead = \core\log\TextLogReader::readTail('spider', $emptyFile, 2000, $spiderBase);
        TestAssert::false($emptyRead['exists'], 'zero-byte file exists=false');
        TestAssert::same(0, $emptyRead['shown'], 'zero-byte shown is 0');

        $aiMonth = \core\log\TextLogReader::monthDir('ai', 2026, 9, $aiBase);
        TestAssert::true(mkdir($aiMonth, 0777, true), 'create ai month dir');
        $aiFile = $aiMonth . '/20260917.log';
        file_put_contents($aiFile, "a\nb\n");
        $aiRead = \core\log\TextLogReader::readTail('ai', $aiFile, 2000, $aiBase);
        TestAssert::true($aiRead['exists'], 'ai file readable via ai type');
        TestAssert::same(array('a', 'b'), $aiRead['lines'], 'ai lines');

        echo "=== resolveBase defaults by type ===\n";

        $src = file_get_contents(CORE_PATH . '/log/TextLogReader.php');
        TestAssert::contains($src, "'spider' => 'log/spider'", 'spider subdir mapped');
        TestAssert::contains($src, "'ai' => 'log/ai'", 'ai subdir mapped');
        TestAssert::notContains($src, 'log/system', 'reader must not map system path');

        echo "=== controller / view contracts ===\n";

        $controller = file_get_contents(APP_PATH . '/admin/controller/system/SyslogController.php');
        TestAssert::false($controller === false, 'syslog controller readable');
        TestAssert::contains($controller, 'TextLogReader', 'uses TextLogReader');
        TestAssert::contains($controller, 'assignTextLogs', 'loads text logs via assignTextLogs');
        TestAssert::contains($controller, "get('tab', 'var')", 'tab via get');
        TestAssert::contains($controller, "in_array(\$tab, array(", 'tab whitelist');
        TestAssert::contains($controller, "get('year')", 'reads year via get()');
        TestAssert::contains($controller, '(int) $yearRaw', 'year is int-cast after get()');
        TestAssert::notContains($controller, "\$_GET['year']", 'does not read $_GET year directly');
        TestAssert::notContains($controller, "\$_GET['file']", 'no file param');
        TestAssert::notContains($controller, "\$_GET['path']", 'no path param');
        TestAssert::notContains($controller, 'hasY || $hasM || $hasD', 'dates must not switch tab');
        TestAssert::contains($controller, 'date_invalid', 'invalid date flag');
        TestAssert::contains($controller, '! $emptyMonth', 'skip day view when month empty');
        TestAssert::contains($controller, "\$tab === 'sys'", 'sys tab loads DB only when active');
        TestAssert::contains($controller, "assign('text_tab'", 'assigns shared text_tab for template');
        TestAssert::notContains($controller, 'SpiderLogReader', 'no SpiderLogReader');
        TestAssert::notContains($controller, 'AiLogReader', 'no AiLogReader');
        TestAssert::false(file_exists(APP_PATH . '/admin/controller/system/SpiderlogController.php'), 'no SpiderlogController');
        TestAssert::false(file_exists(CORE_PATH . '/log/SpiderLogReader.php'), 'no SpiderLogReader file');
        TestAssert::false(file_exists(CORE_PATH . '/log/AiLogReader.php'), 'no AiLogReader file');

        $view = file_get_contents(APP_PATH . '/admin/view/default/system/syslog.html');
        TestAssert::contains((string) $view, 'lay-id="t2"', 'spider tab');
        TestAssert::contains((string) $view, 'lay-id="t3"', 'AI tab');
        TestAssert::contains((string) $view, "system/syslog_text.html", 'shared text log partial');
        TestAssert::true(file_exists(APP_PATH . '/admin/view/default/system/syslog_text.html'), 'syslog_text partial exists');

        $textView = file_get_contents(APP_PATH . '/admin/view/default/system/syslog_text.html');
        TestAssert::contains((string) $textView, '{$pathinfo}', 'framework pathinfo');
        TestAssert::contains((string) $textView, 'name="tab" value="{$text_tab}"', 'form keeps current tab');
        TestAssert::contains((string) $textView, '日期参数无效', 'invalid date tip');
        TestAssert::contains((string) $textView, '该月无日志', 'empty month tip');
        TestAssert::contains((string) $textView, '该日期无日志', 'empty day tip');
        TestAssert::contains((string) $textView, '{$text_shown}', 'truncate uses shown');
        TestAssert::contains((string) $textView, '{if([$text_show_day])}', 'day block nested under month');
        TestAssert::notContains((string) $textView, '即将开放', 'no spider placeholder');
        TestAssert::notContains((string) $textView, '最后 2000 行', 'truncate tip not hardcoded 2000');

        $route = file_get_contents(APP_PATH . '/common/route.php');
        TestAssert::notContains((string) $route, 'Spiderlog', 'no Spiderlog route');

        $mysqlSql = @file_get_contents(ROOT_PATH . 'script/mysql-3.2.27-update.sql');
        if ($mysqlSql !== false) {
            TestAssert::notContains($mysqlSql, 'M1102', 'no M1102 menu in mysql update');
            TestAssert::notContains($mysqlSql, '/admin/Spiderlog/index', 'no Spiderlog menu url in mysql update');
        }
        $sqliteSql = @file_get_contents(ROOT_PATH . 'script/sqlite-3.2.27-update.sql');
        if ($sqliteSql !== false) {
            TestAssert::notContains($sqliteSql, 'M1102', 'no M1102 menu in sqlite update');
            TestAssert::notContains($sqliteSql, '/admin/Spiderlog/index', 'no Spiderlog menu url in sqlite update');
        }
        $dump = file_get_contents(ROOT_PATH . 'static/backup/sql/pbootcms.sql');
        TestAssert::notContains((string) $dump, "'M1102'", 'seed dump has no spider log menu');
    } finally {
        textlog_reader_rmdir($spiderBase);
        textlog_reader_rmdir($aiBase);
    }
});

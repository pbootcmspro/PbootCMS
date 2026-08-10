<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers core/database/{Sqlite,Mysqli,Pdo}.php 错误模式源码不变量
 *
 * 锁住 failSoft 先于全局 error()、query 错误分支 return false、以及 PHP8 兼容开关不被移除
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 截取 class 方法体（粗糙但足够钉不变量）
 */
function db_contract_method_body(string $src, string $method): string
{
    $pos = strpos($src, 'function ' . $method . '(');
    TestAssert::false($pos === false, "method $method found");
    if ($pos === false) {
        return '';
    }
    $next = strpos($src, "\n    public function ", $pos + 1);
    $next2 = strpos($src, "\n    protected function ", $pos + 1);
    $next3 = strpos($src, "\n    private function ", $pos + 1);
    $end = strlen($src);
    foreach (array($next, $next2, $next3) as $cand) {
        if ($cand !== false && $cand < $end) {
            $end = $cand;
        }
    }
    return substr($src, $pos, $end - $pos);
}

return TestAssert::runSuite(function () {
    $files = array(
        'Sqlite' => CORE_PATH . '/database/Sqlite.php',
        'Mysqli' => CORE_PATH . '/database/Mysqli.php',
        'Pdo' => CORE_PATH . '/database/Pdo.php'
    );

    echo "=== E1 failSoft branch returns before global error() ===\n";

    foreach ($files as $name => $path) {
        $src = file_get_contents($path);
        TestAssert::false($src === false, "$name readable");
        $body = db_contract_method_body($src, 'error');
        $softPos = strpos($body, 'if (self::$failSoft)');
        TestAssert::false($softPos === false, "$name error() has failSoft branch");
        $returnPos = strpos($body, 'return;', $softPos);
        TestAssert::false($returnPos === false, "$name failSoft branch returns");
        // 全局 error( 调用应在 failSoft return 之后（或不应在 return 之前到达）
        $globalPos = strpos($body, "error('执行SQL发生错误");
        if ($globalPos === false) {
            // Mysqli/Sqlite/Pdo 均使用该文案；连接错误在 conn() 不在此方法
            $globalPos = strpos($body, 'error(');
            // 跳过注释掉的 error( 行
            while ($globalPos !== false) {
                $lineStart = strrpos(substr($body, 0, $globalPos), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart;
                $line = substr($body, $lineStart, $globalPos - $lineStart);
                if (strpos($line, '//') !== false) {
                    $globalPos = strpos($body, 'error(', $globalPos + 1);
                    continue;
                }
                break;
            }
        }
        TestAssert::false($globalPos === false, "$name error() still calls global error() for hard path");
        TestAssert::true($returnPos < $globalPos, "E1 $name failSoft return before global error()");
    }

    echo "=== E2 query() error branches return false ===\n";

    foreach ($files as $name => $path) {
        $src = file_get_contents($path);
        $body = db_contract_method_body($src, 'query');
        // 每个 $this->error( 之后应出现 return false（允许中间有空白）
        $offset = 0;
        $count = 0;
        while (($pos = strpos($body, '$this->error(', $offset)) !== false) {
            $count++;
            $slice = substr($body, $pos, 120);
            TestAssert::contains($slice, 'return false', "E2 $name query error#$count followed by return false");
            $offset = $pos + 1;
        }
        TestAssert::true($count >= 1, "E2 $name query has at least one error branch");
    }

    echo "=== E3 PHP8 compatibility switches present ===\n";

    $mysqliSrc = file_get_contents($files['Mysqli']);
    TestAssert::contains($mysqliSrc, 'mysqli_report(MYSQLI_REPORT_OFF)', 'E3 Mysqli::conn sets MYSQLI_REPORT_OFF');

    $pdoSrc = file_get_contents($files['Pdo']);
    TestAssert::contains($pdoSrc, 'PDO::ATTR_ERRMODE', 'E3 Pdo sets ATTR_ERRMODE');
    TestAssert::contains($pdoSrc, 'PDO::ERRMODE_SILENT', 'E3 Pdo uses ERRMODE_SILENT');

    echo "=== D5 non-failSoft commit comment present ===\n";

    foreach ($files as $name => $path) {
        $src = file_get_contents($path);
        $body = db_contract_method_body($src, 'commitTransaction');
        TestAssert::contains($body, '非 failSoft', "D5 $name commitTransaction documents non-failSoft behavior");
    }

    echo "=== G4 public SQL error message identical across drivers ===\n";

    $safeNeedle = "error('数据库执行错误，请稍后重试或联系管理员！')";
    foreach ($files as $name => $path) {
        $src = file_get_contents($path);
        $body = db_contract_method_body($src, 'error');
        TestAssert::contains($body, $safeNeedle, "G4 $name uses identical generic message");
        TestAssert::contains($body, '@error_log(', "G4 $name logs full detail before public error()");
        TestAssert::contains($body, "Config::get('debug')", "G4 $name gates detail on debug");
    }
});

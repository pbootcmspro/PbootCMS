<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers order=isheadline 排序串首字段为 a.isheadline（#234）
 *
 * 防止 isheadline 分支误用 isrecommend 排序串导致头条不优先。
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 从源码中提取 case 'name': 后紧跟的 $order = '...' 字符串。
 *
 * @return string|null
 */
function order_isheadline_extract_case_order($src, $caseName)
{
    $pattern = "/case\\s+'" . preg_quote($caseName, '/') . "'\\s*:\\s*\\r?\\n\\s*\\\$order\\s*=\\s*'([^']+)'\\s*;/";
    if (!preg_match($pattern, $src, $m)) {
        return null;
    }
    return $m[1];
}

/**
 * 统计某 case 的 $order 赋值出现次数（Parser list/search 各一处）。
 *
 * @return int
 */
function order_isheadline_count_case_order($src, $caseName)
{
    $pattern = "/case\\s+'" . preg_quote($caseName, '/') . "'\\s*:\\s*\\r?\\n\\s*\\\$order\\s*=\\s*'([^']+)'\\s*;/";
    return preg_match_all($pattern, $src);
}

return TestAssert::runSuite(function () {
    $headline = 'a.isheadline DESC,a.istop DESC,a.isrecommend DESC,a.sorting ASC,a.date DESC,a.id DESC';
    $recommend = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
    $istop = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';

    $files = array(
        APP_PATH . '/home/controller/ParserController.php' => 2,
        APP_PATH . '/api/controller/CmsController.php' => 1,
        APP_PATH . '/api/controller/ListController.php' => 1,
    );

    echo "=== order=isheadline 四处排序串 ===\n";

    foreach ($files as $path => $expectedCount) {
        $name = basename($path);
        $src = file_get_contents($path);
        TestAssert::false($src === false, "$name 源码可读");

        $count = order_isheadline_count_case_order($src, 'isheadline');
        TestAssert::same($expectedCount, $count, "$name case isheadline 的 \$order 赋值共 $expectedCount 处");

        $gotHeadline = order_isheadline_extract_case_order($src, 'isheadline');
        TestAssert::same($headline, $gotHeadline, "$name isheadline 首字段为 a.isheadline DESC");

        $gotRecommend = order_isheadline_extract_case_order($src, 'isrecommend');
        TestAssert::same($recommend, $gotRecommend, "$name isrecommend 行为不变");

        $gotIstop = order_isheadline_extract_case_order($src, 'istop');
        TestAssert::same($istop, $gotIstop, "$name istop 行为不变");

        TestAssert::true($gotHeadline !== $gotRecommend, "$name isheadline 与 isrecommend 排序串不同");
    }

    echo "=== SQLite ORDER BY 语义：头条优先于推荐 ===\n";

    $orders = array(
        'isheadline' => $headline,
        'isrecommend' => $recommend,
        'istop' => $istop,
    );
    $expectFirst = array(
        'isheadline' => '乙-仅头条',
        'isrecommend' => '甲-仅推荐',
        'istop' => '甲-仅推荐', // 二者 istop 相同，次字段 isrecommend 让甲在前
    );

    $runOrders = function ($queryFn) use ($orders, $expectFirst) {
        foreach ($orders as $label => $orderBy) {
            $titles = $queryFn('SELECT title FROM a ORDER BY ' . $orderBy);
            TestAssert::same($expectFirst[$label], $titles[0], "ORDER BY ($label) 首条为 {$expectFirst[$label]}");
        }
    };

    if (extension_loaded('pdo_sqlite')) {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE a (
            id INTEGER PRIMARY KEY,
            title TEXT,
            istop INTEGER,
            isrecommend INTEGER,
            isheadline INTEGER,
            sorting INTEGER,
            date TEXT
        )');
        $pdo->exec("INSERT INTO a (id,title,istop,isrecommend,isheadline,sorting,date) VALUES
            (1,'甲-仅推荐',0,1,0,255,'2026-01-01'),
            (2,'乙-仅头条',0,0,1,255,'2026-01-01')");
        $runOrders(function ($sql) use ($pdo) {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        });
    } elseif (extension_loaded('sqlite3')) {
        $db = new SQLite3(':memory:');
        $db->exec('CREATE TABLE a (
            id INTEGER PRIMARY KEY,
            title TEXT,
            istop INTEGER,
            isrecommend INTEGER,
            isheadline INTEGER,
            sorting INTEGER,
            date TEXT
        )');
        $db->exec("INSERT INTO a (id,title,istop,isrecommend,isheadline,sorting,date) VALUES
            (1,'甲-仅推荐',0,1,0,255,'2026-01-01'),
            (2,'乙-仅头条',0,0,1,255,'2026-01-01')");
        $runOrders(function ($sql) use ($db) {
            $titles = array();
            $res = $db->query($sql);
            while ($row = $res->fetchArray(SQLITE3_NUM)) {
                $titles[] = $row[0];
            }
            return $titles;
        });
    } else {
        echo "SKIP: sqlite 扩展不可用\n";
    }
});

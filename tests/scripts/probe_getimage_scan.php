<?php
/**
 * 验收脚本：ContentModel::getImage() 游标扫描
 *
 * 用法:
 *   php tests/scripts/probe_getimage_scan.php [N] [--sqlite] [--keep]
 *   N 默认 2001；再跑一次传 5000
 *   --sqlite 使用临时 SQLite 库（不改 database.php）
 *   --keep 保留造数 / 临时库
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

$n = 2001;
$keep = false;
$useSqlite = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--keep') {
        $keep = true;
    } elseif ($arg === '--sqlite') {
        $useSqlite = true;
    } elseif (ctype_digit($arg)) {
        $n = (int) $arg;
    }
}

$root = dirname(__DIR__, 2);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

define('IS_INDEX', true);
define('URL_BIND', 'home');

require $root . '/core/init.php';

use app\admin\model\content\ContentModel;
use core\basic\Config;

$dbCfgFile = require $root . '/config/database.php';
$driverLabel = $useSqlite ? 'sqlite(temp)' : $dbCfgFile['database']['type'];
echo "=== getImage scan probe ===\n";
echo "db_type={$driverLabel} N={$n}\n";

$src = file_get_contents($root . '/apps/admin/model/content/ContentModel.php');
if (preg_match('/function getImage\(\)[\s\S]*?limit\(2000\)/', $src)) {
    fwrite(STDERR, "FAIL: getImage() still has limit(2000)\n");
    exit(1);
}
echo "OK: no limit(2000) in getImage()\n";

$probeRel = '/static/upload/image/test_probe_getimage_' . $n . '.png';
$probeAbs = DOC_PATH . SITE_DIR . $probeRel;
$probeDir = dirname($probeAbs);
if (!is_dir($probeDir)) {
    mkdir($probeDir, 0777, true);
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents($probeAbs, $png);
echo "OK: probe file {$probeAbs}\n";

$marker = 'probe_getimage_' . $n . '_' . date('YmdHis');
$insertedIds = array();
$sqlitePath = null;
$mysqli = null;
$code = 1;

$norm = function ($p) {
    return strtolower(str_replace('\\', '/', $p));
};

try {
    if ($useSqlite) {
        $dataDir = $root . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        $sqlitePath = $dataDir . '/probe_getimage_scan.db';
        if (is_file($sqlitePath)) {
            @unlink($sqlitePath);
        }

        $sqlite = new SQLite3($sqlitePath);
        $sqlite->busyTimeout(5000);
        echo "sqlite db ready\n";
        $sqlite->exec('CREATE TABLE ay_content_sort (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            acode TEXT NOT NULL,
            scode TEXT NOT NULL
        )');
        $sqlite->exec("INSERT INTO ay_content_sort (acode, scode) VALUES ('cn', '1')");
        $sqlite->exec('CREATE TABLE ay_content (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            acode TEXT NOT NULL DEFAULT \'\',
            scode TEXT NOT NULL DEFAULT \'\',
            subscode TEXT NOT NULL DEFAULT \'\',
            title TEXT NOT NULL DEFAULT \'\',
            titlecolor TEXT NOT NULL DEFAULT \'\',
            subtitle TEXT NOT NULL DEFAULT \'\',
            filename TEXT NOT NULL DEFAULT \'\',
            author TEXT NOT NULL DEFAULT \'\',
            source TEXT NOT NULL DEFAULT \'\',
            outlink TEXT NOT NULL DEFAULT \'\',
            date TEXT NOT NULL DEFAULT \'\',
            ico TEXT NOT NULL DEFAULT \'\',
            pics TEXT NOT NULL DEFAULT \'\',
            picstitle TEXT NOT NULL DEFAULT \'\',
            content TEXT NOT NULL DEFAULT \'\',
            tags TEXT NOT NULL DEFAULT \'\',
            enclosure TEXT NOT NULL DEFAULT \'\',
            keywords TEXT NOT NULL DEFAULT \'\',
            description TEXT NOT NULL DEFAULT \'\',
            sorting INTEGER NOT NULL DEFAULT 255,
            status TEXT NOT NULL DEFAULT \'1\',
            istop TEXT NOT NULL DEFAULT \'0\',
            isrecommend TEXT NOT NULL DEFAULT \'0\',
            isheadline TEXT NOT NULL DEFAULT \'0\',
            visits INTEGER NOT NULL DEFAULT 0,
            likes INTEGER NOT NULL DEFAULT 0,
            oppose INTEGER NOT NULL DEFAULT 0,
            create_user TEXT NOT NULL DEFAULT \'\',
            update_user TEXT NOT NULL DEFAULT \'\',
            create_time TEXT NOT NULL DEFAULT \'\',
            update_time TEXT NOT NULL DEFAULT \'\',
            gtype TEXT NOT NULL DEFAULT \'4\',
            gid TEXT NOT NULL DEFAULT \'\',
            gnote TEXT NOT NULL DEFAULT \'\'
        )');

        // 覆盖 Config，使 ContentModel 走 sqlite
        Config::get('database.type'); // 触发加载
        $ref = new ReflectionClass('core\\basic\\Config');
        $prop = $ref->getProperty('configs');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $configs = $prop->getValue();
        $configs['database']['type'] = 'sqlite';
        $configs['database']['dbname'] = '/data/probe_getimage_scan.db';
        $prop->setValue(null, $configs);

        $now = date('Y-m-d H:i:s');
        echo "sqlite seeding {$n} rows...\n";
        $sqlite->exec('BEGIN');
        $stmt = $sqlite->prepare('INSERT INTO ay_content (
            acode,scode,subscode,title,titlecolor,subtitle,filename,author,source,outlink,date,
            ico,pics,picstitle,content,tags,enclosure,keywords,description,sorting,status,
            istop,isrecommend,isheadline,visits,likes,oppose,create_user,update_user,create_time,update_time,gtype,gid,gnote
        ) VALUES (
            \'cn\',\'1\',\'\',:title,\'#000000\',\'\',\'\',\'admin\',\'\',\'\',:date,
            \'\',\'\',\'\',:content,\'\',\'\',\'\',\'\',255,\'1\',
            \'0\',\'0\',\'0\',0,0,0,\'probe\',\'probe\',:ctime,:utime,\'4\',\'\',\'\'
        )');
        for ($i = 1; $i <= $n; $i++) {
            $isLast = ($i === $n);
            $stmt->bindValue(':title', $marker . '_' . $i, SQLITE3_TEXT);
            $stmt->bindValue(':date', $now, SQLITE3_TEXT);
            $stmt->bindValue(':content', $isLast ? '<p><img src="' . $probeRel . '" alt="probe"></p>' : '', SQLITE3_TEXT);
            $stmt->bindValue(':ctime', $now, SQLITE3_TEXT);
            $stmt->bindValue(':utime', $now, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $sqlite->exec('COMMIT');
        echo "sqlite seed done\n";
        $after = (int) $sqlite->querySingle('SELECT COUNT(*) FROM ay_content');
        $lastId = (int) $sqlite->querySingle('SELECT id FROM ay_content ORDER BY id DESC LIMIT 1');
        $lastContent = (string) $sqlite->querySingle("SELECT content FROM ay_content WHERE id={$lastId}");
        $sqlite->close();

        echo "content_after={$after} inserted={$n}\n";
        if ($after !== $n) {
            throw new RuntimeException('sqlite inserted count mismatch');
        }
        if (strpos($lastContent, $probeRel) === false) {
            throw new RuntimeException('last row missing probe img');
        }
        echo "OK: last_id={$lastId} has probe in content\n";
    } else {
        $c = $dbCfgFile['database'];
        if (strpos(strtolower($c['type']), 'sqlite') !== false) {
            throw new RuntimeException('database.php is sqlite; run without --sqlite against that file, or use --sqlite for temp db');
        }
        $mysqli = new mysqli($c['host'], $c['user'], $c['passwd'], $c['dbname'], (int) $c['port']);
        if ($mysqli->connect_error) {
            throw new RuntimeException('mysqli: ' . $mysqli->connect_error);
        }
        $mysqli->set_charset('utf8');

        $row = $mysqli->query('SELECT scode, acode FROM ay_content_sort LIMIT 1')->fetch_assoc();
        if (!$row) {
            throw new RuntimeException('ay_content_sort empty');
        }
        $scode = $mysqli->real_escape_string($row['scode']);
        $acode = $mysqli->real_escape_string($row['acode']);
        $hasPicsTitle = $mysqli->query("SHOW COLUMNS FROM ay_content LIKE 'picstitle'")->num_rows > 0;

        $before = (int) $mysqli->query('SELECT COUNT(*) AS c FROM ay_content')->fetch_assoc()['c'];
        echo "content_before={$before}\n";

        $now = date('Y-m-d H:i:s');
        $fields = $hasPicsTitle
            ? 'acode,scode,subscode,title,titlecolor,subtitle,filename,author,source,outlink,date,ico,pics,picstitle,content,tags,enclosure,keywords,description,sorting,status,istop,isrecommend,isheadline,visits,likes,oppose,create_user,update_user,create_time,update_time,gtype,gid,gnote'
            : 'acode,scode,subscode,title,titlecolor,subtitle,filename,author,source,outlink,date,ico,pics,content,tags,enclosure,keywords,description,sorting,status,istop,isrecommend,isheadline,visits,likes,oppose,create_user,update_user,create_time,update_time,gtype,gid,gnote';

        $batch = 200;
        $i = 0;
        while ($i < $n) {
            $values = array();
            $chunk = min($batch, $n - $i);
            for ($j = 0; $j < $chunk; $j++) {
                $i++;
                $isLast = ($i === $n);
                $title = $mysqli->real_escape_string($marker . '_' . $i);
                $content = $isLast
                    ? $mysqli->real_escape_string('<p><img src="' . $probeRel . '" alt="probe"></p>')
                    : '';
                if ($hasPicsTitle) {
                    $values[] = "('{$acode}','{$scode}','','{$title}','#000000','','','admin','','','{$now}','','','','{$content}','','','','',255,'1','0','0','0',0,0,0,'probe','probe','{$now}','{$now}','4','','')";
                } else {
                    $values[] = "('{$acode}','{$scode}','','{$title}','#000000','','','admin','','','{$now}','','','{$content}','','','','',255,'1','0','0','0',0,0,0,'probe','probe','{$now}','{$now}','4','','')";
                }
            }
            if (!$mysqli->query('INSERT INTO ay_content (' . $fields . ') VALUES ' . implode(',', $values))) {
                throw new RuntimeException('insert fail: ' . $mysqli->error);
            }
        }

        $after = (int) $mysqli->query('SELECT COUNT(*) AS c FROM ay_content')->fetch_assoc()['c'];
        echo "content_after={$after} inserted=" . ($after - $before) . "\n";
        if ($after - $before !== $n) {
            throw new RuntimeException('inserted count mismatch');
        }

        $like = $mysqli->real_escape_string($marker . '_%');
        $rs = $mysqli->query("SELECT id FROM ay_content WHERE title LIKE '{$like}' ORDER BY id ASC");
        while ($x = $rs->fetch_assoc()) {
            $insertedIds[] = (int) $x['id'];
        }
        $lastId = (int) end($insertedIds);
        $last = $mysqli->query("SELECT content FROM ay_content WHERE id={$lastId}")->fetch_assoc();
        if (strpos($last['content'], $probeRel) === false) {
            throw new RuntimeException('last row missing probe img');
        }
        echo "OK: last_id={$lastId} has probe in content\n";
    }

    $model = new ContentModel();
    $memBefore = memory_get_usage(true);
    $t0 = microtime(true);
    $list = $model->getImage();
    $elapsed = round((microtime(true) - $t0) * 1000);
    $memAfter = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    echo "getImage rows=" . count($list) . " elapsed_ms={$elapsed} mem_delta=" . ($memAfter - $memBefore) . " peak={$peak}\n";

    if (count($list) !== $after) {
        throw new RuntimeException('getImage row count ' . count($list) . ' != content total ' . $after);
    }
    echo "OK: scan count matches table (no miss/dup at row level)\n";

    $dataArr = array();
    array_walk_recursive($list, function ($key1) use (&$dataArr) {
        if (!empty($key1)) {
            $dataArr[] = DOC_PATH . SITE_DIR . $key1;
        }
    });
    $dataArr = array_unique($dataArr);
    $refNorms = array_map($norm, $dataArr);
    $probeNorm = $norm($probeAbs);

    if (!in_array($probeNorm, $refNorms, true)) {
        $hits = array();
        foreach ($refNorms as $p) {
            if (strpos($p, 'test_probe_getimage_' . $n) !== false) {
                $hits[] = $p;
            }
        }
        throw new RuntimeException('probe NOT in reference set. similar=' . json_encode($hits));
    }
    echo "OK: probe is in reference set\n";

    $Filepath = DOC_PATH . STATIC_DIR . '/upload';
    $fileList = get_dir($Filepath);
    $fileArr = array();
    array_walk_recursive($fileList, function ($key) use (&$fileArr) {
        $fileArr[] = $key;
    });
    $diff = array_diff(array_map($norm, $fileArr), $refNorms);
    if (in_array($probeNorm, $diff, true)) {
        throw new RuntimeException('FAIL: probe appeared in redundant diff');
    }
    echo "OK: probe NOT in redundant list\n";
    echo "PASS N={$n} driver={$driverLabel}\n";
    $code = 0;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $code = 1;
}

if (!$keep) {
    try {
        if ($mysqli && $insertedIds) {
            $ids = implode(',', array_map('intval', $insertedIds));
            $mysqli->query("DELETE FROM ay_content WHERE id IN ({$ids})");
            echo "cleaned inserted rows=" . count($insertedIds) . "\n";
        }
        if (is_file($probeAbs)) {
            @unlink($probeAbs);
            echo "cleaned probe file\n";
        }
        if ($sqlitePath && is_file($sqlitePath)) {
            @unlink($sqlitePath);
            echo "cleaned sqlite temp db\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'cleanup warn: ' . $e->getMessage() . "\n");
    }
} else {
    echo "KEEP marker={$marker} probe={$probeAbs}\n";
}

exit($code);

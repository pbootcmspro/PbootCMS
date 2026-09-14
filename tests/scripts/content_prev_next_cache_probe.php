<?php
/**
 * #233 跟进：上下篇键控缓存线上/本机验收
 *
 * 用法:
 *   php tests/scripts/content_prev_next_cache_probe.php
 *   set PBOOT_TEST_BASE_URL=http://dev.pbootcms.lan
 *   php tests/scripts/content_prev_next_cache_probe.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'dev.pbootcms.lan';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_COOKIE['lg'] = 'cn'; // CLI 无 cookie 时 get_lg() 会因 lgs 未加载报错

define('IS_INDEX', true);
define('URL_BIND', 'home');
require $root . '/core/init.php';
require APP_PATH . '/common/function.php';

use app\home\model\ParserModel;
use app\home\controller\ParserController;

$code = 0;

function probe_ok($cond, $msg)
{
    global $code;
    if ($cond) {
        echo "OK: $msg\n";
    } else {
        echo "FAIL: $msg\n";
        $code = 1;
    }
}

function probe_http($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array('Cache-Control: no-cache'),
    ));
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        throw new RuntimeException("HTTP fail {$url}: {$err}");
    }
    return array($status, (string) $body);
}

echo 'PHP ' . PHP_VERSION . "\n";

echo "=== model: consecutive getContentPre distinct keys ===\n";

$model = new ParserModel();
// 本站 MySQL：id=6 为栏目4首篇无上一篇；用 id=7(→6) 与 id=11(→10) 验证不串缓存
$idA = 7;
$idB = 11;
$cA = $model->getContent($idA);
$cB = $model->getContent($idB);
probe_ok($cA && isset($cA->id), "content id={$idA} exists");
probe_ok($cB && isset($cB->id), "content id={$idB} exists");

if ($cA && $cB) {
    $scodeA = (string) $cA->scode;
    $scodeB = (string) $cB->scode;
    echo "id={$idA} scode={$scodeA} title={$cA->title}\n";
    echo "id={$idB} scode={$scodeB} title={$cB->title}\n";

    // 先查有结果的一篇，再查另一篇（旧单槽缓存会把第二篇污染成第一篇）
    $preB = $model->getContentPre($scodeB, $cB->id);
    $preA = $model->getContentPre($scodeA, $cA->id);

    $pidA = $preA ? (int) $preA->id : 0;
    $pidB = $preB ? (int) $preB->id : 0;
    echo "pre({$idB}) => id={$pidB}" . ($preB ? " title={$preB->title}" : '') . "\n";
    echo "pre({$idA}) => id={$pidA}" . ($preA ? " title={$preA->title}" : '') . "\n";

    probe_ok($preA && $preB, 'both prev rows found');
    probe_ok($pidA > 0 && $pidB > 0 && $pidA !== $pidB, 'second call not polluted by first (ids differ)');
    probe_ok($pidA === 6, 'id=7 prev is id=6');
    probe_ok($pidB === 10, 'id=11 prev is id=10');
}

echo "=== model: empty then other key still queries ===\n";
$model2 = new ParserModel();
$empty = $model2->getContentPre('4', 1); // 栏目4内 id<1 无上一篇
$later = $model2->getContentPre((string) $cA->scode, $cA->id);
probe_ok($empty === false || $empty === null || $empty === array(), 'empty pre for impossible id');
probe_ok($later && isset($later->id) && (int) $later->id === 6, 'after empty cache, real pre still works');

echo "=== parserSpecifyContentLabel: two blocks ===\n";

$ref = new ReflectionClass(ParserController::class);
$parser = $ref->newInstanceWithoutConstructor();
$modelProp = $ref->getProperty('model');
if (PHP_VERSION_ID < 80100) {
    $modelProp->setAccessible(true);
}
$modelProp->setValue($parser, new ParserModel());
$preProp = $ref->getProperty('pre');
if (PHP_VERSION_ID < 80100) {
    $preProp->setAccessible(true);
}
$preProp->setValue($parser, array());
$varProp = $ref->getProperty('var');
if (PHP_VERSION_ID < 80100) {
    $varProp->setAccessible(true);
}
$varProp->setValue($parser, array());

$tpl = "A:{pboot:content id={$idA}}[content:pretitle]{/pboot:content}"
    . "|B:{pboot:content id={$idB}}[content:pretitle]{/pboot:content}";
$out = $parser->parserSpecifyContentLabel($tpl);
echo "OUT: {$out}\n";

if (preg_match('/A:(.*)\|B:(.*)$/u', $out, $m)) {
    $titleA = trim($m[1]);
    $titleB = trim($m[2]);
    probe_ok($titleA !== '' && $titleB !== '', 'both blocks rendered');
    probe_ok($titleA !== $titleB, 'two blocks have distinct pre titles (not cache bleed)');
    probe_ok(strpos($out, '没有了！') === false, 'not empty fallback text');
    probe_ok(strpos($titleA, '锤子') !== false || (int) $pidA === 6, 'block A shows prev of id=7');
    probe_ok(strpos($titleB, '基础版') !== false || (int) $pidB === 10, 'block B shows prev of id=11');
} else {
    probe_ok(false, 'parse output shape A:…|B:…');
}

$base = getenv('PBOOT_TEST_BASE_URL');
if (is_string($base) && trim($base) !== '') {
    $base = rtrim(trim($base), '/');
    echo "=== HTTP inject two {pboot:content} blocks on home ===\n";

    $tplFile = $root . '/template/default/html/comm/head.html';
    $marker = 'PREVNEXT_CACHE_PROBE';
    if (! is_file($tplFile)) {
        echo "SKIP HTTP: template missing\n";
    } else {
        $original = file_get_contents($tplFile);
        $probeBlock = "\n<!--{$marker}_START-->"
            . "<div id=\"{$marker}\">"
            . "A:{pboot:content id=7}[content:pretitle]{/pboot:content}"
            . "|B:{pboot:content id=11}[content:pretitle]{/pboot:content}"
            . "</div>"
            . "<!--{$marker}_END-->\n";
        $injected = $original . $probeBlock;
        $httpFail = false;
        try {
            if (file_put_contents($tplFile, $injected) === false) {
                throw new RuntimeException('failed to write template');
            }
            foreach (array('complile', 'cache') as $sub) {
                $dir = $root . '/runtime/' . $sub;
                if (! is_dir($dir)) {
                    continue;
                }
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        @unlink($f->getPathname());
                    }
                }
            }

            list($stHome, $home) = probe_http($base . '/');
            probe_ok($stHome === 200, "home HTTP {$stHome}");
            probe_ok(strpos($home, "id=\"{$marker}\"") !== false, 'probe marker present');
            probe_ok(strpos($home, 'Fatal error') === false && strpos($home, 'Parse error') === false, 'home no fatal');

            if (preg_match('/id="' . preg_quote($marker, '/') . '">A:(.*?)\|B:(.*?)<\/div>/s', $home, $hm)) {
                $titleA = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES, 'UTF-8'));
                $titleB = trim(html_entity_decode(strip_tags($hm[2]), ENT_QUOTES, 'UTF-8'));
                echo "HTTP A pretitle={$titleA}\n";
                echo "HTTP B pretitle={$titleB}\n";
                probe_ok($titleA !== '' && $titleB !== '' && $titleA !== '没有了！' && $titleB !== '没有了！', 'HTTP both pretitles non-empty');
                probe_ok($titleA !== $titleB, 'HTTP two blocks distinct pretitles (no cache bleed)');
            } else {
                probe_ok(false, 'HTTP probe block parse A|B');
            }
        } catch (Exception $e) {
            $httpFail = true;
            echo 'HTTP ERR: ' . $e->getMessage() . "\n";
            $code = 1;
        }
        if (file_put_contents($tplFile, $original) === false) {
            fwrite(STDERR, "CRITICAL: failed to restore template\n");
            exit(2);
        }
        echo "template restored\n";
        if ($httpFail) {
            probe_ok(false, 'HTTP inject probe completed without exception');
        }
    }
} else {
    echo "SKIP HTTP: set PBOOT_TEST_BASE_URL for front smoke\n";
}

exit($code);

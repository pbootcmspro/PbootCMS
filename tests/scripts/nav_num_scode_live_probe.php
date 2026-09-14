<?php

/**
 * 前台活体探测：临时注入 {pboot:nav} 验收标签，HTTP 拉取首页后还原模板。
 *
 * 用法: php tests/scripts/nav_num_scode_live_probe.php [base_url]
 */

$base = isset($argv[1]) ? rtrim($argv[1], '/') : 'http://dev.pbootcms.lan';
$root = dirname(__DIR__, 2);
$tpl = $root . '/template/default/html/comm/head.html';
$marker = 'NAV235_PROBE';

if (! is_file($tpl)) {
    fwrite(STDERR, "template missing: $tpl\n");
    exit(1);
}

$original = file_get_contents($tpl);
if ($original === false) {
    fwrite(STDERR, "cannot read template\n");
    exit(1);
}

// 站点顶级栏目含 8、9（服务案例、招贤纳士），位于前 2 条之外，可复现旧 bug
$probeBlock = "\n<!--{$marker}_START-->"
    . "<div id=\"{$marker}\">"
    . "A:{pboot:nav num=2 scode=8,9}[nav:scode]{/pboot:nav};"
    . "B:{pboot:nav num=2}[nav:scode]{/pboot:nav};"
    . "C:{pboot:nav scode=8,9}[nav:scode]{/pboot:nav};"
    . "</div>"
    . "<!--{$marker}_END-->\n";

$injected = str_replace('</body>', $probeBlock . '</body>', $original);
if ($injected === $original) {
    // head.html 无 </body>，插到文件末尾
    $injected = $original . $probeBlock;
}

$httpBody = '';
$status = 0;

try {
    if (file_put_contents($tpl, $injected) === false) {
        throw new RuntimeException('failed to write template');
    }

    // 清编译/页面缓存，避免旧 HTML
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

    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "User-Agent: nav235-probe\r\n",
        ),
    ));
    $httpBody = @file_get_contents($base . '/', false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($httpBody === false || $status >= 400) {
        throw new RuntimeException("HTTP fetch failed status=$status");
    }

    if (strpos($httpBody, "id=\"{$marker}\"") === false && strpos($httpBody, "id='{$marker}'") === false) {
        // 可能被缓存/未包含 head；仍尝试按片段断言
        fwrite(STDERR, "WARN: probe marker HTML id not found in response\n");
    }

    $extract = static function ($label, $body) {
        if (! preg_match('/' . preg_quote($label, '/') . ':([^;]*);/', $body, $m)) {
            return null;
        }
        return preg_replace('/\s+/', '', $m[1]);
    };

    $a = $extract('A', $httpBody);
    $b = $extract('B', $httpBody);
    $c = $extract('C', $httpBody);

    echo "HTTP status: $status\n";
    echo "A(num=2 scode=8,9): " . var_export($a, true) . "\n";
    echo "B(num=2): " . var_export($b, true) . "\n";
    echo "C(scode=8,9): " . var_export($c, true) . "\n";

    $fail = 0;
    if ($a !== '89') {
        echo "FAIL: A expected 89 (range then num)\n";
        $fail++;
    } else {
        echo "OK: A acceptance\n";
    }
    if ($b !== '12') {
        echo "FAIL: B expected 12 (only num, top order 1,2,...)\n";
        $fail++;
    } else {
        echo "OK: B only num\n";
    }
    if ($c !== '89') {
        echo "FAIL: C expected 89 (only scode)\n";
        $fail++;
    } else {
        echo "OK: C only scode\n";
    }

    // 先还原模板再 exit，避免部分环境下 exit 与 finally 交互导致未还原
    if (file_put_contents($tpl, $original) === false) {
        fwrite(STDERR, "CRITICAL: failed to restore template\n");
        exit(2);
    }
    echo "template restored\n";
    exit($fail > 0 ? 1 : 0);
} catch (Throwable $e) {
    file_put_contents($tpl, $original);
    fwrite(STDERR, 'PROBE ERR: ' . $e->getMessage() . "\n");
    echo "template restored (after error)\n";
    exit(1);
}

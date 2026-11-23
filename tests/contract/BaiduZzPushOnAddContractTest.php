<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ContentController::add 百度普通收录自动推送挂钩；手动推送超时不变
 *
 *   php tests/run.php --suite=contract --filter=BaiduZzPushOnAdd
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 截取 class 内指定方法体（到同级下一个 method/class 结束前）
 */
function baidu_zz_extract_method($src, $name)
{
    $needle = 'function ' . $name . '(';
    $pos = strpos($src, $needle);
    if ($pos === false) {
        return false;
    }
    $brace = strpos($src, '{', $pos);
    if ($brace === false) {
        return false;
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $brace; $i < $len; $i++) {
        $ch = $src[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $brace, $i - $brace + 1);
            }
        }
    }
    return false;
}

return TestAssert::runSuite(function () {
    echo "=== ContentController::add 挂钩顺序 ===\n";

    $ctrlPath = APP_PATH . '/admin/controller/content/ContentController.php';
    $ctrl = file_get_contents($ctrlPath);
    TestAssert::false($ctrl === false, 'ContentController readable');

    $addBody = baidu_zz_extract_method($ctrl, 'add');
    TestAssert::false($addBody === false, 'add() body found');
    TestAssert::contains($addBody, 'pushBaiduZzOnAdd(', 'add calls pushBaiduZzOnAdd');
    TestAssert::contains($addBody, 'success($msg', 'add still calls success($msg');

    $extPos = strpos($addBody, 'addContentExt($data2)');
    $pushPos = strpos($addBody, 'pushBaiduZzOnAdd(');
    $successPos = strpos($addBody, 'success($msg');
    TestAssert::true($extPos !== false && $pushPos !== false && $successPos !== false, 'ext/push/success anchors exist');
    TestAssert::true($extPos < $pushPos, 'push after addContentExt');
    TestAssert::true($pushPos < $successPos, 'push before success()');

    echo "=== pushBaiduZzOnAdd 约束 ===\n";

    $pushBody = baidu_zz_extract_method($ctrl, 'pushBaiduZzOnAdd');
    TestAssert::false($pushBody === false, 'pushBaiduZzOnAdd() body found');
    TestAssert::contains($pushBody, 'post_baidu($api, array($url), 5, 10)', 'auto push uses 5/10 timeout');
    TestAssert::contains($pushBody, 'urlencode(', 'site/token urlencoded');
    TestAssert::contains($pushBody, 'parserLink(2,', 'server-side parserLink');
    TestAssert::notContains($pushBody, 'alert_back(', 'push does not alert_back');
    TestAssert::notContains($pushBody, 'error(', 'push does not error()');
    TestAssert::notContains($pushBody, "post('urls'", 'push ignores client urls');
    TestAssert::notContains($pushBody, 'type=daily', 'push is ordinary zz only');
    TestAssert::notContains($pushBody, '->message', 'push does not echo raw message');
    preg_match_all('/\$this->log\(([^;]+);/', $pushBody, $logCalls);
    TestAssert::true(count($logCalls[1]) >= 1, 'push writes system log');
    foreach ($logCalls[1] as $i => $args) {
        TestAssert::notContains($args, '$token', 'log call #' . ($i + 1) . ' does not include $token');
        TestAssert::notContains($args, '->message', 'log call #' . ($i + 1) . ' does not include raw message');
    }

    echo "=== 手动批量/单页推送超时不变 ===\n";

    $modBody = baidu_zz_extract_method($ctrl, 'mod');
    TestAssert::false($modBody === false, 'mod() body found');
    TestAssert::contains($modBody, 'post_baidu($api, $post_urls)', 'batch baiduzz keeps default timeout call');
    TestAssert::notContains($modBody, 'post_baidu($api, $post_urls, 5, 10)', 'batch does not use auto timeout');

    $singlePath = APP_PATH . '/admin/controller/content/SingleController.php';
    $single = file_get_contents($singlePath);
    TestAssert::false($single === false, 'SingleController readable');
    TestAssert::contains($single, 'post_baidu($api, $urls)', 'single keeps default timeout call');
    TestAssert::notContains($single, 'post_baidu($api, $urls, 5, 10)', 'single does not use auto timeout');

    echo "=== 新增页勾选项 ===\n";

    $viewPath = APP_PATH . '/admin/view/default/content/content.html';
    $view = file_get_contents($viewPath);
    TestAssert::false($view === false, 'content.html readable');

    $count = preg_match_all('/\bname="baidu_zz_push"/', $view, $m);
    TestAssert::same(1, $count, 'baidu_zz_push appears once');

    $pushTagPos = strpos($view, 'name="baidu_zz_push"');
    $modPos = strpos($view, '{if([$mod])}');
    TestAssert::true($pushTagPos !== false && $modPos !== false, 'checkbox and mod block found');
    TestAssert::true($pushTagPos < $modPos, 'checkbox is in add form before mod block');

    $before = substr($view, 0, $pushTagPos);
    $ifPos = strrpos($before, '{if([$baidu_zz_token])}');
    TestAssert::true($ifPos !== false, 'checkbox wrapped by baidu_zz_token if');
    $between = substr($view, $ifPos, $pushTagPos - $ifPos);
    TestAssert::notContains($between, '{/if}', 'token if still open at checkbox');

    echo "=== ModelModel::getUrlname ===\n";

    $modelPath = APP_PATH . '/admin/model/content/ModelModel.php';
    $model = file_get_contents($modelPath);
    TestAssert::false($model === false, 'ModelModel readable');
    TestAssert::contains($model, 'function getUrlname($mcode)', 'getUrlname exists');
});

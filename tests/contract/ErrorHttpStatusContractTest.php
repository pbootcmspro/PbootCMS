<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers error() 前台语义化 HTTP 状态码调用点
 * @covers error() AJAX 默认 200 与 is_ajax() XHR 检测
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== helper.php error() signature and _404() fallback ===\n";

    $helper = file_get_contents(CORE_PATH . '/function/helper.php');
    TestAssert::false($helper === false, 'helper.php readable');
    TestAssert::contains($helper, 'function error($string, $jump_url = null, $time = 2, $status = null)', 'error() auto status param');
    TestAssert::contains($helper, '$is_ajax = is_ajax();', 'error() resolves XHR once');
    TestAssert::contains($helper, '$as_json = Config::get(\'return_data_type\') == \'json\' || $is_ajax;', 'error() body uses json module or XHR');
    TestAssert::contains($helper, '$status = $is_ajax ? 200 : 404;', 'error() defaults XHR→200 HTML→404');
    TestAssert::contains($helper, 'http_response_code($status);', 'error() uses status param');
    TestAssert::contains($helper, 'error($string, $jump_url, $time, 404);', '_404() passes explicit 404 to error()');

    echo "=== is_ajax() checks X-Requested-With only ===\n";

    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'HTTP_X_REQUESTED_WITH', 'is_ajax checks X-Requested-With');
    TestAssert::false(strpos($handle, 'application/json') !== false, 'is_ajax does not check Accept JSON');

    echo "=== frontend permission and config errors use semantic codes ===\n";

    $index = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    TestAssert::contains($index, "error('请到后台设置分类栏目列表页模板！', null, 2, 500);", 'IndexController listtpl missing → 500');
    TestAssert::contains($index, "error('请到后台设置分类栏目内容页模板！', null, 2, 500);", 'IndexController contenttpl missing → 500');
    TestAssert::contains($index, 'error($gnote, null, 2, 403);', 'IndexController logged-in deny → 403');
    TestAssert::contains($index, 'error($gnote, Url::home(', 'IndexController login redirect deny keeps jump url');
    TestAssert::contains($index, ', 403);', 'IndexController unauthenticated deny → 403');

    $parser = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::contains($parser, "error('您的权限不足，无法浏览本页面！', Url::home('member/login', null, \"backurl=\" . urlencode(get_current_url())), 2, 403);", 'ParserController mustlogin → 403');
    TestAssert::contains($parser, "error('请不要在一个页面使用多个具有分页的列表，您可将多余的使用page=0关闭分页！', null, 2, 500);", 'ParserController duplicate paging → 500');
    TestAssert::contains($parser, "error('模板中指定id输出tags时不允许scode指定多个栏目！', null, 2, 500);", 'ParserController tags scode conflict → 500');

    $homeCommon = file_get_contents(APP_PATH . '/common/HomeController.php');
    TestAssert::false($homeCommon === false, 'HomeController readable');
    TestAssert::contains($homeCommon, "error(\$close_site_note ?: '本站维护中，请稍后再访问，带来不便，敬请谅解！', null, 2, 503);", 'HomeController site closed → 503');
    TestAssert::contains($homeCommon, "error('本站启用了黑名单功能，您的IP(' . \$user_ip . ')不允许访问！', null, 2, 403);", 'HomeController IP deny → 403');
    TestAssert::contains($homeCommon, "error('本站启用了白名单功能，您的IP(' . \$user_ip . ')不在允许范围！', null, 2, 403);", 'HomeController IP allow → 403');

    $member = file_get_contents(APP_PATH . '/home/controller/MemberController.php');
    TestAssert::contains($member, "error('系统已经关闭登录功能，请到后台开启再试！', null, 2, 403);", 'MemberController login disabled → 403');
    TestAssert::contains($member, "error('系统已经关闭注册功能，请到后台开启再试！', null, 2, 403);", 'MemberController register disabled → 403');
    TestAssert::contains($member, "alert_back('会员注册失败！');", 'MemberController register failure uses alert_back');
    TestAssert::contains($member, "alert_back('资料修改失败！');", 'MemberController profile failure uses alert_back');

    $comment = file_get_contents(APP_PATH . '/home/controller/CommentController.php');
    TestAssert::contains($comment, "error('系统已经关闭评论功能，请到后台开启再试！', null, 2, 403);", 'CommentController comment disabled → 403');

    $form = file_get_contents(APP_PATH . '/home/controller/FormController.php');
    TestAssert::contains($form, "error('系统已经关闭表单功能，请到后台开启再试！', null, 2, 403);", 'FormController form disabled → 403');

    $message = file_get_contents(APP_PATH . '/home/controller/MessageController.php');
    TestAssert::contains($message, "error('系统已经关闭留言功能，请到后台开启再试！', null, 2, 403);", 'MessageController message disabled → 403');

    echo "=== admin checkLevel still surfaces permission text via error() ===\n";

    $adminCommon = file_get_contents(APP_PATH . '/common/AdminController.php');
    TestAssert::contains($adminCommon, "error('您的账号权限不足，您无法执行该操作！');", 'checkLevel permission error keeps message');

    echo "=== true missing pages still use _404() ===\n";

    TestAssert::contains($index, "_404('您访问的内容不存在，请核对后重试！');", 'IndexController missing content still _404');
    TestAssert::contains($parser, "_404('您访问的地址有误，请核对后重试！');", 'ParserController bad address still _404');
});

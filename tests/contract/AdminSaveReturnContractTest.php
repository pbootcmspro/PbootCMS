<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/admin/controller remaining save/delete return targets
 *
 * 运行：php tests/contract/AdminSaveReturnContractTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== reproduced admin saves return to their own pages ===\n";

    $cases = array(
        array('apps/admin/controller/content/ContentController.php', 'function mod(', '单独修改状态', '修改操作', 'admin_model_list_url($id)', '文章状态'),
        array('apps/admin/controller/content/ContentSortController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/ContentSort/index')", '栏目状态'),
        array('apps/admin/controller/content/SingleController.php', 'function mod(', '单独修改状态', '修改操作', 'admin_model_list_url($id, true)', '单页状态'),
        array('apps/admin/controller/content/ModelController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/Model/index')", '模型状态'),
        array('apps/admin/controller/content/MessageController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/Message/index')", '留言状态'),
        array('apps/admin/controller/member/MemberCommentController.php', 'function mod(', '单独修改状态', '}', "url('/admin/MemberComment/index')", '评论状态'),
        array('apps/admin/controller/system/MenuController.php', 'function mod(', '单独修改状态', '修改操作', "url('admin/Menu/index')", '菜单状态'),
        array('apps/admin/controller/member/MemberGroupController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/MemberGroup/index')", '会员等级状态'),
        array('apps/admin/controller/content/SlideController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/Slide/index')", '幻灯片状态'),
        array('apps/admin/controller/member/MemberController.php', 'function mod(', '单独修改状态', '修改操作', '$memberUrl', '会员状态'),
        array('apps/admin/controller/system/UserController.php', 'function mod(', '单独修改状态', '修改操作', "url('/admin/User/index')", '用户状态'),
    );

    foreach ($cases as $case) {
        $source = file_get_contents(APP_PATH . '/' . substr($case[0], strlen('apps/')));
        TestAssert::false($source === false, $case[5] . ' readable');
        $method = save_return_method_body($source, $case[1]);
        $block = save_return_between($method, $case[2], $case[3]);
        TestAssert::contains($block, $case[4], $case[5] . ' returns to its admin list');
        TestAssert::notContains($block, 'location(-1)', $case[5] . ' does not use compact referer sentinel');
        TestAssert::notContains($block, 'location(- 1)', $case[5] . ' does not use spaced referer sentinel');
    }

    $sentinelFiles = array(
        'admin/controller/content/SiteController.php',
        'admin/controller/content/CompanyController.php',
        'admin/controller/content/SlideController.php',
        'admin/controller/content/ExtFieldController.php',
        'admin/controller/content/FormController.php',
        'admin/controller/content/LabelController.php',
        'admin/controller/member/MemberController.php',
        'admin/controller/member/MemberFieldController.php',
        'admin/controller/system/TypeController.php',
        'admin/controller/system/UserController.php',
        'admin/controller/system/RoleController.php',
        'admin/controller/system/AreaController.php',
        'admin/controller/system/DatabaseController.php',
        'admin/controller/system/SyslogController.php',
    );
    foreach ($sentinelFiles as $rel) {
        $source = file_get_contents(APP_PATH . '/' . $rel);
        TestAssert::false($source === false, $rel . ' readable');
        TestAssert::notContains($source, 'base64_decode($backurl)', $rel . ' does not trust raw backurl');
        TestAssert::notContains($source, 'location(- 1)', $rel . ' does not location(-1)');
        TestAssert::notContains($source, 'location(-1)', $rel . ' does not location(-1 compact)');
        TestAssert::notContains($source, ", - 1)", $rel . ' does not jump with -1');
        TestAssert::notContains($source, ", -1)", $rel . ' does not jump with compact -1');
    }

    $commonFn = file_get_contents(APP_PATH . '/common/function.php');
    $listUrl = save_return_method_body($commonFn, 'function admin_model_list_url(');
    TestAssert::contains($listUrl, "'/admin/Content/index/mcode/'", 'content list keeps the model');
    TestAssert::contains($listUrl, "'/admin/Single/index/mcode/'", 'single list keeps the model');
    TestAssert::contains($listUrl, "return url('/admin/Index/home');", 'missing model returns to the admin home');
    TestAssert::notContains(file_get_contents(CORE_PATH . '/function/helper.php'), 'function admin_model_list_url(', 'list url left the core helper');
    TestAssert::contains($commonFn, 'function admin_resolve_fallback(', 'posted field can delay fallback');

    $contentMod = save_return_method_body(file_get_contents(APP_PATH . '/admin/controller/content/ContentController.php'), 'function mod(');
    $contentStatus = save_return_between($contentMod, '单独修改状态', '修改操作');
    TestAssert::contains($contentStatus, 'function () use ($id)', 'content status fallback is lazy');
    TestAssert::notContains($contentStatus, 'admin_posted_field(array(\'status\', \'istop\', \'isrecommend\'), admin_model_list_url($id))', 'content status does not eager-evaluate list url');

    $deletes = array(
        array('admin/controller/member/MemberGroupController.php', "/admin/MemberGroup/index", '会员等级删除'),
        array('admin/controller/content/TagsController.php', "/admin/Tags/index", '内链删除'),
        array('admin/controller/content/LinkController.php', "/admin/Link/index", '友情链接删除'),
        array('admin/controller/content/SlideController.php', "/admin/Slide/index", '幻灯片删除'),
        array('admin/controller/system/TypeController.php', "/admin/Type/index", '类型删除'),
        array('admin/controller/system/RoleController.php', "admin/Role/index", '角色删除'),
    );
    foreach ($deletes as $delete) {
        $source = file_get_contents(APP_PATH . '/' . $delete[0]);
        $method = save_return_method_body($source, 'function del(');
        TestAssert::contains($method, "REQUEST_METHOD", $delete[2] . ' requires POST');
        TestAssert::contains($method, "success('删除成功！'", $delete[2] . ' success uses explicit target');
        TestAssert::notContains($method, "success('删除成功！', - 1)", $delete[2] . ' success does not use referer');
        TestAssert::notContains($method, "success('删除成功！', -1)", $delete[2] . ' success does not use compact referer');
        TestAssert::contains($method, $delete[1], $delete[2] . ' keeps module list');
    }

    $area = file_get_contents(APP_PATH . '/admin/controller/system/AreaController.php');
    $areaDel = save_return_method_body($area, 'function del(');
    TestAssert::contains($areaDel, "url('/admin/Index/index')", 'area delete still forces re-login');

    $site = file_get_contents(APP_PATH . '/admin/controller/content/SiteController.php');
    TestAssert::contains($site, "url('/admin/Site/index')", 'site save returns to site page');
    TestAssert::contains($site, 'admin_saved(', 'site uses admin_saved for unchanged updates');
    TestAssert::contains($site, "alert_back('修改失败！');", 'site save failure keeps the form via history back');
    TestAssert::notContains($site, "error('修改失败！'", 'site save failure does not jump away with error()');
    $company = file_get_contents(APP_PATH . '/admin/controller/content/CompanyController.php');
    TestAssert::contains($company, "url('/admin/Company/index')", 'company save returns to company page');
    TestAssert::contains($company, 'admin_saved(', 'company uses admin_saved for unchanged updates');
    TestAssert::contains($company, "alert_back('修改失败！');", 'company save failure keeps the form via history back');
    TestAssert::notContains($company, "error('修改失败！'", 'company save failure does not jump away with error()');

    $content = file_get_contents(APP_PATH . '/admin/controller/content/ContentController.php');
    $contentAdd = save_return_method_body($content, 'function add(');
    TestAssert::contains($contentAdd, "alert_back('新增失败！');", 'content add failure keeps the form via history back');
    TestAssert::notContains($contentAdd, "error('新增失败！'", 'content add failure does not jump to list');
    $contentMod = save_return_method_body($content, 'function mod(');
    $contentFormFail = save_return_between($contentMod, '修改操作', '调取修改内容');
    TestAssert::contains($contentFormFail, "error('编辑的内容已经不存在！'", 'missing content jumps to list instead of history back');
    TestAssert::notContains($contentFormFail, "alert_back('修改失败！')", 'missing content does not alert_back as generic save failure');
    $contentStatusFail = save_return_between($contentMod, '单独修改状态', '修改操作');
    TestAssert::contains($contentStatusFail, "error('修改失败！'", 'content status failure still returns via error()');

    $form = file_get_contents(APP_PATH . '/admin/controller/content/FormController.php');
    $formClear = save_return_method_body($form, 'function clear(');
    TestAssert::contains($formClear, "alert_location('清空成功！', \$back, 1)", 'form clear success status is 1');
    TestAssert::contains($formClear, 'REQUEST_METHOD', 'form clear requires POST');

    $syslog = file_get_contents(APP_PATH . '/admin/controller/system/SyslogController.php');
    $syslogClear = save_return_method_body($syslog, 'function clear(');
    TestAssert::contains($syslogClear, "alert_location('清空成功！', \$back, 1)", 'syslog clear success status is 1');
    TestAssert::contains($syslogClear, 'REQUEST_METHOD', 'syslog clear requires POST');

    $tags = file_get_contents(APP_PATH . '/admin/controller/content/TagsController.php');
    $tagsMod = save_return_method_body($tags, 'function mod(');
    $unchanged = save_return_between($tagsMod, 'modTags($id, $data)', '调取修改内容');
    TestAssert::notContains($unchanged, 'location(', 'unchanged tag save does not follow referer');
    TestAssert::contains($unchanged, 'getTags($id)', 'unchanged tag save checks the record');
    TestAssert::contains($unchanged, "success('修改成功！', url('/admin/Tags/index'))", 'unchanged tag save returns to the tag list');
    TestAssert::contains($unchanged, "error('编辑的内容已经不存在！', url('/admin/Tags/index'))", 'missing tag jumps to the tag list');
    TestAssert::notContains($unchanged, "alert_back('编辑的内容已经不存在！')", 'missing tag does not history-back to a stale edit page');

    $index = file_get_contents(APP_PATH . '/admin/controller/IndexController.php');
    $ucenter = save_return_method_body($index, 'function ucenter(');
    TestAssert::contains($ucenter, "success('用户资料修改成功！', url('/admin/Index/ucenter'))", 'profile save returns to ucenter');
    TestAssert::contains($ucenter, "alert_location('当前密码错误！', url('/admin/Index/ucenter'))", 'wrong password returns to ucenter');
    TestAssert::notContains($ucenter, '-1', 'ucenter does not use the referer sentinel');

    $version = file_get_contents(APP_PATH . '/common/version.php');
    TestAssert::contains($version, "'3.2.32'", 'app version is 3.2.32');
    $foot = file_get_contents(APP_PATH . '/admin/view/default/common/foot.html');
    TestAssert::contains($foot, 'comm.js?v=v3.2.33', 'comm.js cache bust is v3.2.33');

    $commJs = file_get_contents(APP_PATH . '/admin/view/default/js/comm.js');
    TestAssert::contains($commJs, "data-pboot-ephemeral", 'pboot-post marks ephemeral forms');
    TestAssert::contains($commJs, 'function pbootDisposeForm(form)', 'ephemeral forms are disposed after submit');

    $siteTpl = file_get_contents(APP_PATH . '/admin/view/default/content/site.html');
    TestAssert::contains($siteTpl, 'pboot-ajax', 'site form uses ajax submit');
    $companyTpl = file_get_contents(APP_PATH . '/admin/view/default/content/company.html');
    TestAssert::contains($companyTpl, 'pboot-ajax', 'company form uses ajax submit');
    $contentTpl = file_get_contents(APP_PATH . '/admin/view/default/content/content.html');
    TestAssert::notContains($contentTpl, 'class="switch"', 'content status no longer uses legacy switch links');
    TestAssert::contains($contentTpl, 'admin_switch_btn($value->id,\'status\',\'0\'', 'content status uses post switch');
    TestAssert::contains($contentTpl, 'admin_switch_btn($value->id,\'status\',\'1\'', 'content status enable uses post switch');

    $groupTpl = file_get_contents(APP_PATH . '/admin/view/default/member/group.html');
    TestAssert::notContains($groupTpl, 'class="switch"', 'member group status no longer uses legacy switch links');
    TestAssert::contains($groupTpl, 'admin_switch_btn($value->id,\'status\',\'0\'', 'member group disable uses post switch');
    TestAssert::contains($groupTpl, 'admin_switch_btn($value->id,\'status\',\'1\'', 'member group enable uses post switch');

    foreach (glob(APP_PATH . '/admin/view/default/*/*.html') as $tpl) {
        TestAssert::notContains(file_get_contents($tpl), 'data-after="nav" data-', basename($tpl) . ' add/edit forms use plain submit');
        TestAssert::false(preg_match('/<form[^>]*pboot-ajax[^>]*data-after="nav"/', file_get_contents($tpl)) === 1, basename($tpl) . ' add/edit forms are not ajax');
    }
});

function save_return_method_body($source, $signature)
{
    $start = strpos($source, $signature);
    TestAssert::false($start === false, $signature . ' found');
    $from = $start + strlen($signature);
    $candidates = array();
    foreach (array("\n    public function ", "\n    private function ") as $marker) {
        $pos = strpos($source, $marker, $from);
        if ($pos !== false) {
            $candidates[] = $pos;
        }
    }
    if (count($candidates) === 0) {
        return substr($source, $start);
    }
    $next = min($candidates);
    return substr($source, $start, $next - $start);
}

function save_return_between($source, $startMarker, $endMarker)
{
    $start = strpos($source, $startMarker);
    TestAssert::false($start === false, $startMarker . ' found');
    $end = strpos($source, $endMarker, $start + strlen($startMarker));
    TestAssert::false($end === false, $endMarker . ' found after ' . $startMarker);
    return substr($source, $start, $end - $start);
}

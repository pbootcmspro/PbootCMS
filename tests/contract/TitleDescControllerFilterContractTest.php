<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ContentController / ContentSortController / SiteController 标题与描述清洗链路
 *
 * 审计 #8：防止保存入口绕过 post()/clear_html_blank 回归
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 截取类方法体（到下一 public function 或文件尾）
 */
function title_desc_method_body($src, $method)
{
    $needle = 'function ' . $method . '(';
    $pos = strpos($src, $needle);
    if ($pos === false) {
        return null;
    }
    $next = null;
    if (preg_match('/\n\s*public function\s+\w+\s*\(/', $src, $nm, PREG_OFFSET_CAPTURE, $pos + strlen($needle))) {
        $next = $nm[0][1];
    }
    if ($next !== null && $next > $pos) {
        return substr($src, $pos, $next - $pos);
    }
    return substr($src, $pos);
}

return TestAssert::runSuite(function () {
    echo "=== helper.php filter() always escape_string ===\n";
    $helper = file_get_contents(CORE_PATH . '/function/helper.php');
    TestAssert::false($helper === false, 'helper.php readable');
    TestAssert::contains($helper, 'function post(', 'post() defined');
    TestAssert::contains($helper, 'function filter(', 'filter() defined');
    TestAssert::contains($helper, 'return escape_string($data);', 'filter returns escape_string');

    echo "=== handle.php escape_string HTML + SQL ===\n";
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function escape_string(', 'escape_string defined');
    TestAssert::contains($handle, "htmlspecialchars(trim(\$string), ENT_QUOTES, 'UTF-8')", 'XSS htmlspecialchars');
    TestAssert::contains($handle, 'addslashes($string)', 'SQL addslashes');
    TestAssert::contains($handle, 'function clear_html_blank(', 'clear_html_blank defined');

    echo "=== ContentController add/mod title+description via post + clear_html_blank ===\n";
    $content = file_get_contents(APP_PATH . '/admin/controller/content/ContentController.php');
    TestAssert::false($content === false, 'ContentController readable');

    $addBody = title_desc_method_body($content, 'add');
    $modBody = title_desc_method_body($content, 'mod');
    TestAssert::true($addBody !== null && $addBody !== '', 'add() body found');
    TestAssert::true($modBody !== null && $modBody !== '', 'mod() body found');

    TestAssert::contains($addBody, "post('title')", 'content add: title via post');
    TestAssert::contains($addBody, "post('description')", 'content add: description via post');
    TestAssert::contains($addBody, "clear_html_blank(\$description)", 'content add: clear_html_blank description');
    TestAssert::notContains($addBody, "\$_POST['title']", 'content add: no raw $_POST title');
    TestAssert::notContains($addBody, '$_POST["title"]', 'content add: no raw $_POST title dq');

    TestAssert::contains($modBody, "post('title')", 'content mod: title via post');
    TestAssert::contains($modBody, "post('description')", 'content mod: description via post');
    TestAssert::contains($modBody, "clear_html_blank(\$description)", 'content mod: clear_html_blank description');
    TestAssert::notContains($modBody, "\$_POST['title']", 'content mod: no raw $_POST title');

    // 空描述自动摘要仍须 escape_string + clear_html_blank
    TestAssert::contains($addBody, 'escape_string(clear_html_blank(', 'content add: auto desc escape+clear');
    TestAssert::contains($modBody, 'escape_string(clear_html_blank(', 'content mod: auto desc escape+clear');

    echo "=== ContentSortController add/mod title+description via post ===\n";
    $sort = file_get_contents(APP_PATH . '/admin/controller/content/ContentSortController.php');
    TestAssert::false($sort === false, 'ContentSortController readable');

    $sortAdd = title_desc_method_body($sort, 'add');
    $sortMod = title_desc_method_body($sort, 'mod');
    TestAssert::true($sortAdd !== null && $sortAdd !== '', 'sort add() body found');
    TestAssert::true($sortMod !== null && $sortMod !== '', 'sort mod() body found');

    TestAssert::contains($sortAdd, "post('title')", 'sort add: title via post');
    TestAssert::contains($sortAdd, "post('description')", 'sort add: description via post');
    TestAssert::notContains($sortAdd, "\$_POST['title']", 'sort add: no raw $_POST title');
    TestAssert::notContains($sortAdd, "\$_POST['description']", 'sort add: no raw $_POST description');

    TestAssert::contains($sortMod, "post('title')", 'sort mod: title via post');
    TestAssert::contains($sortMod, "post('description')", 'sort mod: description via post');
    TestAssert::notContains($sortMod, "\$_POST['title']", 'sort mod: no raw $_POST title');
    TestAssert::notContains($sortMod, "\$_POST['description']", 'sort mod: no raw $_POST description');

    echo "=== SiteController mod title+description via post ===\n";
    $site = file_get_contents(APP_PATH . '/admin/controller/content/SiteController.php');
    TestAssert::false($site === false, 'SiteController readable');

    $siteMod = title_desc_method_body($site, 'mod');
    TestAssert::true($siteMod !== null && $siteMod !== '', 'site mod() body found');
    TestAssert::contains($siteMod, "post('title')", 'site mod: title via post');
    TestAssert::contains($siteMod, "post('description')", 'site mod: description via post');
    TestAssert::notContains($siteMod, "\$_POST['title']", 'site mod: no raw $_POST title');
    TestAssert::notContains($siteMod, "\$_POST['description']", 'site mod: no raw $_POST description');
});

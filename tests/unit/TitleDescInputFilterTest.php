<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/helper.php post() / filter()
 * @covers core/function/handle.php escape_string() / clear_html_blank()
 *
 * 审计 #8 回归：内容标题与描述输入清洗（内容/栏目/站点共用 post 链路）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'fixtures/security/TitleDescPayloads.php';

/**
 * 模拟 ContentController / ContentSortController / SiteController 标题入口
 */
function title_desc_title_pipeline($raw)
{
    $_POST['title'] = $raw;
    return post('title');
}

/**
 * 模拟 ContentController 描述落库：post + clear_html_blank
 */
function title_desc_description_save_pipeline($raw)
{
    $_POST['description'] = $raw;
    $description = post('description');
    return clear_html_blank($description);
}

/**
 * 模拟 ContentController 空描述自动摘要路径
 */
function title_desc_auto_description_pipeline($contentRaw)
{
    return escape_string(clear_html_blank(substr_both(strip_tags($contentRaw), 0, 150)));
}

/**
 * 断言经 post()/escape_string 后 XSS 不可执行（尖括号已实体化，无法成标签）
 */
function assert_escaped_not_executable($out, $label, $raw = null)
{
    TestAssert::true(is_string($out), "$label: string result");
    // htmlspecialchars 后不应残留原始尖括号，标签无法形成
    TestAssert::notContains($out, '<', "$label: no raw <");
    TestAssert::notContains($out, '>', "$label: no raw >");
    if ($raw !== null && strpos($raw, '<') !== false) {
        TestAssert::contains($out, '&lt;', "$label: < escaped to entity");
    }
}

return TestAssert::runSuite(function () {
    echo "=== post(title) escapes XSS ===\n";
    foreach (TitleDescPayloads::xss() as $payload) {
        assert_escaped_not_executable(title_desc_title_pipeline($payload), 'title xss', $payload);
    }

    echo "=== post(description)+clear_html_blank escapes XSS ===\n";
    foreach (TitleDescPayloads::xss() as $payload) {
        assert_escaped_not_executable(title_desc_description_save_pipeline($payload), 'desc xss', $payload);
    }

    echo "=== post(title) escapes SQLi special chars ===\n";
    foreach (TitleDescPayloads::sqli() as $payload) {
        $out = title_desc_title_pipeline($payload);
        // ENT_QUOTES：单引号变 &#039;，不再保留可拼 SQL 的裸引号语义
        TestAssert::notContains($out, "' OR '", 'title sqli: raw OR quote pattern gone');
        TestAssert::true(
            strpos($out, '&#039;') !== false || strpos($out, "\\'") !== false || strpos($out, '&quot;') !== false,
            'title sqli: quotes escaped'
        );
    }

    echo "=== post(description) escapes SQLi special chars ===\n";
    foreach (TitleDescPayloads::sqli() as $payload) {
        $out = title_desc_description_save_pipeline($payload);
        TestAssert::notContains($out, "' OR '", 'desc sqli: raw OR quote pattern gone');
        TestAssert::true(
            strpos($out, '&#039;') !== false || strpos($out, "\\'") !== false || strpos($out, '&quot;') !== false,
            'desc sqli: quotes escaped'
        );
    }

    echo "=== post(title) preserves valid titles (escaped entities ok) ===\n";
    foreach (TitleDescPayloads::titleValid() as $title) {
        $out = title_desc_title_pipeline($title);
        TestAssert::true($out !== null && $out !== '', "valid title non-empty: $title");
        // & 会被转义为 &amp;
        if (strpos($title, '&') !== false) {
            TestAssert::contains($out, '&amp;', 'ampersand escaped');
        } else {
            TestAssert::contains($out, $title, "valid title preserved: $title");
        }
    }

    echo "=== clear_html_blank on description save ===\n";
    foreach (TitleDescPayloads::descriptionBlankMap() as $input => $forbidden) {
        $out = title_desc_description_save_pipeline($input);
        TestAssert::notContains($out, $forbidden, 'blank cleaned: ' . json_encode($forbidden));
    }
    // 连续空白折叠为空格
    $collapsed = title_desc_description_save_pipeline("a   \n\t  b");
    TestAssert::same('a b', $collapsed, 'whitespace collapsed to single space');

    echo "=== auto description from content strips tags + escapes ===\n";
    $auto = title_desc_auto_description_pipeline(TitleDescPayloads::richContentWithScript());
    TestAssert::notContains($auto, '<script>', 'auto desc: no script tag');
    TestAssert::notContains($auto, '<p>', 'auto desc: html tags stripped');
    TestAssert::contains($auto, '安全摘要', 'auto desc: text kept');
    // strip_tags 后若仍有尖括号文本，escape_string 会实体化；script 字样可保留为文本
    assert_escaped_not_executable($auto, 'auto desc');

    echo "=== filter()/escape_string HTML+SQL chain present ===\n";
    $raw = '<b>x</b>\'y"';
    $_POST['title'] = $raw;
    $viaPost = post('title');
    $viaEscape = escape_string($raw);
    TestAssert::same($viaEscape, $viaPost, 'post() equals escape_string for plain string');
    TestAssert::contains($viaPost, '&lt;b&gt;', 'html tags escaped');
    TestAssert::contains($viaPost, '&#039;', 'single quote as entity');
    TestAssert::contains($viaPost, '&quot;', 'double quote as entity');

    echo "=== PHP version note ===\n";
    TestAssert::true(PHP_VERSION_ID >= 70000, 'PHP >= 7.0 (got ' . PHP_VERSION . ')');
});

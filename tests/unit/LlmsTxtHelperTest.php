<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php llms_normalize_scodes() llms_plain_text() llms_escape_link_text() llms_escape_link_url() llms_link_line()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');

require APP_PATH . '/common/function.php';

return TestAssert::runSuite(function () {
    echo "=== llms_normalize_scodes() 去重与非法编码过滤 ===\n";

    TestAssert::same('1,2,3', llms_normalize_scodes(array('1', '2', '3')), '数组转逗号串');
    TestAssert::same('1,2', llms_normalize_scodes(array('1', '2', '1')), '重复编码去重');
    TestAssert::same('1,2', llms_normalize_scodes(' 1 , 2 '), '逗号串去空格');
    TestAssert::same('a-b_c', llms_normalize_scodes('a-b_c'), '连字符下划线合法');
    TestAssert::same('', llms_normalize_scodes("1' OR 1=1"), '注入片段被过滤');
    TestAssert::same('', llms_normalize_scodes(str_repeat('a', 21)), '超过字段长度被过滤');
    TestAssert::same('1', llms_normalize_scodes(array('1', '<script>', '')), '非法项跳过合法项保留');
    TestAssert::same('', llms_normalize_scodes(null), 'null 返回空串');
    TestAssert::same('', llms_normalize_scodes(123), '标量非串返回空串');

    echo "=== llms_plain_text() 去标签、压行与截断 ===\n";

    TestAssert::same('纯文本', llms_plain_text('<p>纯文本</p>'), '去除HTML标签');
    TestAssert::same('上 下', llms_plain_text("上\r\n下"), '换行压成单空格');
    TestAssert::same('上 下', llms_plain_text('上<br/>下'), 'br 压成单空格');
    TestAssert::same('a b', llms_plain_text("a\t\t   b"), '连续空白合并');
    TestAssert::same('a&b', llms_plain_text('a&amp;b'), 'HTML实体还原');
    TestAssert::same('', llms_plain_text('   '), '纯空白返回空串');
    TestAssert::same('', llms_plain_text(null), 'null 返回空串');
    TestAssert::same('中文中文中…', llms_plain_text('中文中文中文', 5), '按字数而非字节截断');
    TestAssert::same('中文中文中文', llms_plain_text('中文中文中文', 6), '未超长不加省略号');
    TestAssert::same('', llms_plain_text('内容', 0), '长度为0返回空串');

    echo "=== llms_plain_text() 入库转义形态（escape_string 后的真实数据）===\n";

    // 富文本经 escape_string 入库后是实体形态，若先剥标签再解码会把标签放回输出
    TestAssert::same('你好', llms_plain_text('&lt;p&gt;你好&lt;/p&gt;'), '实体形态的标签被剥除');
    TestAssert::same('上 下', llms_plain_text('上&lt;br /&gt;下'), '实体形态的 br 压成空格');
    TestAssert::same(
        '段一 段二',
        llms_plain_text('&lt;p&gt;段一&lt;/p&gt;&lt;p&gt;段二&lt;/p&gt;'),
        '相邻段落不被粘连'
    );
    TestAssert::notContains(llms_plain_text('&lt;p&gt;你好&lt;/p&gt;'), '<', '输出不含尖括号');
    TestAssert::same('上 下', llms_plain_text('上&lt;BR&gt;下'), '大写标签同样处理');
    TestAssert::same('a b', llms_plain_text('a&amp;nbsp;b'), '入库形态的 &nbsp; 还原为空格');
    TestAssert::same('上 下', llms_plain_text('上　下'), '全角空格压成半角空格');
    TestAssert::same('段一 段二', llms_plain_text('&lt;td&gt;段一&lt;/td&gt;&lt;td&gt;段二&lt;/td&gt;'), '表格单元格不粘连');
    TestAssert::same('正文', llms_plain_text('&lt;!--注释--&gt;正文'), 'HTML注释被移除');

    echo "=== llms_plain_text() 裸尖括号不得吞掉后续文字 ===\n";

    // 解码后 strip_tags 会把小于号后的内容一路吃掉，必须只剥「<字母」开头的合法标签
    TestAssert::same('价格 <100 元，有货', llms_plain_text('价格 &lt;100 元，有货'), '数字前的小于号原样保留');
    TestAssert::same('库存 >500 件', llms_plain_text('库存 &gt;500 件'), '大于号原样保留');
    TestAssert::same('区间 <=10', llms_plain_text('区间 &lt;=10'), '比较符原样保留');
    TestAssert::same('适用于 容器', llms_plain_text('适用于 &lt;div&gt; 容器'), '合法标签形态仍被剥除');

    echo "=== llms_plain_text() 对已清洗文本幂等 ===\n";

    // llms_link_line 曾对说明二次清洗，非幂等会让还原出的字面标签在第二遍被剥掉
    $samples = array(
        '价格 <100 元，有货',
        '普通描述文字',
        '上 下',
        '适用于 容器',
        'a b'
    );
    foreach ($samples as $sample) {
        $once = llms_plain_text($sample, 500);
        TestAssert::same($once, llms_plain_text($once, 500), '重复清洗结果不变：' . $sample);
    }

    echo "=== llms_truncate_intro() 按转义态收敛且不解码 ===\n";

    // 配置值写库前仍是 post() 转义后的形态，解码入库会让后台回显失去转义保护
    TestAssert::same(
        '&lt;script&gt;',
        llms_truncate_intro('&lt;script&gt;'),
        '不解码，保持入库转义态'
    );
    TestAssert::same('上 下', llms_truncate_intro("上\r\n下"), '换行压成空格');
    TestAssert::same(str_repeat('阿', 190), llms_truncate_intro(str_repeat('阿', 300)), '按字数截断到190');
    TestAssert::same('a&amp;b', llms_truncate_intro('a&amp;b', 190), '完整实体不受影响');
    TestAssert::same('ab', llms_truncate_intro('ab&amp;', 4), '截断切开实体时移除尾部残片');
    TestAssert::same('', llms_truncate_intro('   '), '纯空白返回空串');
    TestAssert::same('', llms_truncate_intro(null), 'null 返回空串');

    echo "=== 链接转义不破坏 Markdown 结构 ===\n";

    TestAssert::same('\[标题\]', llms_escape_link_text('[标题]'), '方括号转义');
    TestAssert::same('a b', llms_escape_link_text("a\nb"), '标题内换行压平');
    TestAssert::same(
        'https://x.com/a%28b%29.html',
        llms_escape_link_url('https://x.com/a(b).html'),
        '地址圆括号转义'
    );
    TestAssert::same(
        'https://x.com/a%20b.html',
        llms_escape_link_url('https://x.com/a b.html'),
        '地址空格转义'
    );
    TestAssert::same(
        'https://x.com/a.html',
        llms_escape_link_url("https://x.com/a.html\r\n"),
        '地址换行剔除，防止注入新行'
    );

    echo "=== llms_link_line() 输出规范链接行 ===\n";

    TestAssert::same(
        '- [公司简介](https://x.com/about.html): 一句话简介',
        llms_link_line('公司简介', 'https://x.com/about.html', '一句话简介'),
        '带说明的链接行'
    );
    TestAssert::same(
        '- [公司简介](https://x.com/about.html)',
        llms_link_line('公司简介', 'https://x.com/about.html', ''),
        '说明为空时省略冒号部分'
    );
    TestAssert::same(
        '- [公司简介](https://x.com/about.html)',
        llms_link_line('公司简介', 'https://x.com/about.html', '   '),
        '说明为纯空白时省略冒号部分'
    );
    TestAssert::same('', llms_link_line('', 'https://x.com/a.html', ''), '标题为空不输出');
    TestAssert::same('', llms_link_line('标题', '', ''), '地址为空不输出');
    // 说明长度由调用方按 llms_desc_len 截断，此处不得再压到更短的上限
    TestAssert::contains(
        llms_link_line('标题', 'https://x.com/a.html', str_repeat('阿', 260)),
        ': ' . str_repeat('阿', 260),
        '超过200字的说明原样保留'
    );
    // 说明已由调用方清洗，这里只做单行保护，不得再解码或剥标签
    TestAssert::same(
        '- [标题](https://x.com/a.html): 价格 <100 元',
        llms_link_line('标题', 'https://x.com/a.html', '价格 <100 元'),
        '已清洗的说明不被二次剥标签'
    );
    TestAssert::same(
        '- [标题](https://x.com/a.html): 上 下',
        llms_link_line('标题', 'https://x.com/a.html', "上\n下"),
        '说明内换行仍被压平'
    );
    TestAssert::notContains(
        llms_link_line("标\n题", 'https://x.com/a.html', "说\n明"),
        "\n",
        '单条链接行内绝不出现换行'
    );
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/ParserController.php buildFilterWhere()
 * @covers core/function/handle.php resolve_search_field()
 * @covers core/function/handle.php resolve_order_field()
 * @covers core/function/handle.php resolve_content_order_custom()
 * @covers core/function/handle.php order_requests_ext_field()
 * @covers core/function/handle.php content_query_fields()
 *
 * 回归目标：
 *   #75 {pboot:list filter=ext_*|值} 被静态白名单拒绝导致完全不过滤
 *   #78 {pboot:search} filter 无白名单、field 未校验
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

use app\home\controller\ParserController;

/**
 * 通过反射调用受保护的 buildFilterWhere()，跳过构造函数的 DB 依赖
 *
 * @return array<int, string>
 */
function invoke_build_filter_where($filter, $fuzzy, array $allowed, $prefix = false, array $extFields = array('ext_color', 'ext_flag')): array
{
    static $method = null;
    static $parser = null;

    if ($method === null) {
        $refClass = new ReflectionClass(ParserController::class);
        $parser = $refClass->newInstanceWithoutConstructor();
        $method = $refClass->getMethod('buildFilterWhere');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
    }

    return $method->invoke($parser, $filter, $fuzzy, $allowed, $prefix, $extFields);
}

return TestAssert::runSuite(function () {
    // 与 ParserController::$allowed_tags_fields 保持同步（不含调节参数 isico/ispics）
    $tagsFieldsMirroringParserController = array('title', 'istop', 'isrecommend', 'isheadline');
    $tagsFields = $tagsFieldsMirroringParserController;
    $allFields = content_query_fields();
    $extFields = array('ext_color', 'ext_flag');

    echo "=== 大小写字段名解析为白名单规范拼写 ===\n";

    TestAssert::same('author', canonical_allowlist_field('AuThOr', $allFields), '普通字段保留白名单拼写');
    TestAssert::same('ext_color', canonical_allowlist_field('EXT_COLOR', $extFields), '扩展字段保留表结构拼写');
    TestAssert::same('Ext_Color', canonical_allowlist_field('EXT_COLOR', array('Ext_Color')), '扩展字段保留实际表结构拼写');
    TestAssert::same('', canonical_allowlist_field('a.title', $allFields), '预限定字段被拒绝');
    TestAssert::same('', canonical_allowlist_field('AUTHOR;DROP', $allFields), '注入形态被拒绝');

    echo "=== #76 幽灵调节参数不得进入列表 filter 白名单 ===\n";

    $where = invoke_build_filter_where('isico|1', false, $tagsFields);
    TestAssert::same(0, count($where), 'isico: 非列名，不产生 SQL');

    $where = invoke_build_filter_where('ispics|1', false, $tagsFields);
    TestAssert::same(0, count($where), 'ispics: 非列名，不产生 SQL');

    echo "=== #75 列表标签：ext_ 扩展字段筛选恢复生效 ===\n";

    $where = invoke_build_filter_where('ext_color|红色', false, $tagsFields);
    TestAssert::same(1, count($where), 'ext_color: 产生 1 个条件');
    TestAssert::contains($where[0], "ext_color='红色'", 'ext_color: 整列等于分支');
    TestAssert::contains($where[0], "ext_color like '红色,%'", 'ext_color: 前缀边界');
    TestAssert::contains($where[0], "ext_color like '%,红色'", 'ext_color: 后缀边界');
    TestAssert::contains($where[0], "ext_color like '%,红色,%'", 'ext_color: 中间边界');

    echo "=== #75 多选逗号边界：1 不得误命中 11、21（与 #19 验收一致）===\n";

    $where = invoke_build_filter_where('ext_color|1', false, $tagsFields);
    TestAssert::notContains($where[0], "like '%1%'", '精确模式不产生无边界模糊匹配');
    TestAssert::contains($where[0], "ext_color like '1,%'", '精确模式保留前缀边界');

    echo "=== #75 多值与空值处理 ===\n";

    $where = invoke_build_filter_where('ext_color|红色,橙色', false, $tagsFields);
    TestAssert::same(2, count($where), '多值: 产生 2 个条件');
    TestAssert::contains($where[1], "ext_color='橙色'", '多值: 第二个值正确');

    $where = invoke_build_filter_where('ext_color|红色, ,橙色', false, $tagsFields);
    TestAssert::same(2, count($where), '空值被跳过');

    $where = invoke_build_filter_where('ext_color|', false, $tagsFields);
    TestAssert::same(0, count($where), '值为空: 无条件');

    echo "=== 字面量 0 是合法筛选值，普通字段与扩展字段必须一致 ===\n";

    $where = invoke_build_filter_where('istop|0', false, $tagsFields);
    TestAssert::same("istop='0'", $where[0], '普通字段: 值 0 生效');

    $where = invoke_build_filter_where('ext_flag|0', false, $tagsFields);
    TestAssert::same(1, count($where), '扩展字段: 值 0 产生条件');
    TestAssert::contains($where[0], "ext_flag='0'", '扩展字段: 值 0 整列等于分支');
    TestAssert::contains($where[0], "ext_flag like '0,%'", '扩展字段: 值 0 保留边界匹配');

    TestAssert::true(build_extfield_where('ext_flag', '0', false) !== '', 'build_extfield_where: 值 0 不被丢弃');
    TestAssert::true(build_extfield_where('ext_flag', 0, false) !== '', 'build_extfield_where: 整数 0 不被丢弃');
    TestAssert::true(build_tags_where('0', false) !== '', 'build_tags_where: 标签 0 不被丢弃');
    TestAssert::same('', build_extfield_where('ext_flag', '', false), 'build_extfield_where: 空字符串仍被丢弃');
    TestAssert::same('', build_extfield_where('ext_flag', '   ', false), 'build_extfield_where: 纯空格仍被丢弃');
    TestAssert::same('', build_extfield_where('ext_flag', null, false), 'build_extfield_where: null 仍被丢弃');

    echo "=== #75 fuzzy=1 时保留子串匹配语义 ===\n";

    $where = invoke_build_filter_where('ext_color|红色', true, $tagsFields);
    TestAssert::contains($where[0], "ext_color like '%红色%'", 'fuzzy: 子串匹配');

    echo "=== 既有核心字段语法不回归 ===\n";

    $where = invoke_build_filter_where('title|php,asp', false, $tagsFields);
    TestAssert::same(2, count($where), 'title: 2 个值');
    TestAssert::same("a.title='php'", $where[0], 'title: 重映射为 a.title');
    TestAssert::same("a.title='asp'", $where[1], 'title: 第二个值');

    $where = invoke_build_filter_where('title|php', true, $tagsFields);
    TestAssert::same("a.title like '%php%'", $where[0], 'title: fuzzy 语义保留');

    $where = invoke_build_filter_where('istop|1', false, $tagsFields);
    TestAssert::same("istop='1'", $where[0], 'istop: 列表路径保持不限定（无 a. 前缀）');

    echo "=== #78 非白名单字段被拒绝，不产生任何 SQL ===\n";

    $where = invoke_build_filter_where('nonexistent|x', false, $tagsFields);
    TestAssert::same(0, count($where), '非白名单字段: 无条件');

    $where = invoke_build_filter_where('ext_missing|红色', false, $tagsFields, false, $extFields);
    TestAssert::same(0, count($where), '不存在的扩展字段: 无条件');

    $where = invoke_build_filter_where("1=1 or x|y", false, $tagsFields);
    TestAssert::same(0, count($where), '注入形态的列名: 无条件');

    $where = invoke_build_filter_where("a.id) or (1=1|y", false, $allFields, true);
    TestAssert::same(0, count($where), '带括号的列名: 无条件');

    echo "=== #78 搜索路径：白名单 + a. 表限定 ===\n";

    $where = invoke_build_filter_where('id|5', false, $allFields, true);
    TestAssert::same("a.id='5'", $where[0], '搜索: id 补 a. 限定，消除歧义');

    $where = invoke_build_filter_where('author|张三', true, $allFields, true);
    TestAssert::same("a.author like '%张三%'", $where[0], '搜索: fuzzy 默认语义保留');

    $where = invoke_build_filter_where('AuThOr|张三', true, $allFields, true);
    TestAssert::same("a.author like '%张三%'", $where[0], '搜索: 混合大小写普通字段使用规范列名');

    $where = invoke_build_filter_where('ext_color|红色', false, $allFields, true);
    TestAssert::contains($where[0], "ext_color='红色'", '搜索: ext 字段不加 a. 限定');
    TestAssert::notContains($where[0], 'a.ext_color', '搜索: ext 字段不得被误加 a. 前缀');

    $where = invoke_build_filter_where('EXT_COLOR|红色', false, $allFields, true);
    TestAssert::contains($where[0], "ext_color='红色'", '搜索: 大写 ext 字段使用规范列名');

    echo "=== 参数形态异常时安全返回 ===\n";

    TestAssert::same(0, count(invoke_build_filter_where('', false, $tagsFields)), '空 filter');
    TestAssert::same(0, count(invoke_build_filter_where('nopipe', false, $tagsFields)), '缺少管道符');
    TestAssert::same(0, count(invoke_build_filter_where(array('x'), false, $tagsFields)), '非字符串');

    $where = invoke_build_filter_where('ext_color|a|b', false, $tagsFields);
    TestAssert::same(1, count($where), '多个管道符: 只按首个分割，值含管道符');
    TestAssert::contains($where[0], "ext_color='a|b'", '多个管道符: 值保留完整');

    echo "=== #78 resolve_search_field() 字段解析 ===\n";

    TestAssert::same('a.title', resolve_search_field('title'), 'title -> a.title');
    TestAssert::same('a.author', resolve_search_field('AUTHOR'), 'AUTHOR -> a.author');
    TestAssert::same('tags', canonical_allowlist_field('TAGS', $allFields), 'TAGS -> tags，保留参数按规范拼写识别');
    TestAssert::same('a.title', resolve_search_field('TiTlE'), 'TiTlE -> a.title');
    TestAssert::same('a.id', resolve_search_field('id'), 'id -> a.id（修复歧义）');
    TestAssert::same('a.content', resolve_search_field('content'), 'content -> a.content');
    TestAssert::same('', resolve_search_field('ext_color'), '未传真实扩展字段白名单时拒绝 ext_color');
    TestAssert::same('ext_color', resolve_search_field('ext_color', $allFields, $extFields), '真实 ext_color 保持不限定');
    TestAssert::same('ext_color', resolve_search_field('EXT_COLOR', $allFields, $extFields), 'EXT_COLOR -> ext_color');
    TestAssert::same('', resolve_search_field('ext_missing', $allFields, $extFields), '不存在的扩展字段被拒绝');
    TestAssert::same('a.title', resolve_search_field(' title '), '首尾空格被裁剪');
    TestAssert::same('', resolve_search_field('nonexistent'), '非白名单字段被拒绝');
    TestAssert::same('', resolve_search_field(''), '空字符串被拒绝');
    TestAssert::same('', resolve_search_field(null), 'null 被拒绝');
    TestAssert::same('', resolve_search_field('a.title'), '已限定名不再二次限定');
    TestAssert::same('', resolve_search_field("title' or '1"), '注入形态被拒绝');
    TestAssert::same('', resolve_search_field('ext_'), '不完整的 ext_ 前缀被拒绝');

    echo "=== content_query_fields() 白名单内容 ===\n";

    TestAssert::same(35, count($allFields), '白名单共 35 个字段');
    TestAssert::true(in_array('gnote', $allFields, true), '包含 gnote');
    TestAssert::false(in_array('isico', $allFields, true), '不含调节参数名 isico');
    TestAssert::false(in_array('ispics', $allFields, true), '不含调节参数名 ispics');

    echo "=== #76 搜索 URL 参数：碰撞列经 resolve_search_field 均带 a. ===\n";

    // 与 ay_content_sort / ay_model / ay_member_group JOIN 后会歧义的 17 列
    $ambiguous = array(
        'id', 'acode', 'scode', 'filename', 'outlink', 'ico', 'keywords', 'description',
        'sorting', 'status', 'create_user', 'update_user', 'create_time', 'update_time',
        'gtype', 'gid', 'gnote',
    );
    foreach ($ambiguous as $field) {
        TestAssert::same('a.' . $field, resolve_search_field($field, $allFields), $field . ' -> a.' . $field);
    }

    echo "=== #76 全部 35 个 allowed_fields 均可安全解析为限定列名 ===\n";

    foreach ($allFields as $field) {
        $column = resolve_search_field($field, $allFields);
        TestAssert::same('a.' . $field, $column, $field . ' 解析为 a.' . $field);
        TestAssert::true((bool) preg_match('/^a\.\w+$/', $column), $field . ' 限定名格式合法');
    }

    TestAssert::same('', resolve_search_field('isico', $allFields), '搜索路径拒绝幽灵名 isico');
    TestAssert::same('', resolve_search_field('ispics', $allFields), '搜索路径拒绝幽灵名 ispics');

    echo "=== resolve_order_field() order 令牌解析 ===\n";

    TestAssert::same('a.id', resolve_order_field('id'), 'id -> a.id');
    TestAssert::same('a.id', resolve_order_field('ID'), 'ID -> a.id');
    TestAssert::same('a.visits DESC', resolve_order_field('visits DESC'), 'visits DESC -> a.visits DESC');
    TestAssert::same('a.visits DESC', resolve_order_field('VISITS desc'), 'VISITS desc -> a.visits DESC');
    TestAssert::same('e.ext_color', resolve_order_field('ext_color', $allFields, $extFields), 'ext_color -> e.ext_color');
    TestAssert::same('e.ext_color', resolve_order_field('EXT_COLOR', $allFields, $extFields), 'EXT_COLOR -> e.ext_color');
    TestAssert::same('e.ext_color DESC', resolve_order_field('EXT_COLOR DESC', $allFields, $extFields), 'EXT_COLOR DESC -> e.ext_color DESC');
    TestAssert::same('e.ext_color ASC', resolve_order_field('ext_color ASC', $allFields, $extFields), 'ext_color ASC -> e.ext_color ASC');
    TestAssert::same('', resolve_order_field('EXT_COLOR DESC', $allFields, array()), '空白名单时带方向扩展字段被拒绝');
    TestAssert::same('', resolve_order_field('ext_missing', $allFields, $extFields), '不存在的扩展字段被拒绝');
    TestAssert::same('', resolve_order_field('ext_missing DESC', $allFields, $extFields), '不存在的扩展字段带方向被拒绝');
    TestAssert::same('', resolve_order_field('asc'), 'asc 被拒绝（非列名）');
    TestAssert::same('', resolve_order_field('desc'), 'desc 被拒绝（非列名）');
    TestAssert::same('', resolve_order_field('price'), 'price 被拒绝（非白名单）');
    TestAssert::same('', resolve_order_field(' ASC'), '纯 ASC 被拒绝');
    TestAssert::same('', resolve_order_field('DESC'), '纯 DESC 被拒绝');
    TestAssert::same('', resolve_order_field('a.title'), '预限定名被拒绝');
    TestAssert::same('', resolve_order_field('random'), 'random 伪排序名被拒绝');
    TestAssert::same('', resolve_order_field(''), '空字符串被拒绝');
    TestAssert::same('', resolve_order_field(null), 'null 被拒绝');

    echo "=== order_requests_ext_field() 预加载检测 ===\n";

    TestAssert::true(order_requests_ext_field('EXT_COLOR'), '裸 EXT_COLOR 触发预加载');
    TestAssert::true(order_requests_ext_field('EXT_COLOR DESC'), 'EXT_COLOR DESC 触发预加载');
    TestAssert::true(order_requests_ext_field('ext_color ASC'), 'ext_color ASC 触发预加载');
    TestAssert::true(order_requests_ext_field('EXT_COLOR DESC,date'), '带方向多令牌触发预加载');
    TestAssert::true(order_requests_ext_field('ext_color,date'), '裸扩展字段多令牌触发预加载');
    TestAssert::false(order_requests_ext_field('visits DESC'), '普通字段带方向不触发预加载');
    TestAssert::false(order_requests_ext_field('asc'), 'asc 不触发预加载');
    TestAssert::false(order_requests_ext_field('price'), 'price 不触发预加载');
    TestAssert::false(order_requests_ext_field(''), '空字符串不触发预加载');

    echo "=== resolve_content_order_custom() 多令牌 order ===\n";

    TestAssert::same('a.visits,a.id', resolve_content_order_custom('visits,id'), 'visits,id 多令牌合法');
    TestAssert::same('a.visits DESC,a.id ASC', resolve_content_order_custom('visits DESC,id ASC'), '带方向多令牌合法');
    TestAssert::same('', resolve_content_order_custom('visits,bogus'), '部分非法令牌整体拒绝');
    TestAssert::same('', resolve_content_order_custom('asc'), '单令牌 asc 整体拒绝');
    TestAssert::same('e.ext_color,a.date', resolve_content_order_custom('EXT_COLOR,date', $allFields, $extFields), '扩展字段大小写不敏感');
    TestAssert::same('e.ext_color DESC,a.date', resolve_content_order_custom('EXT_COLOR DESC,date', $allFields, $extFields), '扩展字段带方向多令牌合法');
    TestAssert::same('', resolve_content_order_custom('EXT_COLOR DESC', $allFields, array()), '空白名单时带方向扩展字段整体拒绝');
});

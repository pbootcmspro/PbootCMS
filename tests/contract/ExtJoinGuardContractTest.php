<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ParserModel::getList 扩展字段 JOIN 守卫接线不变量
 * @covers ParserController filter 路径统一走 buildFilterWhere()
 *
 * 单元测试只覆盖判定逻辑本身，本契约测试锁定「守卫确实被接到 JOIN 之前」，
 * 因为 getList() 执行到底需要真实数据库连接，无法在测试内断言最终 SQL。
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== #77 ParserModel::getList 守卫接线顺序 ===\n";

    $src = file_get_contents(APP_PATH . '/home/model/ParserModel.php');
    TestAssert::false($src === false, 'ParserModel 源码可读');

    $posGetList = strpos($src, 'public function getList(');
    $posNext = strpos($src, 'private function hasExtFieldReference(');
    TestAssert::false($posGetList === false, 'getList() 存在');
    TestAssert::false($posNext === false, 'hasExtFieldReference() 存在');

    $body = substr($src, $posGetList, $posNext - $posGetList);

    $posGuard = strpos($body, 'hasExtFieldReference(');
    $posJoin = strpos($body, "'ay_content_ext e '");

    TestAssert::false($posGuard === false, 'getList 内调用了 hasExtFieldReference');
    TestAssert::false($posJoin === false, 'getList 内仍保留 ay_content_ext JOIN');
    TestAssert::true($posGuard < $posJoin, '守卫位于 JOIN 拼装之前');

    // 守卫必须覆盖全部 ext 发出点，漏传任一参数都会让对应路径重新产生坏 SQL
    TestAssert::true(
        (bool) preg_match('/hasExtFieldReference\(([^)]*)\)/', $body, $callArgs),
        '可解析出守卫调用的参数列表'
    );
    foreach (array('$order', '$filter', '$tags', '$select') as $param) {
        TestAssert::contains($callArgs[1], $param, '守卫参数包含 ' . $param);
    }

    echo "=== #78 lfield 字段白名单接线 ===\n";

    TestAssert::contains($body, 'content_query_fields()', 'lfield 拼装使用统一白名单');
    TestAssert::notContains($body, "\$fields[\$key] = 'a.' . \$value;", 'lfield 不再无条件拼 a. 前缀');
    TestAssert::contains($body, "\$safe_fields[] = 'a.*';", 'lfield=* 恢复映射为 a.*');
    TestAssert::contains($body, 'getExtFields()', 'lfield 扩展字段从真实表结构白名单读取');
    TestAssert::contains($body, 'canonical_allowlist_field($value, $extFields)', 'lfield 解析扩展字段为表结构规范拼写');
    TestAssert::contains($body, 'canonical_allowlist_field($value, $allowed)', 'lfield 解析普通字段为白名单规范拼写');
    $posLfieldInit = strpos($body, '$extFields = null;');
    $posLfieldExtItem = strpos($body, "elseif (preg_match('/^ext_[\\w\\-]+$/i', \$value))");
    $posLfieldLoad = strpos($body, '$extFields = $this->getExtFields();');
    TestAssert::false($posLfieldInit === false, 'lfield 扩展字段缓存初始状态存在');
    TestAssert::false($posLfieldExtItem === false, 'lfield 扩展字段项分支存在');
    TestAssert::false($posLfieldLoad === false, 'lfield 按需读取真实扩展字段');
    TestAssert::true($posLfieldInit < $posLfieldExtItem && $posLfieldExtItem < $posLfieldLoad, '普通 lfield 不触发表结构读取');

    foreach (array(
        APP_PATH . '/home/model/ParserModel.php',
        APP_PATH . '/api/model/CmsModel.php',
    ) as $modelPath) {
        $modelSrc = file_get_contents($modelPath);
        TestAssert::false($modelSrc === false, basename($modelPath) . ' 源码可读');
        TestAssert::contains($modelSrc, 'public function getExtFields()', basename($modelPath) . ' 提供真实扩展字段发现');
        TestAssert::contains($modelSrc, "parent::tableFields('ay_content_ext')", basename($modelPath) . ' 通过表结构发现扩展字段');
        TestAssert::contains($modelSrc, "preg_match('/^ext_[\\w\\-]+$/i', \$field)", basename($modelPath) . '仅缓存合法 ext_* 字段并保留规范拼写');
    }

    echo "=== #75/#78 ParserController filter 路径统一 ===\n";

    $parserSrc = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::false($parserSrc === false, 'ParserController 源码可读');

    TestAssert::contains(
        $parserSrc,
        '$where1 = $this->buildFilterWhere($filter, $fuzzy, $this->allowed_tags_fields, false, $extFields);',
        '列表标签向 buildFilterWhere 传入真实扩展字段白名单'
    );
    TestAssert::contains(
        $parserSrc,
        '$where1 = $this->buildFilterWhere($filter, $fuzzy, $this->allowed_fields, true, $extFields);',
        '搜索标签向 buildFilterWhere 传入真实扩展字段白名单并启用 a. 限定'
    );
    TestAssert::same(
        2,
        preg_match_all('/\$this->buildFilterWhere\(/', $parserSrc),
        'filter 逻辑仅剩两个调用点，无重复实现'
    );
    TestAssert::notContains(
        $parserSrc,
        "\$filter = explode('|', \$filter);",
        '旧的内联 filter 解析已移除'
    );

    $posListMethod = strpos($parserSrc, 'public function parserListLabel(');
    $posSearchMethod = strpos($parserSrc, 'public function parserSearchLabel(');
    $listBody = substr($parserSrc, $posListMethod, $posSearchMethod - $posListMethod);
    $posListLoop = strpos($listBody, 'for ($i = 0; $i < count($matches[0]); $i++)');
    $posListLoad = strpos($listBody, '$extFields = $this->model->getExtFields();');
    TestAssert::contains($listBody, '$extFields = null;', '列表标签扩展字段缓存延迟初始化');
    TestAssert::contains($listBody, 'if ($extFields === null && preg_match(', '列表 filter 仅在 syntactic ext_* 时读取表结构');
    TestAssert::false($posListLoop === false, '列表标签循环存在');
    TestAssert::false($posListLoad === false, '列表标签有按需扩展字段读取');
    TestAssert::true($posListLoop < $posListLoad, '列表标签不会因存在标签而预读扩展字段');

    echo "=== #76 搜索/接口 URL 参数统一 a. 限定，tags 白名单无幽灵列 ===\n";

    TestAssert::contains(
        $parserSrc,
        "protected \$allowed_tags_fields = array('title','istop','isrecommend','isheadline');",
        'allowed_tags_fields 已剔除 isico/ispics'
    );
    TestAssert::false(
        (bool) preg_match("/allowed_tags_fields\s*=\s*array\([^)]*'isico'/", $parserSrc),
        'allowed_tags_fields 数组不含 isico'
    );
    TestAssert::false(
        (bool) preg_match("/allowed_tags_fields\s*=\s*array\([^)]*'ispics'/", $parserSrc),
        'allowed_tags_fields 数组不含 ispics'
    );

    // 接收环：先解析为规范白名单字段，再写 a. 前缀
    TestAssert::contains(
        $parserSrc,
        "canonical_allowlist_field(\$key, \$this->allowed_fields)",
        '前台搜索接收环大小写不敏感解析白名单字段'
    );
    TestAssert::contains(
        $parserSrc,
        "\$where3['a.' . \$canonicalKey] = \$value;",
        '前台搜索接收环对规范字段写 a. 前缀'
    );
    TestAssert::notContains(
        $parserSrc,
        "if (\$key == 'title') {\n                            \$key = 'a.title';",
        '前台搜索接收环已移除失效的 title 特例重映射'
    );

    $apiSrc = file_get_contents(APP_PATH . '/api/controller/CmsController.php');
    TestAssert::false($apiSrc === false, 'CmsController 源码可读');
    $posApiSearch = strpos($apiSrc, 'public function search()');
    $posApiNext = strpos($apiSrc, '    // 读取留言记录');
    TestAssert::false($posApiSearch === false, 'CmsController search 方法存在');
    TestAssert::false($posApiNext === false, 'CmsController search 后续方法注释存在');
    TestAssert::true($posApiSearch < $posApiNext, 'CmsController search 位于后续方法之前');
    $apiSearchBody = substr($apiSrc, $posApiSearch, $posApiNext - $posApiSearch);
    TestAssert::contains($apiSearchBody, '$hasExtField = ($keyword !== null && $keyword !== \'\') && preg_match(', 'API 仅在非空 keyword 的 whitespace-tolerant ext_* field 时读取表结构');
    TestAssert::contains($apiSearchBody, "preg_match('/^ext_[\\w\\-]+$/i', \$key)", 'API 根据大小写不敏感 ext_* 请求键决定是否读取表结构');
    TestAssert::contains($apiSearchBody, 'order_requests_ext_field($rorder)', 'API order 经统一预加载检测（含可选 ASC/DESC）');
    TestAssert::contains($apiSearchBody, '$extFields = $hasExtField ? $this->model->getExtFields() : array();', 'API 未使用 ext_* 时不读取表结构');
    TestAssert::contains($apiSearchBody, "if (\$keyword !== null && \$keyword !== '')", 'API keyword=0 不会因 truthiness 被跳过');
    TestAssert::contains($apiSearchBody, "} elseif (\$value !== null && \$value !== '')", 'API 普通白名单字段值 0 不会因 truthiness 被跳过');

    $listCtrlSrc = file_get_contents(APP_PATH . '/api/controller/ListController.php');
    TestAssert::false($listCtrlSrc === false, 'ListController 源码可读');
    TestAssert::contains(
        $listCtrlSrc,
        'order_requests_ext_field($rorder)',
        'ListController order 经统一预加载检测（含可选 ASC/DESC）'
    );
    TestAssert::notContains(
        $listCtrlSrc,
        "/(?:^|,)\\s*ext_[\\w\\-]+\\s*(?:,|$)/i",
        'ListController 不再使用不含方向的旧预加载正则'
    );

    $handleSrc = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handleSrc === false, 'handle.php 源码可读');
    TestAssert::contains(
        $handleSrc,
        "function order_requests_ext_field(",
        'order_requests_ext_field() 已定义'
    );
    TestAssert::contains(
        $handleSrc,
        '/(?:^|,)\\s*ext_[\\w\\-]+(?:\\s+(?:ASC|DESC))?\\s*(?:,|$)/i',
        '预加载检测允许可选 ASC/DESC'
    );
    TestAssert::contains(
        $apiSrc,
        "canonical_allowlist_field(\$key, \$allowed)",
        'API 搜索接收环大小写不敏感解析白名单字段'
    );
    TestAssert::contains(
        $apiSrc,
        "\$where3['a.' . \$canonicalKey] = \$value;",
        'API 搜索接收环对规范字段写 a. 前缀'
    );
    TestAssert::contains(
        $apiSrc,
        'content_query_fields()',
        'API 搜索接收环使用统一白名单'
    );
    TestAssert::contains(
        $apiSrc,
        'build_extfield_where($canonicalKey, $value, $fuzzy)',
        'API POST 真实 ext_* 字段恢复集合边界筛选并使用规范拼写'
    );
    TestAssert::contains(
        $apiSrc,
        'canonical_allowlist_field($key, $extFields)',
        'API 接收环拒绝不存在的 ext_* 字段'
    );

    // 保留参数 scode/acode/tags 不得因 a. 前缀绕过独立处理路径
    TestAssert::contains(
        $parserSrc,
        "if (! \$canonicalKey || \$canonicalKey === 'scode')",
        '前台接收环按规范拼写跳过 scode'
    );
    TestAssert::contains(
        $parserSrc,
        "unset(\$where3['scode'], \$where3['a.scode']);",
        '前台双清 scode 与 a.scode'
    );
    TestAssert::contains(
        $apiSrc,
        "in_array(\$canonicalKey, array('scode', 'acode', 'tags'), true)",
        'API 接收环按规范拼写跳过 scode/acode/tags'
    );
    TestAssert::contains(
        $apiSrc,
        "unset(\$where3['scode'], \$where3['a.scode']);",
        'API 双清 scode 与 a.scode'
    );
    TestAssert::contains(
        $apiSrc,
        "unset(\$where3['acode'], \$where3['a.acode']);",
        'API 双清 acode 与 a.acode'
    );
    TestAssert::contains(
        $apiSrc,
        "unset(\$where3['tags'], \$where3['a.tags']);",
        'API 双清 tags 与 a.tags，避免独立 tags 条件与通用字段条件重复'
    );

    echo "=== #76 keyword 与独立 title 参数优先级 ===\n";

    foreach (array($parserSrc, $apiSearchBody) as $idx => $code) {
        $name = $idx === 0 ? 'ParserController.php' : 'CmsController.php';
        TestAssert::contains(
            $code,
            "\$keywordUsesTitle = false; // 多字段 keyword 条件存于 where3[0]，需单独记录是否搜索标题",
            $name . ': 初始化多字段 keyword 标题搜索标记'
        );
        TestAssert::contains(
            $code,
            "if (\$column === 'a.title')",
            $name . ': 多字段 keyword 识别 title 列'
        );
        TestAssert::contains(
            $code,
            "\$keywordUsesTitle = true;",
            $name . ': 多字段 keyword 包含 title 时设置标记'
        );
        TestAssert::contains(
            $code,
            "if (\$canonicalKey === 'title' && (\$keywordUsesTitle || array_key_exists('a.title', \$where3)))",
            $name . ': keyword 通过 keyed 或多字段条件搜索标题时跳过通用 title'
        );
        TestAssert::contains(
            $code,
            '// keyword 可能以 a.title 键或多字段 OR 子句搜索标题；通用 title 仅在 keyword 未搜索标题时独立筛选',
            $name . ': 注释准确说明 keyword/title 优先级'
        );
    }

    echo "=== #78 field 多字段路径不再拼接未校验列名 ===\n";

    foreach (array($parserSrc, $apiSrc) as $idx => $code) {
        $name = $idx === 0 ? 'ParserController.php' : 'CmsController.php';
        TestAssert::contains($code, 'resolve_search_field(', $name . ': 使用 resolve_search_field 解析字段');
        TestAssert::notContains($code, "\$where3[0] = \$value . \$like;", $name . ': 旧的原始列名拼接已移除');
        TestAssert::notContains($code, "\$where3[\$field] = \$keyword;", $name . ': 旧的未校验键赋值已移除');
    }

    TestAssert::contains(
        $parserSrc,
        'resolve_search_field($value, $this->allowed_fields, $extFields)',
        '前台多字段 keyword 搜索传入真实扩展字段白名单'
    );
    TestAssert::contains(
        $parserSrc,
        'resolve_search_field($field, $this->allowed_fields, $extFields)',
        '前台单字段 keyword 搜索传入真实扩展字段白名单'
    );
    $searchBody = substr($parserSrc, $posSearchMethod, strpos($parserSrc, 'protected function buildFilterWhere') - $posSearchMethod);
    $posSearchLoop = strpos($searchBody, 'for ($i = 0; $i < $count; $i++)');
    $posSearchLoad = strpos($searchBody, '$extFields = $this->model->getExtFields();');
    TestAssert::contains($searchBody, '$extFields = null;', '搜索标签扩展字段缓存延迟初始化');
    TestAssert::contains($searchBody, 'if ($extFields === null && ((($keyword !== null && $keyword !== \'\') && preg_match(', '搜索标签仅在非空 keyword field 或 filter 为 syntactic ext_* 时读取表结构');
    TestAssert::contains($searchBody, "if (\$keyword !== null && \$keyword !== '')", '前台 keyword=0 不会因 truthiness 被跳过');
    TestAssert::contains($searchBody, "if (\$value !== null && \$value !== '')", '前台普通白名单字段值 0 不会因 truthiness 被跳过');
    TestAssert::contains(
        $searchBody,
        "preg_match('/^ext_[\\w\\-]+$/i', \$key)",
        '搜索 receive 在白名单前识别 ext_*（大小写不敏感）'
    );
    TestAssert::contains(
        $searchBody,
        'build_extfield_where($field, $value, $fuzzy)',
        '搜索 receive 对真实 ext_* 走集合边界匹配'
    );
    TestAssert::contains(
        $searchBody,
        'canonical_allowlist_field($key, $extFields)',
        '搜索 receive 用真实扩展字段白名单规范化 ext_*'
    );
    TestAssert::notContains(
        $searchBody,
        "\$where3[\$key] = \$value;",
        '搜索 receive 不再写无表前缀的旧键赋值'
    );
    TestAssert::false($posSearchLoop === false, '搜索标签循环存在');
    TestAssert::false($posSearchLoad === false, '搜索标签有按需扩展字段读取');
    TestAssert::true($posSearchLoop < $posSearchLoad, '搜索标签不会因存在标签而预读扩展字段');
    TestAssert::contains(
        $apiSrc,
        'resolve_search_field($value, $allowed, $extFields)',
        'API 多字段 keyword 搜索传入真实扩展字段白名单'
    );
    TestAssert::contains(
        $apiSrc,
        'resolve_search_field($field, $allowed, $extFields)',
        'API 单字段 keyword 搜索传入真实扩展字段白名单'
    );

    $posPicsMethod = strpos($parserSrc, 'public function parserContentPicsLabel(');
    $posCheckboxMethod = strpos($parserSrc, 'public function parserContentCheckboxLabel(');
    $posTagsMethod = strpos($parserSrc, 'public function parserContentTagsLabel(');
    TestAssert::false($posPicsMethod === false, '多图标签方法存在');
    TestAssert::false($posCheckboxMethod === false, '多选标签方法存在');
    TestAssert::false($posTagsMethod === false, '内容 tags 标签方法存在');
    $picsBody = substr($parserSrc, $posPicsMethod, $posCheckboxMethod - $posPicsMethod);
    $checkboxBody = substr($parserSrc, $posCheckboxMethod, $posTagsMethod - $posCheckboxMethod);
    $posPicsExtCheck = strpos($picsBody, "if (\$canonicalField === '' && preg_match('/^ext_");
    $posPicsLoad = strpos($picsBody, '$extFields = $this->model->getExtFields();');
    TestAssert::contains($picsBody, '$contentFields = content_query_fields();', '多图普通内容字段仍使用统一白名单');
    TestAssert::contains($picsBody, '$extFields = null;', '多图扩展字段缓存延迟初始化');
    TestAssert::false($posPicsExtCheck === false, '多图存在 syntactic ext_* 校验分支');
    TestAssert::false($posPicsLoad === false, '多图按需读取真实扩展字段');
    TestAssert::true($posPicsExtCheck < $posPicsLoad, '多图普通字段不读取扩展表结构');
    TestAssert::contains($picsBody, 'canonical_allowlist_field($field, $contentFields)', '多图普通字段解析为白名单规范拼写');
    TestAssert::contains($picsBody, 'canonical_allowlist_field($field, $extFields)', '多图拒绝不存在的扩展字段并使用表结构规范拼写');
    TestAssert::contains($checkboxBody, '$extFields = null;', '多选扩展字段缓存延迟初始化');
    TestAssert::contains($checkboxBody, '$extFields = $this->model->getExtFields();', '多选按需读取真实扩展字段');
    TestAssert::contains($checkboxBody, 'canonical_allowlist_field($field, $extFields)', '多选只接受真实扩展字段并使用表结构规范拼写');
});

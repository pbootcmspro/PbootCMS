<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/model/ParserModel.php getContentPre / getContentNext
 *
 * 上下篇按 scode-id 键缓存，避免同请求多篇文章互相污染
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Model.php';
require_once APP_PATH . '/home/model/ParserModel.php';

use app\home\model\ParserModel;

/**
 * 缓存未命中时 getSubScodes 会抛错，用于证明不会误用其它键的缓存
 */
final class ContentPrevNextCacheModelDouble extends ParserModel
{
    public function getSubScodes($scode)
    {
        throw new RuntimeException('query attempted for scode=' . $scode);
    }
}

/**
 * @return ContentPrevNextCacheModelDouble
 */
function content_prev_next_model()
{
    $ref = new ReflectionClass(ContentPrevNextCacheModelDouble::class);
    return $ref->newInstanceWithoutConstructor();
}

/**
 * @param object $model
 * @param string $prop
 * @param mixed $value
 */
function content_prev_next_set_prop($model, $prop, $value)
{
    $ref = new ReflectionClass(ParserModel::class);
    $p = $ref->getProperty($prop);
    if (PHP_VERSION_ID < 80100) {
        $p->setAccessible(true);
    }
    $p->setValue($model, $value);
}

/**
 * @param object $model
 * @param string $prop
 * @return mixed
 */
function content_prev_next_get_prop($model, $prop)
{
    $ref = new ReflectionClass(ParserModel::class);
    $p = $ref->getProperty($prop);
    if (PHP_VERSION_ID < 80100) {
        $p->setAccessible(true);
    }
    return $p->getValue($model);
}

return TestAssert::runSuite(function () {
    echo "=== source: keyed cache ===\n";

    $src = file_get_contents(APP_PATH . '/home/model/ParserModel.php');
    TestAssert::false($src === false, 'ParserModel readable');
    TestAssert::contains($src, "protected \$pre = array()", 'pre defaults to array');
    TestAssert::contains($src, "protected \$next = array()", 'next defaults to array');
    TestAssert::contains($src, "\$key = \$scode . '-' . \$id", 'cache key scode-id');
    TestAssert::contains($src, 'isset($this->pre[$key])', 'pre uses isset keyed cache');
    TestAssert::contains($src, 'isset($this->next[$key])', 'next uses isset keyed cache');
    TestAssert::notContains($src, 'if (! $this->pre)', 'old single-slot pre gate removed');
    TestAssert::notContains($src, 'if (! $this->next)', 'old single-slot next gate removed');

    echo "=== getContentPre: hit / miss / empty cache ===\n";

    $model = content_prev_next_model();
    $pre5 = new stdClass();
    $pre5->id = 5;
    content_prev_next_set_prop($model, 'pre', array('4-6' => $pre5));

    $hit = $model->getContentPre('4', 6);
    TestAssert::same(5, (int) $hit->id, 'pre cache hit returns keyed row');

    $missed = false;
    try {
        $model->getContentPre('6', 11);
    } catch (RuntimeException $e) {
        $missed = true;
        TestAssert::contains($e->getMessage(), 'scode=6', 'pre miss queries with new scode');
    }
    TestAssert::true($missed, 'pre different key does not reuse 4-6');

    // find() 无结果为 false；isset 应命中，避免旧逻辑 if (!$this->pre) 反复查库
    content_prev_next_set_prop($model, 'pre', array('4-6' => false));
    TestAssert::same(false, $model->getContentPre('4', 6), 'pre empty false cached by isset');

    echo "=== getContentNext: hit / miss ===\n";

    $model2 = content_prev_next_model();
    $next7 = new stdClass();
    $next7->id = 7;
    content_prev_next_set_prop($model2, 'next', array('4-6' => $next7));

    $hitNext = $model2->getContentNext('4', 6);
    TestAssert::same(7, (int) $hitNext->id, 'next cache hit returns keyed row');

    $missedNext = false;
    try {
        $model2->getContentNext('6', 11);
    } catch (RuntimeException $e) {
        $missedNext = true;
        TestAssert::contains($e->getMessage(), 'scode=6', 'next miss queries with new scode');
    }
    TestAssert::true($missedNext, 'next different key does not reuse 4-6');

    echo "=== property defaults ===\n";

    $fresh = content_prev_next_model();
    TestAssert::same(array(), content_prev_next_get_prop($fresh, 'pre'), 'fresh pre is empty array');
    TestAssert::same(array(), content_prev_next_get_prop($fresh, 'next'), 'fresh next is empty array');
});

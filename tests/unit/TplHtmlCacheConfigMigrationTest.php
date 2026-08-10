<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers ConfigModel::migrateTplHtmlCacheConfig()
 *
 * 运行：
 *   php tests/run.php --filter=TplHtmlCache
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/ConfigStub.php';
require_once CORE_PATH . '/basic/Model.php';
require_once APP_PATH . '/admin/model/system/ConfigModel.php';

final class TplHtmlCacheConfigModelDouble extends \app\admin\model\system\ConfigModel
{
    /** @var array<string, string> */
    public $store = array();

    /** @var array<int, array<string, mixed>> */
    public $added = array();

    /** @var bool */
    public $failAdd = false;

    public function checkConfig($where)
    {
        if (preg_match("/name='([^']+)'/", $where, $matches)) {
            $name = $matches[1];
            return array_key_exists($name, $this->store) ? (object) array('id' => 1) : null;
        }
        return null;
    }

    public function addConfig(array $data)
    {
        if ($this->failAdd) {
            return 0;
        }
        $this->added[] = $data;
        if (isset($data['name'], $data['value'])) {
            $this->store[$data['name']] = $data['value'];
        }
        return 1;
    }
}

return TestAssert::runSuite(function () {
    echo "=== migrate: copies file config when db rows missing ===\n";

    ConfigStub::reset();
    ConfigStub::set(array(
        'tpl_html_cache' => 1,
        'tpl_html_cache_time' => 1800,
    ));

    $model = new TplHtmlCacheConfigModelDouble();
    $migrated = $model->migrateTplHtmlCacheConfig();

    TestAssert::true($migrated, 'migrate: returns true when rows added');
    TestAssert::same(2, count($model->added), 'migrate: two rows inserted');
    TestAssert::same('1', $model->store['tpl_html_cache'], 'migrate: cache switch from file');
    TestAssert::same('1800', $model->store['tpl_html_cache_time'], 'migrate: cache time from file');

    echo "=== migrate: skips existing db values ===\n";

    ConfigStub::reset();
    ConfigStub::set(array(
        'tpl_html_cache' => 0,
        'tpl_html_cache_time' => 900,
    ));

    $model = new TplHtmlCacheConfigModelDouble();
    $model->store = array(
        'tpl_html_cache' => '1',
        'tpl_html_cache_time' => '3600',
    );
    $migrated = $model->migrateTplHtmlCacheConfig();

    TestAssert::false($migrated, 'existing db: no migration');
    TestAssert::same(0, count($model->added), 'existing db: no inserts');
    TestAssert::same('1', $model->store['tpl_html_cache'], 'existing db: value unchanged');
    TestAssert::same('3600', $model->store['tpl_html_cache_time'], 'existing db: time unchanged');

    echo "=== migrate: empty values default to 1 / 900000000000 ===\n";

    ConfigStub::reset();
    ConfigStub::set(array(
        'tpl_html_cache' => '',
        'tpl_html_cache_time' => '',
    ));

    $model = new TplHtmlCacheConfigModelDouble();
    $model->migrateTplHtmlCacheConfig();

    TestAssert::same('1', $model->store['tpl_html_cache'], 'empty switch: default 1');
    TestAssert::same('900000000000', $model->store['tpl_html_cache_time'], 'empty time: default 900000000000');

    echo "=== migrate: addConfig failure does not report success ===\n";

    ConfigStub::reset();
    ConfigStub::set(array(
        'tpl_html_cache' => 1,
        'tpl_html_cache_time' => 1800,
    ));

    $model = new TplHtmlCacheConfigModelDouble();
    $model->failAdd = true;
    $migrated = $model->migrateTplHtmlCacheConfig();

    TestAssert::false($migrated, 'addConfig fail: migrated false');
    TestAssert::same(0, count($model->added), 'addConfig fail: no rows stored');
    TestAssert::false($model->hasTplHtmlCacheConfig(), 'addConfig fail: not ready');

    echo "=== hasTplHtmlCacheConfig: both keys required ===\n";

    $model = new TplHtmlCacheConfigModelDouble();
    TestAssert::false($model->hasTplHtmlCacheConfig(), 'ready: empty false');
    $model->store['tpl_html_cache'] = '1';
    TestAssert::false($model->hasTplHtmlCacheConfig(), 'ready: partial false');
    $model->store['tpl_html_cache_time'] = '900000000000';
    TestAssert::true($model->hasTplHtmlCacheConfig(), 'ready: both true');

    echo "=== migrate: idempotent second run ===\n";

    ConfigStub::reset();
    ConfigStub::set(array(
        'tpl_html_cache' => 1,
        'tpl_html_cache_time' => 1800,
    ));

    $model = new TplHtmlCacheConfigModelDouble();
    $model->migrateTplHtmlCacheConfig();
    $migratedAgain = $model->migrateTplHtmlCacheConfig();
    TestAssert::false($migratedAgain, 'idempotent: second run false');
    TestAssert::same(2, count($model->added), 'idempotent: no extra inserts');

    ConfigStub::reset();
});

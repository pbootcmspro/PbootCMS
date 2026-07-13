<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers ConfigModel::appendIframeWhitelistHosts()
 * @covers sync_iframe_whitelist_from_content()
 * @covers iframe_whitelist_sync_notice() / iframe_whitelist_sync_notice_many()
 *
 * 运行：
 *   php tests/run.php --suite=integration --filter=IframeWhitelistSync
 *   php tests/run.php --filter=Iframe
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/IframeSyncHarness.php';
require_once TEST_ROOT . 'fixtures/security/IframeSyncPayloads.php';

return TestAssert::runSuite(function () {
    IframeSyncHarness::loadDeps();
    IframeSyncHarness::cleanupRunPath();

    echo "=== append: blank whitelist adds exact host ===\n";

    $cacheFile = IframeSyncHarness::seedConfigCache();
    $model = new ConfigModelTestDouble(array(), true);
    $rs = $model->appendIframeWhitelistHosts(array('player.example.com'));
    TestAssert::true($rs['ok'], 'append blank: ok');
    TestAssert::true($rs['added'], 'append blank: added');
    TestAssert::same(array('player.example.com'), $rs['added_hosts'], 'append blank: added_hosts');
    TestAssert::same('player.example.com', $model->store['content_iframe_whitelist'], 'append blank: store exact');
    TestAssert::notContains($model->store['content_iframe_whitelist'], '*.', 'append blank: no wildcard derived');
    TestAssert::true($model->modCalled, 'append blank: modValue path');
    TestAssert::false(file_exists($cacheFile), 'append blank: config cache deleted');

    echo "=== append: wildcard covers subdomain, skip write ===\n";

    $cacheFile = IframeSyncHarness::seedConfigCache();
    $model = new ConfigModelTestDouble(array(
        'content_iframe_whitelist' => '*.example.com',
    ), true);
    $rs = $model->appendIframeWhitelistHosts(array('sub.example.com'));
    TestAssert::true($rs['ok'], 'wildcard cover: ok');
    TestAssert::false($rs['added'], 'wildcard cover: not added');
    TestAssert::same('*.example.com', $model->store['content_iframe_whitelist'], 'wildcard cover: unchanged');
    TestAssert::false($model->modCalled, 'wildcard cover: no write');
    TestAssert::true(file_exists($cacheFile), 'wildcard cover: cache kept');

    echo "=== append: exact host idempotent ===\n";

    $model = new ConfigModelTestDouble(array(
        'content_iframe_whitelist' => 'player.example.com',
    ), true);
    $rs = $model->appendIframeWhitelistHosts(array('player.example.com'));
    TestAssert::true($rs['ok'], 'idempotent: ok');
    TestAssert::false($rs['added'], 'idempotent: not added');
    TestAssert::false($model->modCalled, 'idempotent: no write');

    echo "=== append: config row missing uses addConfig ===\n";

    $cacheFile = IframeSyncHarness::seedConfigCache();
    $model = new ConfigModelTestDouble(array(), false);
    $rs = $model->appendIframeWhitelistHosts(array('github.com'));
    TestAssert::true($rs['ok'], 'addConfig: ok');
    TestAssert::true($rs['added'], 'addConfig: added');
    TestAssert::true($model->addCalled, 'addConfig: called');
    TestAssert::false($model->modCalled, 'addConfig: not mod');
    TestAssert::same('content_iframe_whitelist', $model->lastAddData['name'], 'addConfig: name');
    TestAssert::same('github.com', $model->lastAddData['value'], 'addConfig: value');
    TestAssert::false(file_exists($cacheFile), 'addConfig: cache deleted');

    echo "=== append: write failure keeps cache, returns msg ===\n";

    $cacheFile = IframeSyncHarness::seedConfigCache();
    $model = new ConfigModelTestDouble(array(), true);
    $model->writeFails = true;
    $rs = $model->appendIframeWhitelistHosts(array('player.example.com'));
    TestAssert::false($rs['ok'], 'write fail: not ok');
    TestAssert::false($rs['added'], 'write fail: not added');
    TestAssert::same(array('player.example.com'), $rs['added_hosts'], 'write fail: added_hosts kept');
    TestAssert::same('白名单写入失败', $rs['msg'], 'write fail: msg');
    TestAssert::true(file_exists($cacheFile), 'write fail: cache not deleted');
    TestAssert::false(isset($model->store['content_iframe_whitelist']), 'write fail: store unchanged');

    echo "=== append: empty hosts no-op ===\n";

    $model = new ConfigModelTestDouble(array(), true);
    $rs = $model->appendIframeWhitelistHosts(array());
    TestAssert::true($rs['ok'], 'empty: ok');
    TestAssert::false($rs['added'], 'empty: not added');

    echo "=== sync_iframe_whitelist_from_content pipeline ===\n";

    $cacheFile = IframeSyncHarness::seedConfigCache();
    $model = new ConfigModelTestDouble(array(
        'content_iframe_whitelist' => 'map.baidu.com',
    ), true);
    $sync = sync_iframe_whitelist_from_content(IframeSyncPayloads::playerExample(), $model);
    TestAssert::true($sync['ok'], 'sync: ok');
    TestAssert::true($sync['added'], 'sync: added');
    TestAssert::contains($model->store['content_iframe_whitelist'], 'player.example.com', 'sync: host merged');
    TestAssert::contains($model->store['content_iframe_whitelist'], 'map.baidu.com', 'sync: old kept');
    TestAssert::false(file_exists($cacheFile), 'sync: cache deleted');

    $model2 = new ConfigModelTestDouble(array(), true);
    $syncEmpty = sync_iframe_whitelist_from_content(IframeSyncPayloads::noIframe(), $model2);
    TestAssert::true($syncEmpty['ok'], 'sync no iframe: ok');
    TestAssert::false($syncEmpty['added'], 'sync no iframe: not added');
    TestAssert::false($model2->modCalled, 'sync no iframe: no write');

    $model3 = new ConfigModelTestDouble(array(), true);
    $syncEscaped = sync_iframe_whitelist_from_content(IframeSyncPayloads::escapedPlayer(), $model3);
    TestAssert::true($syncEscaped['added'], 'sync escaped: decode then add');
    TestAssert::same('player.example.com', $model3->store['content_iframe_whitelist'], 'sync escaped: host');

    echo "=== iframe_whitelist_sync_notice failure text ===\n";

    $failModel = new ConfigModelTestDouble(array(), true);
    $failModel->writeFails = true;
    $notice = iframe_whitelist_sync_notice(IframeSyncPayloads::playerExample(), '修改成功！', $failModel);
    TestAssert::contains($notice, '修改成功！', 'notice: success prefix');
    TestAssert::contains($notice, 'iframe 域名未能自动加入白名单', 'notice: fail phrase');
    TestAssert::contains($notice, 'player.example.com', 'notice: host');
    TestAssert::contains($notice, 'iframe白名单', 'notice: manual path hint');

    $okModel = new ConfigModelTestDouble(array(
        'content_iframe_whitelist' => 'player.example.com',
    ), true);
    $noticeOk = iframe_whitelist_sync_notice(IframeSyncPayloads::playerExample(), '修改成功！', $okModel);
    TestAssert::same('', $noticeOk, 'notice: empty when ok');

    echo "=== iframe_whitelist_sync_notice_many merges ext fields ===\n";

    $manyModel = new ConfigModelTestDouble(array(), true);
    $manyModel->writeFails = true;
    $noticeMany = iframe_whitelist_sync_notice_many(
        array(
            IframeSyncPayloads::noIframe(),
            IframeSyncPayloads::github(),
        ),
        '新增成功！',
        $manyModel
    );
    TestAssert::contains($noticeMany, '新增成功！', 'notice many: prefix');
    TestAssert::contains($noticeMany, 'github.com', 'notice many: host from ext');

    $manyOk = new ConfigModelTestDouble(array(
        'content_iframe_whitelist' => '*.example.com',
    ), true);
    $noticeManyOk = iframe_whitelist_sync_notice_many(
        array(IframeSyncPayloads::subExample()),
        '修改成功！',
        $manyOk
    );
    TestAssert::same('', $noticeManyOk, 'notice many: wildcard covered empty');

    IframeSyncHarness::cleanupRunPath();
});

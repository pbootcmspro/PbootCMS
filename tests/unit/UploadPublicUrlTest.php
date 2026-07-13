<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php upload_public_url upload_is_upload_path rewrite_static_urls_in_html
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

// 子目录部署环境
define('SITE_DIR', '/cms');
define('STATIC_DIR', SITE_DIR . '/static');
defined('DOC_PATH') ?: define('DOC_PATH', ROOT_PATH);
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/function/file.php';

$_SERVER['HTTP_HOST'] = 'www.example.com';
$_SERVER['SERVER_PORT'] = '80';

function upload_public_test_inject_config(array $overrides)
{
    $ref = new ReflectionClass('core\basic\Config');
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array_merge(array(
        'upload_public_url_enable' => '0',
        'upload_public_url' => '',
    ), $overrides));
}

return TestAssert::runSuite(function () {
    echo "=== upload_is_upload_path (SITE_DIR=/cms) ===\n";

    upload_public_test_inject_config(array());

    TestAssert::true(
        upload_is_upload_path('/cms/static/upload/image/x.jpg'),
        'is upload: with SITE_DIR prefix'
    );
    TestAssert::true(
        upload_is_upload_path('/static/upload/image/x.jpg'),
        'is upload: canonical without SITE_DIR'
    );
    TestAssert::false(
        upload_is_upload_path('/cms/static/images/logo.png'),
        'is upload: non-upload static rejected'
    );

    echo "=== upload_public_url CDN off (fallback origin) ===\n";

    TestAssert::same(
        '/cms/static/upload/image/x.jpg',
        upload_public_url('/static/upload/image/x.jpg'),
        'cdn off: relative with SITE_DIR'
    );

    echo "=== upload_public_url CDN on ===\n";

    upload_public_test_inject_config(array(
        'upload_public_url_enable' => '1',
        'upload_public_url' => 'https://cdn.example.com',
    ));

    TestAssert::same(
        'https://cdn.example.com/static/upload/image/x.jpg',
        upload_public_url('/cms/static/upload/image/x.jpg'),
        'cdn on: path with SITE_DIR'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/x.jpg',
        upload_public_url('/static/upload/image/x.jpg'),
        'cdn on: canonical path'
    );

    echo "=== rewrite_static_urls_in_html ===\n";

    $htmlUeditor = '<img src="/cms/static/upload/image/a.jpg">';
    $htmlDb = '<a href="/static/upload/file/b.pdf">link</a>';

    TestAssert::contains(
        rewrite_static_urls_in_html($htmlUeditor),
        'https://cdn.example.com/static/upload/image/a.jpg',
        'rewrite: UEditor path'
    );
    TestAssert::contains(
        rewrite_static_urls_in_html($htmlDb),
        'https://cdn.example.com/static/upload/file/b.pdf',
        'rewrite: DB canonical path'
    );

    echo "=== upload_output_html CDN off (API compat) ===\n";

    upload_public_test_inject_config(array(
        'upload_public_url_enable' => '0',
        'upload_public_url' => '',
    ));

    $raw = '<img src="/cms/static/upload/image/x.jpg">';
    TestAssert::contains(
        upload_output_html($raw),
        'http://www.example.com/cms/static/upload/image/x.jpg',
        'output html: absolute origin when cdn off'
    );

    $canonical = '<img src="/static/upload/image/x.jpg">';
    TestAssert::contains(
        upload_output_html($canonical),
        'http://www.example.com/cms/static/upload/image/x.jpg',
        'output html cdn off: canonical /static/upload path'
    );

    echo "=== upload_output_url / paths (API single fields) ===\n";

    upload_public_test_inject_config(array(
        'upload_public_url_enable' => '0',
        'upload_public_url' => '',
    ));

    TestAssert::same(
        '/cms/static/upload/image/a.jpg',
        upload_output_url('/static/upload/image/a.jpg'),
        'output url cdn off: relative with SITE_DIR'
    );
    TestAssert::same(
        'https://other.example.com/x.jpg',
        upload_output_url('https://other.example.com/x.jpg'),
        'output url: external http unchanged'
    );

    upload_public_test_inject_config(array(
        'upload_public_url_enable' => '1',
        'upload_public_url' => 'https://cdn.example.com',
    ));

    TestAssert::same(
        'https://cdn.example.com/static/upload/image/a.jpg',
        upload_output_url('/static/upload/image/a.jpg'),
        'output url cdn on: CDN absolute'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/a.jpg,https://cdn.example.com/static/upload/image/b.jpg',
        upload_output_paths('/static/upload/image/a.jpg,/static/upload/image/b.jpg'),
        'output paths cdn on: comma-separated'
    );

    $row = (object) array(
        'ico' => '/static/upload/image/i.jpg',
        'pics' => '/static/upload/image/p1.jpg,/static/upload/image/p2.jpg',
        'enclosure' => '/static/upload/file/e.pdf',
    );
    upload_output_content_assets($row);
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/i.jpg',
        $row->ico,
        'output content assets: ico'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/p1.jpg,https://cdn.example.com/static/upload/image/p2.jpg',
        $row->pics,
        'output content assets: pics'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/file/e.pdf',
        $row->enclosure,
        'output content assets: enclosure'
    );

    echo "=== upload_output API helpers (full phase 2) ===\n";

    $slide = array(
        (object) array('pic' => '/static/upload/image/s1.jpg'),
        (object) array('pic' => '/static/upload/image/s2.jpg'),
    );
    upload_output_rows($slide, array('pic'));
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/s1.jpg',
        $slide[0]->pic,
        'output rows: slide pic'
    );

    $link = array((object) array('logo' => '/static/upload/image/l.jpg'));
    upload_output_rows($link, array('logo'));
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/l.jpg',
        $link[0]->logo,
        'output rows: link logo'
    );

    $child = (object) array(
        'ico' => '/static/upload/image/c.ico.jpg',
        'pic' => '/static/upload/image/c.pic.jpg',
    );
    $tree = array(
        (object) array(
            'ico' => '/static/upload/image/p.ico.jpg',
            'son' => array($child),
        ),
    );
    upload_output_sorts_tree($tree);
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/p.ico.jpg',
        $tree[0]->ico,
        'output sorts tree: parent ico'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/c.pic.jpg',
        $tree[0]->son[0]->pic,
        'output sorts tree: child pic'
    );

    $site = (object) array('logo' => '/static/upload/image/logo.jpg');
    upload_output_site_data($site);
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/logo.jpg',
        $site->logo,
        'output site: logo on object'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/logo.jpg',
        upload_output_site_data('/static/upload/image/logo.jpg', 'logo'),
        'output site: logo single field'
    );
    TestAssert::same(
        'PbootCMS',
        upload_output_site_data('PbootCMS', 'title'),
        'output site: non-image field unchanged'
    );

    $company = (object) array('weixin' => '/static/upload/image/wx.jpg');
    upload_output_company_data($company);
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/wx.jpg',
        $company->weixin,
        'output company: weixin on object'
    );
    TestAssert::same(
        'https://cdn.example.com/static/upload/image/wx.jpg',
        upload_output_company_data('/static/upload/image/wx.jpg', 'weixin'),
        'output company: weixin single field'
    );
});

<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php upload_catalog_extensions ueditor_merge_upload_config
 */

if (! function_exists('ueditor_merge_upload_config')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

use core\basic\Config;

function svg_avif_upload_config_reset()
{
    $ref = new ReflectionClass(Config::class);
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, null);
}

function svg_avif_upload_config_set($format)
{
    $ref = new ReflectionClass(Config::class);
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array(
        'upload' => array(
            'format' => $format,
        ),
    ));
}

return TestAssert::runSuite(function () {
    svg_avif_upload_config_reset();

    $catalog = upload_catalog_extensions();
    TestAssert::true(in_array('svg', $catalog, true), 'catalog contains svg');
    TestAssert::true(in_array('svgz', $catalog, true), 'catalog contains svgz');
    TestAssert::true(in_array('avif', $catalog, true), 'catalog contains avif');

    $format = Config::get('upload.format', true);
    TestAssert::true(is_array($format), 'upload.format loads as array');
    TestAssert::true(in_array('svg', $format, true), 'upload.format contains svg');
    TestAssert::true(in_array('svgz', $format, true), 'upload.format contains svgz');
    TestAssert::true(in_array('avif', $format, true), 'upload.format contains avif');

    $merged = ueditor_merge_upload_config(array(
        'imageAllowFiles' => array('.png', '.jpg'),
    ));
    foreach (array('.svg', '.svgz', '.avif') as $dot) {
        TestAssert::true(
            in_array($dot, $merged['imageAllowFiles'], true),
            'ueditor imageAllowFiles contains ' . $dot
        );
        TestAssert::true(
            in_array($dot, $merged['catcherAllowFiles'], true),
            'ueditor catcherAllowFiles contains ' . $dot
        );
    }

    svg_avif_upload_config_set('jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf');
    $legacy = upload_config_extensions();
    TestAssert::true(in_array('svg', $legacy, true), 'legacy default enables svg');
    TestAssert::true(in_array('svgz', $legacy, true), 'legacy default enables svgz');
    TestAssert::true(in_array('avif', $legacy, true), 'legacy default enables avif');

    TestAssert::same('image/svg+xml', upload_image_content_type('svg'), 'svg mime');
    TestAssert::same('image/svg+xml', upload_image_content_type('svgz'), 'svgz mime');
    TestAssert::same('image/avif', upload_image_content_type('avif'), 'avif mime');

    svg_avif_upload_config_reset();
});

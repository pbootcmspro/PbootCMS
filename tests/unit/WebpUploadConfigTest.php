<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Config.php
 * @covers core/function/file.php ueditor_merge_upload_config
 */

if (! function_exists('ueditor_merge_upload_config')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

use core\basic\Config;

function webp_upload_config_reset()
{
    $ref = new ReflectionClass(Config::class);
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, null);
}

function webp_upload_config_set($format)
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
    webp_upload_config_reset();

    $format = Config::get('upload.format', true);
    TestAssert::true(is_array($format), 'upload.format loads as array');
    TestAssert::true(in_array('webp', $format, true), 'upload.format contains webp');

    $merged = ueditor_merge_upload_config(array(
        'imageAllowFiles' => array('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp'),
    ));
    TestAssert::true(
        in_array('.webp', $merged['imageAllowFiles'], true),
        'ueditor imageAllowFiles contains .webp'
    );
    TestAssert::true(
        in_array('.webp', $merged['catcherAllowFiles'], true),
        'ueditor catcherAllowFiles contains .webp'
    );

    webp_upload_config_set('jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf');
    $legacy = upload_config_extensions();
    TestAssert::true(in_array('webp', $legacy, true), 'legacy default upload.format enables webp');
    $legacy_merged = ueditor_merge_upload_config(array());
    TestAssert::true(
        in_array('.webp', $legacy_merged['imageAllowFiles'], true),
        'legacy default enables UEditor webp'
    );

    webp_upload_config_set('jpg,jpeg,png,gif');
    $custom = upload_config_extensions();
    TestAssert::false(in_array('webp', $custom, true), 'custom upload.format keeps webp disabled');

    webp_upload_config_reset();
});

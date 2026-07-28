<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php imagick_probe image_capability_matrix image_backend_config
 *         image_backend_for image_backend_effective image_backend_clear_probe_cache
 * @covers core/function/handle.php get_server_info
 */

if (! function_exists('imagick_probe')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ConfigStub', false)) {
    require dirname(__DIR__) . '/support/ConfigStub.php';
}

exit(TestAssert::runSuite(function () {
    $cleanup = function () {
        unset(
            $GLOBALS['__test_imagick_probe'],
            $GLOBALS['__test_image_capability_matrix'],
            $GLOBALS['__test_image_backend_for']
        );
        image_backend_clear_probe_cache();
        ConfigStub::reset();
    };
    $cleanup();

    // --- 无 Imagick：探测安全返回 ---
    $GLOBALS['__test_imagick_probe'] = array(
        'loaded' => false,
        'version' => '',
        'formats' => array(
            'AVIF' => false,
            'HEIC' => false,
            'GIF' => false,
            'WEBP' => false,
            'JPEG' => false,
            'PNG' => false
        ),
        'delegates' => array(
            'avif' => false,
            'heic' => false,
            'webp' => false
        ),
        'animated_gif' => false
    );
    $probeEmpty = imagick_probe();
    TestAssert::false(! empty($probeEmpty['loaded']), 'imagick_probe loaded=false when extension absent (stub)');
    TestAssert::same('', $probeEmpty['version'], 'imagick_probe version empty when unloaded');
    TestAssert::false($probeEmpty['formats']['AVIF'], 'AVIF false when unloaded');
    TestAssert::false($probeEmpty['animated_gif'], 'animated_gif false when unloaded');

    // --- 有 Imagick：能力矩阵正确 ---
    $GLOBALS['__test_imagick_probe'] = array(
        'loaded' => true,
        'version' => 'ImageMagick 7.1.0 stub',
        'formats' => array(
            'AVIF' => true,
            'HEIC' => true,
            'GIF' => true,
            'WEBP' => true,
            'JPEG' => true,
            'PNG' => true
        ),
        'delegates' => array(
            'avif' => true,
            'heic' => true,
            'webp' => true
        ),
        'animated_gif' => true
    );
    image_backend_clear_probe_cache();
    $matrix = image_capability_matrix();
    TestAssert::true(! empty($matrix['imagick']['loaded']), 'matrix reflects imagick loaded');
    TestAssert::same('ImageMagick 7.1.0 stub', $matrix['imagick']['version'], 'matrix version');
    TestAssert::true(! empty($matrix['avif']['imagick']), 'AVIF via Imagick');
    TestAssert::true(! empty($matrix['heic']['imagick']), 'HEIC via Imagick');
    TestAssert::true(! empty($matrix['animated_gif']['imagick']), 'animated GIF via Imagick');
    TestAssert::false(! empty($matrix['animated_gif']['gd']), 'GD never claims animated GIF');

    // --- 探测结果缓存：同请求内不重复计算（覆盖桩只读一次路径） ---
    $GLOBALS['__image_capability_matrix_cache'] = array('cached' => true);
    $cached = image_capability_matrix();
    TestAssert::true(isset($cached['cached']), 'capability matrix uses request cache');
    unset($GLOBALS['__image_capability_matrix_cache'], $GLOBALS['__test_image_capability_matrix']);

    // --- image_backend 配置缺省 / 非法 → auto ---
    ConfigStub::set(array('image_backend' => ''));
    TestAssert::same('auto', image_backend_config(), 'empty config => auto');
    ConfigStub::set(array('image_backend' => 'bogus'));
    TestAssert::same('auto', image_backend_config(), 'invalid config => auto');
    ConfigStub::set(array('image_backend' => 'gd_only'));
    TestAssert::same('gd_only', image_backend_config(), 'gd_only accepted');
    ConfigStub::set(array('image_backend' => 'prefer_imagick'));
    TestAssert::same('prefer_imagick', image_backend_config(), 'prefer_imagick accepted');

    // --- 分派意图 vs 实际处理：prefer_imagick 意图可为 imagick，实际处理本阶段恒 gd ---
    ConfigStub::set(array('image_backend' => 'auto'));
    TestAssert::same('gd', image_backend_for('resize', IMAGETYPE_JPEG), 'auto => gd (phase1 no takeover)');
    TestAssert::same('gd', image_backend_effective(), 'effective is gd under auto');

    ConfigStub::set(array('image_backend' => 'gd_only'));
    // 即便 Imagick 探测为可用，gd_only 仍强制 GD
    TestAssert::same('gd', image_backend_for('watermark', null), 'gd_only forces gd');
    TestAssert::same('gd', image_backend_effective(), 'effective is gd under gd_only');

    ConfigStub::set(array('image_backend' => 'prefer_imagick'));
    TestAssert::same('imagick', image_backend_for('info', null), 'prefer_imagick + loaded => intent imagick');
    TestAssert::same('gd', image_backend_effective(), 'prefer_imagick + loaded => effective still gd (no takeover)');

    $GLOBALS['__test_imagick_probe'] = array(
        'loaded' => false,
        'version' => '',
        'formats' => array(
            'AVIF' => false,
            'HEIC' => false,
            'GIF' => false,
            'WEBP' => false,
            'JPEG' => false,
            'PNG' => false
        ),
        'delegates' => array(
            'avif' => false,
            'heic' => false,
            'webp' => false
        ),
        'animated_gif' => false
    );
    image_backend_clear_probe_cache();
    TestAssert::same('gd', image_backend_for('info', null), 'prefer_imagick without imagick => gd');
    TestAssert::same('gd', image_backend_effective(), 'effective remains gd without imagick');

    // --- 零行为变化：prefer_imagick 下缩放仍走现有 GD 路径 ---
    if (function_exists('imagecreatetruecolor')
        && function_exists('imagejpeg')
        && function_exists('resize_img')) {
        $tmpdir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_img_backend_' . mt_rand(10000, 99999);
        if (mkdir($tmpdir) || is_dir($tmpdir)) {
            $src = $tmpdir . DIRECTORY_SEPARATOR . 'src.jpg';
            $out = $tmpdir . DIRECTORY_SEPARATOR . 'out.jpg';
            try {
                $img = imagecreatetruecolor(40, 30);
                $red = imagecolorallocate($img, 200, 40, 40);
                imagefill($img, 0, 0, $red);
                imagejpeg($img, $src, 90);
                gd_free_image($img);
                // 恢复「Imagick 可用」桩，确认即使意图为 imagick 也不影响 GD 缩放
                $GLOBALS['__test_imagick_probe'] = array(
                    'loaded' => true,
                    'version' => 'IM-resize-stub',
                    'formats' => array(
                        'AVIF' => false,
                        'HEIC' => false,
                        'GIF' => true,
                        'WEBP' => false,
                        'JPEG' => true,
                        'PNG' => true
                    ),
                    'delegates' => array(
                        'avif' => false,
                        'heic' => false,
                        'webp' => false
                    ),
                    'animated_gif' => true
                );
                image_backend_clear_probe_cache();
                ConfigStub::set(array('image_backend' => 'prefer_imagick'));
                TestAssert::same(true, resize_img($src, $out, 20, 15), 'resize_img still works under prefer_imagick');
                TestAssert::true(is_file($out) && filesize($out) > 0, 'resize_img wrote GD output under prefer_imagick');
                $info = @getimagesize($out);
                TestAssert::true(is_array($info) && (int) $info[0] <= 20 && (int) $info[1] <= 15, 'resize_img GD dimensions under prefer_imagick');
            } finally {
                @unlink($src);
                @unlink($out);
                @rmdir($tmpdir);
            }
        }
    }

    // --- get_server_info 字段齐全；实际处理恒为 gd ---
    unset($GLOBALS['__test_imagick_probe']);
    $GLOBALS['__test_imagick_probe'] = array(
        'loaded' => true,
        'version' => 'IM-test-1.0',
        'formats' => array(
            'AVIF' => true,
            'HEIC' => false,
            'GIF' => true,
            'WEBP' => true,
            'JPEG' => true,
            'PNG' => true
        ),
        'delegates' => array(
            'avif' => true,
            'heic' => false,
            'webp' => true
        ),
        'animated_gif' => true
    );
    image_backend_clear_probe_cache();
    ConfigStub::set(array('image_backend' => 'prefer_imagick', 'database' => array('type' => 'sqlite')));

    // CLI 下补齐 get_server_info 依赖的 $_SERVER 键
    $serverKeys = array(
        'HTTP_HOST' => 'localhost',
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80',
        'SERVER_ADDR' => '127.0.0.1',
        'SERVER_SOFTWARE' => 'cli',
        'TMP' => sys_get_temp_dir()
    );
    foreach ($serverKeys as $k => $v) {
        if (! isset($_SERVER[$k])) {
            $_SERVER[$k] = $v;
        }
    }

    $server = get_server_info();
    TestAssert::true(isset($server->image_backend_config), 'server has image_backend_config');
    TestAssert::true(isset($server->image_backend_effective), 'server has image_backend_effective');
    TestAssert::true(isset($server->imagick), 'server has imagick');
    TestAssert::true(isset($server->imagick_version), 'server has imagick_version');
    TestAssert::true(isset($server->image_cap_avif), 'server has image_cap_avif');
    TestAssert::true(isset($server->image_cap_heic), 'server has image_cap_heic');
    TestAssert::true(isset($server->image_cap_animated_gif), 'server has image_cap_animated_gif');
    TestAssert::same('prefer_imagick', $server->image_backend_config, 'server config prefer_imagick');
    TestAssert::same('gd', $server->image_backend_effective, 'server effective still gd under prefer_imagick');
    TestAssert::same('IM-test-1.0', $server->imagick_version, 'server imagick version from probe');
    TestAssert::true(strpos((string) $server->image_cap_avif, 'Imagick') !== false, 'AVIF cap string mentions Imagick');

    // auto 配置下实际处理同样为 gd（不二次调用 get_server_info，避免 YES/NO 常量重定义）
    ConfigStub::set(array('image_backend' => 'auto'));
    TestAssert::same('auto', image_backend_config(), 'config auto after switch');
    TestAssert::same('gd', image_backend_effective(), 'effective gd under auto');

    $cleanup();
}));

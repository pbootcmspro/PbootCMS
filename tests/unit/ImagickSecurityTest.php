<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php imagick_probe_finalize imagick_version_meets_minimum
 *         imagick_detect_registry_risk_coders imagick_detect_runtime_readable_coders
 *         imagick_detect_denied_coders imagick_probe_coder_readable imagick_coder_probe_spec
 *         imagick_detect_dangerous_magic imagick_validate_input imagick_magic_identify
 *         imagick_format_is_whitelisted imagick_create imagick_policy_registry_status
 *         imagick_formats_compatible imagick_extension_matches_format
 *         imagick_guard_before_coalesce imagick_query_all_formats imagick_security_probe_read
 *         imagick_security_probe_cache_valid imagick_security_probe_encode
 */

if (! function_exists('imagick_probe_finalize')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ImagickSecurityPayloads', false)) {
    require dirname(__DIR__) . '/fixtures/security/ImagickSecurityPayloads.php';
}

exit(TestAssert::runSuite(function () {
    $cleanup = function () {
        unset(
            $GLOBALS['__test_imagick_query_formats'],
            $GLOBALS['__test_imagick_coder_readable'],
            $GLOBALS['__test_imagick_policy_applied'],
            $GLOBALS['__test_imagick_max_gif_frames'],
            $GLOBALS['__test_imagick_usable'],
            $GLOBALS['__test_imagick_usable_reason'],
            $GLOBALS['__test_imagick_security_probe']
        );
        image_backend_clear_probe_cache();
    };
    $cleanup();

    $loadedProbeBase = function () {
        $base = imagick_probe_defaults();
        $base['loaded'] = true;
        $base['unusable_reason'] = '';
        $base['version'] = 'ImageMagick 7.1.0-47 Q16';
        $base['formats']['JPEG'] = true;
        $base['formats']['PNG'] = true;
        $base['formats']['GIF'] = true;
        return $base;
    };

    // --- 版本基线 ---
    TestAssert::false(
        imagick_version_meets_minimum('ImageMagick 6.9.10-22 Q16'),
        '6.9.10-22 below baseline'
    );
    TestAssert::true(
        imagick_version_meets_minimum('ImageMagick 6.9.10-23 Q16'),
        '6.9.10-23 meets baseline'
    );
    TestAssert::false(
        imagick_version_meets_minimum('ImageMagick 7.0.8-10 Q16'),
        '7.0.8-10 below baseline'
    );
    TestAssert::true(
        imagick_version_meets_minimum('ImageMagick 7.0.8-11 Q16'),
        '7.0.8-11 meets baseline'
    );
    TestAssert::true(
        imagick_version_meets_minimum('ImageMagick 7.1.0-47 Q16'),
        '7.1.0 meets baseline'
    );
    TestAssert::false(
        imagick_version_meets_minimum('not-a-version'),
        'unparseable version rejected'
    );

    // --- 危险 coder：registry fail-closed（热路径不 readImage） ---
    $registryPdf = imagick_detect_registry_risk_coders(array('JPEG' => true, 'PNG' => true, 'PDF' => true));
    TestAssert::true(in_array('PDF', $registryPdf, true), 'PDF in registry => registry risk');
    $registryClean = imagick_detect_registry_risk_coders(array('JPEG' => true, 'PNG' => true, 'GIF' => true));
    TestAssert::same(array(), $registryClean, 'safe registry has no risk coders');
    $registryMvg = imagick_detect_registry_risk_coders(array('JPEG' => true, 'MVG' => true));
    TestAssert::true(in_array('MVG', $registryMvg, true), 'MVG in registry => registry risk even if runtime blocked');

    // --- 深度探测：file 类 coder 运行时可读性（显式触发路径） ---
    $GLOBALS['__test_imagick_coder_readable'] = array('PDF' => true);
    $withPdf = imagick_detect_runtime_readable_coders(array('JPEG' => true, 'PNG' => true, 'PDF' => true));
    TestAssert::true(in_array('PDF', $withPdf, true), 'PDF detected when runtime readable (deep)');
    unset($GLOBALS['__test_imagick_coder_readable']);
    $clean = imagick_detect_runtime_readable_coders(array('JPEG' => true, 'PNG' => true, 'GIF' => true));
    TestAssert::same(array(), $clean, 'safe format set has no runtime readable coders');

    $GLOBALS['__test_imagick_coder_readable'] = array('MVG' => false);
    $registryOnly = imagick_detect_runtime_readable_coders(array('JPEG' => true, 'MVG' => true));
    TestAssert::same(array(), $registryOnly, 'MVG in registry but not readable => not runtime denied');
    unset($GLOBALS['__test_imagick_coder_readable']);

    // --- 网络/spec coder：永不 readImage ---
    unset($GLOBALS['__test_imagick_coder_readable']);
    TestAssert::false(imagick_probe_coder_readable('HTTPS'), 'HTTPS never probed via readImage');
    TestAssert::false(imagick_probe_coder_readable('URL'), 'URL never probed via readImage');
    TestAssert::false(imagick_probe_coder_readable('HTTP'), 'HTTP never probed via readImage');
    TestAssert::false(imagick_coder_deep_probe_eligible('HTTPS'), 'HTTPS not deep-probe eligible');
    TestAssert::false(imagick_coder_deep_probe_eligible('HTTP'), 'HTTP not deep-probe eligible');

    $registryHttp = imagick_detect_registry_risk_coders(array('JPEG' => true, 'HTTP' => true));
    TestAssert::true(in_array('HTTP', $registryHttp, true), 'HTTP in registry => registry risk');
    $registryMsl = imagick_detect_registry_risk_coders(array('JPEG' => true, 'MSL' => true));
    TestAssert::true(in_array('MSL', $registryMsl, true), 'MSL in registry => registry risk');

    // --- queryFormats 空：fail-closed（第 1 项） ---
    $GLOBALS['__test_imagick_policy_applied'] = true;
    $emptyRegistry = imagick_probe_finalize($loadedProbeBase(), array());
    TestAssert::false(! empty($emptyRegistry['usable']), 'empty queryFormats => unusable');
    TestAssert::true(
        strpos((string) $emptyRegistry['unusable_reason'], 'queryFormats') !== false,
        'empty registry reason mentions queryFormats'
    );

    // --- 传入 formats 避免重复 query（第 6 项） ---
    $safeFormats = array('JPEG' => true, 'PNG' => true, 'GIF' => true, 'WEBP' => true);
    $finalized = imagick_probe_finalize($loadedProbeBase(), $safeFormats);
    TestAssert::true(! empty($finalized['usable']), 'safe registry + policy => usable');
    TestAssert::same(array(), $finalized['denied_coders'], 'no denied coders in safe registry');

    $GLOBALS['__test_imagick_query_formats'] = array('JPEG', 'PNG', 'MVG');
    $GLOBALS['__test_imagick_coder_readable'] = array('MVG' => true);
    $queried = imagick_query_all_formats();
    TestAssert::true(! empty($queried['MVG']), 'test stub query formats');
    $withMvg = imagick_probe_finalize($loadedProbeBase(), $queried);
    TestAssert::false(! empty($withMvg['usable']), 'MVG in registry => unusable (fail-closed)');
    TestAssert::true(
        strpos((string) $withMvg['unusable_reason'], 'MVG') !== false,
        'MVG listed in unusable reason'
    );
    TestAssert::true(in_array('MVG', $withMvg['registry_risk_coders'], true), 'MVG in registry_risk_coders');
    unset($GLOBALS['__test_imagick_query_formats'], $GLOBALS['__test_imagick_coder_readable']);

    // --- IM7：queryFormats 仍登记 MVG/PDF => fail-closed unusable（不依赖 readImage） ---
    $GLOBALS['__test_imagick_query_formats'] = array('JPEG', 'PNG', 'MVG', 'PDF');
    $im7Like = imagick_probe_finalize($loadedProbeBase(), imagick_query_all_formats());
    TestAssert::false(! empty($im7Like['usable']), 'IM7 registry lists MVG/PDF => unusable (registry fail-closed)');
    TestAssert::true(
        in_array('MVG', $im7Like['registry_risk_coders'], true) && in_array('PDF', $im7Like['registry_risk_coders'], true),
        'IM7 registry risk includes MVG and PDF'
    );
    unset($GLOBALS['__test_imagick_query_formats']);

    // --- queryFormats('*') 空 / 非数组：不接受无参降级子集（第 2 项） ---
    $GLOBALS['__test_imagick_query_formats'] = array();
    TestAssert::same(array(), imagick_query_all_formats(), 'empty star registry stays empty');
    $partialBypass = imagick_probe_finalize($loadedProbeBase(), imagick_query_all_formats());
    TestAssert::false(! empty($partialBypass['usable']), 'empty star registry => unusable (no plain fallback)');
    unset($GLOBALS['__test_imagick_query_formats']);

    $GLOBALS['__test_imagick_query_formats'] = false;
    TestAssert::same(array(), imagick_query_all_formats(), 'non-array query stub => empty registry');
    unset($GLOBALS['__test_imagick_query_formats']);

    // --- imagick_probe catch 后 $formats 预置 []（第 1 项） ---
    $afterException = imagick_probe_finalize($loadedProbeBase(), array());
    TestAssert::false(! empty($afterException['usable']), 'pre-init empty formats => unusable');

    // --- 深度探测缓存注入 finalize ---
    $probeVer = 'ImageMagick 7.1.0-47 Q16';
    $GLOBALS['__test_imagick_security_probe'] = array(
        'version_hash' => md5($probeVer),
        'probed_at' => 1700000000,
        'runtime_coders' => array('PDF'),
        'probe_mode' => 'file_only'
    );
    $withDeep = imagick_probe_finalize($loadedProbeBase(), $safeFormats);
    TestAssert::same(1700000000, $withDeep['deep_probe_at'], 'deep probe cache probed_at attached');
    TestAssert::same(array('PDF'), $withDeep['deep_probe_runtime_coders'], 'deep probe runtime coders attached');
    unset($GLOBALS['__test_imagick_security_probe']);

    $GLOBALS['__test_imagick_security_probe'] = array(
        'version' => $probeVer,
        'probed_at' => 1700000001,
        'runtime_coders' => array('MSL'),
        'probe_mode' => 'file_only'
    );
    $withLegacyDeep = imagick_probe_finalize($loadedProbeBase(), $safeFormats);
    TestAssert::same(1700000001, $withLegacyDeep['deep_probe_at'], 'legacy version string cache still attached');
    TestAssert::same(array('MSL'), $withLegacyDeep['deep_probe_runtime_coders'], 'legacy cache runtime coders attached');
    unset($GLOBALS['__test_imagick_security_probe']);

    TestAssert::true(
        imagick_security_probe_cache_valid(array(
            'version_hash' => md5($probeVer),
            'probed_at' => 1700000000
        ), $probeVer),
        'version_hash cache matches current version'
    );
    TestAssert::false(
        imagick_security_probe_cache_valid(array(
            'version_hash' => md5($probeVer),
            'probed_at' => 1700000000
        ), $probeVer . ' other'),
        'version_hash cache rejects other version'
    );
    TestAssert::true(
        imagick_security_probe_cache_valid(array(
            'version' => $probeVer,
            'probed_at' => 1700000000
        ), $probeVer),
        'legacy short version string cache still valid'
    );

    $worstVersion = 'ImageMagick 7.1.1-36 Q16-HDRI x86_64 openSUSE 2024-03-11 https://imagemagick.org';
    $worstCoders = array('MSVG', 'MVG', 'MSL', 'PS', 'EPS', 'PDF', 'SVG', 'SVGZ');
    $hashPayload = array(
        'version_hash' => md5($worstVersion),
        'probed_at' => 1780000000,
        'runtime_coders' => $worstCoders,
        'probe_mode' => 'file_only'
    );
    $hashJson = imagick_security_probe_encode($hashPayload);
    TestAssert::true(is_string($hashJson), 'worst-case hash payload encodes');
    TestAssert::true(
        is_string($hashJson) && strlen($hashJson) <= 200,
        'worst-case probe JSON fits ay_config.value varchar(200)'
    );
    $decodedHash = is_string($hashJson) ? json_decode($hashJson, true) : null;
    TestAssert::true(is_array($decodedHash) && ! isset($decodedHash['version']), 'raw versionString not persisted');
    TestAssert::same(md5($worstVersion), isset($decodedHash['version_hash']) ? $decodedHash['version_hash'] : null, 'persist version_hash');

    $legacyOverflow = json_encode(array(
        'version' => $worstVersion,
        'probed_at' => 1780000000,
        'runtime_coders' => $worstCoders,
        'probe_mode' => 'file_only'
    ), JSON_UNESCAPED_UNICODE);
    TestAssert::true(
        is_string($legacyOverflow) && strlen($legacyOverflow) > 200,
        'legacy raw versionString JSON overflowed varchar(200)'
    );

    // --- 魔数检测 ---
    TestAssert::true(imagick_detect_dangerous_magic(ImagickSecurityPayloads::pdfHead()), 'PDF magic');
    TestAssert::true(imagick_detect_dangerous_magic(ImagickSecurityPayloads::psHead()), 'PS magic');
    TestAssert::true(imagick_detect_dangerous_magic(ImagickSecurityPayloads::svgHead()), 'SVG magic');
    TestAssert::true(imagick_detect_dangerous_magic(ImagickSecurityPayloads::mvgHead()), 'MVG magic');
    TestAssert::false(imagick_detect_dangerous_magic("\xFF\xD8\xFF"), 'JPEG SOI not dangerous');

    // --- 魔数白名单识别 ---
    TestAssert::same('JPEG', imagick_magic_identify("\xFF\xD8\xFF\xE0"), 'magic JPEG');
    TestAssert::same('HEIC', imagick_magic_identify(ImagickSecurityPayloads::heicHead()), 'magic HEIC');
    TestAssert::same('AVIF', imagick_magic_identify(ImagickSecurityPayloads::avifHead()), 'magic AVIF');
    TestAssert::true(imagick_format_is_whitelisted('HEIC'), 'format whitelist HEIC');
    TestAssert::true(imagick_format_is_whitelisted('webp'), 'format whitelist case-insensitive');

    TestAssert::true(imagick_formats_compatible('JPEG', 'JPG'), 'JPEG/JPG compatible');
    TestAssert::true(imagick_formats_compatible('HEIC', 'HEIF'), 'HEIC/HEIF compatible');
    TestAssert::false(imagick_formats_compatible('JPEG', 'PNG'), 'JPEG/PNG incompatible');
    TestAssert::true(imagick_extension_matches_format('heif', 'HEIC'), 'heif ext matches HEIC format');

    // --- policy 注册表状态摘要 ---
    TestAssert::contains(
        imagick_policy_registry_status(array('registry_risk_coders' => array())),
        '注册表无危险 coder',
        'policy status clean registry'
    );
    TestAssert::contains(
        imagick_policy_registry_status(array('registry_risk_coders' => array('PDF', 'MSL'))),
        'PDF',
        'policy status lists risk coders'
    );

    // --- imagick_validate_input ---
    TestAssert::same(
        'Imagick 拒绝远程 URL 输入',
        imagick_validate_input('https://evil.example/payload.jpg'),
        'reject remote URL'
    );

    $tmpdir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_imagick_sec_' . mt_rand(10000, 99999);
    if (! (mkdir($tmpdir) || is_dir($tmpdir))) {
        throw new RuntimeException('cannot create temp dir');
    }
    try {
        $svgPath = $tmpdir . DIRECTORY_SEPARATOR . 'x.svg';
        file_put_contents($svgPath, ImagickSecurityPayloads::svgHead());
        TestAssert::true(
            is_string(imagick_validate_input($svgPath)),
            'reject .svg extension'
        );

        $polyPath = $tmpdir . DIRECTORY_SEPARATOR . 'poly.jpg';
        file_put_contents($polyPath, ImagickSecurityPayloads::pdfHead());
        TestAssert::same(
            'Imagick 拒绝危险文件内容',
            imagick_validate_input($polyPath),
            'reject PDF magic in .jpg'
        );

        $jpegPath = $tmpdir . DIRECTORY_SEPARATOR . 'ok.jpg';
        $jpegBytes = ImagickSecurityPayloads::minimalJpegBytes();
        if ($jpegBytes !== false && $jpegBytes !== '') {
            file_put_contents($jpegPath, $jpegBytes);
            TestAssert::same(true, imagick_validate_input($jpegPath), 'accept minimal JPEG');
        }

        $jpegBinPath = $tmpdir . DIRECTORY_SEPARATOR . 'payload.bin';
        if ($jpegBytes !== false && $jpegBytes !== '') {
            file_put_contents($jpegBinPath, $jpegBytes);
            TestAssert::same(
                'Imagick 无法确认图片类型，拒绝处理',
                imagick_validate_input($jpegBinPath),
                'reject JPEG content with .bin extension (getimagesize path)'
            );
        }

        $jpegPngPath = $tmpdir . DIRECTORY_SEPARATOR . 'misnamed.png';
        if ($jpegBytes !== false && $jpegBytes !== '') {
            file_put_contents($jpegPngPath, $jpegBytes);
            TestAssert::same(
                'Imagick 无法确认图片类型，拒绝处理',
                imagick_validate_input($jpegPngPath),
                'reject JPEG content with .png extension (ext/type mismatch)'
            );
        }

        $truncPath = $tmpdir . DIRECTORY_SEPARATOR . 'trunc.jpg';
        file_put_contents($truncPath, ImagickSecurityPayloads::truncatedJpegHead());
        TestAssert::same(
            'Imagick 无法确认图片类型，拒绝处理',
            imagick_validate_input($truncPath),
            'reject truncated JPEG (fail-closed)'
        );

        $unknownPath = $tmpdir . DIRECTORY_SEPARATOR . 'unknown.bin';
        file_put_contents($unknownPath, 'not-an-image-at-all');
        TestAssert::same(
            'Imagick 无法确认图片类型，拒绝处理',
            imagick_validate_input($unknownPath),
            'reject unknown content'
        );

        $heicPath = $tmpdir . DIRECTORY_SEPARATOR . 'photo.heic';
        file_put_contents($heicPath, ImagickSecurityPayloads::heicHead());
        TestAssert::same(true, imagick_validate_input($heicPath), 'accept HEIC via magic+ext');

        $heicWrongExt = $tmpdir . DIRECTORY_SEPARATOR . 'photo.jpg';
        file_put_contents($heicWrongExt, ImagickSecurityPayloads::heicHead());
        TestAssert::same(
            'Imagick 无法确认图片类型，拒绝处理',
            imagick_validate_input($heicWrongExt),
            'reject HEIC magic with .jpg extension'
        );

        $avifPath = $tmpdir . DIRECTORY_SEPARATOR . 'photo.avif';
        file_put_contents($avifPath, ImagickSecurityPayloads::avifHead());
        TestAssert::same(true, imagick_validate_input($avifPath), 'accept AVIF via magic+ext');
    } finally {
        foreach (glob($tmpdir . DIRECTORY_SEPARATOR . '*') ?: array() as $f) {
            @unlink($f);
        }
        @rmdir($tmpdir);
    }

    // --- imagick_create 工厂路径（stub，不依赖真实 Imagick 扩展） ---
    $GLOBALS['__test_imagick_usable'] = false;
    $GLOBALS['__test_imagick_usable_reason'] = '测试不可用';
    list($nil, $err) = imagick_create('/any/path.jpg');
    TestAssert::same(null, $nil, 'imagick_create unusable => null instance');
    TestAssert::contains($err, 'Imagick 不可用', 'imagick_create unusable reason');

    unset($GLOBALS['__test_imagick_usable'], $GLOBALS['__test_imagick_usable_reason']);
    $GLOBALS['__test_imagick_usable'] = true;
    $GLOBALS['__test_imagick_policy_applied'] = false;
    list($nil2, $err2) = imagick_create();
    TestAssert::same(null, $nil2, 'imagick_create policy fail => null');
    TestAssert::contains($err2, '资源限制未能应用', 'imagick_create policy fail reason');

    unset($GLOBALS['__test_imagick_policy_applied']);
    $GLOBALS['__test_imagick_policy_applied'] = true;
    list($nil3, $err3) = imagick_create('https://evil.example/x.jpg');
    TestAssert::same(null, $nil3, 'imagick_create URL => null');
    TestAssert::contains($err3, '远程 URL', 'imagick_create URL rejected');

    $tmpdir2 = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_imagick_create_' . mt_rand(10000, 99999);
    if (! (mkdir($tmpdir2) || is_dir($tmpdir2))) {
        throw new RuntimeException('cannot create temp dir');
    }
    try {
        $svgCreate = $tmpdir2 . DIRECTORY_SEPARATOR . 'x.svg';
        file_put_contents($svgCreate, ImagickSecurityPayloads::svgHead());
        list($nil4, $err4) = imagick_create($svgCreate);
        TestAssert::same(null, $nil4, 'imagick_create .svg => null');
        TestAssert::true(is_string($err4) && $err4 !== '', 'imagick_create .svg error');

        $jpegCreate = $tmpdir2 . DIRECTORY_SEPARATOR . 'ok.jpg';
        $jpegBytes2 = ImagickSecurityPayloads::minimalJpegBytes();
        if ($jpegBytes2 !== false && $jpegBytes2 !== '' && extension_loaded('imagick') && imagick_is_usable()) {
            file_put_contents($jpegCreate, $jpegBytes2);
            list($imOk, $errOk) = imagick_create($jpegCreate);
            TestAssert::true(is_object($imOk), 'imagick_create valid JPEG => Imagick instance');
            TestAssert::same('', $errOk, 'imagick_create valid JPEG no error');
            if (is_object($imOk) && method_exists($imOk, 'destroy')) {
                $imOk->destroy();
            }
        }
    } finally {
        foreach (glob($tmpdir2 . DIRECTORY_SEPARATOR . '*') ?: array() as $f) {
            @unlink($f);
        }
        @rmdir($tmpdir2);
    }
    unset($GLOBALS['__test_imagick_usable'], $GLOBALS['__test_imagick_policy_applied']);

    // --- coalesceImages 前守卫 ---
    $makeGifMock = function (array $frames) {
        return new class($frames) {
            private $frames;
            private $idx = 0;

            public function __construct(array $frames)
            {
                $this->frames = $frames;
            }

            public function getNumberImages()
            {
                return count($this->frames);
            }

            public function setIteratorIndex($i)
            {
                $this->idx = (int) $i;
            }

            public function getImageWidth()
            {
                return (int) $this->frames[$this->idx][0];
            }

            public function getImageHeight()
            {
                return (int) $this->frames[$this->idx][1];
            }

            public function getImagePage()
            {
                $w = (int) $this->frames[$this->idx][0];
                $h = (int) $this->frames[$this->idx][1];
                return array('width' => $w, 'height' => $h, 'x' => 0, 'y' => 0);
            }
        };
    };

    TestAssert::same(null, imagick_guard_before_coalesce($makeGifMock(array(array(100, 100)))), 'small GIF ok');

    $GLOBALS['__test_imagick_max_gif_frames'] = 2;
    TestAssert::true(
        is_string(imagick_guard_before_coalesce($makeGifMock(array(array(10, 10), array(10, 10), array(10, 10))))),
        'too many frames rejected'
    );
    unset($GLOBALS['__test_imagick_max_gif_frames']);

    $GLOBALS['__test_imagick_gif_decode_budget_ratio'] = 0.001;
    $GLOBALS['__test_imagick_resource_limit_memory'] = 1024 * 1024;
    $GLOBALS['__test_imagick_resource_limit_map'] = 1024 * 1024;
    TestAssert::true(
        is_string(imagick_guard_before_coalesce($makeGifMock(array(array(1000, 1000))))),
        'decode volume over budget rejected'
    );
    TestAssert::true(
        is_string(imagick_guard_before_coalesce($makeGifMock(array(array(10, 10))), 1000, 1000)),
        'guard uses getimagesize canvas over frame patch size'
    );
    unset($GLOBALS['__test_imagick_gif_decode_budget_ratio']);
    unset($GLOBALS['__test_imagick_resource_limit_memory']);
    unset($GLOBALS['__test_imagick_resource_limit_map']);

    TestAssert::true(
        is_string(imagick_guard_before_coalesce(new stdClass())),
        'invalid instance rejected'
    );

    $cleanup();
}));

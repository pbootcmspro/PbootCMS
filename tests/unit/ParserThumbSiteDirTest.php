<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app/home/controller/ParserController parserAssetUrl adjustLabelData (subdir thumbs)
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function parser_thumb_probe(string $siteDir, array $payload): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'parser_thumb_path_probe.php';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded = base64_encode($json);
    $cmd = escapeshellarg($php) . ' '
        . escapeshellarg($script) . ' '
        . escapeshellarg($siteDir) . ' '
        . escapeshellarg($encoded);
    $output = shell_exec($cmd);
    if (! is_string($output) || $output === '') {
        return array();
    }
    $data = json_decode(trim($output), true);
    return is_array($data) ? $data : array();
}

function parser_thumb_assert_ok(array $data, string $siteDir, string $label)
{
    TestAssert::true(isset($data['url']) && $data['url'] !== '', $label . ': url returned');
    TestAssert::true(! empty($data['src_exists']), $label . ': source image exists');
    TestAssert::true(! empty($data['out_exists']), $label . ': thumb written to disk');
    TestAssert::true(empty($data['has_double_prefix']), $label . ': no double SITE_DIR prefix');
    TestAssert::true(! empty($data['url_matches_adjust']), $label . ': parserAssetUrl matches adjustLabelData');
    TestAssert::same($data['url'], $data['url_from_prefixed'], $label . ': prefixed input same URL');

    $prefix = $siteDir === '' ? '' : $siteDir;
    TestAssert::true(
        strpos($data['url'], $prefix . '/runtime/image/') === 0,
        $label . ': url under SITE_DIR/runtime/image'
    );
    TestAssert::true(
        strpos(str_replace('\\', '/', $data['adjust_raw']), '/runtime/image/') === 0
            || strpos(str_replace('\\', '/', $data['adjust_raw']), 'runtime/image/') === 0,
        $label . ': adjust returns install-relative runtime path'
    );
}

return TestAssert::runSuite(function () {
    $canonical = '/static/upload/image/probe_thumb_13.jpg';
    $cases = array(
        array('site' => '', 'path' => $canonical, 'name' => 'root deploy'),
        array('site' => '/cms', 'path' => $canonical, 'name' => 'subdir /cms canonical'),
        array('site' => '/cms', 'path' => '/cms' . $canonical, 'name' => 'subdir /cms ueditor path'),
        array('site' => '/a/b', 'path' => $canonical, 'name' => 'multi-level /a/b'),
        array('site' => '/a/b', 'path' => '/a/b' . $canonical, 'name' => 'multi-level /a/b prefixed'),
    );

    $paramSets = array(
        array('name' => 'width+height', 'params' => array('width' => 40, 'height' => 30)),
        array('name' => 'width-only', 'params' => array('width' => 40)),
        array('name' => 'height-only', 'params' => array('height' => 30)),
        array('name' => 'maxwidth+maxheight', 'params' => array('maxwidth' => 40, 'maxheight' => 30)),
        array('name' => 'maxwidth-only', 'params' => array('maxwidth' => 40)),
        array('name' => 'maxheight-only', 'params' => array('maxheight' => 30)),
    );

    foreach ($cases as $case) {
        echo '=== ' . $case['name'] . " ===\n";
        foreach ($paramSets as $set) {
            $label = $case['name'] . ' / ' . $set['name'];
            $data = parser_thumb_probe($case['site'], array(
                'path' => $case['path'],
                'params' => $set['params'],
                'cleanup' => true,
            ));
            if (isset($data['error'])) {
                TestAssert::true(false, $label . ': probe error ' . $data['error']);
                continue;
            }
            parser_thumb_assert_ok($data, $case['site'], $label);
        }
    }

    echo "=== no resize: public URL ===\n";
    $noResize = parser_thumb_probe('/cms', array(
        'path' => $canonical,
        'params' => array(),
        'cleanup' => true,
    ));
    TestAssert::same(
        '/cms/static/upload/image/probe_thumb_13.jpg',
        isset($noResize['url']) ? $noResize['url'] : '',
        'subdir no-resize URL'
    );
    TestAssert::true(empty($noResize['has_double_prefix']), 'subdir no-resize: no double prefix');

    echo "=== CDN absolute URL + width (subdir) ===\n";
    $cdn = 'https://cdn.example.com';
    $cdnData = parser_thumb_probe('/cms', array(
        'path' => $cdn . $canonical,
        'params' => array('width' => 40, 'height' => 30),
        'cdn' => $cdn,
        'cleanup' => true,
    ));
    if (isset($cdnData['error'])) {
        TestAssert::true(false, 'cdn thumb: probe error ' . $cdnData['error']);
    } else {
        parser_thumb_assert_ok($cdnData, '/cms', 'cdn absolute + width');
        TestAssert::same(
            '/cms/runtime/image/w40_h30_probe_thumb_13.jpg',
            $cdnData['url'],
            'cdn thumb stays local under SITE_DIR (not CDN)'
        );
    }
});

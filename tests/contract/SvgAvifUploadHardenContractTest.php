<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 审计 #25 SVG/SVGZ/AVIF 上传加固契约
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
if (! function_exists('upload_ensure_htaccess')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    echo "=== file.php exposes sanitize + avif helpers ===\n";

    $fileFn = file_get_contents(CORE_PATH . '/function/file.php');
    TestAssert::false($fileFn === false, 'file.php readable');
    TestAssert::contains($fileFn, 'function sanitize_svg_string(', 'sanitize_svg_string defined');
    TestAssert::contains($fileFn, 'function sanitize_uploaded_svg(', 'sanitize_uploaded_svg defined');
    TestAssert::contains($fileFn, 'function svgz_decompress_limited(', 'svgz_decompress_limited defined');
    TestAssert::contains($fileFn, 'function svg_attr_value_has_unsafe_url(', 'svg_attr_value_has_unsafe_url defined');
    TestAssert::contains($fileFn, 'function svg_output_has_unsafe_url(', 'svg_output_has_unsafe_url defined');
    TestAssert::contains($fileFn, 'function gd_supports_avif(', 'gd_supports_avif defined');
    TestAssert::contains($fileFn, 'function upload_security_headers_map(', 'upload_security_headers_map defined');
    TestAssert::true(strpos($fileFn, 'function upload_send_security_headers(') === false, 'upload_send_security_headers removed');
    TestAssert::true(strpos($fileFn, 'function upload_emit_public_file(') === false, 'upload_emit_public_file removed');
    TestAssert::true(strpos($fileFn, 'function upload_json_extra(') === false, 'upload_json_extra removed');
    TestAssert::contains($fileFn, "'set', 'animate'", 'svg denyTags blocks SMIL');
    TestAssert::contains($fileFn, 'function imagick_post_process_avif(', 'imagick_post_process_avif defined');
    TestAssert::contains($fileFn, 'function avif_unsupported_error(', 'avif_unsupported_error defined');
    TestAssert::contains($fileFn, 'function image_type_arg_is_avif(', 'image_type_arg_is_avif defined');
    TestAssert::contains($fileFn, 'function upload_should_post_process_image(', 'upload_should_post_process_image defined');
    TestAssert::contains($fileFn, "'svg', 'svgz', 'avif'", 'catalog lists svg svgz avif');
    TestAssert::contains($fileFn, "'.svg', '.svgz', '.avif'", 'ueditor image_set lists new formats');

    echo "=== UEditor post-process includes SVG path ===\n";

    $upload = file_get_contents(CORE_PATH . '/extend/ueditor/php/action_upload.php');
    TestAssert::contains($upload, 'upload_should_post_process_image($full_path)', 'action_upload processes SVG/AVIF');
    TestAssert::contains($upload, '@unlink($full_path)', 'action_upload deletes on post-process fail');
    TestAssert::true(strpos($upload, "['warning']") === false, 'action_upload does not attach warning');
    $crawler = file_get_contents(CORE_PATH . '/extend/ueditor/php/action_crawler.php');
    TestAssert::contains($crawler, 'upload_should_post_process_image($full_path)', 'action_crawler processes SVG/AVIF');
    TestAssert::contains($crawler, '@unlink($full_path)', 'action_crawler deletes on post-process fail');
    TestAssert::true(strpos($crawler, "['warning']") === false, 'action_crawler does not attach warning');

    echo "=== upload .htaccess MIME + nosniff (deployed path) ===\n";

    TestAssert::true(is_file(ROOT_PATH . 'rewrite/static-upload.htaccess'), 'rewrite template tracked');
    TestAssert::true(is_file(ROOT_PATH . 'rewrite/static-upload.nginx.conf'), 'nginx snippet tracked');
    $ngx = file_get_contents(ROOT_PATH . 'rewrite/static-upload.nginx.conf');
    TestAssert::contains($ngx, 'image/svg+xml', 'nginx svg mime');
    TestAssert::contains($ngx, 'image/avif', 'nginx avif mime');
    TestAssert::contains($ngx, 'nosniff', 'nginx nosniff');
    TestAssert::contains($ngx, 'Content-Encoding', 'nginx svgz Content-Encoding');
    TestAssert::contains($ngx, '.svgz', 'nginx svgz location');
    TestAssert::contains($fileFn, 'function upload_ensure_htaccess(', 'upload_ensure_htaccess defined');

    // 禁止仅有 rewrite 模板却未部署到真实上传目录时误报通过
    TestAssert::true(function_exists('upload_ensure_htaccess'), 'upload_ensure_htaccess callable');
    TestAssert::true(upload_ensure_htaccess() === true, 'upload_ensure_htaccess succeeds');

    $dest = ROOT_PATH . 'static/upload/.htaccess';
    TestAssert::true(is_file($dest), 'static/upload/.htaccess deployed');
    $ht = file_get_contents($dest);
    TestAssert::false($ht === false, 'deployed htaccess readable');
    TestAssert::contains($ht, 'image/svg+xml', 'svg mime');
    TestAssert::contains($ht, 'image/avif', 'avif mime');
    TestAssert::contains($ht, 'X-Content-Type-Options', 'nosniff header');
    TestAssert::contains($ht, 'nosniff', 'nosniff value');
    TestAssert::contains($ht, 'FilesMatch', 'script execution denied');
});

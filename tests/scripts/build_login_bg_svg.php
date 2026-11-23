<?php

/**
 * 生成后台登录页背景 apps/admin/view/default/images/bg.svg（PHP 7.0+）
 *
 * 晨曦线性渐变 + 两块随机星点 pattern（最亮一组带十字光芒）。
 * 星点用自带的 Park-Miller 伪随机数，结果与 PHP 版本无关；mt_rand 在 PHP 7.1 改过范围算法，不可用。
 *
 * 用法: php tests/scripts/build_login_bg_svg.php [输出路径]
 * 默认输出: apps/admin/view/default/images/bg.svg
 * 改参数后重新运行本脚本并提交 bg.svg，tests/unit/LoginBgSvgTest.php 会校验两者一致。
 */

function login_bg_stars($size, $count, $seed, array $groups, $arm)
{
    $state = $seed;
    $next = function ($min, $max) use (&$state) {
        $state = ($state * 48271) % 2147483647;
        return $min + $state % ($max - $min + 1);
    };
    $out = '';
    $last = count($groups) - 1;
    foreach ($groups as $k => $g) {
        list($share, $width, $opacity) = $g;
        $dots = $cross = '';
        for ($i = 0, $n = (int) round($count * $share); $i < $n; $i++) {
            $x = $next($arm + 1, $size - $arm - 1);
            $y = $next($arm + 1, $size - $arm - 1);
            $dots .= "M$x {$y}h0";
            if ($k === $last) {
                $cross .= 'M' . ($x - $arm) . " {$y}h" . (2 * $arm) . "M$x " . ($y - $arm) . 'v' . (2 * $arm);
            }
        }
        $out .= "<path stroke='white' stroke-linecap='round' stroke-width='$width' stroke-opacity='$opacity' d='$dots'/>";
        if ($cross !== '') {
            $out .= "<path stroke='white' stroke-width='.6' stroke-opacity='.45' d='$cross'/>";
        }
    }
    return $out;
}

function login_bg_svg()
{
    $a = login_bg_stars(509, 120, 7, array(array(.5, 1.2, .35), array(.35, 1.6, .5), array(.15, 2, .7)), 3);
    $b = login_bg_stars(743, 80, 13, array(array(.6, 1.6, .55), array(.3, 2.2, .75), array(.1, 3, .95)), 5);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2560 1440" preserveAspectRatio="xMidYMid slice">'
        . '<defs><linearGradient id="g" x1="0.41" y1="0" x2="0.59" y2="1">'
        . '<stop offset="0" stop-color="#241a44"/><stop offset=".35" stop-color="#4b2f63"/>'
        . '<stop offset=".62" stop-color="#8e4f72"/><stop offset=".85" stop-color="#d9826b"/>'
        . '<stop offset="1" stop-color="#f2c48d"/></linearGradient>'
        . '<pattern id="a" width="509" height="509" patternUnits="userSpaceOnUse">' . $a . '</pattern>'
        . '<pattern id="b" width="743" height="743" patternUnits="userSpaceOnUse">' . $b . '</pattern></defs>'
        . '<rect width="100%" height="100%" fill="url(#g)"/><rect width="100%" height="100%" fill="url(#b)"/>'
        . '<rect width="100%" height="100%" fill="url(#a)"/></svg>' . "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $out = isset($argv[1]) ? (string) $argv[1]
        : dirname(dirname(__DIR__)) . '/apps/admin/view/default/images/bg.svg';
    if (file_put_contents($out, login_bg_svg()) === false) {
        fwrite(STDERR, "cannot write: $out\n");
        exit(1);
    }
    echo "written: $out\n";
}

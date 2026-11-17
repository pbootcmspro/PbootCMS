<?php

declare(strict_types=1);

/**
 * 线上后台 HTTP 验收：删后整单保存 / 删后快捷开关 / 未改保存
 *
 * 用法: php tests/scripts/admin_missing_record_http_probe.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$base = getenv('PBOOT_ADMIN_BASE') ?: 'http://dev.pbootcms.lan';
$user = getenv('PBOOT_ADMIN_USER') ?: 'admin';
$pass = getenv('PBOOT_ADMIN_PASS') ?: 'admin';
$mcode = getenv('PBOOT_MCODE') ?: '2'; // 新闻
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_accept_' . getmypid() . '.cookie';
$failed = 0;
$createdIds = array();

function http_ok($cond, $msg)
{
    global $failed;
    if ($cond) {
        echo "OK: $msg\n";
    } else {
        echo "FAIL: $msg\n";
        $failed++;
    }
}

function http_req($method, $url, $post = null, $headers = array())
{
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array(
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json, text/javascript, */*; q=0.01'
    ), $headers));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return array('code' => 0, 'headers' => '', 'body' => $err, 'json' => null);
    }
    $headersOut = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $json = json_decode($body, true);
    return array('code' => $code, 'headers' => $headersOut, 'body' => $body, 'json' => $json);
}

function extract_formcheck($html)
{
    if (preg_match('/name=["\']formcheck["\'][^>]*value=["\']([^"\']+)["\']/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']formcheck["\']/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/data-formcheck=["\']([^"\']+)["\']/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function extract_first_mod_id($html)
{
    if (preg_match('#Content/mod/id/(\d+)#', $html, $m)) {
        return (int) $m[1];
    }
    return 0;
}

@unlink($cookieFile);

echo "BASE $base\n";

// 登录页
$loginGet = http_req('GET', $base . '/admin.php');
$formcheck = extract_formcheck($loginGet['body']);
http_ok($formcheck !== '', 'login page formcheck');

$login = http_req('POST', $base . '/admin.php?p=/Index/login', array(
    'username' => $user,
    'password' => $pass,
    'formcheck' => $formcheck
));
$loginOk = false;
if (is_array($login['json']) && isset($login['json']['code']) && (int) $login['json']['code'] === 1) {
    $loginOk = true;
}
if (! $loginOk && (strpos($login['body'], '管理中心') !== false || strpos($login['headers'], 'Index/home') !== false)) {
    $loginOk = true;
}
http_ok($loginOk, 'admin login');

$home = http_req('GET', $base . '/admin.php?p=/Index/home');
$formcheck = extract_formcheck($home['body']) ?: $formcheck;
http_ok(strpos($home['body'], '管理中心') !== false, 'admin home');

$listUrl = $base . '/admin.php?p=/Content/index/mcode/' . $mcode;
$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;

// 新增一篇测试文章
$addPage = http_req('GET', $base . '/admin.php?p=/Content/add/mcode/' . $mcode);
$formcheck = extract_formcheck($addPage['body']) ?: $formcheck;
$title = 'accept345_' . date('His') . '_' . getmypid();
$add = http_req('POST', $base . '/admin.php?p=/Content/add/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'scode' => '2',
    'title' => $title,
    'titlecolor' => '#333333',
    'subtitle' => '',
    'filename' => '',
    'author' => 'admin',
    'source' => '本站',
    'outlink' => '',
    'date' => date('Y-m-d H:i:s'),
    'ico' => '',
    'pics' => '',
    'content' => 'accept body',
    'tags' => '',
    'keywords' => '',
    'description' => '',
    'status' => '1',
    'istop' => '0',
    'isrecommend' => '0',
    'isheadline' => '0',
    'gid' => '0',
    'gtype' => '4',
    'gnote' => '',
    'enclosure' => '',
    'subscode' => ''
));
$addMsg = is_array($add['json']) && isset($add['json']['data']['info']) ? $add['json']['data']['info'] : $add['body'];
$addOk = (is_array($add['json']) && isset($add['json']['code']) && (int) $add['json']['code'] === 1)
    || strpos($add['body'], '成功') !== false;
http_ok($addOk, 'create test content (' . $title . ')');

$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;
$id = 0;
if (preg_match('#Content/mod/id/(\d+)[^>]*>[\s\S]{0,200}' . preg_quote($title, '#') . '#u', $list['body'], $m)) {
    $id = (int) $m[1];
}
if (! $id && preg_match('#' . preg_quote($title, '#') . '[\s\S]{0,400}Content/mod/id/(\d+)#u', $list['body'], $m)) {
    $id = (int) $m[1];
}
if (! $id) {
    // fallback: newest mod link
    if (preg_match_all('#Content/mod/id/(\d+)#', $list['body'], $mm)) {
        $id = (int) $mm[1][0];
    }
}
http_ok($id > 0, 'resolve content id=' . $id);
$createdIds[] = $id;

$modUrl = $base . '/admin.php?p=/Content/mod/id/' . $id . '/mcode/' . $mcode;
$modPage = http_req('GET', $modUrl);
$formcheck = extract_formcheck($modPage['body']) ?: $formcheck;
http_ok(strpos($modPage['body'], $title) !== false || strpos($modPage['body'], '修改') !== false, 'open mod page before delete');

// 删除该文章
$del = http_req('POST', $base . '/admin.php?p=/Content/del/id/' . $id . '/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'stay' => ''
));
$delOk = (is_array($del['json']) && isset($del['json']['code']) && (int) $del['json']['code'] === 1)
    || strpos($del['body'], '删除成功') !== false;
http_ok($delOk, 'delete content id=' . $id);
$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;

// 删后整单保存（仍用旧 id）
$saveGone = http_req('POST', $modUrl, array(
    'formcheck' => $formcheck,
    'scode' => '2',
    'title' => $title,
    'titlecolor' => '#333333',
    'subtitle' => '',
    'filename' => '',
    'author' => 'admin',
    'source' => '本站',
    'outlink' => '',
    'date' => date('Y-m-d H:i:s'),
    'ico' => '',
    'pics' => '',
    'content' => 'accept body',
    'tags' => '',
    'keywords' => '',
    'description' => '',
    'status' => '1',
    'istop' => '0',
    'isrecommend' => '0',
    'isheadline' => '0',
    'gid' => '0',
    'gtype' => '4',
    'gnote' => '',
    'enclosure' => '',
    'subscode' => ''
));
$saveInfo = '';
if (is_array($saveGone['json'])) {
    if (isset($saveGone['json']['data']['info'])) {
        $saveInfo = (string) $saveGone['json']['data']['info'];
    } elseif (isset($saveGone['json']['data']) && is_string($saveGone['json']['data'])) {
        $saveInfo = $saveGone['json']['data'];
    } elseif (isset($saveGone['json']['info'])) {
        $saveInfo = (string) $saveGone['json']['info'];
    }
}
if ($saveInfo === '') {
    $saveInfo = $saveGone['body'];
}
http_ok(strpos($saveInfo, '已经不存在') !== false, 'delete-then-full-save already_gone');
http_ok(strpos($saveInfo, '修改成功') === false, 'delete-then-full-save no false success');

// 再建一篇测快捷开关
$title2 = 'accept345sw_' . date('His') . '_' . getmypid();
$add2 = http_req('POST', $base . '/admin.php?p=/Content/add/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'scode' => '2',
    'title' => $title2,
    'titlecolor' => '#333333',
    'author' => 'admin',
    'source' => '本站',
    'date' => date('Y-m-d H:i:s'),
    'content' => 'switch body',
    'status' => '1',
    'istop' => '0',
    'isrecommend' => '0',
    'isheadline' => '0',
    'gid' => '0',
    'gtype' => '4'
));
$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;
$id2 = 0;
if (preg_match_all('#Content/mod/id/(\d+)#', $list['body'], $mm)) {
    $id2 = (int) $mm[1][0];
}
http_ok($id2 > 0, 'second content id=' . $id2);
$createdIds[] = $id2;

$del2 = http_req('POST', $base . '/admin.php?p=/Content/del/id/' . $id2 . '/mcode/' . $mcode, array(
    'formcheck' => $formcheck
));
$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;

foreach (array(
    array('status', '0'),
    array('istop', '1'),
    array('isrecommend', '1')
) as $pair) {
    $sw = http_req('POST', $base . '/admin.php?p=/Content/mod/id/' . $id2 . '/mcode/' . $mcode, array(
        'formcheck' => $formcheck,
        'field' => $pair[0],
        'value' => $pair[1]
    ));
    $info = '';
    if (is_array($sw['json'])) {
        if (isset($sw['json']['data']['info'])) {
            $info = (string) $sw['json']['data']['info'];
        } elseif (isset($sw['json']['info'])) {
            $info = (string) $sw['json']['info'];
        }
    }
    if ($info === '') {
        $info = $sw['body'];
    }
    http_ok(strpos($info, '已经不存在') !== false, 'delete-then-toggle ' . $pair[0] . ' already_gone');
    http_ok(strpos($info, '修改成功') === false, 'delete-then-toggle ' . $pair[0] . ' no false success');
}

// 未改保存仍成功：再建并保存相同内容
$title3 = 'accept345ok_' . date('His') . '_' . getmypid();
http_req('POST', $base . '/admin.php?p=/Content/add/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'scode' => '2',
    'title' => $title3,
    'titlecolor' => '#333333',
    'author' => 'admin',
    'source' => '本站',
    'date' => date('Y-m-d H:i:s'),
    'content' => 'ok body',
    'status' => '1',
    'istop' => '0',
    'isrecommend' => '0',
    'isheadline' => '0',
    'gid' => '0',
    'gtype' => '4'
));
$list = http_req('GET', $listUrl);
$formcheck = extract_formcheck($list['body']) ?: $formcheck;
$id3 = 0;
if (preg_match_all('#Content/mod/id/(\d+)#', $list['body'], $mm)) {
    $id3 = (int) $mm[1][0];
}
http_ok($id3 > 0, 'third content id=' . $id3);
$createdIds[] = $id3;
$mod3 = http_req('GET', $base . '/admin.php?p=/Content/mod/id/' . $id3 . '/mcode/' . $mcode);
$formcheck = extract_formcheck($mod3['body']) ?: $formcheck;
// extract scode from page if present
$scode = '2';
if (preg_match('/name=["\']scode["\'][^>]*value=["\']([^"\']+)["\']/', $mod3['body'], $m)) {
    $scode = $m[1];
} elseif (preg_match('/<option[^>]+value=["\']([^"\']+)["\'][^>]*selected/', $mod3['body'], $m)) {
    $scode = $m[1];
}
$saveOk = http_req('POST', $base . '/admin.php?p=/Content/mod/id/' . $id3 . '/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'scode' => $scode,
    'title' => $title3,
    'titlecolor' => '#333333',
    'author' => 'admin',
    'source' => '本站',
    'date' => date('Y-m-d H:i:s'),
    'content' => 'ok body',
    'status' => '1',
    'istop' => '0',
    'isrecommend' => '0',
    'isheadline' => '0',
    'gid' => '0',
    'gtype' => '4'
));
$okInfo = '';
if (is_array($saveOk['json']) && isset($saveOk['json']['data']['info'])) {
    $okInfo = (string) $saveOk['json']['data']['info'];
} else {
    $okInfo = $saveOk['body'];
}
http_ok(strpos($okInfo, '修改成功') !== false, 'unchanged existing full-save success');

$swOk = http_req('POST', $base . '/admin.php?p=/Content/mod/id/' . $id3 . '/mcode/' . $mcode, array(
    'formcheck' => $formcheck,
    'field' => 'status',
    'value' => '1'
));
$swInfo = '';
if (is_array($swOk['json']) && isset($swOk['json']['data']['info'])) {
    $swInfo = (string) $swOk['json']['data']['info'];
} else {
    $swInfo = $swOk['body'];
}
http_ok(strpos($swInfo, '修改成功') !== false, 'unchanged existing toggle success');

// 清理测试文章
http_req('POST', $base . '/admin.php?p=/Content/del/id/' . $id3 . '/mcode/' . $mcode, array(
    'formcheck' => $formcheck
));

// 站点页抽查无回归
$site = http_req('GET', $base . '/admin.php?p=/Site/index');
http_ok(strpos($site['body'], '站点') !== false || strpos($site['body'], 'site') !== false, 'site page loads');

@unlink($cookieFile);

echo "\n";
if ($failed > 0) {
    echo "RESULT: FAIL ($failed)\n";
    if ($saveInfo !== '') {
        echo "DEBUG saveGone: " . substr($saveInfo, 0, 300) . "\n";
    }
    exit(1);
}
echo "RESULT: PASS\n";
exit(0);

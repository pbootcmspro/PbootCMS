<?php

/**
 * #234 线上验收（不改模板）：ParserModel 列表/搜索排序 + API list/search
 *
 * 用法:
 *   set PBOOT_TEST_BASE_URL=http://dev.pbootcms.lan
 *   php tests/scripts/order_isheadline_live_probe.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = getenv('PBOOT_TEST_BASE_URL');
if (!is_string($base) || trim($base) === '') {
    fwrite(STDERR, "SKIP: set PBOOT_TEST_BASE_URL\n");
    exit(0);
}
$base = rtrim(trim($base), '/');

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = parse_url($base, PHP_URL_HOST) ?: 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

define('IS_INDEX', true);
define('URL_BIND', 'home');
require $root . '/core/init.php';

use app\home\model\ParserModel;
use core\basic\Config;
use core\basic\Model;

$marker = 'issue234_' . date('YmdHis');
$titleRec = $marker . '_甲仅推荐';
$titleHead = $marker . '_乙仅头条';
$scode = '2';
$date = date('Y-m-d H:i:s', time() - 120);
$ids = array();
$code = 1;

$headlineOrder = 'a.isheadline DESC,a.istop DESC,a.isrecommend DESC,a.sorting ASC,a.date DESC,a.id DESC';
$recommendOrder = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
$istopOrder = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';

function issue234_http($url, $post = null)
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array('Cache-Control: no-cache'),
    );
    if (is_array($post)) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        throw new RuntimeException("HTTP fail {$url}: {$err}");
    }
    return array($status, (string) $body);
}

function issue234_api_auth_params()
{
    $q = array();
    $auth = Config::get('api_auth');
    if ($auth) {
        $appid = (string) Config::get('api_appid');
        $secret = (string) Config::get('api_secret');
        $ts = (string) time();
        $q['appid'] = $appid;
        $q['timestamp'] = $ts;
        $q['signature'] = md5(md5($appid . $secret . $ts));
    }
    return $q;
}

function issue234_api_url($base, $path, $query)
{
    return $base . $path . '?' . http_build_query(array_merge(issue234_api_auth_params(), $query));
}

function issue234_json_titles($json)
{
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['code'])) {
        throw new RuntimeException('API 响应非 JSON: ' . substr($json, 0, 200));
    }
    if ((int) $data['code'] !== 1) {
        $msg = is_string($data['data']) ? $data['data'] : json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        throw new RuntimeException('API code!=1: ' . $msg);
    }
    $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : array();
    $titles = array();
    foreach ($rows as $row) {
        if (isset($row['title'])) {
            $titles[] = $row['title'];
        }
    }
    return $titles;
}

function issue234_assert_titles(array $titles, $firstTitle, $secondTitle, $label)
{
    $p1 = array_search($firstTitle, $titles, true);
    $p2 = array_search($secondTitle, $titles, true);
    if ($p1 === false || $p2 === false) {
        throw new RuntimeException("{$label}: 未同时返回夹具标题");
    }
    if ($p1 >= $p2) {
        throw new RuntimeException("{$label}: 期望「{$firstTitle}」先于「{$secondTitle}」(pos {$p1}>={$p2})");
    }
    echo "OK: {$label}\n";
}

function issue234_rows_titles($rows)
{
    $titles = array();
    if (!$rows) {
        return $titles;
    }
    foreach ($rows as $row) {
        $t = is_object($row) ? $row->title : (isset($row['title']) ? $row['title'] : null);
        if ($t !== null) {
            $titles[] = $t;
        }
    }
    return $titles;
}

try {
    echo "=== #234 live probe base={$base} marker={$marker} ===\n";

    foreach (array(
        $root . '/apps/home/controller/ParserController.php',
        $root . '/apps/api/controller/CmsController.php',
        $root . '/apps/api/controller/ListController.php',
    ) as $file) {
        $src = file_get_contents($file);
        if (!preg_match_all("/case\\s+'isheadline'\\s*:\\s*\\r?\\n\\s*\\\$order\\s*=\\s*'([^']+)'\\s*;/", $src, $m)) {
            throw new RuntimeException(basename($file) . ' 缺少 isheadline $order');
        }
        foreach ($m[1] as $got) {
            if ($got !== $headlineOrder) {
                throw new RuntimeException(basename($file) . ' isheadline 排序串不符: ' . $got);
            }
        }
        if (!preg_match("/case\\s+'isrecommend'\\s*:\\s*\\r?\\n\\s*\\\$order\\s*=\\s*'([^']+)'\\s*;/", $src, $mr)
            || $mr[1] !== $recommendOrder) {
            throw new RuntimeException(basename($file) . ' isrecommend 回归');
        }
        if (!preg_match("/case\\s+'istop'\\s*:\\s*\\r?\\n\\s*\\\$order\\s*=\\s*'([^']+)'\\s*;/", $src, $mi)
            || $mi[1] !== $istopOrder) {
            throw new RuntimeException(basename($file) . ' istop 回归');
        }
    }
    echo "OK: 控制器排序串（list/search/API 源码）\n";

    $model = new Model();
    $common = array(
        'acode' => 'cn',
        'scode' => $scode,
        'subscode' => '',
        'titlecolor' => '',
        'subtitle' => '',
        'filename' => '',
        'author' => 'issue234',
        'source' => '',
        'outlink' => '',
        'date' => $date,
        'ico' => '',
        'pics' => '',
        'content' => 'issue234 probe ' . $marker,
        'tags' => $marker,
        'enclosure' => '',
        'keywords' => $marker,
        'description' => $marker,
        'sorting' => 255,
        'status' => 1,
        'istop' => 0,
        'visits' => 0,
        'likes' => 0,
        'oppose' => 0,
        'create_user' => 'issue234',
        'update_user' => 'issue234',
        'create_time' => $date,
        'update_time' => $date,
        'gtype' => 4,
        'gid' => '',
        'gnote' => '',
    );

    $id1 = $model->table('ay_content')->insertGetId(array_merge($common, array(
        'title' => $titleRec,
        'isrecommend' => 1,
        'isheadline' => 0,
    )));
    $id2 = $model->table('ay_content')->insertGetId(array_merge($common, array(
        'title' => $titleHead,
        'isrecommend' => 0,
        'isheadline' => 1,
    )));
    if (!$id1 || !$id2) {
        throw new RuntimeException('夹具写入失败');
    }
    $ids = array((int) $id1, (int) $id2);
    echo "OK: 夹具 id={$id1},{$id2}\n";

    $sqlHeadline = str_replace('a.', '', $headlineOrder);
    $sqlRecommend = str_replace('a.', '', $recommendOrder);
    $sqlIstop = str_replace('a.', '', $istopOrder);
    $rows = $model->table('ay_content')->field('title')->where("title like '{$marker}%'")->order($sqlHeadline)->select();
    issue234_assert_titles(issue234_rows_titles($rows), $titleHead, $titleRec, 'SQL order=isheadline');
    $rowsR = $model->table('ay_content')->field('title')->where("title like '{$marker}%'")->order($sqlRecommend)->select();
    issue234_assert_titles(issue234_rows_titles($rowsR), $titleRec, $titleHead, 'SQL order=isrecommend 无回归');
    $rowsT = $model->table('ay_content')->field('title')->where("title like '{$marker}%'")->order($sqlIstop)->select();
    issue234_assert_titles(issue234_rows_titles($rowsT), $titleRec, $titleHead, 'SQL order=istop 无回归');

    // ParserModel::getList ≈ {pboot:list}
    $parser = new ParserModel();
    $listHead = $parser->getList($scode, 50, $headlineOrder);
    issue234_assert_titles(issue234_rows_titles($listHead), $titleHead, $titleRec, 'ParserModel getList order=isheadline ≈ {pboot:list}');
    $listRec = $parser->getList($scode, 50, $recommendOrder);
    issue234_assert_titles(issue234_rows_titles($listRec), $titleRec, $titleHead, 'ParserModel getList order=isrecommend 无回归');
    $listTop = $parser->getList($scode, 50, $istopOrder);
    issue234_assert_titles(issue234_rows_titles($listTop), $titleRec, $titleHead, 'ParserModel getList order=istop 无回归');

    // API
    $listUrl = issue234_api_url($base, '/api.php/list', array(
        'scode' => $scode,
        'order' => 'isheadline',
        'num' => 50,
    ));
    list($stL, $bodyL) = issue234_http($listUrl);
    if ($stL < 200 || $stL >= 400) {
        throw new RuntimeException("api list HTTP {$stL}");
    }
    issue234_assert_titles(issue234_json_titles($bodyL), $titleHead, $titleRec, 'api.php/list order=isheadline');

    $listRecUrl = issue234_api_url($base, '/api.php/list', array(
        'scode' => $scode,
        'order' => 'isrecommend',
        'num' => 50,
    ));
    list(, $bodyRec) = issue234_http($listRecUrl);
    issue234_assert_titles(issue234_json_titles($bodyRec), $titleRec, $titleHead, 'api.php/list order=isrecommend 无回归');

    $listTopUrl = issue234_api_url($base, '/api.php/list', array(
        'scode' => $scode,
        'order' => 'istop',
        'num' => 50,
    ));
    list(, $bodyTop) = issue234_http($listTopUrl);
    issue234_assert_titles(issue234_json_titles($bodyTop), $titleRec, $titleHead, 'api.php/list order=istop 无回归');

    $searchPost = array_merge(issue234_api_auth_params(), array(
        'keyword' => $marker,
        'order' => 'isheadline',
        'num' => 50,
    ));
    list(, $bodySearch) = issue234_http($base . '/api.php/cms/search', $searchPost);
    issue234_assert_titles(issue234_json_titles($bodySearch), $titleHead, $titleRec, 'api.php/cms/search order=isheadline');

    $searchRecPost = array_merge(issue234_api_auth_params(), array(
        'keyword' => $marker,
        'order' => 'isrecommend',
        'num' => 50,
    ));
    list(, $bodySearchRec) = issue234_http($base . '/api.php/cms/search', $searchRecPost);
    issue234_assert_titles(issue234_json_titles($bodySearchRec), $titleRec, $titleHead, 'api.php/cms/search order=isrecommend 无回归');

    $code = 0;
    echo "=== ALL PASS ===\n";
} catch (Exception $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $code = 1;
} finally {
    if (!empty($ids)) {
        try {
            $cleanup = new Model();
            foreach ($ids as $id) {
                $cleanup->table('ay_content')->where('id=' . (int) $id)->delete();
            }
            echo "OK: 夹具已清理\n";
        } catch (Exception $ex) {
            fwrite(STDERR, 'WARN: 清理夹具失败: ' . $ex->getMessage() . "\n");
        }
    }
}

exit($code);

<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2021年10月27日
 *
 */
namespace app\home\controller;

use core\basic\Controller;
use core\log\LogSpider;

class SpiderController extends Controller
{

    private $url;

    public function __construct($url = null)
    {
        $this->url = $this->normalizeUrl($url);
    }

    public function index()
    {
        if ($this->config('spiderlog') === '0') {
            return;
        }
        if ($this->url === '') {
            return;
        }
        $rs = $this->getSpider();
        if ($rs !== false) {
            LogSpider::getInstance()->write($rs, $this->url);
        }
    }

    // 规范化待记录 URL：去控制字符、限制长度，保留原始可读性
    private function normalizeUrl($url = null)
    {
        if ($url === null || $url === '') {
            $url = get('url');
        }
        if (! is_string($url)) {
            return '';
        }
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $url = str_replace(array("\0", "\r", "\n", "\t"), '', $url);
        if (mb_strlen($url) > 2000) {
            $url = mb_substr($url, 0, 2000);
        }
        return $url;
    }

    private function getSpider()
    {
        $useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($useragent, 'googlebot') !== false) {
            return 'Google';
        } elseif (strpos($useragent, 'baiduspider') !== false) {
            return 'Baidu';
        } elseif (strpos($useragent, 'webscan') !== false) {
            return '360WebScan';
        } elseif (strpos($useragent, '360spider') !== false) {
            return '360So';
        } elseif (strpos($useragent, 'adsbot') !== false) {
            return 'Adwords';
        } elseif (strpos($useragent, 'bingbot') !== false) {
            return 'Bing';
        } elseif (strpos($useragent, 'slurp') !== false) {
            return 'Yahoo';
        } elseif (strpos($useragent, 'sosospider') !== false) {
            return 'Soso';
        } elseif (strpos($useragent, 'sogou') !== false) {
            return 'Sogou';
        } elseif (strpos($useragent, 'yodaobot') !== false) {
            return 'Yodao';
        } elseif (strpos($useragent, 'speedy') !== false) {
            return 'Speedy';
        } elseif (strpos($useragent, 'yandexbot') !== false) {
            return 'Yandex';
        } elseif (strpos($useragent, 'easouspider') !== false) {
            return 'Easou';
        } elseif (strpos($useragent, 'symantecspider') !== false) {
            return 'Symantec';
        } elseif (strpos($useragent, 'qiniu') !== false) {
            return 'Qiniu';
        } elseif (strpos($useragent, 'jiankongbao') !== false) {
            return 'JianKongBao';
        } elseif (strpos($useragent, 'dnspod') !== false) {
            return 'DNSPod';
        } elseif (strpos($useragent, 'linkpadbot') !== false) {
            return 'Linkpad';
        } elseif (strpos($useragent, 'mj12bot') !== false) {
            return 'MJ12';
        } elseif (strpos($useragent, 'dingtalkbot') !== false) {
            return 'DingTalk';
        } elseif (strpos($useragent, 'bytespider') !== false) {
            return 'Byte';
        } elseif (strpos($useragent, 'zoominfobot') !== false) {
            return 'Zoominfo';
        } elseif (strpos($useragent, 'yisouspider') !== false) {
            return 'Yisou';
        } elseif (strpos($useragent, 'spider') !== false) {
            return 'other-spider';
        } elseif (strpos($useragent, 'bot') !== false) {
            return 'other-bot';
        }
        return false;
    }
}
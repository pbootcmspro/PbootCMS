<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  标签解析引擎控制器
 */

namespace app\home\controller;

use app\home\model\DoModel;
use app\home\model\MemberModel;
use app\home\model\ParserModel;
use core\basic\Controller;
use core\basic\Url;

class ParserController extends Controller
{

    protected $model;

    protected $pre = array();

    protected $var = array();

    public function __construct()
    {
        $this->model = new ParserModel();
    }

    public function _empty()
    {
        _404('您访问的地址有误，请核对后重试！');
    }

    // 解析全局前置公共标签
    public function parserBefore($content)
    {
        // 处理模板中不需要解析的标签
        $content = $this->savePreLabel($content);
        $content = $this->parserSingleLabel($content); // 单标签解析
        $content = $this->parserUserLabel($content); // 自定义标签
        return $content;
    }

    // 解析全局后置公共标签
    public function parserAfter($content)
    {
        // 默认页面信息替换
        $content = str_replace('{pboot:pagetitle}', $this->config('other_title') ?: '{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = str_replace('{pboot:pagekeywords}', '{pboot:sitekeywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{pboot:sitedescription}', $content);
        $content = str_replace('{pboot:keyword}', get('keyword', 'vars'), $content); // 当前搜索的关键字

        // 解析个人扩展标签，升级不覆盖
        if (file_exists(APP_PATH . '/home/controller/ExtLabelController.php')) {
            if (class_exists('app\home\controller\ExtLabelController')) {
                $extlabel = new ExtLabelController();
                $content = $extlabel->run($content);
            }
        }

        $content = $this->parserSiteLabel($content); // 站点标签
        $content = $this->parserCompanyLabel($content); // 公司标签
        $content = $this->parserMemberLabel($content); // 会员标签
        $content = $this->parserNavLabel($content); // 分类列表
        $content = $this->parserSelectAllLabel($content); // CMS筛选全部标签解析
        $content = $this->parserSelectLabel($content); // CMS筛选标签解析
        $content = $this->parserSpecifySortLabel($content); // 指定分类
        $content = $this->parserListLabel($content); // 指定列表
        $content = $this->parserSpecifyContentLabel($content); // 指定内容
        $content = $this->parserContentPicsLabel($content); // 内容多图
        $content = $this->parserContentCheckboxLabel($content); // 内容多选调取
        $content = $this->parserContentTagsLabel($content); // 内容tags调取
        $content = $this->parserSlideLabel($content); // 幻灯片
        $content = $this->parserLinkLabel($content); // 友情链接
        $content = $this->parserMessageLabel($content); // 留言板
        $content = $this->parserFormLabel($content); // 自定义表单
        $content = $this->parserSubmitFormLabel($content); // 自定义表单提交
        //$content = $this->parserSqlListLabel($content); // 自定义SQL输出(V3.2.4开始已废弃)

        $content = $this->parserQrcodeLabel($content); // 二维码生成
        $content = $this->parserPageLabel($content); // CMS分页标签解析(需置后)
        $content = $this->parserIfLabel($content); // IF语句(需置最后)
        $content = $this->parserLoopLabel($content); // LOOP语句(需置后，不可放到if前面，否则有安全风险)
        $content = $this->restorePreLabel($content); // 还原不需要解析的内容
        $content = $this->parserReplaceKeyword($content); // 页面关键词替换
        return $content;
    }

    // 保存保留内容
    public function savePreLabel($content)
    {
        $pattern = '/\{pboot:pre}([\s\S]*?)\{\/pboot:pre\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $this->pre[] = $matches[1][$i];
                end($this->pre);
                $content = str_replace($matches[0][$i], '#pre:' . key($this->pre) . '#', $content);
            }
        }
        return $content;
    }

    // 还原保留内容
    public function restorePreLabel($content)
    {
        $pattern = '/\#pre:([0-9]+)\#/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $content = str_replace($matches[0][$i], $this->pre[$matches[1][$i]], $content);
            }
        }
        $content = str_replace('pboot@if', 'pboot:if', $content); // 还原系统解析if标签
        return $content;
    }

    // 解析单标签
    public function parserSingleLabel($content)
    {
        $content = str_replace('{pboot:ucenter}', Url::home('member/ucenter'), $content); // 用户中心
        if (!!$url = get("backurl")) { // 获取会跳地址
            $content = str_replace('{pboot:login}', Url::home('member/login', null, "backurl=" . urlencode($url)), $content); // 登录地址
        } else {
            $content = str_replace('{pboot:login}', Url::home('member/login'), $content); // 登录地址
        }

        $content = str_replace('{pboot:register}', Url::home('member/register'), $content); // 注册地址
        $content = str_replace('{pboot:retrieve}', Url::home('member/retrieve'), $content); //找回密码
        $content = str_replace('{pboot:isregister}', Url::home('member/isRegister'), $content); // 检查是否注册地址
        $content = str_replace('{pboot:umodify}', Url::home('member/umodify'), $content); // 修改资料地址
        $content = str_replace('{pboot:logout}', Url::home('member/logout'), $content); // 推出登录
        $content = str_replace('{pboot:upload}', Url::home('member/upload'), $content); // 上传资料

        if (strpos($content, '{pboot:sendemail}')) {
            session('sendemail', true); // 避免非法外部提交
            $content = str_replace('{pboot:sendemail}', Url::home('member/sendEmail'), $content); // 上传资料
        } else {
            session('sendemail', false);
        }

        $content = str_replace('{pboot:islogin}', session('pboot_uid') ? 1 : 0, $content); // 是否登录
        if (strpos($content, '{pboot:mustlogin}') !== false) {
            $content = str_replace('{pboot:mustlogin}', '', $content);
            if (!session('pboot_uid')) { // 没有经登录
                if ($this->config('login_no_wait')) {
                    location(Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                } else {
                    error('您的权限不足，无法浏览本页面！', Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                }
            }
        }

        $content = str_replace('{pboot:msgaction}', Url::home('message'), $content); // 留言提交路径
        $content = str_replace('{pboot:scaction}', Url::home('search'), $content); // 搜索提交路径
        $content = str_replace('{pboot:msgcodestatus}', $this->config('message_check_code') === '0' ? 0 : 1, $content); // 是否开留言启验证码
        $content = str_replace('{pboot:formcodestatus}', $this->config('form_check_code') === '0' ? 0 : 1, $content); // 是否开启表单验证码

        $content = str_replace('{pboot:checkcode}', CORE_DIR . '/code.php', $content); // 验证码路径
        $content = str_replace('{pboot:lgpath}', Url::get('home/Do/area'), $content); // 多语言切换前置路径,如{pboot:lgpath}?lg=cn

        $content = str_replace('{pboot:appid}', $this->config('api_appid'), $content); // API认证用户
        $content = str_replace('{pboot:timestamp}', time(), $content); // 认证时间戳
        $content = str_replace('{pboot:signature}', md5(md5($this->config('api_appid') . $this->config('api_secret') . time())), $content); // API认证密钥

        $content = str_replace('{pboot:httpurl}', get_http_url(), $content); // 当前访问的域名地址
        $content = str_replace('{pboot:pageurl}', get_current_url(), $content); // 当前页面的地址

        $content = str_replace('{pboot:registercodestatus}', $this->config('register_check_code') === '0' ? 0 : ($this->config('register_check_code') ?: 1), $content); // 是否开启注册验证码
        $content = str_replace('{pboot:logincodestatus}', $this->config('login_check_code') === '0' ? 0 : 1, $content); // 是否开启评论验证码
        $content = str_replace('{pboot:commentcodestatus}', $this->config('comment_check_code') === '0' ? 0 : 1, $content); // 是否开启评论验证码
        $content = str_replace('{pboot:commentaction}', Url::home('comment/add', null, "contentid={content:id}"), $content); // 评论提交路径
        $content = str_replace('{pboot:mycommentpage}', Url::home('comment/my'), $content); // 我的评论

        $content = str_replace('{pboot:registerstatus}', $this->config('register_status') === '0' ? 0 : 1, $content); // 是否开启注册
        $content = str_replace('{pboot:loginstatus}', $this->config('login_status') === '0' ? 0 : 1, $content); // 是否开启登录
        $content = str_replace('{pboot:commentstatus}', $this->config('comment_status') === '0' ? 0 : 1, $content); // 是否开启评论

        // 记录蜘蛛爬行
        if ($this->config('spiderlog') !== '0') {
            if ($this->config('tpl_html_cache')) { // 缓存时插入script,否则直接执行
                $spidercode = "<script src='" . Url::home('Spider', null, 'url=' . URL) . "' async='async'></script>";
                $content = preg_replace('/(<\/body>)/i', $spidercode . "\n$1", $content);
            } else {
                $spider = new SpiderController(URL);
                $spider->index();
            }
        }

        return $content;
    }

    // 解析站点标签
    public function parserSiteLabel($content)
    {
        $pattern = '/\{pboot:site([\w]+)(\s+[^}]+)?\}/';
        $data = array();

        // 页面自适应标题避免多横线
        if (preg_match($pattern, $content)) {
            $data = $this->model->getSite();
            if (!$data->subtitle) {
                $content = str_replace('{pboot:sitetitle}-{pboot:sitesubtitle}', '{pboot:sitetitle}', $content);
            }
        }

        if (preg_match_all($pattern, $content, $matches)) {
            $data = $data ?: $this->model->getSite();
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    case 'index':
                        $content = str_replace($matches[0][$i], Url::home('home/Index/'), $content);
                        break;
                    case 'path':
                        $content = str_replace($matches[0][$i], SITE_DIR, $content);
                        break;
                    case 'enter':
                        $content = str_replace($matches[0][$i], SITE_INDEX_DIR, $content);
                        break;
                    case 'logo':
                        if (isset($data->logo) && $data->logo) {
                            if (!preg_match('/^http/', $data->logo)) {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, SITE_DIR . $data->logo), $content);
                            } else {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->logo), $content);
                            }
                        } else {
                            $content = str_replace($matches[0][$i], STATIC_DIR . '/images/logo.png', $content);
                        }
                        break;
                    case 'tplpath':
                        $content = str_replace($matches[0][$i], APP_THEME_DIR, $content);
                        break;
                    case 'language':
                        $content = str_replace($matches[0][$i], get_lg(), $content);
                        break;
                    case 'statistical':
                        if (isset($data->statistical)) {
                            $content = str_replace($matches[0][$i], decode_string($data->statistical), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                    case 'copyright':
                        if (isset($data->copyright)) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, decode_string($data->copyright)), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                    default:
                        if (strpos(file_get_contents(CORE_PATH . base64_decode('L2Jhc2ljL0tlcm5lbC5waHA=')), base64_decode('S2VybmVs')))
                            exit();
                        if (isset($data->{$matches[1][$i]})) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->{$matches[1][$i]}), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                }
            }
        }
        return $content;
    }

    // 解析公司标签
    public function parserCompanyLabel($content)
    {
        $pattern = '/\{pboot:company([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $data = $this->model->getCompany();
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                if (!$data) { // 无数据时直接替换为空
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    case 'weixin':
                        if (isset($data->weixin) && $data->weixin) {
                            if (!preg_match('/^http/', $data->weixin)) {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, SITE_DIR . $data->weixin), $content);
                            } else {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->weixin), $content);
                            }
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                        break;
                    default:
                        if (isset($data->{$matches[1][$i]})) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->{$matches[1][$i]}), $content);
                        }
                }
            }
        }
        return $content;
    }

    // 解析自定义标签
    public function parserUserLabel($content)
    {
        $pattern = '/\{label:([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $data = $this->model->getLabel();
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                if (!$data) { // 无数据时直接替换为空
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    default:
                        if (isset($data[$matches[1][$i]])) {
                            if ($data[$matches[1][$i]]['type'] == 3 && $data[$matches[1][$i]]['value']) {
                                if (!preg_match('/^http/', $data[$matches[1][$i]]['value'])) {
                                    $data[$matches[1][$i]]['value'] = $this->adjustLabelData($params, SITE_DIR . $data[$matches[1][$i]]['value']);
                                } else {
                                    $data[$matches[1][$i]]['value'] = $this->adjustLabelData($params, $data[$matches[1][$i]]['value']);
                                }
                            }
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data[$matches[1][$i]]['value']), $content);
                        }
                }
            }
        }
        return $content;
    }

    // 会员标签解析
    private function parserMemberLabel($content)
    {
        $pattern = '/\{user:([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            $model = new MemberModel();
            $data = $model->getUser();
            for ($i = 0; $i < $count; $i++) {
                // 无数据直接替换并跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    case 'password': // 密码不允许显示
                        $content = str_replace($matches[0][$i], '', $content);
                        break;
                    case 'registertime':
                        $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->register_time), $content);
                        break;
                    case 'logincount':
                        $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->login_count), $content);
                        break;
                    case 'lastloginip':
                        $content = str_replace($matches[0][$i], $this->adjustLabelData($params, long2ip($data->last_login_ip)), $content);
                        break;
                    case 'lastlogintime':
                        $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->last_login_time), $content);
                        break;
                    case 'headpic':
                        if ($data->headpic) {
                            if (!preg_match('/^http/', $data->headpic)) {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, SITE_DIR . $data->headpic), $content);
                            } else {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->headpic), $content);
                            }
                        } else {
                            $content = str_replace($matches[0][$i], SITE_DIR . '/apps/admin/view/default/images/logo.png', $content);
                        }
                    default:
                        if (isset($data->{$matches[1][$i]})) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $data->{$matches[1][$i]}), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                }
            }
        }
        return $content;
    }

    // 解析栏目列表标签
    public function parserNavLabel($content)
    {
        $pattern = '/\{pboot:nav(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:nav\}/';
        $pattern2 = '/\[nav:([\w]+)(\s+[^]]+)?\]/';
        $pattern3 = '/pboot:([0-9])+nav/';
        if (preg_match_all($pattern, $content, $matches)) {
            $data = $this->model->getSortsTree();
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {

                // 无数据时直接替换整体标签为空
                if (!$data['tree']) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }
                $parent = 0;
                $num = 0;
                $scode = 0;
                $scode_arr = array();
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'parent':
                            $parent = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'scode':
                            $scode = $value;
                            $scode_arr = explode(',', $scode);
                            break;
                    }
                }

                if ($parent) { // 非顶级栏目起始,调用子栏目
                    $parent_arr = explode(',', $parent);
                    $out_data = array();
                    foreach ($parent_arr as $vp) {
                        if (isset($data['tree'][trim($vp)]['son'])) {
                            $out_data = array_merge($out_data, $data['tree'][trim($vp)]['son']);
                        }
                    }
                } else { // 顶级栏目起始
                    $out_data = $data['top'];
                }

                // 读取指定数量
                if ($num) {
                    $out_data = array_slice($out_data, 0, $num);
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($out_data as $value) { // 按查询的数据条数循环
                    if ($scode_arr && !in_array($value['scode'], $scode_arr)) {
                        continue;
                    }
                    $one_html = $matches[2][$i];
                    if ($count2) {
                        for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                            $params = $this->parserParam($matches2[2][$j]);
                            switch ($matches2[1][$j]) {
                                case 'n':
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                    break;
                                case 'i':
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                    break;
                                case 'link':
                                    if ($value['outlink']) {
                                        $one_html = str_replace($matches2[0][$j], $value['outlink'], $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->parserLink($value['type'], $value['urlname'], 'list', $value['scode'], $value['filename'], '', ''), $one_html);
                                    }
                                    break;
                                case 'soncount':
                                    if (isset($data['tree'][$value['scode']]['son'])) {
                                        $one_html = str_replace($matches2[0][$j], count($data['tree'][$value['scode']]['son']), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], 0, $one_html);
                                    }
                                    break;
                                case 'rows':
                                    $one_html = str_replace($matches2[0][$j], $this->model->getSortRows($value['scode']), $one_html);
                                    break;
                                case 'ico':
                                    if ($value['ico']) {
                                        if (!preg_match('/^http/', $value['ico'])) {
                                            $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value['ico']), $one_html);
                                        } else {
                                            $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value['ico']), $one_html);
                                        }
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], '', $one_html);
                                    }
                                    break;
                                case 'pic':
                                    if ($value['pic']) {
                                        if (!preg_match('/^http/', $value['pic'])) {
                                            $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value['pic']), $one_html);
                                        } else {
                                            $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value['pic']), $one_html);
                                        }
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], '', $one_html);
                                    }
                                    break;
                                default:
                                    if (isset($value[$matches2[1][$j]])) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value[$matches2[1][$j]]), $one_html);
                                    }
                            }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }

                // 无限极嵌套解析
                if (preg_match($pattern3, $out_html, $matches3)) {
                    $out_html = str_replace('pboot:' . $matches3[1] . 'nav', 'pboot:nav', $out_html);
                    $out_html = str_replace('[' . $matches3[1] . 'nav:', '[nav:', $out_html);
                    $out_html = $this->parserNavLabel($out_html);
                }

                // 执行内容替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析当前位置
    public function parserPositionLabel($content, $scode, $page = null, $link = null)
    {
        $pattern = '/\{pboot:position(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            $data = $this->model->getPosition($scode);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[1][$i], false); // 保留对html标签的支持

                $separator = '';
                $separatoricon = '';
                $indextext = '';
                $indexicon = '';

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'separator':
                            $separator = $value;
                            break;
                        case 'separatoricon':
                            $separatoricon = $value;
                            break;
                        case 'indextext':
                            $indextext = $value;
                            break;
                        case 'indexicon':
                            $indexicon = $value;
                            break;
                    }
                }

                // 已经设置图标，则图标优先，如果没有，则判断是否已经设置文字
                if ($separatoricon) {
                    $separator = ' <i class="' . $separatoricon . '"></i> ';
                } elseif (!$separator) {
                    $separator = ' >> ';
                }

                if ($indexicon) {
                    $indextext = '<i class="' . $indexicon . '"></i>';
                } elseif (!$indextext) {
                    $indextext = '首页';
                }

                $out_html = '<a href="' . SITE_INDEX_DIR . '/">' . $indextext . '</a>';
                if ($page && $scode == 0) {
                    $out_html .= $separator . '<a href="' . $link . '">' . $page . '</a>';
                } else {
                    foreach ($data as $key => $value) {
                        if ($value['outlink']) {
                            $out_html .= $separator . '<a href="' . $value['outlink'] . '">' . $value['name'] . '</a>';
                        } else {
                            $out_html .= $separator . '<a href="' . $this->parserLink($value['type'], $value['urlname'], 'list', $value['scode'], $value['filename'], '', '') . '">' . $value['name'] . '</a>';
                        }
                    }
                }
                // 执行内容替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析当前分类标签
    public function parserSortLabel($content, $sort)
    {
        $pattern = '/\{sort:([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    case 'link':
                        if ($sort->outlink) {
                            $content = str_replace($matches[0][$i], $sort->outlink, $content);
                        } else {
                            $content = str_replace($matches[0][$i], $this->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', ''), $content);
                        }
                        break;
                    case 'tcode': // 顶级栏目ID
                        if (!isset($tcode))
                            $tcode = $this->model->getSortTopScode($sort->scode);
                        $content = str_replace($matches[0][$i], $tcode, $content);
                        break;
                    case 'topname':
                        if (!isset($tcode))
                            $tcode = $this->model->getSortTopScode($sort->scode);
                        $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $this->model->getSortName($tcode)), $content);
                        break;
                    case 'toplink':
                        if (!isset($tcode)) {
                            $tcode = $this->model->getSortTopScode($sort->scode);
                        }
                        $top_sort = $this->model->getSort($tcode);
                        if ($top_sort->outlink) {
                            $toplink = $top_sort->outlink;
                        } else {
                            $toplink = $this->parserLink($top_sort->type, $top_sort->urlname, 'list', $top_sort->scode, $top_sort->filename, '', '');
                        }
                        $content = str_replace($matches[0][$i], $toplink, $content);
                        break;
                    case 'parentname':
                        if ($sort->pcode == 0) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->name), $content);
                        } else {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->parentname), $content);
                        }
                        break;
                    case 'parentlink':
                        if ($sort->pcode == 0) {
                            $parent_sort = $sort;
                        } else {
                            $parent_sort = $this->model->getSort($sort->pcode);
                        }
                        if ($parent_sort->outlink) {
                            $parentlink = $top_sort->outlink;
                        } else {
                            $parentlink = $this->parserLink($parent_sort->type, $parent_sort->urlname, 'list', $parent_sort->scode, $parent_sort->filename, '', '');
                        }
                        $content = str_replace($matches[0][$i], $parentlink, $content);
                        break;
                    case 'toprows':
                        if (!isset($tcode))
                            $tcode = $this->model->getSortTopScode($sort->scode);
                        $content = str_replace($matches[0][$i], $this->model->getSortRows($tcode), $content);
                        break;
                    case 'parentrows':
                        if ($sort->pcode == 0) {
                            $content = str_replace($matches[0][$i], $this->model->getSortRows($sort->scode), $content);
                        } else {
                            $content = str_replace($matches[0][$i], $this->model->getSortRows($sort->pcode), $content);
                        }
                        break;
                    case 'rows':
                        $content = str_replace($matches[0][$i], $this->model->getSortRows($sort->scode), $content);
                        break;
                    case 'ico':
                        if ($sort->ico) {
                            if (!preg_match('/^http/', $sort->ico)) {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, SITE_DIR . $sort->ico), $content);
                            } else {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->ico), $content);
                            }
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                        break;
                    case 'pic':
                        if ($sort->pic) {
                            if (!preg_match('/^http/', $sort->pic)) {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, SITE_DIR . $sort->pic), $content);
                            } else {
                                $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->pic), $content);
                            }
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                        break;
                    case 'keywords': // 如果栏目关键字为空，则自动使用全局关键字
                        if ($sort->keywords) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->keywords), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '{pboot:sitekeywords}', $content);
                        }
                        break;
                    case 'description': // 如果栏目描述为空，则自动使用全局描述
                        if ($sort->description) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->description), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '{pboot:sitedescription}', $content);
                        }
                        break;
                    default:
                        if (isset($sort->{$matches[1][$i]})) {
                            $content = str_replace($matches[0][$i], $this->adjustLabelData($params, $sort->{$matches[1][$i]}), $content);
                        } else {
                            $content = str_replace($matches[0][$i], '', $content);
                        }
                }
            }
        }
        return $content;
    }

    // 解析非列表页分类标签
    public function parserSpecialPageSortLabel($content, $id, $page, $link)
    {
        $pattern = '/\{sort:([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[2][$i]);
                switch ($matches[1][$i]) {
                    case 'tcode': // 顶级栏目ID
                        $content = str_replace($matches[0][$i], $id, $content);
                        break;
                    case 'topname':
                        $content = str_replace($this->adjustLabelData($params, $matches[0][$i]), $page, $content);
                        break;
                    case 'toplink':
                        $content = str_replace($matches[0][$i], $link, $content);
                        break;
                    case 'pcode': // 父栏目ID
                        $content = str_replace($matches[0][$i], $id, $content);
                        break;
                    case 'parentname':
                        $content = str_replace($this->adjustLabelData($params, $matches[0][$i]), $page, $content);
                        break;
                    case 'parentlink':
                        $content = str_replace($matches[0][$i], $link, $content);
                        break;
                    case 'scode': // 当前栏目ID
                        $content = str_replace($matches[0][$i], $id, $content);
                        break;
                    case 'link':
                        $content = str_replace($matches[0][$i], $link, $content);
                        break;
                    case 'name': // 当前分类名称
                        $content = str_replace($this->adjustLabelData($params, $matches[0][$i]), $page, $content);
                        break;
                    case 'keywords': // 当前分类关键字,使用全局
                        $content = str_replace($this->adjustLabelData($params, $matches[0][$i]), '{pboot:sitekeywords}', $content);
                        break;
                    case 'description': // 当前分类描述,使用全局
                        $content = str_replace($this->adjustLabelData($params, $matches[0][$i]), '{pboot:sitedescription}', $content);
                        break;
                    default:
                        $content = str_replace($matches[0][$i], '', $content);
                }
            }
        }
        return $content;
    }

    // 解析指定分类标签
    public function parserSpecifySortLabel($content)
    {
        $pattern = '/\{pboot:sort(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:sort\}/';
        $pattern2 = '/\[sort:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $scode = -1;

                // 跳过未指定scode的列表
                if (!array_key_exists('scode', $params)) {
                    continue;
                }

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 分离分类编码
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'scode':
                            $scode = $value;
                            break;
                    }
                }

                if (!$scode) {
                    $scode = -1;
                }

                // 读取一个或多个栏目数据
                $data = $this->model->getMultSort(escape_string($scode));

                // 无数据直接跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'link':
                                if ($value->outlink) {
                                    $one_html = str_replace($matches2[0][$j], $value->outlink, $one_html);
                                } else {
                                    $one_html = str_replace($matches2[0][$j], $this->parserLink($value->type, $value->urlname, 'list', $value->scode, $value->filename, '', ''), $one_html);
                                }
                                break;
                            case 'ico':
                                if ($value->ico) {
                                    if (!preg_match('/^http/', $value->ico)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->ico), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->ico), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                                break;
                            case 'pic':
                                if ($value->pic) {
                                    if (!preg_match('/^http/', $value->pic)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->pic), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->pic), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                                break;
                            case 'rows':
                                $one_html = str_replace($matches2[0][$j], $this->model->getSortRows($value->scode), $one_html); // 获取分类包含子类的内容数量
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                // 执行替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析筛选全部
    public function parserSelectAllLabel($content)
    {
        $pattern = '/\{pboot:selectall(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[1][$i]);
                $text = '全部';
                $field = '';
                $class = '';
                $active = '';

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'field':
                            $field = $value;
                            break;
                        case 'text':
                            $text = $value;
                            break;
                        case 'class':
                            $class = $value;
                            break;
                        case 'active':
                            $active = $value;
                            break;
                    }
                }

                // 跳过不带field的标签
                if (!$field) {
                    continue;
                }

                $url_rule_type = $this->config('url_rule_type') ?: 3;
                $url_rule_suffix = $this->config('url_rule_suffix') ?: '.html';
                $url_break_char = $this->config('url_break_char') ?: '_';
                $url_rule_sort_suffix = '/';

                // 附加后缀及参数
                if ($url_rule_type == 1 || $url_rule_type == 2) {
                    // 获取地址路径
                    $url = parse_url(URL);

                    // 避免非根目录首页筛选问题
                    if (trim($url['path'], '/') == trim(SITE_DIR, '/')) {
                        $url_rule_sort_suffix = '/';
                    }

                    $path = preg_replace('/\/page\/[0-9]+/', '', $url['path']); // 去除路径方式分页

                    // 去后缀扩展
                    if (!!$pos = strripos($path, $url_rule_suffix)) {
                        $path = substr($path, 0, $pos);
                    }

                    // 去路径分页，回到首页
                    if (defined('CMS_PAGE_CUSTOM')) {
                        $path = preg_replace('/(.*)' . $url_break_char . '[0-9]+$/', '$1', rtrim($path, '/'));
                    } else {
                        $path = preg_replace('/(.*)(' . $url_break_char . '[0-9]+)' . $url_break_char . '[0-9]+$/', '$1$2', rtrim($path, '/'));
                    }

                    // 拼接地址
                    $path .= $url_rule_sort_suffix . query_string('p,s,' . $field);
                } elseif ($url_rule_type == 3) {
                    $output = array();
                    if (isset($_SERVER["QUERY_STRING"]) && !!$qs = $_SERVER["QUERY_STRING"]) {
                        parse_str($qs, $output);
                        unset($output['page']); // 去除字符串方式分页，回到第一页
                        unset($output['p']); // 去除保留参数
                        unset($output['s']); // 去除保留参数
                        unset($output[$field]); // 不筛选该字段

                        if ($output && !current($output)) {
                            $path_qs = key($output); // 第一个参数为路径信息，注意PHP数组会自动将点转换下划线
                            unset($output[$path_qs]); // 去除路径参数
                            $temp_suffix = substr($url_rule_suffix, 1);
                            if (!!$pos = strripos($path_qs, '_' . $temp_suffix)) {
                                $path = substr($path_qs, 0, $pos); // 去扩展
                            } else {
                                $path = $path_qs;
                            }

                            // 去除原分页参数
                            if (defined('CMS_PAGE_CUSTOM')) {
                                $path = preg_replace('/(.*)' . $url_break_char . '[0-9]+$/', "$1", rtrim($path, '/'));
                            } else {
                                $path = preg_replace('/(.*)(' . $url_break_char . '[0-9]+)' . $url_break_char . '[0-9]+$/', "$1$2", rtrim($path, '/'));
                            }

                            $path = SITE_INDEX_DIR . '/?' . $path . $url_rule_sort_suffix;
                        } else {
                            $path = '';
                        }

                        $qs = http_build_query($output);

                        if ($path && $qs) { // 重组地址
                            $path = rtrim($path, '/') . '/&' . $qs;
                        } elseif ($qs) {
                            $path = SITE_INDEX_DIR . '/?' . $qs;
                        } elseif (!$path) {
                            $path = SITE_INDEX_DIR . '/';
                        }
                    } else {
                        $path = SITE_INDEX_DIR . '/';
                    }
                }

                // 如果有对本字段进行筛选，则不高亮
                if (get($field, 'vars')) {
                    $out_html = '<a href="' . $path . '" class="' . $class . '">' . $text . '</a>';
                } else {
                    $out_html = '<a href="' . $path . '" class="' . $active . '">' . $text . '</a>';
                }

                // 执行内容替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析筛选标签
    public function parserSelectLabel($content)
    {
        $pattern = '/\{pboot:select(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:select\}/';
        $pattern2 = '/\[select:([\w]+)(\s+[^]]+)?\]/';

        // 参数处理
        if (preg_match($pattern, $content)) {

            $url_rule_type = $this->config('url_rule_type') ?: 3;
            $url_rule_suffix = $this->config('url_rule_suffix') ?: '.html';
            $url_break_char = $this->config('url_break_char') ?: '_';
            $url_rule_sort_suffix = '/';

            // 附加后缀及参数
            if ($url_rule_type == 1 || $url_rule_type == 2) {
                // 获取地址路径
                $url = parse_url(URL);

                // 避免非根目录首页筛选问题
                if (trim($url['path'], '/') == trim(SITE_DIR, '/')) {
                    $url_rule_sort_suffix = '/';
                }

                $path = preg_replace('/\/page\/[0-9]+/', '', $url['path']); // 去除路径方式分页，回到第一页

                // 去后缀扩展
                if (!!$pos = strripos($path, $url_rule_suffix)) {
                    $path = substr($path, 0, $pos);
                }

                // 去路径分页，回到首页
                if (defined('CMS_PAGE_CUSTOM')) {
                    $path = preg_replace('/(.*)' . $url_break_char . '[0-9]+$/', '$1', rtrim($path, '/'));
                } else {
                    $path = preg_replace('/(.*)(' . $url_break_char . '[0-9]+)' . $url_break_char . '[0-9]+$/', '$1$2', rtrim($path, '/'));
                }
            }

            $output = array();
            if (isset($_SERVER["QUERY_STRING"]) && !!$qs = $_SERVER["QUERY_STRING"]) {
                parse_str($qs, $output);
                unset($output['page']); // 去除字符串方式分页，回到第一页
                unset($output['p']); // 去除保留参数
                unset($output['s']); // 去除保留参数
                if ($url_rule_type == 3 && $output && !current($output)) {
                    $path_qs = key($output); // 第一个参数为路径信息，注意PHP数组会自动将点转换下划线
                    unset($output[$path_qs]); // 去除路径参数
                    $temp_suffix = substr($url_rule_suffix, 1);
                    if (!!$pos = strripos($path_qs, '_' . $temp_suffix)) {
                        $path = substr($path_qs, 0, $pos); // 去扩展
                    } else {
                        $path = $path_qs;
                    }

                    // 去除原分页参数
                    if (defined('CMS_PAGE_CUSTOM')) {
                        $path = preg_replace('/(.*)' . $url_break_char . '[0-9]+$/', "$1", rtrim($path, '/'));
                    } else {
                        $path = preg_replace('/(.*)(' . $url_break_char . '[0-9]+)' . $url_break_char . '[0-9]+$/', "$1$2", rtrim($path, '/'));
                    }
                    $path = SITE_INDEX_DIR . '/?' . $path;
                    $not_index = true;
                }
            }
            $path = isset($path) ? $path . $url_rule_sort_suffix : SITE_INDEX_DIR . '/';
        }

        // 执行匹配替换
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {

                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $field = '';

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'field':
                            $field = $value;
                            break;
                    }
                }

                // 跳过不带field的标签
                if (!$field) {
                    continue;
                }

                // 读取数据
                if (!!$data = $this->model->getSelect(escape_string($field))) {
                    $data = explode(',', $data);
                } else {
                    $data = array();
                }

                // 无数据直接替换为空并跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'value':
                                $one_html = str_replace($matches2[0][$j], $value, $one_html);
                                break;
                            case 'current':
                                $one_html = str_replace($matches2[0][$j], get($field, 'vars'), $one_html);
                                break;
                            case 'link':
                                $qs = $output; // 需使用中间变量，避免多个链接相同问题
                                $qs[$field] = $value;
                                $qs = http_build_query($qs);
                                if ($url_rule_type == 3 && $not_index) {
                                    $link = rtrim($path, '/') . '/&' . $qs;
                                } else {
                                    $link = $path . '?' . $qs;
                                }
                                $one_html = str_replace($matches2[0][$j], $link, $one_html);
                                break;
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析内容列表标签
    public function parserListLabel($content, $cscode = '')
    {
        $pattern = '/\{pboot:list(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:list\}/';

        if (preg_match_all($pattern, $content, $matches)) {

            for ($i = 0; $i < count($matches[0]); $i++) {

                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize'); // 未设置条数时使用默认15
                $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC'; // 默认排序
                $filter = ''; // 过滤
                $tags = ''; // tag标签
                $fuzzy = true; // 设置过滤、tag、筛选是否模糊匹配
                $ispics = ''; // 是否多图
                $isico = ''; // 是否缩略图
                $istop = ''; // 是否置顶
                $isrecommend = ''; // 是否推荐
                $isheadline = ''; // 是否头条
                $start = 1; // 起始条数，默认第一条开始
                $lfield = ''; // 查询字段限制

                // 判断当前栏目和指定栏目
                if ($cscode && !array_key_exists('scode', $params)) { // 解析当前
                    $scode = $cscode;
                    $page = true; // 如果未指定分类默认分页
                } elseif (!$cscode && array_key_exists('scode', $params)) { // 解析指定
                    $scode = $params['scode'];
                    $page = false; // 如果指定分类默认不分页
                } else {
                    continue;
                }

                if ($scode == '*') {
                    $scode = '';
                }

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'order':
                            switch ($value) {
                                case 'id':
                                    $order = 'a.id DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC';
                                    break;
                                case 'date':
                                    $order = 'a.date DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.id DESC';
                                    break;
                                case 'sorting':
                                    $order = 'a.sorting ASC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.date DESC,a.id DESC';
                                    break;
                                case 'istop':
                                    $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'isrecommend':
                                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'isheadline':
                                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'visits':
                                case 'likes':
                                case 'oppose':
                                    $order = $value . ' DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'random': // 随机取数
                                    $db_type = get_db_type();
                                    if ($db_type == 'mysql') {
                                        $order = "RAND()";
                                    } elseif ($db_type == 'sqlite') {
                                        $order = "RANDOM()";
                                    }
                                    break;
                                default:
                                    if ($value) {
                                        $orders = explode(',', $value);
                                        foreach ($orders as $k => $v) {
                                            if (strpos($v, 'ext_') === 0) {
                                                $orders[$k] = 'e.' . $v;
                                            } else {
                                                $orders[$k] = 'a.' . $v;
                                            }
                                        }
                                        $value = implode(',', $orders);
//                                        $order = $value . ',a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                        $order = $value;
                                    }
                            }
                            break;
                        case 'filter':
                            $filter = $value;
                            break;
                        case 'fuzzy':
                            $fuzzy = $value;
                            break;
                        case 'tags':
                            $tags = $value;
                            break;
                        case 'ispics':
                            $ispics = $value;
                            break;
                        case 'isico':
                            $isico = $value;
                            break;
                        case 'istop':
                            $istop = $value;
                            break;
                        case 'isrecommend':
                            $isrecommend = $value;
                            break;
                        case 'isheadline':
                            $isheadline = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'lfield':
                            $lfield = $value;
                            break;
                    }
                }

                // filter数据筛选
                $where1 = array();
                if ($filter) {
                    $filter = explode('|', $filter);
                    if (count($filter) == 2) {
                        $filter_arr = explode(',', $filter[1]);
                        if ($filter[0] == 'title') {
                            $filter[0] = 'a.title';
                        }
                        foreach ($filter_arr as $value) {
                            if ($value) {
                                if ($fuzzy) {
                                    $where1[] = $filter[0] . " like '%" . escape_string($value) . "%'";
                                } else {
                                    $where1[] = $filter[0] . "='" . escape_string($value) . "'";
                                }
                            }
                        }
                    }
                }

                // tags数据参数筛选
                $where2 = array();
                if ($tags) {
                    $tags_arr = explode(',', $tags);
                    foreach ($tags_arr as $value) {
                        if ($value) {
                            if ($fuzzy) {
                                $where2[] = "a.tags like '%" . escape_string($value) . "%'";
                            } else {
                                $where2[] = "a.tags='" . escape_string($value) . "'";
                            }
                        }
                    }
                }

                // 重置存储条件
                $where3 = [];

                // 只对有分页的列表有效
                if ($page) {

                    if (isset($paging)) {
                        error('请不要在一个页面使用多个具有分页的列表，您可将多余的使用page=0关闭分页！');
                    }

                    $paging = true;

                    // tags数据传值筛选
                    if (!!$get_tag = get('tag', 'vars')) {
                        if ($fuzzy) {
                            $where2[] = "a.tags like '%" . $get_tag . "%'";
                        } else {
                            $where2[] = "a.tags='" . $get_tag . "'";
                        }
                    }

                    // 扩展字段数据筛选
                    foreach ($_GET as $key => $value) {
                        if (preg_match('/^ext_[\w\-]+$/', $key)) { // 其他字段不加入
                            $where3[$key] = get($key, 'vars');
                        }
                    }
                }

                // 判断多图调节参数
                if ($ispics !== '') {
                    if ($ispics) {
                        $where3[] = "a.pics<>''";
                    } else {
                        $where3[] = "a.pics=''";
                    }
                }

                // 判断缩略图调节参数
                if ($isico !== '') {
                    if ($isico) {
                        $where3[] = "a.ico<>''";
                    } else {
                        $where3[] = "a.ico=''";
                    }
                }

                // 判断置顶调节参数
                if ($istop !== '') {
                    if ($istop) {
                        $where3[] = "a.istop=1";
                    } else {
                        $where3[] = "a.istop=0";
                    }
                }

                // 判断推荐调节参数
                if ($isrecommend !== '') {
                    if ($isrecommend) {
                        $where3[] = "a.isrecommend=1";
                    } else {
                        $where3[] = "a.isrecommend=0";
                    }
                }

                // 判断头条调节参数
                if ($isheadline !== '') {
                    if ($isheadline) {
                        $where3[] = "a.isheadline=1";
                    } else {
                        $where3[] = "a.isheadline=0";
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                $data = $this->model->getList($scode, $num, $order, $where1, $where2, $where3, $fuzzy, $start, $lfield, null, $page);

                // 无数据直接替换
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $pattern2 = '/\[list:([\w\+\-\*\/\%]+)(\s+[^]]+)?\]/';
                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        $one_html = $this->parserList($matches2[1][$j], $matches2[0][$j], $one_html, $value, $params, $key);
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析当前内容标签
    public function parserCurrentContentLabel($content, $sort, $data)
    {
        $pattern = '/\{content:([\w]+)(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 无数据直接替换并跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }
                $params = $this->parserParam($matches[2][$i]);
                $content = $this->parserContent($matches[1][$i], $matches[0][$i], $content, $data, $params, $sort);
            }
        }

        // 新增计数代码,非缓存方式，直接计数
        if ($this->config('tpl_html_cache')) {
            if (!isset($this->var['addvisits'])) {
                $visits = "<script src='" . Url::get('home/Do/visits/id/' . $data->id) . "' async='async'></script>";
                $content = preg_replace('/(<\/body>)/i', $visits . "\n$1", $content);
                $this->var['addvisits'] = true;
            }
        } else {
            $do = new DoModel();
            $do->addVisits($data->id);
        }
        return $content;
    }

    // 解析指定内容标签,单页支持使用scode调用
    public function parserSpecifyContentLabel($content)
    {
        $pattern = '/\{pboot:content(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:content\}/';
        $pattern2 = '/\[content:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $id = -1;
                $scode = -1;

                // 跳过未指定id和scode的列表
                if (array_key_exists('id', $params)) {
                    $id = $params['id'];
                    $data = $this->model->getContent(escape_string($id));
                } elseif (array_key_exists('scode', $params)) {
                    $scode = $params['scode'];
                    $data = $this->model->getAbout(escape_string($scode));
                } else {
                    continue;
                }

                // 读取数据
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = $matches[2][$i];
                for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                    $params = $this->parserParam($matches2[2][$j]);
                    $out_html = $this->parserContent($matches2[1][$j], $matches2[0][$j], $out_html, $data, $params, $scode);
                }
                // 执行替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析指定内容多图
    public function parserContentPicsLabel($content)
    {
        $pattern = '/\{pboot:pics(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:pics\}/';
        $pattern2 = '/\[pics:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $id = -1;
                $field = "pics";

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 跳过未指定id的列表
                if (!array_key_exists('id', $params)) {
                    continue;
                }

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'id':
                            $id = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'field':
                            $field = $value;
                            break;
                    }
                }

                // 读取内容多图
                if (!!$rs = $this->model->getContentPics(escape_string($id), $field)) {
                    $pics = explode(',', $rs->$field);
                    $picstitle = explode(',', $rs->picstitle);
                } else {
                    $pics = array();
                    $picstitle = array();
                }

                // 无图直接替换为空并跳过
                if (!$pics) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($pics as $vkey => $value) { // 按查询图片条数循环
                    $one_html = $matches[2][$i];
                    if (!$value)
                        continue;
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'src':
                                if ($value) {
                                    if (!preg_match('/^http/', $value)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                                break;
                            case 'title':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, isset($picstitle[$vkey]) ? $picstitle[$vkey] : ''), $one_html);
                                break;
                            default:
                                $one_html = str_replace($matches2[0][$j], '', $one_html);
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                    if (isset($num) && $key > $num) {
                        unset($num);
                        break;
                    }
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析指定内容多选
    public function parserContentCheckboxLabel($content)
    {
        $pattern = '/\{pboot:checkbox(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:checkbox\}/';
        $pattern2 = '/\[checkbox:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $id = -1;

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 跳过未指定id的调用
                if (!array_key_exists('id', $params)) {
                    continue;
                }

                // 跳过未指定field的调用
                if (!array_key_exists('field', $params)) {
                    continue;
                }

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'id':
                            $id = $value;
                            break;
                        case 'field':
                            $field = $value;
                            break;
                    }
                }

                // 读取内容多图
                if (!!$checkboxs = $this->model->getContentCheckbox(escape_string($id), escape_string($field))) {
                    $data = explode(',', $checkboxs);
                } else {
                    $data = array();
                }

                // 无内容直接替换为空并跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'text':
                                $one_html = str_replace($this->adjustLabelData($params, $matches2[0][$j]), $value, $one_html);
                                break;
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析内容tags
    public function parserContentTagsLabel($content)
    {
        $pattern = '/\{pboot:tags(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:tags\}/';
        $pattern2 = '/\[tags:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $id = ''; // 调取指定内容的tags
                $scode = ''; // 调取指定分类的tags
                $target = 'list'; // 标签跳转目标，可以是内容列表，也可以是独立tags.html页面

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'id':
                            $id = $value;
                            break;
                        case 'scode':
                            $scode = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'target':
                            $target = $value;
                    }
                }

                // 获取数据
                $data = array();
                if ($id) { // 获取单个内容的tags
                    if (strpos($scode, ',') !== false) {
                        error('模板中指定id输出tags时不允许scode指定多个栏目！');
                    }
                    if (!!$rs = $this->model->getContentTags(escape_string($id))) {
                        if ($rs->tags) {
                            $tags = explode(',', $rs->tags);
                            $scode = $scode ?: $rs->scode;
                            $sort = $this->model->getSort($scode); // 获取栏目信息
                            foreach ($tags as $key => $value) {
                                $data[] = array(
                                    'sort' => $sort,
                                    'tags' => $value
                                );
                            }
                        }
                    }
                } elseif ($scode) { // 获取指定栏目的tags
                    $scodes = explode(',', $scode); // 多个栏目是分别获取
                    foreach ($scodes as $key => $value) {
                        $sort = $this->model->getSort($value); // 获取栏目信息
                        if (!!$rs = $this->model->getSortTags($value)) {
                            $tags = implode(',', $rs); // 把栏目tags串起来
                            $tags = array_unique(explode(',', $tags)); // 再把所有tags组成数组并去重
                            foreach ($tags as $key2 => $value2) {
                                if (!in_array($value2, array_column($data, 'tags'))) { // 避免重复输出
                                    $data[] = array(
                                        'sort' => $sort,
                                        'tags' => $value2
                                    );
                                }
                            }
                        }
                    }
                } else {
                    // 全部栏目时候强制标签页形式
                    $target = 'tag';
                    if (!!$rs = $this->model->getSortTags('')) {
                        $tags = implode(',', $rs); // 把栏目tags串起来
                        $tags = array_unique(explode(',', $tags)); // 再把所有tags组成数组并去重
                        foreach ($tags as $key2 => $value2) {
                            if (!in_array($value2, array_column($data, 'tags'))) { // 避免重复输出
                                $data[] = array(
                                    'tags' => $value2
                                );
                            }
                        }
                    }
                }

                // 无内容直接替换为空并跳过
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'text':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value['tags']), $one_html);
                                break;
                            case 'link':
                                $url_rule_type = $this->config('url_rule_type') ?: 3;
                                if ($target == 'tag') {
                                    if ($url_rule_type == 3) {
                                        $link = Url::home('tag=' . urlencode($value['tags']), false);
                                    } else {
                                        $link = Url::home('tag/' . urlencode($value['tags']), false);
                                    }
                                } else {
                                    $link = $this->parserLink($value['sort']->type, $value['sort']->urlname, 'list', $value['sort']->scode, $value['sort']->filename, '', '');
                                    if ($url_rule_type == 3) {
                                        $link = $link . '&tag=' . urlencode($value['tags']);
                                    } else {
                                        $link = $link . '?tag=' . urlencode($value['tags']);
                                    }
                                }

                                $one_html = str_replace($matches2[0][$j], $link, $one_html);
                                break;
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                    if (isset($num) && $key > $num) {
                        unset($num);
                        break;
                    }
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析幻灯片标签
    public function parserSlideLabel($content)
    {
        $pattern = '/\{pboot:slide(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:slide\}/';
        $pattern2 = '/\[slide:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $gid = 1;
                $num = 5;
                $start = 1;

                // 跳过未指定gid的标签
                if (!array_key_exists('gid', $params)) {
                    continue;
                }

                // 分离参数
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'gid':
                            $gid = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getSlides(escape_string($gid), escape_string($num), $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'src':
                                if ($value->pic) {
                                    if (!preg_match('/^http/', $value->pic)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->pic), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->pic), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析友情链接标签
    public function parserLinkLabel($content)
    {
        $pattern = '/\{pboot:link(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:link\}/';
        $pattern2 = '/\[link:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $gid = 1;
                $num = 10;
                $start = 1;

                // 跳过未指定gid的标签
                if (!array_key_exists('gid', $params)) {
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'gid':
                            $gid = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getLinks(escape_string($gid), escape_string($num), $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $key = 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'logo':
                                if ($value->logo) {
                                    if (!preg_match('/^http/', $value->logo)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->logo), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->logo), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析留言板标签
    public function parserMessageLabel($content)
    {
        $pattern = '/\{pboot:message(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:message\}/';
        $pattern2 = '/\[message:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize');
                $page = true;
                $start = 1;
                $lg = '';

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'lg':
                            $lg = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getMessage(escape_string($num), $page, $start, $lg)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';

                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'ip':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, long2ip($value->user_ip)), $one_html);
                                break;
                            case 'os':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->user_os), $one_html);
                                break;
                            case 'bs':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->user_bs), $one_html);
                                break;
                            case 'askdate':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->create_time), $one_html);
                                break;
                            case 'replydate':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->update_time), $one_html);
                                break;
                            case 'headpic':
                                if ($value->headpic) {
                                    if (!preg_match('/^http/', $value->headpic)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->headpic), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->headpic), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], SITE_DIR . '/apps/admin/view/default/images/logo.png', $one_html);
                                }
                                break;
                            case 'nickname':
                                if ($value->nickname) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->nickname), $one_html);
                                } elseif (!$value->username) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, "匿名用户"), $one_html);
                                }
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析表单数据标签
    public function parserFormLabel($content)
    {
        $pattern = '/\{pboot:formlist(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:formlist\}/';
        $pattern2 = '/\[form:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize');
                $fcode = -1;
                $page = true;
                $start = 1;

                // 跳过未指定fcode的标签
                if (!array_key_exists('fcode', $params)) {
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'fcode':
                            $fcode = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 获取表名称
                if (!$table = $this->model->getFormTable(escape_string($fcode))) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 读取数据
                if (!$data = $this->model->getForm($table, escape_string($num), $page, $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'date':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->create_time), $one_html);
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                } else {
                                    $one_html = str_replace($matches2[0][$j], '', $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析表单提交标签
    public function parserSubmitFormLabel($content)
    {
        $pattern = '/\{pboot:form(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[1][$i]);
                $fcode = '';
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'fcode':
                            $fcode = $value;
                            break;
                    }
                }
                if (!$fcode) { // 无表单编码不解析
                    continue;
                }
                $content = str_replace($matches[0][$i], Url::home('form/' . $fcode), $content);
            }
        }
        return $content;
    }

    // 解析文章评论
    public function parserCommentLabel($content)
    {
        $pattern = '/\{pboot:comment(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:comment\}/';
        $pattern2 = '/\[comment:([\w]+)(\s+[^]]+)?\]/';
        $pattern3 = '/\{pboot:commentsub(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:commentsub\}/';
        $pattern4 = '/\[commentsub:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize');
                $page = true;
                $order = 'a.id desc';
                $start = 1;

                // 跳过未指定fcode的标签
                if (!array_key_exists('contentid', $params)) {
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'contentid':
                            $contentid = $value;
                            break;
                        case 'order':
                            $order = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getComment($contentid, 0, $num, $order, $page, $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';

                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        $one_html = $this->parserComment($matches2[1][$j], $matches2[0][$j], $one_html, $value, $params, $key);
                    }
                    $key++;

                    // 解析子评论
                    if (preg_match_all($pattern3, $one_html, $matches3)) {
                        $count3 = count($matches3[0]);
                        for ($k = 0; $k < $count3; $k++) {

                            // 读取子评论数据，正序排列，最大100条
                            if (!$data_sub = $this->model->getComment($contentid, $value->id, 100, 'a.id asc')) {
                                $one_html = str_replace($matches3[0][$k], '', $one_html);
                                continue;
                            }

                            // 匹配到子评论内部标签
                            if (preg_match_all($pattern4, $matches3[2][$k], $matches4)) {
                                $count4 = count($matches4[0]); // 循环内的内容标签数量
                            } else {
                                $count4 = 0;
                            }

                            $out_html_sub = '';
                            $key_sub = 1;
                            foreach ($data_sub as $value_sub) { // 按子查询数据条数循环
                                $one_html_sub = $matches3[2][$k];
                                for ($m = 0; $m < $count4; $m++) { // 循环替换数据
                                    $params_sub = $this->parserParam($matches4[2][$m]);
                                    $one_html_sub = $this->parserComment($matches4[1][$m], $matches4[0][$m], $one_html_sub, $value_sub, $params_sub, $key_sub);
                                }
                                $key_sub++;
                                $out_html_sub .= $one_html_sub;
                            }
                            $one_html = str_replace($matches3[0][$k], $out_html_sub, $one_html);
                        }
                    }

                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析我的评论
    public function parserMyCommentLabel($content)
    {
        $pattern = '/\{pboot:mycomment(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:mycomment\}/';
        $pattern2 = '/\[mycomment:([\w]+)(\s+[^]]+)?\]/';

        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize');
                $page = true;
                $order = 'a.id desc';
                $start = 1;

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'order':
                            $order = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getMyComment($num, $order, $page, $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';

                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        $one_html = str_replace("[mycomment:delaction]", Url::home('comment/del', null, 'id=' . $value->id), $one_html);
                        $one_html = $this->parserComment($matches2[1][$j], $matches2[0][$j], $one_html, $value, $params, $key);
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析评论内容
    private function parserComment($label, $search, $content, $data, $params, $key)
    {
        switch ($label) {
            case 'n':
                $content = str_replace($search, $this->adjustLabelData($params, $key) - 1, $content);
                break;
            case 'i':
                $content = str_replace($search, $this->adjustLabelData($params, $key), $content);
                break;
            case 'ip':
                $content = str_replace($search, $this->adjustLabelData($params, long2ip($data->user_ip)), $content);
                break;
            case 'os':
                $content = str_replace($search, $this->adjustLabelData($params, $data->user_os), $content);
                break;
            case 'bs':
                $content = str_replace($search, $this->adjustLabelData($params, $data->user_bs), $content);
                break;
            case 'date':
                $content = str_replace($search, $this->adjustLabelData($params, $data->create_time), $content);
                break;
            case 'headpic':
                if ($data->headpic) {
                    if (!preg_match('/^http/', $data->headpic)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->headpic), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->headpic), $content);
                    }
                } else {
                    $content = str_replace($search, SITE_DIR . '/apps/admin/view/default/images/logo.png', $content);
                }
                break;
            case 'pheadpic':
                if ($data->pheadpic) {
                    if (!preg_match('/^http/', $data->pheadpic)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->pheadpic), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->pheadpic), $content);
                    }
                } else {
                    $content = str_replace($search, SITE_DIR . '/apps/admin/view/default/images/logo.png', $content);
                }
                break;
            case 'replyaction':
                if ($data->pid) {
                    $pid = $data->pid;
                } else {
                    $pid = $data->id;
                }
                $content = str_replace($search, Url::home('comment/add', null, "contentid=" . $data->contentid . "&pid=" . $pid . "&puid=" . $data->uid), $content);
                break;
            case 'nickname':
                if ($data->nickname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->nickname), $content);
                } elseif ($data->username) {
                    $content = str_replace($search, $this->adjustLabelData($params, "匿名"), $content);
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, "游客"), $content);
                }
                break;
            case 'pnickname':
                if ($data->pnickname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->pnickname), $content);
                } elseif ($data->pusername) {
                    $content = str_replace($search, $this->adjustLabelData($params, "匿名"), $content);
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, "游客"), $content);
                }
                break;
            default:
                if (isset($data->{$label})) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->{$label}), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
        }
        return $content;
    }

    // 解析评论子楼层
    public function parserCommentsubLabel($content)
    {
        $pattern = '/\{pboot:commentsub(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:commentsub\}/';
        $pattern2 = '/\[commentsub:([\w]+)(\s+[^]]+)?\]/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $num = $this->config('pagesize');
                $page = false;
                $order = 'a.id desc';
                $start = 1;

                // 跳过未指定fcode的标签
                if (!array_key_exists('contentid', $params)) {
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'num':
                            $num = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'contentid':
                            $contentid = $value;
                            break;
                        case 'order':
                            $order = $value;
                            break;
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if (!$data = $this->model->getComment($contentid, $num, $order, $page, $start)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';

                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        switch ($matches2[1][$j]) {
                            case 'n':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
                                break;
                            case 'i':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
                                break;
                            case 'ip':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, long2ip($value->user_ip)), $one_html);
                                break;
                            case 'os':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->user_os), $one_html);
                                break;
                            case 'bs':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->user_bs), $one_html);
                                break;
                            case 'date':
                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->create_time), $one_html);
                                break;
                            case 'headpic':
                                if ($value->headpic) {
                                    if (!preg_match('/^http/', $value->headpic)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->headpic), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->headpic), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], SITE_DIR . '/apps/admin/view/default/images/logo.png', $one_html);
                                }
                                break;
                            case 'pheadpic':
                                if ($value->pheadpic) {
                                    if (!preg_match('/^http/', $value->pheadpic)) {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, SITE_DIR . $value->pheadpic), $one_html);
                                    } else {
                                        $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->pheadpic), $one_html);
                                    }
                                } else {
                                    $one_html = str_replace($matches2[0][$j], SITE_DIR . '/apps/admin/view/default/images/logo.png', $one_html);
                                }
                                break;
                            default:
                                if (isset($value->{$matches2[1][$j]})) {
                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
                                }
                        }
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析自定义SQL循环 V3.2.4 build20230304开始废弃(安全性修复)

//    public function parserSqlListLabel($content)
//    {
//        $pattern = '/\{pboot:sql(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:sql\}/';
//        $pattern2 = '/\[sql:([\w]+)(\s+[^]]+)?\]/';
//
//        if (preg_match_all($pattern, $content, $matches)) {
//
//            $count = count($matches[0]);
//            for ($i = 0; $i < $count; $i ++) {
//                // 获取调节参数
//                $params = $this->parserParam($matches[1][$i]);
//
//                if (! self::checkLabelLevel($params)) {
//                    $content = str_replace($matches[0][$i], '', $content);
//                    continue;
//                }
//
//                $num = 1000; // 最大读取1000条
//                $sql = '';
//
//                foreach ($params as $key => $value) {
//                    switch ($key) {
//                        case 'num':
//                            $num = $value;
//                            break;
//                        case 'sql':
//                            $sql = $value;
//                            break;
//                    }
//                }
//
//                // 特殊表不允许输出
//                if (preg_match('/ay_user|ay_member/i', $sql)) {
//                    $content = str_replace($matches[0][$i], '', $content);
//                    continue;
//                }
//
//                // 判断是否有条数限制
//                if ($num && ! preg_match('/limit/i', $sql)) {
//                    $sql .= " limit " . $num;
//                }
//
//                // 读取数据
//                if (! $data = $this->model->all($sql)) {
//                    $content = str_replace($matches[0][$i], '', $content);
//                    continue;
//                }
//
//                // 匹配到内部标签
//                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
//                    $count2 = count($matches2[0]); // 循环内的内容标签数量
//                } else {
//                    $count2 = 0;
//                }
//
//                $out_html = '';
//
//                $pagenum = defined('PAGE') ? PAGE : 1;
//                $key = ($pagenum - 1) * $num + 1;
//                foreach ($data as $value) { // 按查询数据条数循环
//                    $one_html = $matches[2][$i];
//                    for ($j = 0; $j < $count2; $j ++) { // 循环替换数据
//                        $params = $this->parserParam($matches2[2][$j]);
//                        switch ($matches2[1][$j]) {
//                            case 'n':
//                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key) - 1, $one_html);
//                                break;
//                            case 'i':
//                                $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $key), $one_html);
//                                break;
//                            default:
//                                if (isset($value->{$matches2[1][$j]})) {
//                                    $one_html = str_replace($matches2[0][$j], $this->adjustLabelData($params, $value->{$matches2[1][$j]}), $one_html);
//                                }
//                        }
//                    }
//                    $key ++;
//                    $out_html .= $one_html;
//                }
//                $content = str_replace($matches[0][$i], $out_html, $content);
//            }
//        }
//        return $content;
//    }

    // 解析二维码生成标签
    public function parserQrcodeLabel($content)
    {
        $pattern = '/\{pboot:qrcode(\s+[^}]+)?\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $params = $this->parserParam($matches[1][$i]);
                $string = '';
                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'string':
                            $string = $value;
                            break;
                    }
                }
                if (!$string) { // 无内容不解析
                    continue;
                }
                $content = str_replace($matches[0][$i], '<img src="' . CORE_DIR . '/qrcode.php?string=' . urlencode($string) . '" class="qrcode" alt="二维码">', $content);
            }
        }
        return $content;
    }

    // 解析内容搜索结果标签
    public function parserSearchLabel($content)
    {
        $pattern = '/\{pboot:search(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:search\}/';

        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            $field = request('field');
            if (!preg_match('/^[\w\|\s]+$/', $field)) {
                $field = '';
            }
            $keyword = request('keyword', 'vars');
            $scode = request('scode');
            $start = 1;
            if (!preg_match('/^[\w,\s]+$/', $scode)) {
                $scode = '';
            }

            for ($i = 0; $i < $count; $i++) {

                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $num = $this->config('pagesize'); // 未设置条数时使用默认15
                $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC'; // 默认排序
                $filter = ''; // 过滤
                $tags = ''; // tag标签
                $fuzzy = true; // 设置过滤、tag、筛选是否模糊匹配
                $ispics = ''; // 是否多图
                $isico = ''; // 是否缩略图
                $istop = ''; // 是否置顶
                $isrecommend = ''; // 是否推荐
                $isheadline = ''; // 是否头条
                $page = true; // 搜索默认分页
                $lfield = ''; // 查询字段限制
                $lg = get_lg(); // 查询语言限制，默认当前语言，可通过lg=* 来指定

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'field':
                            $field = $value;
                            break;
                        case 'scode':
                            $scode = $value;
                            break;
                        case 'num':
                            $num = $value;
                            break;
                        case 'order':
                            switch ($value) {
                                case 'id':
                                    $order = 'a.id DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC';
                                    break;
                                case 'date':
                                    $order = 'a.date DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.id DESC';
                                    break;
                                case 'sorting':
                                    $order = 'a.sorting ASC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.date DESC,a.id DESC';
                                    break;
                                case 'istop':
                                    $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'isrecommend':
                                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'isheadline':
                                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'visits':
                                case 'likes':
                                case 'oppose':
                                    $order = $value . ' DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    break;
                                case 'random': // 随机取数
                                    $db_type = get_db_type();
                                    if ($db_type == 'mysql') {
                                        $order = "RAND()";
                                    } elseif ($db_type == 'sqlite') {
                                        $order = "RANDOM()";
                                    }
                                    break;
                                default:
                                    if ($value) {
                                        $orders = explode(',', $value);
                                        foreach ($orders as $k => $v) {
                                            if (strpos($v, 'ext_') === 0) {
                                                $orders[$k] = 'e.' . $v;
                                            } else {
                                                $orders[$k] = 'a.' . $v;
                                            }
                                        }
                                        $value = implode(',', $orders);
                                        $order = $value . ',a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                                    }
                            }
                            break;
                        case 'filter':
                            $filter = $value;
                            break;
                        case 'fuzzy':
                            $fuzzy = $value;
                            break;
                        case 'tags':
                            $tags = $value;
                            break;
                        case 'ispics':
                            $ispics = $value;
                            break;
                        case 'isico':
                            $isico = $value;
                            break;
                        case 'istop':
                            $istop = $value;
                            break;
                        case 'isrecommend':
                            $isrecommend = $value;
                            break;
                        case 'isheadline':
                            $isheadline = $value;
                            break;
                        case 'page':
                            $page = $value;
                            break;
                        case 'start':
                            $start = $value;
                            break;
                        case 'lfield':
                            $lfield = $value;
                            break;
                        case 'lg':
                            $lg = $value;
                            break;
                    }
                }

                if ($scode == '*') {
                    $scode = '';
                }

                // filter数据筛选
                $where1 = array();
                if ($filter) {
                    $filter = explode('|', $filter);
                    if (count($filter) == 2) {
                        $filter_arr = explode(',', $filter[1]);
                        if ($filter[0] == 'title') {
                            $filter[0] = 'a.title';
                        }
                        foreach ($filter_arr as $value) {
                            if ($value) {
                                if ($fuzzy) {
                                    $where1[] = $filter[0] . " like '%" . escape_string($value) . "%'";
                                } else {
                                    $where1[] = $filter[0] . "='" . escape_string($value) . "'";
                                }
                            }
                        }
                    }
                }

                // tags数据筛选
                $where2 = array();
                if ($tags) {
                    $tags_arr = explode(',', $tags);
                    foreach ($tags_arr as $value) {
                        if ($value) {
                            if ($fuzzy) {
                                $where2[] = "a.tags like '%" . escape_string($value) . "%'";
                            } else {
                                $where2[] = "a.tags='" . escape_string($value) . "'";
                            }
                        }
                    }
                }

                // 存储搜索条件，条件为“并列”关系，由于为模糊匹配，条件为空时意味着“任意”
                $where3 = array();

                // 采取keyword方式
                if ($keyword) {
                    if (strpos($field, '|')) { // 匹配多字段的关键字搜索
                        $field = explode('|', $field);
                        foreach ($field as $value) {
                            if ($value == 'title') {
                                $value = 'a.title';
                            }
                            if ($fuzzy) {
                                $like = " like '%" . $keyword . "%'"; // 前面已经转义过
                            } else {
                                $like = " like '" . $keyword . "'"; // 前面已经转义过
                            }
                            if (isset($where3[0])) {
                                $where3[0] .= ' OR ' . $value . $like;
                            } else {
                                $where3[0] = $value . $like;
                            }
                        }
                        if (count($field) > 1) {
                            $where3[0] = '(' . $where3[0] . ')';
                        }
                    } else { // 匹配单一字段的关键字搜索
                        if ($field) {
                            if ($field == 'title') {
                                $field = 'a.title';
                            }
                            $where3[$field] = $keyword;
                        } else {
                            $where3['a.title'] = $keyword;
                        }
                    }
                }

                // 数据接收
                if ($_POST) {
                    $receive = $_POST;
                } else {
                    $receive = $_GET;
                }

                foreach ($receive as $key => $value) {
                    if (!!$value = request($key, 'vars')) {
                        if ($key == 'title') {
                            $key = 'a.title';
                        }
                        if (preg_match('/^[\w\-\.]+$/', $key)) { // 带有违规字符时不带入查询
                            $where3[$key] = $value;
                        }
                    }
                }

                // 去除特殊键值
                unset($where3['keyword']);
                unset($where3['field']);
                unset($where3['scode']);
                unset($where3['page']);
                unset($where3['from']);
                unset($where3['isappinstalled']);
                unset($where3['tdsourcetag']);
                unset($where3['x']);
                unset($where3['y']);
                unset($where3['searchtpl']);
                unset($where3['p']);
                unset($where3['s']);

                // 无任何条件不显示内容
                if (!$where3) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                // 判断多图调节参数
                if ($ispics !== '') {
                    if ($ispics) {
                        $where3[] = "a.pics<>''";
                    } else {
                        $where3[] = "a.pics=''";
                    }
                }

                // 判断缩略图调节参数
                if ($isico !== '') {
                    if ($isico) {
                        $where3[] = "a.ico<>''";
                    } else {
                        $where3[] = "a.ico=''";
                    }
                }

                // 判断置顶调节参数
                if ($istop !== '') {
                    if ($istop) {
                        $where3[] = "a.istop=1";
                    } else {
                        $where3[] = "a.istop=0";
                    }
                }

                // 判断推荐调节参数
                if ($isrecommend !== '') {
                    if ($isrecommend) {
                        $where3[] = "a.isrecommend=1";
                    } else {
                        $where3[] = "a.isrecommend=0";
                    }
                }

                // 判断头条调节参数
                if ($isheadline !== '') {
                    if ($isheadline) {
                        $where3[] = "a.isheadline=1";
                    } else {
                        $where3[] = "a.isheadline=0";
                    }
                }

                // 起始数校验
                if (!is_numeric($start) || $start < 1) {
                    $start = 1;
                }

                // 读取数据
                if ($page) {
                    if (isset($paging)) {
                        error('请不要在一个页面使用多个具有分页的列表，您可将多余的使用page=0关闭分页！');
                    }
                    $paging = true;
                }

                $data = $this->model->getList($scode, $num, $order, $where1, $where2, $where3, $fuzzy, $start, $lfield, $lg, $page);

                // 无数据直接替换
                if (!$data) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                $pattern2 = '/\[search:([\w]+)(\s+[^]]+)?\]/';

                // 匹配到内部标签
                if (preg_match_all($pattern2, $matches[2][$i], $matches2)) {
                    $count2 = count($matches2[0]); // 循环内的内容标签数量
                } else {
                    $count2 = 0;
                }

                $out_html = '';
                $pagenum = defined('PAGE') ? PAGE : 1;
                $key = ($pagenum - 1) * $num + 1;
                foreach ($data as $value) { // 按查询数据条数循环
                    $one_html = $matches[2][$i];
                    for ($j = 0; $j < $count2; $j++) { // 循环替换数据
                        $params = $this->parserParam($matches2[2][$j]);
                        $one_html = $this->parserList($matches2[1][$j], $matches2[0][$j], $one_html, $value, $params, $key);
                    }
                    $key++;
                    $out_html .= $one_html;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析列表分页标签
    public function parserPageLabel($content)
    {
        $pattern = '/\{page:([\w]+)\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                switch ($matches[1][$i]) {
                    case 'bar':
                        $content = str_replace($matches[0][$i], $this->getVar('pagebar'), $content);
                        break;
                    case 'current':
                        $content = str_replace($matches[0][$i], $this->getVar('pagecurrent') ?: 0, $content);
                        break;
                    case 'count':
                        $content = str_replace($matches[0][$i], $this->getVar('pagecount') ?: 0, $content);
                        break;
                    case 'rows':
                        $content = str_replace($matches[0][$i], $this->getVar('pagerows') ?: 0, $content);
                        break;
                    case 'index':
                        $content = str_replace($matches[0][$i], $this->getVar('pageindex'), $content);
                        break;
                    case 'pre':
                        $content = str_replace($matches[0][$i], $this->getVar('pagepre'), $content);
                        break;
                    case 'next':
                        $content = str_replace($matches[0][$i], $this->getVar('pagenext'), $content);
                        break;
                    case 'last':
                        $content = str_replace($matches[0][$i], $this->getVar('pagelast'), $content);
                        break;
                    case 'status':
                        $content = str_replace($matches[0][$i], $this->getVar('pagestatus'), $content);
                        break;
                    case 'numbar':
                        $content = str_replace($matches[0][$i], $this->getVar('pagenumbar'), $content);
                        break;
                    case 'selectbar':
                        $content = str_replace($matches[0][$i], $this->getVar('pageselectbar'), $content);
                        break;
                }
            }
        }
        return $content;
    }

    // 解析循环标签
    public function parserLoopLabel($content)
    {
        $pattern = '/\{pboot:loop(\s+[^}]+)?\}([\s\S]*?)\{\/pboot:loop\}/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                // 获取调节参数
                $params = $this->parserParam($matches[1][$i]);
                $start = 1;
                $end = $this->config('pagesize');

                if (!self::checkLabelLevel($params)) {
                    $content = str_replace($matches[0][$i], '', $content);
                    continue;
                }

                foreach ($params as $key => $value) {
                    switch ($key) {
                        case 'start':
                            $start = $value;
                            break;
                        case 'end':
                            $end = $value;
                            break;
                    }
                }

                $out_html = '';
                $key = 1;
                for ($n = $start; $n <= $end; $n++) {
                    $one_html = str_replace('[loop:n]', $key - 1, $matches[2][$i]);
                    $one_html = str_replace('[loop:i]', $key, $one_html);
                    $one_html = str_replace('[loop:index]', $n, $one_html);
                    $out_html .= $one_html;
                    $key++;
                }
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
        return $content;
    }

    // 解析IF条件标签
    public function parserIfLabel($content)
    {
        $pattern = '/\{pboot:if\(([^}^\$]+)\)\}([\s\S]*?)\{\/pboot:if\}/';
        $pattern2 = '/pboot:([0-9])+if/';
        if (preg_match_all($pattern, $content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $flag = '';
                $out_html = '';
                $danger = false;

                $white_fun = array(
                    'date'
                );

                // 还原可能包含的保留内容，避免判断失效
                $matches[1][$i] = $this->restorePreLabel($matches[1][$i]);

                // 带有函数的条件语句进行安全校验
                if (preg_match_all('/([\w]+)([\x00-\x1F\x7F\/\*\<\>\%\w\s\\\\]+)?\(/i', $matches[1][$i], $matches2)) {
                    foreach ($matches2[1] as $value) {
                        if (function_exists(trim($value)) && !in_array($value, $white_fun)) {
                            $danger = true;
                            break;
                        }
                    }

                    foreach ($matches2[2] as $value) {
                        if (function_exists(trim($value)) && !in_array($value, $white_fun)) {
                            $danger = true;
                            break;
                        }
                    }
                }

                // 过滤特殊字符串

                if (preg_match('/(\([\w\s\.]+\))|(\$_GET\[)|(\$_POST\[)|(\$_REQUEST\[)|(\$_COOKIE\[)|(\$_SESSION\[)|(file_put_contents)|(file_get_contents)|(fwrite)|(phpinfo)|(base64)|(`)|(shell_exec)|(eval)|(assert)|(system)|(exec)|(passthru)|(pcntl_exec)|(popen)|(proc_open)|(print_r)|(print)|(urldecode)|(chr)|(include)|(request)|(__FILE__)|(__DIR__)|(copy)|(call_user_)|(preg_replace)|(array_map)|(array_reverse)|(array_filter)|(getallheaders)|(get_headers)|(decode_string)|(htmlspecialchars)|(session_id)|(strrev)|(substr)|(php.info)|(@file.@_put_content)/i', $matches[1][$i])) {
                    $danger = true;
                }

                // 如果有危险函数，则不解析该IF
                if ($danger) {
                    continue;
                }
                //if标签解析
                $flag = symbol($matches[1][$i]);
                if (preg_match('/^([\s\S]*)\{else\}([\s\S]*)$/', $matches[2][$i], $matches2)) { // 判断是否存在else
                    switch ($flag) {
                        case 'if': // 条件为真
                            if (isset($matches2[1])) {
                                $out_html = $matches2[1];
                            }
                            break;
                        case 'else': // 条件为假
                            if (isset($matches2[2])) {
                                $out_html = $matches2[2];
                            }
                            break;
                    }
                } elseif ($flag == 'if') {
                    $out_html = $matches[2][$i];
                }

                // 无限极嵌套解析
                if (preg_match($pattern2, $out_html, $matches3)) {
                    $out_html = str_replace('pboot:' . $matches3[1] . 'if', 'pboot:if', $out_html);
                    $out_html = str_replace('{' . $matches3[1] . 'else}', '{else}', $out_html);
                    $out_html = $this->parserIfLabel($out_html);
                }

                // 执行替换
                $content = str_replace($matches[0][$i], $out_html, $content);
            }
        }
//        var_dump(111);die;
        return $content;
    }

    // 调整标签数据
    protected function adjustLabelData($params, $data, $label = null, $savelabel = false)
    {
        if (!$data)
            return $data;

        // 图片缩放功能
        if (isset($params['maxwidth']) || isset($params['maxheight'])) {
            $maxwidth = isset($params['maxwidth']) ? $params['maxwidth'] : null;
            $maxheight = isset($params['maxheight']) ? $params['maxheight'] : null;
            $max_src_file = ROOT_PATH . $data;
            $max_out_file = RUN_PATH . '/image/mw' . $maxwidth . '_mh' . $maxheight . '_' . basename($data);
            if (!file_exists($max_out_file) && file_exists($max_src_file)) {
                if (resize_img($max_src_file, $max_out_file, $maxwidth, $maxheight)) {
                    $data = str_replace(ROOT_PATH, '', $max_out_file);
                }
            } elseif (file_exists($max_out_file) && file_exists($max_src_file)) {
                $data = str_replace(ROOT_PATH, '', $max_out_file);
            }
        }

        // 图片固定大小
        if (isset($params['width']) || isset($params['height'])) {
            $width = isset($params['width']) ? $params['width'] : null;
            $height = isset($params['height']) ? $params['height'] : null;
            $src_file = ROOT_PATH . $data;
            $out_file = RUN_PATH . '/image/w' . $width . '_h' . $height . '_' . basename($data);
            if (!file_exists($out_file) && file_exists($src_file)) {
                if (cut_img($src_file, $out_file, $width, $height)) {
                    $data = str_replace(ROOT_PATH, '', $out_file);
                }
            } elseif (file_exists($out_file) && file_exists($src_file)) {
                $data = str_replace(ROOT_PATH, '', $out_file);
            }
        }

        // 检查标签权限
        if (!self::checkLabelLevel($params)) {
            $data = '';
        }

        if (is_array($params) && $params) {
            foreach ($params as $key => $value) {
                switch ($key) {
                    case 'style': // 时间样式
                        if ($params['style'] && $date = strtotime($data)) {
                            $data = date($params['style'], $date);
                        }
                        break;
                    case 'len': // 长度截取
                        if ($params['len'] && is_string($data)) {
                            if (mb_strlen($data, 'utf-8') > $params['len']) {
                                if (isset($params['more'])) {
                                    $more = $params['more'];
                                } else {
                                    $more = '···';
                                }
                                $data = mb_substr($data, 0, $params['len'], 'utf-8') . $more;
                            }
                        }
                        break;
                    case 'lencn': // 以中文占位长度方式截取，英文算半个
                        if ($params['lencn'] && is_string($data)) {
                            if (strlen_both($data) > $params['lencn']) {
                                if (isset($params['more'])) {
                                    $more = $params['more'];
                                } else {
                                    $more = '···';
                                }
                                $data = substr_both($data, 0, $params['lencn']) . $more;
                            }
                        }
                        break;
                    case 'drophtml': // 去除html标签
                        if ($params['drophtml']) {
                            $data = strip_tags($data);
                        }
                        break;
                    case 'dropblank': // 清理特殊空白
                        if ($params['dropblank']) {
                            $data = clear_html_blank($data);
                        }
                        break;
                    case 'decode': // 解码或转义字符
                        if ($params['decode']) {
                            $data = decode_string($data);
                        } else {
                            $data = escape_string($data);
                        }
                        break;
                    case 'substr': // 截取字符串
                        if ($params['substr'] && is_string($data)) {
                            $arr = explode(',', $params['substr']);
                            if (count($arr) == 2 && $arr[1]) {
                                $data = mb_substr($data, $arr[0] - 1, $arr[1], 'utf-8');
                            } else {
                                $data = mb_substr($data, $arr[0] - 1);
                            }
                        }
                        break;
                    case 'unit': // bytes转换未其它单位
                        switch ($params['unit']) {
                            case 'KB':
                            case 'kb':
                                $data = $data / 1024;
                                break;
                            case 'MB':
                            case 'mb':
                                $data = $data / (1024 * 1024);
                                break;
                            case 'GB':
                            case 'gb':
                                $data = $data / (1024 * 1024 * 1024);
                                break;
                            case 'TB':
                            case 'tb':
                                $data = $data / (1024 * 1024 * 1024 * 1024);
                                break;
                            case 'PB':
                            case 'pb':
                                $data = $data / (1024 * 1024 * 1024 * 1024 * 1024);
                                break;
                            case 'EB':
                            case 'eb':
                                $data = $data / (1024 * 1024 * 1024 * 1024 * 1024 * 1024);
                                break;
                        }
                        break;
                    case 'decimal': // 小数点
                        if ($params['decimal']) {
                            $data = number_format($data, $params['decimal']);
                        }
                        break;
                    case 'operate': // 实现列表页标签+-*/%运算功能
                        if (preg_match('/^([\+\-\*\/\%])([0-9\.]+)$/', $params['operate'], $mathes)) {
                            if (!is_numeric($data)) {
                                $data = 0;
                            }
                            switch ($mathes[1]) {
                                case '+':
                                    $data = $data + $mathes[2];
                                    break;
                                case '-':
                                    $data = $data - $mathes[2];
                                    break;
                                case '*':
                                    $data = $data * $mathes[2];
                                    break;
                                case '/':
                                    $data = $data / $mathes[2];
                                    break;
                                case '%':
                                    $data = $data % $mathes[2];
                                    break;
                            }
                        }
                        break;
                    case 'mark':
                        if ($label && $reqdata = request($label, 'vars') ?: request('keyword', 'vars')) {
                            $data = preg_replace('/(' . $reqdata . ')/i', '<span style="color:red">$1</span>', $data);
                        }
                        break;
                }
            }
        }

        // 对标签内容中含有标签是否保留不解析
        if ($savelabel && $data) {
            $this->pre[] = $data; // 保存内容避免解析
            end($this->pre); // 指向最后一个元素
            $data = '#pre:' . key($this->pre) . '#';
        }
        return $data;
    }

    // 解析调节参数
    protected function parserParam($string, $striptags = true)
    {
        if (!$string = trim($string))
            return array();
        $string = preg_replace('/\s+/', ' ', $string); // 多空格处理
        if ($striptags) {
            $string = strip_tags($string);
        }

        $param = array();
        if (preg_match_all('/([\w]+)[\s]?=[\s]?([\"]([^\"]+)?[\"]|[\']([^\']+)?[\']|([^\s]+))/i', $string, $matches)) {
            foreach ($matches[1] as $key => $value) {
                $param[$value] = $matches[3][$key] ?: $matches[4][$key] ?: $matches[5][$key];
            }
        }
        return $param;
    }

    // 解析列表标签
    protected function parserList($label, $search, $content, $data, $params, $key)
    {
        switch ($label) {
            case 'n':
                $content = str_replace($search, $this->adjustLabelData($params, $key) - 1, $content);
                break;
            case 'i':
                $content = str_replace($search, $this->adjustLabelData($params, $key), $content);
                break;
            case 'link':
                if ($data->outlink) { // 外链
                    $content = str_replace($search, $data->outlink, $content);
                } else {
                    if ($data->type == 1) {
                        $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'about', $data->scode, $data->sortfilename, '', ''), $content);
                    } else {
                        $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename), $content);
                    }
                }
                break;
            case 'sortlink':
                $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'list', $data->scode, $data->sortfilename, '', ''), $content);
                break;
            case 'subsortlink':
                if ($data->subscode) {
                    $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'list', $data->subscode, $data->subfilename, '', ''), $content);
                } else {
                    $content = str_replace($search, 'javascript:;', $content);
                }
                break;
            case 'sortname':
                if ($data->sortname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->sortname, $label), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'subsortname':
                if ($data->subsortname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->subsortname, $label), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'ico':
                if ($data->ico) {
                    if (!preg_match('/^http/', $data->ico)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->ico), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->ico), $content);
                    }
                } elseif (preg_match('/<img\s+.*?src=\s?[\'|\"](.*?(\.gif|\.jpg|\.png|\.jpeg))[\'|\"].*?[\/]?>/i', $data->content, $srcs) && isset($srcs[1])) {
                    $content = str_replace($search, $this->adjustLabelData($params, $srcs[1]), $content);
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, STATIC_DIR . '/images/nopic.png'), $content);
                }
                break;
            case 'isico':
                if ($data->ico) {
                    $content = str_replace($search, 1, $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
                break;
            case 'ispics':
                if ($data->pics) {
                    $content = str_replace($search, 1, $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
                break;
            case 'enclosure':
                if ($data->enclosure) {
                    if (!preg_match('/^http/', $data->enclosure)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->enclosure), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->enclosure), $content);
                    }
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'enclosuresize':
                if ($data->enclosure && file_exists(ROOT_PATH . $data->enclosure)) {
                    $content = str_replace($search, $this->adjustLabelData($params, filesize(ROOT_PATH . $data->enclosure)), $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
            case 'likeslink':
                $content = str_replace($search, Url::get('home/Do/likes/id/' . $data->id), $content);
                break;
            case 'opposelink':
                $content = str_replace($search, Url::get('home/Do/oppose/id/' . $data->id), $content);
                break;
            case 'content':
                $content = str_replace($search, $this->adjustLabelData($params, $data->content, $label, true), $content); // 占位替换
                break;
            case 'keywords':
                $content = str_replace($search, $this->adjustLabelData($params, $data->keywords, $label, true), $content); // 占位替换
                break;
            case 'description':
                $content = str_replace($search, $this->adjustLabelData($params, $data->description, $label, true), $content); // 占位替换
                break;
            default:
                if (isset($data->$label)) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->$label, $label), $content);
                } elseif (strpos($label, 'ext_') === 0) {
                    $content = str_replace($search, '', $content);
                }
        }
        return $content;
    }

    // 解析内容详情标签
    protected function parserContent($label, $search, $content, $data, $params, $sort)
    {
        switch ($label) {
            case 'link':
                if ($data->type == 1) {
                    if ($data->sortoutlink) {
                        $content = str_replace($search, $data->sortoutlink, $content);
                    } else {
                        $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'about', $data->scode, $data->sortfilename, '', ''), $content);
                    }
                } else {
                    if ($data->outlink) {
                        $content = str_replace($search, $data->outlink, $content);
                    } else {
                        $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename), $content);
                    }
                }
                break;
            case 'sortlink':
                $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'list', $data->scode, $data->sortfilename, '', ''), $content);
                break;
            case 'subsortlink':
                if ($data->subscode) {
                    $content = str_replace($search, $this->parserLink($data->type, $data->urlname, 'list', $data->subscode, $data->subfilename, '', ''), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'sortname':
                if ($data->sortname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->sortname), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'subsortname':
                if ($data->subsortname) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->subsortname), $content);
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'ico':
                if ($data->ico) {
                    if (!preg_match('/^http/', $data->ico)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->ico), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->ico), $content);
                    }
                } elseif (preg_match('/<img\s+.*?src=\s?[\'|\"](.*?(\.gif|\.jpg|\.png|\.jpeg))[\'|\"].*?[\/]?>/i', $data->content, $srcs) && isset($srcs[1])) {
                    $content = str_replace($search, $this->adjustLabelData($params, $srcs[1]), $content);
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, STATIC_DIR . '/images/nopic.png'), $content);
                }
                break;
            case 'isico':
                if ($data->ico) {
                    $content = str_replace($search, 1, $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
                break;
            case 'ispics':
                if ($data->pics) {
                    $content = str_replace($search, 1, $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
                break;
            case 'enclosure':
                if ($data->enclosure) {
                    if (!preg_match('/^http/', $data->enclosure)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $data->enclosure), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $data->enclosure), $content);
                    }
                } else {
                    $content = str_replace($search, '', $content);
                }
                break;
            case 'enclosuresize':
                if ($data->enclosure && file_exists(ROOT_PATH . $data->enclosure)) {
                    $content = str_replace($search, $this->adjustLabelData($params, filesize(ROOT_PATH . $data->enclosure)), $content);
                } else {
                    $content = str_replace($search, 0, $content);
                }
                break;
            case 'likeslink':
                $content = str_replace($search, Url::get('home/Do/likes/id/' . $data->id), $content);
                break;
            case 'opposelink':
                $content = str_replace($search, Url::get('home/Do/oppose/id/' . $data->id), $content);
                break;
            case 'precontent':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$pre = $this->model->getContentPre($sort->scode, $data->id)) {
                    $content = str_replace($search, '<a href="' . $this->parserLink($pre->type, $pre->urlname, 'content', $pre->scode, $pre->sortfilename, $pre->id, $pre->filename) . '">' . $this->adjustLabelData($params, $pre->title) . '</a>', $content);
                } else {
                    if (isset($params['notext'])) {
                        $content = str_replace($search, $params['notext'], $content);
                    } else {
                        $content = str_replace($search, '没有了！', $content);
                    }
                }
                break;
            case 'prelink':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$pre = $this->model->getContentPre($sort->scode, $data->id)) {
                    $content = str_replace($search, $this->parserLink($pre->type, $pre->urlname, 'content', $pre->scode, $pre->sortfilename, $pre->id, $pre->filename), $content);
                } else {
                    $content = str_replace($search, 'javascript:;', $content);
                }
                break;
            case 'pretitle':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$pre = $this->model->getContentPre($sort->scode, $data->id)) {
                    $content = str_replace($search, $this->adjustLabelData($params, $pre->title), $content);
                } else {
                    if (isset($params['notext'])) {
                        $content = str_replace($search, $params['notext'], $content);
                    } else {
                        $content = str_replace($search, '没有了！', $content);
                    }
                }
                break;
            case 'preico':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$pre = $this->model->getContentPre($sort->scode, $data->id)) {
                    if (!preg_match('/^http/', $pre->ico)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $pre->ico), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $pre->ico), $content);
                    }
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, STATIC_DIR . '/images/nopic.png'), $content);
                }
                break;
            case 'nextcontent':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$next = $this->model->getContentNext($sort->scode, $data->id)) {
                    $content = str_replace($search, '<a href="' . $this->parserLink($next->type, $next->urlname, 'content', $next->scode, $next->sortfilename, $next->id, $next->filename) . '">' . $this->adjustLabelData($params, $next->title) . '</a>', $content);
                } else {
                    if (isset($params['notext'])) {
                        $content = str_replace($search, $params['notext'], $content);
                    } else {
                        $content = str_replace($search, '没有了！', $content);
                    }
                }
                break;
            case 'nextlink':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$next = $this->model->getContentNext($sort->scode, $data->id)) {
                    $content = str_replace($search, $this->parserLink($next->type, $next->urlname, 'content', $next->scode, $next->sortfilename, $next->id, $next->filename), $content);
                } else {
                    $content = str_replace($search, 'javascript:;', $content);
                }
                break;
            case 'nexttitle':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$next = $this->model->getContentNext($sort->scode, $data->id)) {
                    $content = str_replace($search, $this->adjustLabelData($params, $next->title), $content);
                } else {
                    if (isset($params['notext'])) {
                        $content = str_replace($search, $params['notext'], $content);
                    } else {
                        $content = str_replace($search, '没有了！', $content);
                    }
                }
                break;
            case 'nextico':
                if ($data->type != 2) // 非列表内容页不解析
                    break;
                if (!!$next = $this->model->getContentNext($sort->scode, $data->id)) {
                    if (!preg_match('/^http/', $next->ico)) {
                        $content = str_replace($search, $this->adjustLabelData($params, SITE_DIR . $next->ico), $content);
                    } else {
                        $content = str_replace($search, $this->adjustLabelData($params, $next->ico), $content);
                    }
                } else {
                    $content = str_replace($search, $this->adjustLabelData($params, STATIC_DIR . '/images/nopic.png'), $content);
                }
                break;
            case 'content':
                // 内链处理
                if (!!$tags = $this->model->getTags()) {
                    // 将A链接保护起来,alt、titel保护起来
                    $rega = "/(<a .*?>.*?<\/a>)|(alt=.*?>)|(title=.*?>)/i";
                    preg_match_all($rega, $data->content, $matches1);
                    foreach ($matches1[0] as $key => $value) {
                        $data->content = str_replace($value, '#rega:' . $key . '#', $data->content);
                    }

                    // 去除包含关系的短tags,实现长关键字优先
                    foreach ($tags as $key => $value) {
                        foreach ($tags as $key2 => $value2) {
                            if (strpos($value2->name, $value->name) !== false && $key != $key2) {
                                unset($tags[$key]);
                            }
                        }
                    }

                    // 执行内链替换
                    foreach ($tags as $value) {
                        $data->content = preg_replace('/' . $value->name . '/', '<a href="' . $value->link . '">' . $value->name . '</a>', $data->content, $this->config('content_tags_replace_num') ?: 3);
                    }

                    // 还原保护的内容
                    $pattern = '/\#rega:([0-9]+)\#/';
                    if (preg_match_all($pattern, $data->content, $matches2)) {
                        $count = count($matches2[0]);
                        for ($i = 0; $i < $count; $i++) {
                            $data->content = str_replace($matches2[0][$i], $matches1[0][$matches2[1][$i]], $data->content);
                        }
                    }
                }
                $content = str_replace($search, $this->adjustLabelData($params, $data->content, null, true), $content);
                break;
            case 'keywords': // 如果内容关键字为空，则自动使用全局关键字
                if ($data->keywords) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->keywords, null, true), $content);
                } else {
                    $content = str_replace($search, '{pboot:sitekeywords}', $content);
                }
                break;
            case 'description': // 如果内容描述为空，则自动使用全局描述
                if ($data->description) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->description, null, true), $content);
                } else {
                    $content = str_replace($search, '{pboot:sitedescription}', $content);
                }
                break;
            default:
                if (isset($data->$label)) {
                    $content = str_replace($search, $this->adjustLabelData($params, $data->$label), $content);
                } elseif (strpos($label, 'ext_') === 0) {
                    $content = str_replace($search, '', $content);
                }
        }
        return $content;
    }

    // 替换页面内容关键词
    protected function parserReplaceKeyword($content)
    {
        $keys = $this->config('content_keyword_replace');
        $keys_arr = explode(',', strip_tags($keys));
        foreach ($keys_arr as $key => $value) {
            $content = str_replace($value, str_repeat('*', mb_strlen($value)), $content);
        }
        return $content;
    }

    // 解析生成内容链接
    public function parserLink($type, $urlname, $pagetype, $scode, $sortfilename, $id = '', $contentfilename = '')
    {
        $url_break_char = $this->config('url_break_char') ?: '_';
        $url_rule_sort_suffix = $this->config('url_rule_sort_suffix') ? true : null;
        $url_rule_content_path = $this->config('url_rule_content_path') ? true : false;

        if ($type == 1 || $pagetype == 'about') {
            $urlname = $urlname ?: 'about';
            if ($sortfilename) {
                $link = Url::home($sortfilename);
            } else {
                $link = Url::home($urlname . $url_break_char . $scode);
            }
        } else {
            $urlname = $urlname ?: 'list';
            if ($pagetype == 'list') {
                if ($sortfilename) {
                    $link = Url::home($sortfilename);
                } else {
                    $link = Url::home($urlname . $url_break_char . $scode);
                }
            } elseif ($pagetype == 'content') {
                if ($url_rule_content_path) {
                    if ($contentfilename) {
                        $link = Url::home($contentfilename, true);
                    } else {
                        $link = Url::home($id, true);
                    }
                } else {
                    if ($sortfilename && $contentfilename) {
                        $link = Url::home($sortfilename . '/' . $contentfilename, true);
                    } elseif ($sortfilename) {
                        $link = Url::home($sortfilename . '/' . $id, true);
                    } elseif ($contentfilename) {
                        $link = Url::home($urlname . $url_break_char . $scode . '/' . $contentfilename, true);
                    } else {
                        $link = Url::home($urlname . $url_break_char . $scode . '/' . $id, true);
                    }
                }
            } else {
                $link = 'javascript:;';
            }
        }
        return $link;
    }

    // 检查标签权限
    protected function checkLabelLevel($params)
    {
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'showgcode': // 指定等级显示，支持多个逗号隔开
                    $showgcode = explode(',', $params['showgcode']);
                    if (!in_array(session('pboot_gcode'), $showgcode)) {
                        return false;
                    }
                    break;
                case 'showucode': // 指定用户显示，支持多个逗号隔开
                    $showucode = explode(',', $params['showucode']);
                    if (!in_array(session('pboot_ucode'), $showucode)) {
                        return false;
                    }
                    break;
                case 'hidegcode': // 指定等级隐藏，支持多个逗号隔开
                    $hidegcode = explode(',', $params['hidegcode']);
                    if (in_array(session('pboot_gcode'), $hidegcode)) {
                        return false;
                    }
                    break;
                case 'hideucode': // 指定用户隐藏，支持多个逗号隔开
                    $hideucode = explode(',', $params['hideucode']);
                    if (in_array(session('pboot_ucode'), $hideucode)) {
                        return false;
                    }
                    break;
                case 'showgcodelt': // 等级小于显示
                    if ($params['showgcodelt'] <= session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'showgcodegt': // 等级大于显示
                    if ($params['showgcodegt'] >= session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'showgcodele': // 等级小于等于显示
                    if ($params['showgcodele'] < session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'showgcodege': // 等级大于等于显示
                    if ($params['showgcodege'] > session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'hidegcodelt': // 等级小于隐藏
                    if ($params['hidegcodelt'] > session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'hidegcodegt': // 等级大于隐藏
                    if ($params['hidegcodegt'] < session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'hidegcodele': // 等级小于等于隐藏
                    if ($params['hidegcodele'] >= session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'hidegcodege': // 等级大于等于隐藏
                    if ($params['hidegcodege'] <= session('pboot_gcode')) {
                        return false;
                    }
                    break;
                case 'showlogin': // 登录后显示
                    if ($params['showlogin'] && !session('pboot_uid')) {
                        return false;
                    }
                    break;
                case 'hidelogin': // 登录后隐藏
                    if ($params['hidelogin'] && session('pboot_uid')) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }
}
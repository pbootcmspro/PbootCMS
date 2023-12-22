<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年4月20日
 *  CMS通用接口控制器
 */
namespace app\api\controller;

use core\basic\Controller;
use app\api\model\CmsModel;
use core\basic\Url;
use app\home\controller\ParserController;

class CmsController extends Controller
{

    protected $model;

    protected $lg;

    public function __construct()
    {
        $this->model = new CmsModel();
        $this->lg = get_default_lg();
    }

    // 站点基础信息
    public function site()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        
        // 读取数据
        if (! $name = request('name', 'var')) {
            $data = $this->model->getSiteAll($acode);
        } else {
            $data = $this->model->getSite($acode, $name);
        }
        
        // 输出数据
        json(1, $data);
    }

    // 公司信息
    public function company()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        
        // 读取数据
        if (! $name = request('name', 'var')) {
            $data = $this->model->getCompanyAll($acode);
        } else {
            $data = $this->model->getCompany($acode, $name);
        }
        
        // 输出数据
        json(1, $data);
    }

    // 自定义标签信息
    public function label()
    {
        // 获取全部或指定自定义标签
        if (! $name = request('name', 'var')) {
            $data = $this->model->getLabelAll();
        } else {
            $data = $this->model->getLabel($name);
        }
        
        // 输出数据
        json(1, $data);
    }

    // 获取菜单栏目树
    public function nav()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        
        // 获取栏目树
        if (! $scode = request('scode', 'var')) {
            $data = $this->model->getSorts($acode);
        } else { // 获取子类
            $data = $this->model->getSortsSon($acode, $scode);
        }
        // 输出数据
        json(1, $data);
    }

    // 当前栏目位置
    public function position()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        
        if (! ! $scode = request('scode', 'var')) {
            $data = $this->model->getPosition($acode, $scode);
            json(1, $data);
        } else {
            json(0, '必须传递当前分类scode参数');
        }
    }

    // 分类信息
    public function sort()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        
        if (! ! $scode = request('scode', 'var')) {
            $data = $this->model->getSort($acode, $scode);
            json(1, $data);
        } else {
            json(0, '必须传递分类scode参数');
        }
    }

    // 内容多图
    public function pics()
    {
        if (! ! $id = request('id', 'int')) {
            $acode = request('acode', 'var') ?: $this->lg;
            if (! ! $pics = $this->model->getContentPics($acode, $id)) {
                $pics = explode(',', $pics);
            } else {
                $pics = array();
            }
            json(1, $pics);
        } else {
            json(0, '必须传递内容id参数');
        }
    }

    // 幻灯片
    public function slide()
    {
        if (! ! $gid = request('gid', 'var')) {
            $acode = request('acode', 'var') ?: $this->lg;
            $num = request('num', 'int') ?: 10;
            $data = $this->model->getSlides($acode, $gid, $num);
            json(1, $data);
        } else {
            json(0, '必须传递幻灯片分组gid参数');
        }
    }

    // 友情链接
    public function link()
    {
        if (! ! $gid = request('gid', 'var')) {
            $acode = request('acode', 'var') ?: $this->lg;
            $num = request('num', 'int') ?: 20;
            $data = $this->model->getLinks($acode, $gid, $num);
            json(1, $data);
        } else {
            json(0, '必须传递友情链接分组gid参数');
        }
    }

    // 搜索
    public function search()
    {
        if (! $_POST) {
            json(0, '请使用POST提交！');
        }
        
        $acode = request('acode', 'var') ?: $this->lg;
        
        // 获取主要参数
        $field = request('field');
        if (! preg_match('/^[\w\|]+$/', $field)) {
            $field = '';
        }
        $keyword = request('keyword', 'vars');
        $scode = request('scode'); // 支持多个分类逗号隔开
        if (! preg_match('/^[\w,\s]+$/', $scode)) {
            $scode = '';
        }
        if ($scode == '*') { // 星号意味任意栏目
            $scode = '';
        }
        
        $num = request('num', 'int') ?: $this->config('pagesize');
        $rorder = request('order');
        $tags = request('tags', 'vars');
        $fuzzy = request('fuzzy', 'int') ?: true;
        
        if (! preg_match('/^[\w\-,\s]+$/', $rorder)) {
            $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
        } else {
            switch ($rorder) {
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
                    $order = $rorder . ' DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
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
                    if ($rorder) {
                        $orders = explode(',', $rorder);
                        foreach ($orders as $k => $v) {
                            if (strpos($v, 'ext_') === 0) {
                                $orders[$k] = 'e.' . $v;
                            } else {
                                $orders[$k] = 'a.' . $v;
                            }
                        }
                        $order = implode(',', $orders);
                        $order .= ',a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    }
            }
        }
        
        $where1 = array();
        
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
            if (! ! $value = request($key, 'vars')) {
                if ($key == 'title') {
                    $key = 'a.title';
                }
                if (preg_match('/^[\w\-\.]+$/', $key)) { // 带有违规字符时不带入查询
                    $where3[$key] = $value;
                }
            }
        }
        
        // 去除特殊键值
        unset($where3['appid']);
        unset($where3['timestamp']);
        unset($where3['signature']);
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
        
        // 读取数据
        $data = $this->model->getLists($acode, $scode, $num, $order, $where1, $where2, $where3, $fuzzy);
        $Parser = new ParserController();
        
        foreach ($data as $key => $value) {
            if ($value->outlink) {
                $data[$key]->apilink = $value->outlink;
            } else {
                $data[$key]->apilink = url('/api/content/index/scode/' . $value->id, false);
            }
            $data[$key]->likeslink = url('/home/Do/likes/id/' . $value->id, false);
            $data[$key]->opposelink = url('/home/Do/oppose/id/' . $value->id, false);
            $data[$key]->content = str_replace(STATIC_DIR . '/upload/', get_http_url() . STATIC_DIR . '/upload/', $value->content);
            
            // 返回网页链接地址，便于AJAX调用内容
            $data[$key]->contentlink = $Parser->parserLink(2, $value->urlname, 'content', $value->scode, $value->sortfilename, $value->id, $value->filename);
        }
        
        // 输出数据
        if (request('page') <= PAGECOUNT) {
            json(1, $data);
        } else {
            return json(0, '已经到底了！');
        }
    }

    // 读取留言记录
    public function msg()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: $this->lg;
        $num = request('num', 'int') ?: $this->config('pagesize');
        
        // 获取栏目数
        $data = $this->model->getMessage($acode, $num);
        
        if (request('page') <= PAGECOUNT) {
            json(1, $data);
        } else {
            return json(0, '已经到底了！');
        }
    }

    // 新增留言
    public function addmsg()
    {
        if ($_POST) {
            
            if ($this->config('message_status') === '0') {
                json(0, '系统已经关闭留言功能，请到后台开启再试！');
            }
            
            // 读取字段
            if (! $form = $this->model->getFormField(1)) {
                json(0, '接收表单不存在任何字段，请核对后重试！');
            }
            
            // 接收数据
            $mail_body = '';
            foreach ($form as $value) {
                $field_data = post($value->name);
                if (is_array($field_data)) { // 如果是多选等情况时转换
                    $field_data = implode(',', $field_data);
                }
                $field_data = str_replace('pboot:if', '', $field_data);
                if ($value->required && ! $field_data) {
                    json(0, $value->description . '不能为空！');
                } else {
                    $data[$value->name] = $field_data;
                    $mail_body .= $value->description . '：' . $field_data . '<br>';
                }
            }
            
            $status = $this->config('message_verify') == '0' ? 1 : 0;
            
            // 设置其他字段
            if ($data) {
                $data['acode'] = request('acode', 'var') ?: $this->lg;
                $data['user_ip'] = ip2long(get_user_ip());
                $data['user_os'] = get_user_os();
                $data['user_bs'] = get_user_bs();
                $data['recontent'] = '';
                $data['status'] = $status;
                $data['create_user'] = 'api';
                $data['update_user'] = 'api';
            }
            
            // 写入数据
            if ($this->model->addMessage($value->table_name, $data)) {
                $this->log('API提交留言数据成功！');
                if ($this->config('message_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【" . CMSNAME . "】您有新的" . $value->form_name . "信息，请注意查收！";
                    $mail_body .= '<br>来自网站' . get_http_url() . '（' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                json(1, '留言提交成功！');
            } else {
                $this->log('API提交留言数据失败！');
                json(0, '留言提交失败！');
            }
        } else {
            json(0, '留言提交失败，请使用POST方式提交！');
        }
    }

    // 表单记录
    public function form()
    {
        // 获取参数
        $num = request('num', 'int') ?: $this->config('pagesize');
        
        // 获取表单编码
        if (! $fcode = request('fcode', 'var'))
            json(0, '必须传递表单编码fcode');
        
        // 获取表名称
        if (! $table = $this->model->getFormTable($fcode)) {
            json(0, '传递的fcode有误');
        }
        
        // 获取表数据
        $data = $this->model->getForm($table, $num);
        
        if (request('page') <= PAGECOUNT) {
            json(1, $data);
        } else {
            return json(0, '已经到底了！');
        }
    }

    // 表单提交
    public function addform()
    {
        if ($_POST) {
            
            if (! $fcode = request('fcode', 'var')) {
                json(0, '传递的表单编码fcode有误！');
            }
            
            if ($this->config('form_status') === '0') {
                json(0, '系统已经关闭表单功能，请到后台开启再试！');
            }
            
            // 读取字段
            if (! $form = $this->model->getFormField($fcode)) {
                json(0, '接收表单不存在任何字段，请核对后重试！');
            }
            
            // 接收数据
            $mail_body = '';
            foreach ($form as $value) {
                $field_data = post($value->name);
                if (is_array($field_data)) { // 如果是多选等情况时转换
                    $field_data = implode(',', $field_data);
                }
                $field_data = str_replace('pboot:if', '', $field_data);
                if ($value->required && ! $field_data) {
                    json(0, $value->description . '不能为空！');
                } else {
                    $data[$value->name] = $field_data;
                    $mail_body .= $value->description . '：' . $field_data . '<br>';
                }
            }
            
            // 设置创建时间
            if ($data) {
                $data['create_time'] = get_datetime();
            }
            
            // 写入数据
            if ($this->model->addForm($value->table_name, $data)) {
                $this->log('API提交表单数据成功！');
                if ($this->config('form_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【" . CMSNAME . "】您有新的" . $value->form_name . "信息，请注意查收！";
                    $mail_body .= '<br>来自网站' . get_http_url() . '（' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                json(1, '表单提交成功！');
            } else {
                $this->log('API提交表单数据失败！');
                json(0, '表单提交失败！');
            }
        } else {
            json(0, '表单提交失败，请使用POST方式提交！');
        }
    }

    // 空拦截
    public function _empty()
    {
        json(0, '您调用的接口不存在，请核对后重试！');
    }
}
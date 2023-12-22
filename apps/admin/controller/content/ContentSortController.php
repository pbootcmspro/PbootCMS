<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年12月26日
 *  内容栏目控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\ContentSortModel;

class ContentSortController extends Controller
{

    private $count;

    private $blank;

    private $outData = array();

    private $model;

    public function __construct()
    {
        $this->model = new ContentSortModel();
    }

    // 内容栏目列表
    public function index()
    {
        $this->assign('list', true);
        $tree = $this->model->getList();
        $sorts = $this->makeSortList($tree);
        $this->assign('sorts', $sorts);
        
        // 内容模型
        $models = model('admin.content.Model');
        $this->assign('allmodels', $models->getSelectAll());
        $this->assign('models', $models->getSelect());
        
        // 内容栏目下拉表
        $sort_tree = $this->model->getSelect();
        $sort_select = $this->makeSortSelect($sort_tree);
        $this->assign('sort_select', $sort_select);
        
        // 模板文件
        $htmldir = $this->config('tpl_html_dir') ? '/' . $this->config('tpl_html_dir') : '';
        $this->assign('tpls', file_list(ROOT_PATH . current($this->config('tpl_dir')) . '/' . $this->model->getTheme() . $htmldir));
        
        // 前端地址连接符判断
        $url_break_char = $this->config('url_break_char') ?: '_';
        $this->assign('url_break_char', $url_break_char);
        
        // 获取会员分组
        $this->assign('groups', model('admin.member.MemberGroup')->getSelect());
        
        $this->display('content/contentsort.html');
    }

    // 生成无限级内容栏目列表
    private function makeSortList($tree)
    {
        // 循环生成
        foreach ($tree as $value) {
            $this->count ++;
            $this->outData[$this->count] = new \stdClass();
            foreach ($value as $k => $v) {
                $this->outData[$this->count]->$k = $v;
            }
            
            if ($value->son) {
                $this->outData[$this->count]->son = true;
            } else {
                $this->outData[$this->count]->son = false;
            }
            
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $this->makeSortList($value->son);
            }
        }
        
        // 循环完后回归缩进位置
        $this->blank = substr($this->blank, 6);
        return $this->outData;
    }

    // 内容栏目增加
    public function add()
    {
        if ($_POST) {
            if (! ! $multiplename = post('multiplename')) {
                $multiplename = str_replace('，', ',', $multiplename);
                $pcode = post('pcode', 'var');
                $type = post('type');
                $mcode = post('mcode');
                $listtpl = basename(post('listtpl'));
                $contenttpl = basename(post('contenttpl'));
                $status = post('status');
                
                if (! $pcode) { // 父编码默认为0
                    $pcode = 0;
                }
                
                if (! $mcode) {
                    alert_back('栏目模型必须选择！');
                }
                
                if (! $type) {
                    alert_back('栏目类型不能为空！');
                }
                
                $names = explode(',', $multiplename);
                $lastcode = $this->model->getLastCode();
                $scode = get_auto_code($lastcode);
                foreach ($names as $key => $value) {
                    $data[] = array(
                        'acode' => session('acode'),
                        'pcode' => $pcode,
                        'scode' => $scode,
                        'name' => $value,
                        'mcode' => $mcode,
                        'listtpl' => $listtpl,
                        'contenttpl' => $contenttpl,
                        'status' => $status,
                        'gid' => 0,
                        'gtype' => 4,
                        'subname' => '',
                        'filename' => '',
                        'outlink' => '',
                        'ico' => '',
                        'pic' => '',
                        'title' => '',
                        'keywords' => '',
                        'description' => '',
                        'sorting' => 255,
                        'create_user' => session('username'),
                        'update_user' => session('username')
                    );
                    $scode = get_auto_code($scode);
                }
            } else {
                // 获取数据
                $scode = get_auto_code($this->model->getLastCode()); // 自动编码;
                $pcode = post('pcode', 'var');
                $name = post('name');
                $type = post('type');
                $mcode = post('mcode');
                $listtpl = basename(post('listtpl'));
                $contenttpl = basename(post('contenttpl'));
                $status = post('status');
                $subname = post('subname');
                $filename = trim(post('filename'), '/');
                $outlink = post('outlink');
                $ico = post('ico');
                $pic = post('pic');
                $title = post('title');
                $keywords = post('keywords');
                $description = post('description');
                
                $gid = post('gid', 'int') ?: 0;
                $gtype = post('gtype', 'int') ?: 4;
                $gnote = post('gnote');
                
                $def1 = post('def1');
                $def2 = post('def2');
                $def3 = post('def3');
                
                if (! $scode) {
                    alert_back('编码不能为空！');
                }
                
                if (! $pcode) { // 父编码默认为0
                    $pcode = 0;
                }
                
                if (! $name) {
                    alert_back('栏目名不能为空！');
                }
                
                if (! $mcode) {
                    alert_back('栏目模型必须选择！');
                }
                
                if (! $type) {
                    alert_back('栏目类型不能为空！');
                }
                
                if ($filename && ! preg_match('/^[a-zA-Z0-9\-\/]+$/', $filename)) {
                    alert_back('URL名称只允许字母、数字、横线、斜线组成!');
                }
                
                if ($filename && $this->model->checkUrlname($filename)) {
                    alert_back('URL名称与模型URL名称冲突，请换一个名称！');
                }
                
                // 缩放缩略图
                if ($ico) {
                    resize_img(ROOT_PATH . $ico, '', $this->config('ico.max_width'), $this->config('ico.max_height'));
                }
                
                // 检查编码
                if ($this->model->checkSort("scode='$scode'")) {
                    alert_back('该内容栏目编号已经存在，不能再使用！');
                }
                
                // 检查自定义URL名称
                if ($filename) {
                    while ($this->model->checkFilename($filename)) {
                        $filename = $filename . '_' . mt_rand(1, 20);
                    }
                }
                
                // 构建数据
                $data = array(
                    'acode' => session('acode'),
                    'pcode' => $pcode,
                    'scode' => $scode,
                    'name' => $name,
                    'mcode' => $mcode,
                    'listtpl' => $listtpl,
                    'contenttpl' => $contenttpl,
                    'status' => $status,
                    'gid' => $gid,
                    'gtype' => $gtype,
                    'gnote' => $gnote,
                    'subname' => $subname,
                    'def1' => $def1,
                    'def2' => $def2,
                    'def3' => $def3,
                    'filename' => $filename,
                    'outlink' => $outlink,
                    'ico' => $ico,
                    'pic' => $pic,
                    'title' => $title,
                    'keywords' => $keywords,
                    'description' => $description,
                    'sorting' => 255,
                    'create_user' => session('username'),
                    'update_user' => session('username')
                );
            }
            
            // 执行添加
            if ($this->model->addSort($data)) {
                if ($type == 1 && ! $outlink) { // 在填写了外链时不生成单页
                    if ($multiplename) {
                        foreach ($data as $key => $value) {
                            $this->addSingle($value['scode'], $value['name']);
                        }
                    } else {
                        $this->addSingle($scode, $name);
                    }
                }
                $this->log('新增数据内容栏目' . $scode . '成功！');
                success('新增成功！', url('/admin/ContentSort/index'));
            } else {
                $this->log('新增数据内容栏目' . $scode . '失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 生成内容栏目下拉选择
    private function makeSortSelect($tree, $selectid = null)
    {
        $list_html = '';
        foreach ($tree as $value) {
            // 默认选择项
            if ($selectid == $value->scode) {
                $select = "selected='selected'";
            } else {
                $select = '';
            }
            if (get('scode') != $value->scode) { // 不显示本身，避免出现自身为自己的父节点
                $list_html .= "<option value='{$value->scode}' $select>{$this->blank}{$value->name}</option>";
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $list_html .= $this->makeSortSelect($value->son, $selectid);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $list_html;
    }

    // 内容栏目删除
    public function del()
    {
        // 执行批量删除
        if ($_POST) {
            if (! ! $list = post('list')) {
                if ($this->model->delSortList($list)) {
                    $this->log('批量删除栏目成功！');
                    success('批量删除成功！', - 1);
                } else {
                    $this->log('批量删除栏目失败！');
                    error('批量删除失败！', - 1);
                }
            } else {
                alert_back('请选择要删除的栏目！');
            }
        }
        
        if (! $scode = get('scode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        if ($this->model->delSort($scode)) {
            $this->log('删除数据内容栏目' . $scode . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除数据内容栏目' . $scode . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 内容栏目修改
    public function mod()
    {
        if (! ! $submit = post('submit')) {
            switch ($submit) {
                case 'sorting': // 修改列表排序
                    $listall = post('listall');
                    if ($listall) {
                        $sorting = post('sorting');
                        foreach ($listall as $key => $value) {
                            if ($sorting[$key] === '' || ! is_numeric($sorting[$key]))
                                $sorting[$key] = 255;
                            $this->model->modSortSorting($value, "sorting=" . $sorting[$key]);
                        }
                        $this->log('批量修改栏目排序成功！');
                        success('修改成功！', - 1);
                    } else {
                        alert_back('排序失败，无任何内容！');
                    }
                    break;
            }
        }
        
        if (! $scode = get('scode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modSort($scode, "$field='$value',update_user='" . session('username') . "'")) {
                $this->log('修改数据内容栏目' . $scode . '状态' . $value . '成功！');
                location(- 1);
            } else {
                $this->log('修改数据内容栏目' . $scode . '状态' . $value . '失败！');
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $pcode = post('pcode', 'var');
            $name = post('name');
            $mcode = post('mcode');
            $type = post('type');
            $listtpl = basename(post('listtpl'));
            $contenttpl = basename(post('contenttpl'));
            $status = post('status');
            $subname = post('subname');
            $filename = trim(post('filename'), '/');
            $outlink = post('outlink');
            $ico = post('ico');
            $pic = post('pic');
            $title = post('title');
            $keywords = post('keywords');
            $description = post('description');
            $modsub = post('modsub', 'int');
            
            $gid = post('gid', 'int') ?: 0;
            $gtype = post('gtype', 'int') ?: 4;
            $gnote = post('gnote');
            
            $def1 = post('def1');
            $def2 = post('def2');
            $def3 = post('def3');
            
            if (! $pcode) { // 父编码默认为0
                $pcode = 0;
            }
            
            if (! $name) {
                alert_back('栏目名不能为空！');
            }
            
            if (! $mcode) {
                alert_back('栏目模型必须选择！');
            }
            
            if (! $type) {
                alert_back('栏目类型不能为空！');
            }
            
            if ($filename && ! preg_match('/^[a-zA-Z0-9\-\/]+$/', $filename)) {
                alert_back('URL名称只允许字母、数字、横线、斜线组成!');
            }
            
            if ($filename && $this->model->checkUrlname($filename)) {
                alert_back('URL名称与模型URL名称冲突，请换一个名称！');
            }
            
            // 缩放缩略图
            if ($ico) {
                resize_img(ROOT_PATH . $ico, '', $this->config('ico.max_width'), $this->config('ico.max_height'));
            }
            
            if ($filename) {
                while ($this->model->checkFilename($filename, "scode<>'$scode'")) {
                    $filename = $filename . '-' . mt_rand(1, 20);
                }
            }
            
            // 构建数据
            $data = array(
                'pcode' => $pcode,
                'name' => $name,
                'mcode' => $mcode,
                'listtpl' => $listtpl,
                'contenttpl' => $contenttpl,
                'status' => $status,
                'gid' => $gid,
                'gtype' => $gtype,
                'gnote' => $gnote,
                'subname' => $subname,
                'def1' => $def1,
                'def2' => $def2,
                'def3' => $def3,
                'filename' => $filename,
                'outlink' => $outlink,
                'ico' => $ico,
                'pic' => $pic,
                'title' => $title,
                'keywords' => $keywords,
                'description' => $description,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modSort($scode, $data, $modsub)) {
                // 如果修改为单页并且跳转，则删除单页内容，否则判断是否存在内容，不存在则添加
                if ($type == 1 && $outlink) {
                    $this->model->delContent($scode);
                } elseif ($type == 1 && ! $this->model->findContent($scode)) {
                    $this->addSingle($scode, $name);
                }
                
                $this->log('修改数据内容栏目' . $scode . '成功！');
                success('修改成功！', url('/admin/ContentSort/index'));
            } else {
                location(- 1);
            }
        } else { // 调取修改内容
            $this->assign('mod', true);
            
            $sort = $this->model->getSort($scode);
            if (! $sort) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('sort', $sort);
            
            // 父编码下拉选择
            $sort_tree = $this->model->getSelect();
            $sort_select = $this->makeSortSelect($sort_tree, $sort->pcode);
            $this->assign('sort_select', $sort_select);
            
            // 模板文件
            $htmldir = $this->config('tpl_html_dir') ? '/' . $this->config('tpl_html_dir') : '';
            $this->assign('tpls', file_list(ROOT_PATH . current($this->config('tpl_dir')) . '/' . $this->model->getTheme() . $htmldir));
            
            // 内容模型
            $models = model('admin.content.Model');
            $this->assign('models', $models->getSelect());
            
            // 获取会员分组
            $this->assign('groups', model('admin.member.MemberGroup')->getSelect());
            
            $this->display('content/contentsort.html');
        }
    }

    // 添加栏目时执行单页内容增加
    public function addSingle($scode, $title)
    {
        // 构建数据
        $data = array(
            'acode' => session('acode'),
            'scode' => $scode,
            'subscode' => '',
            'title' => $title,
            'titlecolor' => '#333333',
            'subtitle' => '',
            'filename' => '',
            'author' => session('realname'),
            'source' => '本站',
            'outlink' => '',
            'date' => date('Y-m-d H:i:s'),
            'ico' => '',
            'pics' => '',
            'content' => '',
            'tags' => '',
            'enclosure' => '',
            'keywords' => '',
            'description' => '',
            'sorting' => 255,
            'status' => 1,
            'istop' => 0,
            'isrecommend' => 0,
            'isheadline' => 0,
            'gid' => 0,
            'gtype' => 4,
            'gnote' => '',
            'visits' => 0,
            'likes' => 0,
            'oppose' => 0,
            'create_user' => session('username'),
            'update_user' => session('username')
        );
        
        // 执行添加
        if ($this->model->addSingle($data)) {
            return true;
        } else {
            return false;
        }
    }
}

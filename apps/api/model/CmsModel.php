<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  标签解析引擎模型
 */
namespace app\api\model;

use core\basic\Model;

class CmsModel extends Model
{

    // 存储分类及子编码
    protected $scodes = array();

    // 存储分类查询数据
    protected $sorts;

    // 存储栏目位置
    protected $position = array();

    // 单个站点配置信息
    public function getSite($acode, $name)
    {
        return parent::table('ay_site')->where("acode='" . $acode . "'")->value($name);
    }

    // 站点配置信息
    public function getSiteAll($acode)
    {
        return parent::table('ay_site')->where("acode='" . $acode . "'")->find();
    }

    // 单个公司信息
    public function getCompany($acode, $name)
    {
        return parent::table('ay_company')->where("acode='" . $acode . "'")->value($name);
    }

    // 公司信息
    public function getCompanyAll($acode)
    {
        return parent::table('ay_company')->where("acode='" . $acode . "'")->find();
    }

    // 自定义标签
    public function getLabel($name)
    {
        return parent::table('ay_label')->where("name='$name'")
            ->decode()
            ->value('value');
    }

    // 所有自定义标签
    public function getLabelAll()
    {
        return parent::table('ay_label')->decode()->column('value', 'name');
    }

    // 分类信息
    public function getSort($acode, $scode)
    {
        $field = array(
            'a.*',
            'b.type'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        return parent::table('ay_content_sort a')->field($field)
            ->where("a.acode='" . $acode . "'")
            ->where("a.scode='$scode'")
            ->join($join)
            ->find();
    }

    // 分类栏目列表
    public function getSorts($acode)
    {
        $fields = array(
            'a.*',
            'b.type'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        $result = parent::table('ay_content_sort a')->field($fields)
            ->where("a.acode='" . $acode . "'")
            ->where('a.status=1')
            ->join($join)
            ->order('a.pcode,a.sorting,a.id')
            ->select();
        return get_tree($result, 0, 'scode', 'pcode');
    }

    // 获取分类的子类
    public function getSortsSon($acode, $scode)
    {
        $fields = array(
            'a.*',
            'b.type'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        $result = parent::table('ay_content_sort a')->field($fields)
            ->where("a.pcode='" . $scode . "'")
            ->where("a.acode='" . $acode . "'")
            ->where('a.status=1')
            ->join($join)
            ->order('a.sorting,a.id')
            ->select();
        return $result;
    }

    // 分类顶级编码
    public function getSortTopScode($acode, $scode)
    {
        if (! isset($this->sorts)) {
            $fields = array(
                'a.id',
                'a.pcode',
                'a.scode',
                'a.name',
                'b.type'
            );
            $join = array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            );
            $this->sorts = parent::table('ay_content_sort a')->where("a.acode='" . $acode . "'")
                ->join($join)
                ->column($fields, 'scode');
        }
        $result = $this->sorts;
        return $this->getTopParent($scode, $result);
    }

    // 获取位置
    public function getPosition($acode, $scode)
    {
        if (! isset($this->sorts)) {
            $fields = array(
                'a.id',
                'a.pcode',
                'a.scode',
                'a.name',
                'a.outlink',
                'b.type'
            );
            $join = array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            );
            $this->sorts = parent::table('ay_content_sort a')->where("a.acode='" . $acode . "'")
                ->join($join)
                ->column($fields, 'scode');
        }
        $result = $this->sorts;
        $this->position = array();
        $this->getTopParent($scode, $result);
        return array_reverse($this->position);
    }

    // 分类顶级编码及栏目树
    private function getTopParent($scode, $sorts)
    {
        if (! $scode || ! $sorts) {
            return;
        }
        $this->position[] = $sorts[$scode];
        if ($sorts[$scode]['pcode']) {
            return $this->getTopParent($sorts[$scode]['pcode'], $sorts);
        } else {
            return $sorts[$scode]['scode'];
        }
    }

    // 分类子类集
    private function getSubScodes($scode)
    {
        if (! $scode) {
            return;
        }
        $this->scodes[] = $scode;
        $subs = parent::table('ay_content_sort')->where("pcode='$scode'")->column('scode');
        if ($subs) {
            foreach ($subs as $value) {
                $this->getSubScodes($value);
            }
        }
        return $this->scodes;
    }

    // 列表内容,带分页
    public function getLists($acode, $scode, $num, $order, $filter = array(), $tags = array(), $select = array(), $fuzzy = true)
    {
        $fields = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        
        $scode_arr = array();
        if ($scode) {
            // 获取所有子类分类编码
            $this->scodes = array(); // 先清空
            $arr = explode(',', $scode); // 传递有多个分类时进行遍历
            foreach ($arr as $value) {
                $scodes = $this->getSubScodes(trim($value));
            }
            // 拼接条件
            $scode_arr = array(
                "a.scode in (" . implode_quot(',', $scodes) . ")",
                "a.subscode='$scode'"
            );
        }
        
        $where = array(
            "a.acode='" . $acode . "'",
            'a.status=1',
            'd.type=2',
            "a.date<'" . date('Y-m-d H:i:s') . "'"
        );
        
        // 筛选条件支持模糊匹配
        return parent::table('ay_content a')->field($fields)
            ->where($scode_arr, 'OR')
            ->where($where)
            ->where($select, 'AND', 'AND', $fuzzy)
            ->where($filter, 'OR')
            ->where($tags, 'OR')
            ->join($join)
            ->order($order)
            ->page(1, $num)
            ->decode()
            ->select();
    }

    // 列表内容，不带分页
    public function getList($acode, $scode, $num, $order, $filter = array(), $tags = array(), $select = array(), $fuzzy = true)
    {
        $fields = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        
        $scode_arr = array();
        if ($scode) {
            // 获取所有子类分类编码
            $this->scodes = array(); // 先清空
            $arr = explode(',', $scode); // 传递有多个分类时进行遍历
            foreach ($arr as $value) {
                $scodes = $this->getSubScodes(trim($value));
            }
            // 拼接条件
            $scode_arr = array(
                "a.scode in (" . implode_quot(',', $scodes) . ")",
                "a.subscode='$scode'"
            );
        }
        
        $where = array(
            "a.acode='" . $acode . "'",
            'a.status=1',
            'd.type=2',
            "a.date<'" . date('Y-m-d H:i:s') . "'"
        );
        
        // 筛选条件支持模糊匹配
        return parent::table('ay_content a')->field($fields)
            ->where($scode_arr, 'OR')
            ->where($where)
            ->where($select, 'AND', 'AND', $fuzzy)
            ->where($filter, 'OR')
            ->where($tags, 'OR')
            ->join($join)
            ->order($order)
            ->limit($num)
            ->decode()
            ->select();
    }

    // 内容详情
    public function getContent($acode, $id)
    {
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        $result = parent::table('ay_content a')->field($field)
            ->where("a.id='$id'")
            ->where("a.acode='" . $acode . "'")
            ->where('a.status=1')
            ->join($join)
            ->decode()
            ->find();
        if ($result) {
            $data2 = array(
                'visits' => '+=1'
            );
            parent::table('ay_content')->where("id={$result->id}")->update($data2);
        }
        return $result;
    }

    // 单页详情
    public function getAbout($acode, $scode)
    {
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        $result = parent::table('ay_content a')->field($field)
            ->where("a.scode='$scode' OR b.filename='$scode'")
            ->where("a.acode='" . $acode . "'")
            ->where('a.status=1')
            ->join($join)
            ->order('id DESC')
            ->decode()
            ->find();
        if ($result) {
            $data2 = array(
                'visits' => '+=1'
            );
            parent::table('ay_content')->where("id={$result->id}")->update($data2);
        }
        return $result;
    }

    // 指定内容多图
    public function getContentPics($acode, $id)
    {
        $result = parent::table('ay_content')->where("id='$id'")
            ->where("acode='" . $acode . "'")
            ->where('status=1')
            ->value('pics');
        return $result;
    }

    // 幻灯片
    public function getSlides($acode, $gid, $num)
    {
        $result = parent::table('ay_slide')->where("gid='$gid'")
            ->where("acode='" . $acode . "'")
            ->order('sorting asc,id asc')
            ->limit($num)
            ->select();
        return $result;
    }

    // 友情链接
    public function getLinks($acode, $gid, $num)
    {
        $result = parent::table('ay_link')->where("gid='$gid'")
            ->where("acode='" . $acode . "'")
            ->order('sorting asc,id asc')
            ->limit($num)
            ->select();
        return $result;
    }

    // 获取留言
    public function getMessage($acode, $num)
    {
        return parent::table('ay_message')->where("status=1")
            ->where("acode='" . $acode . "'")
            ->order('id DESC')
            ->decode(false)
            ->page(1, $num)
            ->select();
    }

    // 新增留言
    public function addMessage($table, $data)
    {
        return parent::table('ay_message')->autoTime()->insert($data);
    }

    // 获取表单字段
    public function getFormField($fcode)
    {
        $field = array(
            'a.table_name',
            'a.form_name',
            'b.name',
            'b.required',
            'b.description'
        );
        
        $join = array(
            'ay_form_field b',
            'a.fcode=b.fcode',
            'LEFT'
        );
        
        return parent::table('ay_form a')->field($field)
            ->where("a.fcode='$fcode'")
            ->join($join)
            ->order('b.sorting ASC,b.id ASC')
            ->select();
    }

    // 获取表单表名称
    public function getFormTable($fcode)
    {
        return parent::table('ay_form')->where("fcode='$fcode'")->value('table_name');
    }

    // 获取表单数据
    public function getForm($table, $num)
    {
        return parent::table($table)->order('id DESC')
            ->decode(false)
            ->page(1, $num)
            ->select();
    }

    // 新增表单数据
    public function addForm($table, $data)
    {
        return parent::table($table)->insert($data);
    }
}
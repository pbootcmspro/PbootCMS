<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  标签解析引擎模型
 */
namespace app\home\model;

use core\basic\Model;

class ParserModel extends Model
{

    // 扩展内容表中真实存在的扩展字段（单个模型实例内缓存）
    protected $ext_fields;

    // 存储分类及子编码
    protected $scodes = array();

    // 存储分类查询数据
    protected $sorts;

    // 存储栏目位置
    protected $position = array();

    // 上一篇
    protected $pre;

    // 下一篇
    protected $next;

    // 获取模型数据
    public function checkModelUrlname($urlname)
    {
        if ($urlname == 'list' || $urlname == 'about') {
            return true;
        }
        return parent::table('ay_model')->where("urlname='$urlname'")->find();
    }

    // 站点配置信息
    public function getSite()
    {
        return parent::table('ay_site')->where("acode='" . get_lg() . "'")->find();
    }

    // 公司信息
    public function getCompany()
    {
        return parent::table('ay_company')->where("acode='" . get_lg() . "'")->find();
    }

    // 自定义标签，不区分语言，兼容跨语言
    public function getLabel()
    {
        return parent::table('ay_label')->decode()->column('value,type', 'name');
    }

    // 单个分类信息，不区分语言，兼容跨语言
    public function getSort($scode)
    {
        $scode = escape_string($scode);
        $field = array(
            'a.*',
            'c.name AS parentname',
            'b.type',
            'b.urlname',
            'd.gcode'
        );
        $join = array(
            array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.pcode=c.scode',
                'LEFT'
            ),
            array(
                'ay_member_group d',
                'a.gid=d.id',
                'LEFT'
            )
        );
        return parent::table('ay_content_sort a')->field($field)
            ->where("a.scode='$scode' OR a.filename='$scode'")
            ->join($join)
            ->find();
    }

    // 多个分类信息，不区分语言，兼容跨语言
    public function getMultSort($scodes)
    {
        $field = array(
            'a.*',
            'c.name AS parentname',
            'b.type',
            'b.urlname'
        );
        $join = array(
            array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.pcode=c.scode',
                'LEFT'
            )
        );
        return parent::table('ay_content_sort a')->field($field)
            ->in('a.scode', $scodes)
            ->join($join)
            ->order('a.sorting,a.id')
            ->select();
    }

    // 指定分类数量
    public function getSortRows($scode)
    {
        $this->scodes = array(); // 先清空

        // 获取多分类子类
        $arr = explode(',', $scode);
        foreach ($arr as $value) {
            $scodes = $this->getSubScodes(trim($value));
        }

        // 拼接条件
        $where1 = array(
            "scode in (" . implode_quot(',', $scodes) . ")",
            "subscode='$scode'"
        );
        $where2 = array(
            "acode='" . get_lg() . "'",
            'status=1',
            "date<'" . date('Y-m-d H:i:s') . "'"
        );

        $result = parent::table('ay_content')->where($where1, 'OR')
            ->where($where2)
            ->column('id');
        return count($result);
    }

    // 分类栏目列表关系树
    public function getSortsTree()
    {
        $fields = array(
            'a.*',
            'b.type',
            'b.urlname'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        $result = parent::table('ay_content_sort a')->where("a.acode='" . get_lg() . "'")
            ->where('a.status=1')
            ->join($join)
            ->order('a.pcode,a.sorting,a.id')
            ->column($fields, 'scode');

        foreach ($result as $key => $value) {
            if ($value['pcode']) {
                $result[$value['pcode']]['son'][] = $value; // 记录到关系树
            } else {
                $data['top'][] = $value; // 记录顶级菜单
            }
        }
        $data['tree'] = $result;
        return $data;
    }

    // 获取分类名称
    public function getSortName($scode)
    {
        $result = $this->getSortList();
        return $result[$scode]['name'];
    }

    // 分类顶级编码
    public function getSortTopScode($scode)
    {
        $result = $this->getSortList();
        return $this->getTopParent($scode, $result);
    }

    /**
     * 栏目大图回退：当前 → 直接父级 → 顶级；全部无图返回空字符串
     *
     * @param string $scode
     * @return string
     */
    public function resolveSortPic($scode)
    {
        if (! $scode) {
            return '';
        }
        $sorts = $this->getSortList();
        if (! isset($sorts[$scode])) {
            return '';
        }
        if (! empty($sorts[$scode]['pic'])) {
            return $sorts[$scode]['pic'];
        }
        $pcode = isset($sorts[$scode]['pcode']) ? $sorts[$scode]['pcode'] : '';
        if ($pcode && isset($sorts[$pcode]) && ! empty($sorts[$pcode]['pic'])) {
            return $sorts[$pcode]['pic'];
        }
        $tcode = $scode;
        while (! empty($sorts[$tcode]['pcode']) && isset($sorts[$sorts[$tcode]['pcode']])) {
            $tcode = $sorts[$tcode]['pcode'];
        }
        if ($tcode && $tcode != $scode && $tcode != $pcode && ! empty($sorts[$tcode]['pic'])) {
            return $sorts[$tcode]['pic'];
        }
        return '';
    }

    // 获取位置
    public function getPosition($scode)
    {
        $result = $this->getSortList();
        $this->position = array(); // 重置
        $this->getTopParent($scode, $result);
        return array_reverse($this->position);
    }

    // 分类顶级编码
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

/*     // 分类子类集
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
    } */
	// 分类子类集
    public function getSubScodes($scode)
    {
        if (! $scode) {
            return;
        }
        $this->scodes[] = $scode;
        $subs = parent::table('ay_content_sort')->where("pcode='$scode'")
            ->where("outlink=''")
            ->column('scode');
        if ($subs) {
            foreach ($subs as $value) {
                $this->getSubScodes($value);
            }
        }
        return $this->scodes;
    }
	// 清除静态缓存时，获取全部栏目编码
    public function getScodes($type)
    {
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        // 不包括外链
        return parent::table('ay_content_sort a')->join($join)
            ->in('b.type', $type)
            ->where("outlink=''")
            ->column('scode');
    }
	// 生成静态时，获取栏目全部内容ID
    public function getContentIds($scodes, $where = array())
    {
        return parent::table('ay_content')->in('scode', $scodes)
            ->where("outlink=''")
            ->where($where)
            ->column('id');
    }
    // 获取栏目清单
    private function getSortList()
    {
        if (! isset($this->sorts)) {
            $fields = array(
                'a.id',
                'a.pcode',
                'a.scode',
                'a.name',
                'a.filename',
                'a.outlink',
                'a.pic',
                'b.type',
                'b.urlname'
            );
            $join = array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            );
            $this->sorts = parent::table('ay_content_sort a')->where("a.acode='" . get_lg() . "'")
                ->join($join)
                ->column($fields, 'scode');
        }
        return $this->sorts;
    }

    // 获取筛选字段数据
    public function getSelect($field)
    {
        return parent::table('ay_extfield')->where("name='$field'")->value('value');
    }

    // 获取内容扩展表中真实存在的扩展字段
    public function getExtFields()
    {
        if (! isset($this->ext_fields)) {
            $this->ext_fields = array();
            $fields = parent::tableFields('ay_content_ext');
            foreach ($fields as $field) {
                if (is_string($field) && preg_match('/^ext_[\w\-]+$/i', $field)) {
                    $this->ext_fields[] = $field;
                }
            }
        }
        return $this->ext_fields;
    }

    // 列表内容，分页以參數形式帶入，不区分语言，兼容跨语言
    public function getList($scode, $num, $order, $filter = array(), $tags = array(), $select = array(), $fuzzy = false, $start = 1, $lfield = null, $lg = null, bool $page = false)
    {
        $scode = escape_string($scode);
        $ext_table = false;
        if ($lfield) {
            $extFields = null;
            $lfield .= ',id,outlink,type,scode,sortfilename,filename,urlname'; // 附加必须字段
            $fields = explode(',', $lfield);
            $fields = array_unique($fields); // 去重
            $allowed = content_query_fields();
            $relatedFields = array('sortname', 'sortfilename', 'subsortname', 'subfilename', 'type', 'urlname', 'modelname');
            $safe_fields = array();
            foreach ($fields as $value) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
                if ($value == '*') {
                    $safe_fields[] = 'a.*';
                } elseif (preg_match('/^ext_[\w\-]+$/i', $value)) {
                    if ($extFields === null) {
                        $extFields = $this->getExtFields();
                    }
                    $extField = canonical_allowlist_field($value, $extFields);
                    if ($extField === '') {
                        continue; // 不存在或非法的扩展字段名丢弃
                    }
                    $ext_table = true;
                    $safe_fields[] = 'e.' . $extField;
                } else {
                    $relatedField = canonical_allowlist_field($value, $relatedFields);
                    if ($relatedField === 'sortname') {
                        $safe_fields[] = 'b.name as sortname';
                    } elseif ($relatedField === 'sortfilename') {
                        $safe_fields[] = 'b.filename as sortfilename';
                    } elseif ($relatedField === 'subsortname') {
                        $safe_fields[] = 'c.name as subsortname';
                    } elseif ($relatedField === 'subfilename') {
                        $safe_fields[] = 'c.filename as subfilename';
                    } elseif ($relatedField === 'type' || $relatedField === 'urlname') {
                        $safe_fields[] = 'd.' . $relatedField;
                    } elseif ($relatedField === 'modelname') {
                        $safe_fields[] = 'd.name as modelname';
                    } elseif ($contentField = canonical_allowlist_field($value, $allowed)) {
                        $safe_fields[] = 'a.' . $contentField;
                    }
                }
                // 其余非白名单字段丢弃，避免非法列名拼入查询字段列表
            }
            $fields = array_unique($safe_fields);
        } else {
            $ext_table = true;
            $fields = array(
                'a.id',
                'a.scode',
                'a.subscode',
                'a.title',
                'a.filename',
                'a.subtitle',
                'a.author',
                'a.visits',
                'a.likes',
                'a.oppose',
                'a.outlink',
                'a.date',
                'a.ico',
                'a.pics',
                'a.content',
                'a.enclosure',
                'a.keywords',
                'a.description',
                'a.istop',
                'a.isrecommend',
                'a.isheadline',
                'b.name as sortname',
                'b.filename as sortfilename',
                'c.name as subsortname',
                'c.filename as subfilename',
                'd.type',
                'd.name as modelname',
                'd.urlname',
                'e.*',
                'f.gcode'
            );
        }

        $contentSortIndex = '';
        $modelIndex = '';
        $contentExtIndex = '';

        if (get_db_type() == 'mysql') {
            $contentSortIndex = 'FORCE INDEX ( `ay_content_sort_scode` ) ';
            $modelIndex = 'FORCE INDEX ( `ay_model_mcode` )';
            $contentExtIndex = 'FORCE INDEX ( `ay_content_ext_contentid` )';
        }else if(get_db_type() == 'sqlite') {
            $contentSortIndex = 'INDEXED BY `ay_content_sort_scode` ';
            $modelIndex = 'INDEXED BY  `ay_model_mcode` ';
            $contentExtIndex = 'INDEXED BY  `ay_content_ext_contentid` ';
        }

        $join = array(
            array(
//                'ay_content_sort b ',
                'ay_content_sort b '.$contentSortIndex,
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
//                'ay_content_sort c',
                'ay_content_sort c '.$contentSortIndex,
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
//                'ay_model d',
                'ay_model d '.$modelIndex,
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_member_group f',
                'a.gid=f.id',
                'LEFT'
            )
        );

        // 排序、筛选或搜索条件中引用了扩展字段时，同样需要加载扩展字段表，
        // 否则会生成引用未关联表的SQL导致查询报错
        if (! $ext_table && $this->hasExtFieldReference($order, $filter, $tags, $select)) {
            $ext_table = true;
        }

        // 加载扩展字段表
        if ($ext_table) {
            $join[] = array(
//                'ay_content_ext e',
                'ay_content_ext e '.$contentExtIndex,
                'a.id=e.contentid',
                'LEFT'
            );
        }

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
            'a.status=1',
            'd.type=2',
            "a.date<'" . date('Y-m-d H:i:s') . "'"
        );

        if ($lg) {
            $where['a.acode'] = $lg;
        }

        $indexSql = '';
        //todo:V3.1.5判断mysql是否设置了索引
        if (get_db_type() == 'mysql') {
            $checkIndex = parent::table('ay_content')->checkIndexSql();
            foreach ($checkIndex as $item){
                if($item[2] == 'ay_content_unique'){
                    $indexSql = 'FORCE INDEX ( ay_content_unique )';
                    break;
                }
            }
        }

        // 筛选条件支持模糊匹配
        if($page){
            return parent::table('ay_content a ' . $indexSql)->field($fields)
                ->where($scode_arr, 'OR')
                ->where($where)
                ->where($select, 'AND', 'AND', $fuzzy)
                ->where($filter, 'OR')
                ->where($tags, 'OR')
                ->join($join)
                ->order($order)
                ->page(1, $num, $start)
                ->decode()
                ->select();
        }else{
            return parent::table('ay_content a ' . $indexSql)->field($fields)
                ->where($scode_arr, 'OR')
                ->where($where)
                ->where($select, 'AND', 'AND', $fuzzy)
                ->where($filter, 'OR')
                ->where($tags, 'OR')
                ->join($join)
                ->order($order)
                ->limit($start - 1, $num)
                ->decode()
                ->select();
        }

    }

    // 判断传入的排序、条件等参数中是否引用了扩展字段（ext_xxx 或 e.ext_xxx）
    // 递归检查数组的键与值：条件数组既可能以字段名为键，也可能是数值索引的原始子句
    private function hasExtFieldReference()
    {
        foreach (func_get_args() as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $key => $value) {
                    if ($this->hasExtFieldReference($key) || $this->hasExtFieldReference($value)) {
                        return true;
                    }
                }
            } elseif (is_string($arg)) {
                $arg = preg_replace("/'(?:\\\\.|[^'\\\\])*'|\"(?:\\\\.|[^\"\\\\])*\"/", '', $arg);
                if ($arg !== null && preg_match('/(?:^|[^\w])(?:e\.)?ext_[\w\-]+/i', $arg)) {
                    return true;
                }
            }
        }
        return false;
    }

    // 内容详情，不区分语言，兼容跨语言
    public function getContent($id)
    {
        $id = escape_string($id);
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'b.outlink as sortoutlink',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*',
            'f.gcode'
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
            ),
            array(
                'ay_member_group f',
                'a.gid=f.id',
                'LEFT'
            )
        );
        $where = ctype_digit((string) $id) ? "a.id='$id' OR a.filename='$id'" : "a.filename='$id'";
        $result = parent::table('ay_content a')->field($field)
            ->where($where)
            ->where('a.status=1')
            ->where("a.date<'" . date('Y-m-d H:i:s') . "'")
            ->join($join)
            ->decode()
            ->find();
        return $result;
    }

    // 单篇详情,不区分语言，兼容跨语言
    public function getAbout($scode)
    {
        $scode = escape_string($scode);
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'd.name as modelname',
            'd.urlname',
            'e.*',
            'f.gcode'
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
            ),
            array(
                'ay_member_group f',
                'a.gid=f.id',
                'LEFT'
            )
        );
        $result = parent::table('ay_content a')->field($field)
            ->where("a.scode='$scode' OR b.filename='$scode'")
            ->where('a.status=1')
            ->join($join)
            ->decode()
            ->order('id DESC')
            ->find();
        return $result;
    }

    // 指定内容多图
    public function getContentPics($id, $field)
    {
        $join = array(
            'ay_content_ext b',
            'a.id=b.contentid',
            'LEFT'
        );
        $result = parent::table('ay_content a')->field($field . ',picstitle')
            ->join($join)
            ->where("a.id='$id'")
            ->where('a.status=1')
            ->where("a.date<'" . date('Y-m-d H:i:s') . "'")
            ->find();
        return $result;
    }

    // 指定内容多选调用
    public function getContentCheckbox($id, $field)
    {
        $result = parent::table('ay_content_ext')->where("contentid='$id'")->value($field);
        return $result;
    }

    // 指定内容标签调用
    public function getContentTags($id)
    {
        $result = parent::table('ay_content')->field('scode,tags')
            ->where("id='$id'")
            ->where('status=1')
            ->where("date<'" . date('Y-m-d H:i:s') . "'")
            ->find();
        return $result;
    }

    // 指定分类标签调用
    public function getSortTags($scode)
    {
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_model c',
                'b.mcode=c.mcode',
                'LEFT'
            )
        );

        $scode_arr = array();
        if ($scode) {
            // 获取所有子类分类编码
            $this->scodes = array(); // 先清空
            $scodes = $this->getSubScodes(trim($scode)); // 获取子类

            // 拼接条件
            $scode_arr = array(
                "a.scode in (" . implode_quot(',', $scodes) . ")",
                "a.subscode='$scode'"
            );
        }

        $result = parent::table('ay_content a')->where("c.type=2 AND a.tags<>''")
            ->where($scode_arr, 'OR')
            ->join($join)
            ->where('a.status=1')
            ->order('a.visits DESC')
            ->column('a.tags');
        return $result;
    }

    // 上一篇内容
    public function getContentPre($scode, $id)
    {
        if (! $this->pre) {
            $this->scodes = array();
            $scodes = $this->getSubScodes($scode);

            $field = array(
                'a.id',
                'a.title',
                'a.filename',
                'a.ico',
                'a.scode',
                'b.filename as sortfilename',
                'c.type',
                'c.urlname'
            );

            $join = array(
                array(
                    'ay_content_sort b',
                    'a.scode=b.scode',
                    'LEFT'
                ),
                array(
                    'ay_model c',
                    'b.mcode=c.mcode',
                    'LEFT'
                )
            );

            $this->pre = parent::table('ay_content a')->field($field)
                ->where("a.id<$id")
                ->join($join)
                ->in('a.scode', $scodes)
                ->where("a.acode='" . get_lg() . "'")
                ->where('a.status=1')
                ->where("a.date<'" . date('Y-m-d H:i:s') . "'")
                ->order('a.id DESC')
                ->find();
        }
        return $this->pre;
    }

    // 下一篇内容
    public function getContentNext($scode, $id)
    {
        if (! $this->next) {
            $this->scodes = array();
            $scodes = $this->getSubScodes($scode);

            $field = array(
                'a.id',
                'a.title',
                'a.filename',
                'a.ico',
                'a.scode',
                'b.filename as sortfilename',
                'c.type',
                'c.urlname'
            );

            $join = array(
                array(
                    'ay_content_sort b',
                    'a.scode=b.scode',
                    'LEFT'
                ),
                array(
                    'ay_model c',
                    'b.mcode=c.mcode',
                    'LEFT'
                )
            );

            $this->next = parent::table('ay_content a')->field($field)
                ->where("a.id>$id")
                ->join($join)
                ->in('a.scode', $scodes)
                ->where("a.acode='" . get_lg() . "'")
                ->where('a.status=1')
                ->where("a.date<'" . date('Y-m-d H:i:s') . "'")
                ->order('a.id ASC')
                ->find();
        }
        return $this->next;
    }

    // 幻灯片
    public function getSlides($gid, $num, $start = 1)
    {
        $result = parent::table('ay_slide')->where("gid='$gid'")
            ->order('sorting ASC,id ASC')
            ->limit($start - 1, $num)
            ->select();
        return $result;
    }

    // 友情链接
    public function getLinks($gid, $num, $start = 1)
    {
        $result = parent::table('ay_link')->where("gid='$gid'")
            ->order('sorting ASC,id ASC')
            ->limit($start - 1, $num)
            ->select();
        return $result;
    }

    // 获取留言
    public function getMessage($num, $page = true, $start = 1, $lg = null)
    {
        if ($lg == 'all') {
            $where = array();
        } elseif ($lg) {
            $where = array(
                'a.acode' => $lg
            );
        } else {
            $where = array(
                'a.acode' => get_lg()
            );
        }

        $field = array(
            'a.*',
            'b.username',
            'b.nickname',
            'b.headpic'
        );
        $join = array(
            'ay_member b',
            'a.uid=b.id',
            'LEFT'
        );

        if ($page) {
            return parent::table('ay_message a')->field($field)
                ->join($join)
                ->where("a.status=1")
                ->where($where)
                ->order('a.id DESC')
                ->decode(false)
                ->page(1, $num, $start)
                ->select();
        } else {
            return parent::table('ay_message a')->field($field)
                ->join($join)
                ->where("a.status=1")
                ->where($where)
                ->order('a.id DESC')
                ->decode(false)
                ->limit($start - 1, $num)
                ->select();
        }
    }

    // 新增留言
    public function addMessage($data)
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
    public function getForm($table, $num, $page = true, $start = 1)
    {
        if ($page) {
            return parent::table($table)->order('id DESC')
                ->decode(false)
                ->page(1, $num, $start)
                ->select();
        } else {
            return parent::table($table)->order('id DESC')
                ->decode(false)
                ->limit($start - 1, $num)
                ->select();
        }
    }

    // 新增表单数据
    public function addForm($table, $data)
    {
        return parent::table($table)->insert($data);
    }

    // 文章内链
    public function getTags()
    {
        return parent::table('ay_tags')->field('name,link')
            ->where("acode='" . get_lg() . "'")
            ->order('length(name) desc')
            ->select();
    }

    // 新增评论
    public function addComment($data)
    {
        return parent::table('ay_member_comment')->insert($data);
    }

    // 文章评论
    public function getComment($contentid, $pid, $num, $order, $page = false, $start = 1)
    {
        $field = array(
            'a.*',
            'b.username',
            'b.nickname',
            'b.headpic',
            'c.username as pusername',
            'c.nickname as pnickname',
            'c.headpic as pheadpic'
        );
        $join = array(
            array(
                'ay_member b',
                'a.uid=b.id',
                'LEFT'
            ),
            array(
                'ay_member c',
                'a.puid=c.id',
                'LEFT'
            )
        );
        if ($page) {
            return parent::table('ay_member_comment a')->field($field)
                ->join($join)
                ->where("a.contentid='$contentid'")
                ->where('a.pid=' . $pid)
                ->where("a.status=1")
                ->order($order)
                ->page(1, $num, $start)
                ->select();
        } else {
            return parent::table('ay_member_comment a')->field($field)
                ->join($join)
                ->where("a.contentid='$contentid'")
                ->where('a.pid=' . $pid)
                ->where("a.status=1")
                ->order($order)
                ->limit($start - 1, $num)
                ->select();
        }
    }

    // 我的评论
    public function getMyComment($num, $order, $page = false, $start = 1)
    {
        $field = array(
            'a.*',
            'b.username',
            'b.nickname',
            'b.headpic',
            'c.username as pusername',
            'c.nickname as pnickname',
            'c.headpic as pheadpic',
            'd.title'
        );
        $join = array(
            array(
                'ay_member b',
                'a.uid=b.id',
                'LEFT'
            ),
            array(
                'ay_member c',
                'a.puid=c.id',
                'LEFT'
            ),
            array(
                'ay_content d',
                'a.contentid=d.id',
                'LEFT'
            )
        );
        if ($page) {
            return parent::table('ay_member_comment a')->field($field)
                ->join($join)
                ->where("uid='" . session('pboot_uid') . "'")
                ->order($order)
                ->page(1, $num, $start)
                ->select();
        } else {
            return parent::table('ay_member_comment a')->field($field)
                ->join($join)
                ->where("uid='" . session('pboot_uid') . "'")
                ->order($order)
                ->limit($start - 1, $num)
                ->select();
        }
    }

    // 删除评论
    public function delComment($id)
    {
        return parent::table('ay_member_comment')->where("uid='" . session('pboot_uid') . "'")
            ->where("id=$id")
            ->delete();
    }
}

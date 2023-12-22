<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  Sitemap模型
 */
namespace app\home\model;

use core\basic\Model;

class SitemapModel extends Model
{

    // 分类栏目列表
    public function getSorts()
    {
        $fields = array(
            'a.id',
            'a.pcode',
            'a.scode',
            'a.name',
            'a.filename',
            'a.outlink',
            'b.type',
            'b.urlname'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        $result = parent::table('ay_content_sort a')->field($fields)
            ->where('a.status=1')
            ->where("a.acode='" . get_lg() . "'")
            ->join($join)
            ->order('a.pcode,a.sorting,a.id')
            ->select();
        return $result;
    }

    // 指定列表内容
    public function getSortContent($scode)
    {
        $fields = array(
            'a.id',
            'a.filename',
            'a.date',
            'c.type',
            'c.urlname',
            'b.scode',
            'b.filename as sortfilename'
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
        
        $where = array(
            'a.status=1',
            'c.type=2',
            "a.date<'" . date('Y-m-d H:i:s') . "'"
        );
        
        return parent::table('ay_content a')->field($fields)
            ->where("a.scode='$scode'")
            ->where($where)
            ->join($join)
            ->select();
    }
}
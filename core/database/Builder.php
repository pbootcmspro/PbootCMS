<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年8月23日
 *   数据库连接器基类 
 */
namespace core\database;

interface Builder
{

    // 获取单一实例，使用单一实例数据库连接类
    public static function getInstance();

    // 连接数据库，接受数据库连接参数，返回数据库连接对象
    public function conn($cfg);

    // 执行SQL语句,接受完整SQL语句，返回结果集对象
    public function query($sql, $type = 'master');

    // 数据是否存在模型，接受完整SQL语句，返回boolean数据
    public function isExist($sql);

    // 获取记录总量模型，接受数据库表名，返回int数据
    public function rows($table);

    // 读取字段数量模型，接受数据库表名，返回int数据
    public function fields($table);

    // 获取表字段,接受数据库表名，返回表字段数组
    public function tableFields($table);

    // 查询一条数据模型，接受完整SQL语句，有数据返回对象数组，否则空数组
    public function one($sql, $type = null);

    // 查询多条数据模型，接受完整SQL语句，有数据返回二维对象数组，否则空数组
    public function all($sql, $type = null);

    // 数据增、删、改模型，接受完整SQL语句，返回影响的行数的int数据
    public function amd($sql);

    // 最近一次插入数据的自增字段值，返回int数据
    public function insertId();

    // 执行多条SQL模型，成功返回true,否则false
    public function multi($sql);
}


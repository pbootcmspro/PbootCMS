<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月19日
 *  数据库管理模型类,只兼容MySQL数据库
 */
namespace app\admin\model\system;

use core\basic\Model;

class DatabaseModel extends Model
{

    // 数据库表状态列表
    public function getList()
    {
        return parent::all('SHOW TABLE STATUS');
    }

    // 获取全部表
    public function getTables()
    {
        $result = parent::all('SHOW TABLES', 2);
        foreach ($result as $value) {
            $tables[] = $value[0];
        }
        return $tables;
    }

    // 获取表字段数量
    public function getFieldNum($table)
    {
        return parent::fields($table);
    }

    // 获取表字段名
    public function getFields($table)
    {
        $one_data = parent::one("SELECT * FROM " . $table); // 读取数据
        $fields = array();
        if ($one_data) {
            foreach ($one_data as $key => $value) {
                $fields[] = "`$key`";
            }
        }
        return $fields;
    }

    // 获取一条数据
    public function getOne($table)
    {
        return parent::one("SELECT * FROM " . $table);
    }

    // 获取全部数据
    public function getAll($table)
    {
        return parent::all("SELECT * FROM " . $table, MYSQLI_NUM);
    }

    // 数据库表结构
    public function tableStru($table)
    {
        $sql = "DROP TABLE IF EXISTS `" . $table . '`;' . PHP_EOL;
        $result = parent::one('SHOW CREATE TABLE `' . $table . '`', MYSQLI_ASSOC);
        return $sql . $result['Create Table'] . ';' . PHP_EOL . PHP_EOL;
    }

    // 数据库表优化
    public function optimize($tables)
    {
        return parent::query("OPTIMIZE TABLE $tables");
    }

    // 数据库表修复
    public function repair($tables)
    {
        return parent::query("REPAIR TABLE $tables");
    }

    // 锁定数据库表
    public function lock($tablename, $op = "WRITE")
    {
        if (parent::query("lock tables " . $tablename . " " . $op)) {
            return true;
        } else {
            return false;
        }
    }

    // 解锁数据库标
    public function unlock()
    {
        if (parent::query("unlock tables")) {
            return true;
        } else {
            return false;
        }
    }
}
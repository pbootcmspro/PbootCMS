<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年5月9日
 *  数据库管理,只支持MySQL
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\DatabaseModel;

class DatabaseController extends Controller
{

    private $model;

    private $dbauth;

    function __construct()
    {
        $this->model = new DatabaseModel();
        $this->dbauth = $this->config('database');
    }

    // 数据库管理
    public function index()
    {
        switch ($this->dbauth['type']) {
            case 'mysqli':
            case 'pdo_mysql':
                $this->assign('db', 'mysql');
                $this->assign('tables', $this->model->getList());
                break;
            case 'sqlite':
            case 'pdo_sqlite':
                $this->assign('db', 'sqlite');
                break;
            default:
                error('当前配置的数据库类型不支持在线管理！');
        }
        $this->display('system/database.html');
    }

    // 数据库修改
    public function mod()
    {
        if (! $_POST) {
            alert_back('非法访问！', - 1);
        }
        
        $submit = post('submit', 'letter', true);
        
        switch ($submit) {
            case 'yh':
                $tables = self::getTableList();
                if (! $tables)
                    alert_back('请选择数据表！');
                if ($this->model->optimize(implode(',', $tables))) {
                    // $this->log('优化数据库表成功！');
                    success('优化成功！', - 1);
                } else {
                    // $this->log('优化数据库表失败！');
                    error('优化失败！', - 1);
                }
                break;
            case 'xf':
                $tables = self::getTableList();
                if (! $tables)
                    alert_back('请选择数据表！');
                if ($this->model->repair(implode(',', $tables))) {
                    // $this->log('修复数据库表成功！');
                    success('修复成功！', - 1);
                } else {
                    // $this->log('修复数据库表失败！');
                    error('修复失败！', - 1);
                }
                break;
            case 'bf':
                $tables = self::getTableList();
                if (! $tables)
                    alert_back('请选择数据表！');
                if ($this->backupTable($tables)) {
                    $this->log('备份数据库表成功！');
                    success('备份表成功！', - 1);
                } else {
                    $this->log('备份数据库表失败！');
                    error('备份失败！', - 1);
                }
                break;
            case 'bfdb':
                if ($this->backupDB()) {
                    $this->log('备份数据库成功！');
                    success('备份数据库成功！', - 1);
                } else {
                    $this->log('备份数据库失败！');
                    error('备份失败！', - 1);
                }
                break;
            case 'bfsqlite':
                if (copy(DOC_PATH . $this->dbauth['dbname'], DOC_PATH . STATIC_DIR . '/backup/sql/' . get_uniqid() . '_' . date('YmdHis') . '.db')) {
                    $this->log('备份数据库成功！');
                    success('备份数据库成功！', - 1);
                } else {
                    $this->log('备份数据库失败！');
                    error('备份失败！', - 1);
                }
                break;
        }
    }

    // 备份数据表
    public function backupTable($tables)
    {
        $backdir = date('YmdHis');
        foreach ($tables as $table) {
            $sql = '';
            $sql .= $this->header(); // 备份文件头部说明
            $sql .= $this->tableSql($table); // 表结构信息
            $fields = $this->model->getFields($table); // 表字段
            $field_num = $this->model->getFieldNum($table); // 字段数量
            $all_data = $this->model->getAll($table); // 读取全部数据
            $sql .= $this->dataSql($table, $fields, $field_num, $all_data); // 生成语句
            $filename = $backdir . "/" . get_uniqid() . "_" . $backdir . "_" . $table . '.sql'; // 写入文件
            $result = $this->writeFile($filename, $sql);
        }
        return $result;
    }

    // 备份整个数据库
    public function backupDB()
    {
        $sql = '';
        $sql .= $this->header(); // 备份文件头部说明
        $sql .= $this->dbSql(); // 数据库创建语句
        
        $tables = $this->model->getTables(); // 获取所有表
        foreach ($tables as $table) { // 表结构及数据
            $sql .= $this->tableSql($table); // 表结构信息
            $fields = $this->model->getFields($table); // 表字段
            $field_num = $this->model->getFieldNum($table); // 字段数量
            $all_data = $this->model->getAll($table); // 读取全部数据
            if ($all_data) {
                $sql .= $this->dataSql($table, $fields, $field_num, $all_data); // 生成数据语句
            }
            $sql .= '-- --------------------------------------------------------' . PHP_EOL . PHP_EOL;
        }
        // 写入文件
        $filename = get_uniqid() . '_' . date('YmdHis') . '_' . $this->dbauth['dbname'] . '.sql';
        return $this->writeFile($filename, $sql);
    }

    // 插入数据库备份基础信息
    private function header()
    {
        $sql = '-- Online Database Management SQL Dump' . PHP_EOL;
        $sql .= '-- 数据库名: ' . $this->dbauth['dbname'] . PHP_EOL;
        $sql .= '-- 生成日期: ' . date('Y-m-d H:i:s') . PHP_EOL;
        $sql .= '-- PHP 版本: ' . phpversion() . PHP_EOL . PHP_EOL;
        
        $sql .= 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";' . PHP_EOL;
        $sql .= 'SET time_zone = "+08:00";' . PHP_EOL;
        $sql .= 'SET NAMES utf8;' . PHP_EOL . PHP_EOL;
        
        $sql .= '-- --------------------------------------------------------' . PHP_EOL . PHP_EOL;
        return $sql;
    }

    // 数据库创建语句
    private function dbSql()
    {
        $sql = '';
        $sql .= "--" . PHP_EOL;
        $sql .= "-- 数据库名 `" . $this->dbauth['dbname'] . '`' . PHP_EOL;
        $sql .= "--" . PHP_EOL . PHP_EOL;
        
        // 如果数据库不存在则创建
        $sql .= "CREATE DATABASE IF NOT EXISTS `" . $this->dbauth['dbname'] . '` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;' . PHP_EOL;
        // 选择数据库
        $sql .= "USE `" . $this->dbauth['dbname'] . "`;" . PHP_EOL . PHP_EOL;
        $sql .= '-- --------------------------------------------------------' . PHP_EOL . PHP_EOL;
        return $sql;
    }

    // 表结构语句
    private function tableSql($table)
    {
        $sql = '';
        $sql .= "--" . PHP_EOL;
        $sql .= "-- 表的结构 `" . $table . '`' . PHP_EOL;
        $sql .= "--" . PHP_EOL . PHP_EOL;
        
        $sql .= $this->model->tableStru($table); // 表创建语句
        return $sql;
    }

    // 数据语句
    private function dataSql($table, $fields, $fieldNnum, $data)
    {
        if (! $data)
            return;
        $sql = '';
        $sql .= "--" . PHP_EOL;
        $sql .= "-- 转存表中的数据 `" . $table . "`" . PHP_EOL;
        $sql .= "--" . PHP_EOL;
        $sql .= PHP_EOL;
        
        // 循环每个字段下面的内容
        
        $sql .= "INSERT INTO `" . $table . "` (" . implode(',', $fields) . ") VALUES" . PHP_EOL;
        $brackets = "(";
        foreach ($data as $value) {
            $sql .= $brackets;
            $comma = "";
            for ($i = 0; $i < $fieldNnum; $i ++) {
                $sql .= ($comma . "'" . addslashes(decode_string($value[$i])) . "'");
                $comma = ",";
            }
            $sql .= ")";
            $brackets = "," . PHP_EOL . "(";
        }
        $sql .= ';' . PHP_EOL . PHP_EOL;
        return $sql;
    }

    // 写入文件
    private function writeFile($filename, $content)
    {
        $sqlfile = DOC_PATH . STATIC_DIR . '/backup/sql/' . $filename;
        check_file($sqlfile, true);
        if (file_put_contents($sqlfile, $content)) {
            return true;
        }
    }

    // 获取并检查表名称
    private function getTableList()
    {
        $list = post('list');
        foreach ($list as $key => $value) {
            if (! preg_match('/^[\w]+$/', $value)) {
                unset($list[$key]);
            }
        }
        return $list;
    }
}
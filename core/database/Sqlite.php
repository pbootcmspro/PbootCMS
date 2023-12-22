<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年8月23日
 *  数据库Sqlite驱动  ,写入数据时自动启用事务
 */
namespace core\database;

use core\basic\Config;

class Sqlite implements Builder
{

    protected static $sqlite;

    protected $master;

    protected $slave;

    private $begin = false;
    private function __construct()
    {}

    public function __destruct()
    {
        if ($this->begin) { // 存在待提交的事务时自动进行提交
            $this->master->exec('commit;');
        }
    }

    // 获取单一实例，使用单一实例数据库连接类
    public static function getInstance()
    {
        if (! self::$sqlite) {
            self::$sqlite = new self();
        }
        return self::$sqlite;
    }

    // 连接数据库，接受数据库连接参数，返回数据库连接对象
    public function conn($cfg)
    {
        if (extension_loaded('SQLite3')) {
            try {
                $conn = new \SQLite3($cfg);
                $conn->busyTimeout(15 * 1000); // 设置繁忙延迟时间
            } catch (\Exception $e) {
                error("读取数据库文件失败：" . iconv('gbk', 'utf-8', $e->getMessage()));
            }
        } else {
            if (extension_loaded('pdo_sqlite')) {
                error('未检测到您服务器环境的sqlite3数据库扩展，请检查php.ini中是否已经开启该扩展！<br>另外，检测到您服务器支持pdo_sqlite扩展，您也可以修改数据库配置连接驱动为pdo_sqlite试试！');
            } else {
                error('未检测到您服务器环境的sqlite3数据库扩展，请检查php.ini中是否已经开启该扩展！');
            }
        }
        return $conn;
    }

    // 执行SQL语句,接受完整SQL语句，返回结果集对象
    public function query($sql, $type = 'master')
    {
        $time_s = microtime(true);
        if (! $this->master || ! $this->slave) {
            $cfg = ROOT_PATH . Config::get('database.dbname');
            $conn = $this->conn($cfg);
            $this->master = $conn;
            $this->slave = $conn;
        }
        switch ($type) {
            case 'master':
                if (! $this->begin) { // 存在写入时自动开启显式事务，提高写入性能
                    $this->master->exec('begin;');
                    $this->begin = true;
                }
                $result = $this->master->exec($sql) or $this->error($sql, 'master');
                break;
            case 'slave':
                $result = $this->slave->query($sql) or $this->error($sql, 'slave');
                break;
        }
        return $result;
    }

    // 数据是否存在模型，接受完整SQL语句，返回boolean数据
    public function isExist($sql)
    {
        $result = $this->query($sql, 'slave');
        if ($result->fetchArray()) {
            $result->finalize();
            return true;
        } else {
            return false;
        }
    }

    // 获取记录总量模型，接受数据库表名，返回int数据
    public function rows($table)
    {
        $sql = "SELECT count(*) FROM $table";
        $result = $this->query($sql, 'slave');
        if (! ! $row = $result->fetchArray(2)) {
            $result->finalize();
            return $row[0];
        } else {
            return 0;
        }
    }

    // 读取字段数量模型，接受数据库表名，返回int数据
    public function fields($table)
    {
        $sql = "SELECT * FROM $table LIMIT 1";
        $result = $this->query($sql, 'slave');
        if ($result) {
            return $result->numColumns();
        } else {
            return false;
        }
    }

    /**
     * 获取表字段,接受数据库表名，返回表字段数组
     *
     * @param $table 表名
     */
    public function tableFields($table)
    {
        $sql = "pragma table_info($table)";
        $result = $this->query($sql, 'slave');
        $rows = array();
        while (! ! $row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row['name'];
        }
        $result->finalize();
        return $rows;
    }

    // 查询一条数据模型，接受完整SQL语句，有数据返回对象数组，否则空数组
    public function one($sql, $type = null)
    {
        if (! $type) {
            $my_type = SQLITE3_ASSOC;
        } else {
            $my_type = $type;
        }
        $row = array();
        $result = $this->query($sql, 'slave');
        if (! ! $row = $result->fetchArray($my_type)) {
            if (! $type && $row) {
                $out = new \stdClass();
                foreach ($row as $key => $value) {
                    $out->$key = $value;
                }
                $row = $out;
            }
            $result->finalize();
        }
        return $row;
    }

    // 查询多条数据模型，接受完整SQL语句，有数据返回二维对象数组，否则空数组
    public function all($sql, $type = null)
    {
        if (! $type) {
            $my_type = SQLITE3_ASSOC;
        } else {
            $my_type = $type;
        }
        $result = $this->query($sql, 'slave');
        $rows = array();
        while (! ! $row = $result->fetchArray($my_type)) {
            if (! $type && $row) {
                $out = new \stdClass();
                foreach ($row as $key => $value) {
                    $out->$key = $value;
                }
                $row = $out;
            }
            $rows[] = $row;
        }
        $result->finalize();
        return $rows;
    }

    // 数据增、删、改模型，接受完整SQL语句，返回影响的行数的int数据
    public function amd($sql)
    {
        $result = $this->query($sql, 'master');
        if ($result) {
            return $result;
        } else {
            return 0;
        }
    }

    // 最近一次插入数据的自增字段值，返回int数据
    public function insertId()
    {
        return $this->master->lastInsertRowID();
    }

    // 执行多条SQL模型，成功返回true,否则false
    public function multi($sql)
    {
        $sqls = explode(';', $sql);
        foreach ($sqls as $key => $value) {
            $result = $this->query($value, 'master');
        }
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    // 显示执行错误
    protected function error($sql, $conn)
    {
        $err = '错误：' . $this->$conn->lastErrorMsg();
        if ($this->begin) { // 存在显式开启事务时进行回滚
            $this->master->exec('rollback;');
            $this->begin = false;
        }
        // error('执行SQL发生错误！' . $err . '语句：' . $sql);
        error('执行SQL发生错误！' . $err);
    }
}
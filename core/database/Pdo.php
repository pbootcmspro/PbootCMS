<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年8月30日
 *  数据库PDO驱动
 */
namespace core\database;

use core\basic\Config;

class Pdo implements Builder
{

    protected static $pdo;

    protected $master;

    protected $slave;

    protected $begin = false;

    private function __construct()
    {}

    public function __destruct()
    {
        if ($this->begin) { // 存在待提交的事务时自动进行提交
            $this->commit();
        }
    }

    // 获取单一实例，使用单一实例数据库连接类
    public static function getInstance()
    {
        if (! self::$pdo) {
            self::$pdo = new self();
        }
        return self::$pdo;
    }

    // 连接数据库，接受数据库连接参数，返回数据库连接对象
    public function conn($cfg)
    {
        if (get_db_type() == 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            if (extension_loaded('SQLite3')) {
                error('未检测到您服务器环境的pdo_sqlite数据库扩展，请检查php.ini中是否已经开启该扩展！<br>另外，检测到您服务器支持sqlite3扩展，您也可以修改数据库配置连接驱动为sqlite试试！');
            } else {
                error('未检测到您服务器环境的pdo_sqlite数据库扩展，请检查php.ini中是否已经开启对应的数据库扩展！');
            }
        } elseif (get_db_type() == 'mysql' && ! extension_loaded('pdo_mysql')) {
            if (extension_loaded('mysqli')) {
                error('未检测到您服务器环境的pdo_mysqli数据库扩展，请检查php.ini中是否已经开启该扩展！<br>另外，检测到您服务器支持mysqli扩展，您也可以修改数据库配置连接驱动为mysqli试试！');
            } else {
                error('未检测到您服务器环境的pdo_mysqli数据库扩展，请检查php.ini中是否已经开启对应的数据库扩展！');
            }
        }
        
        $charset = Config::get('database.charset') ?: 'utf8';
        switch (Config::get('database.type')) {
            case 'pdo_mysql':
                $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $charset;
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接MySQL数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            case 'pdo_sqlite':
                $dsn = 'sqlite:' . ROOT_PATH . $cfg['dbname'];
                try {
                    $conn = new \PDO($dsn);
                } catch (\PDOException $e) {
                    error('PDO方式连接Sqlite数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            case 'pdo_pgsql':
                $dsn = 'pgsql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'];
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接Pgsql数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            default:
                $dsn = Config::get('database.dsn');
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
        }
        return $conn;
    }

    // 关闭自动提交，开启事务模式
    public function begin()
    {
        $this->master->beginTransaction();
        $this->begin = true;
    }

    // 提交事务
    public function commit()
    {
        $this->master->commit();
        $this->begin = false;
    }

    // 执行SQL语句,接受完整SQL语句，返回结果集对象
    public function query($sql, $type = 'master')
    {
        $time_s = microtime(true);
        switch ($type) {
            case 'master':
                if (! $this->master) {
                    $cfg = Config::get('database');
                    $this->master = $this->conn($cfg);
                    if ($cfg['type'] == 'pdo_mysql') {
                        $this->master->exec("SET sql_mode='NO_ENGINE_SUBSTITUTION'"); // MySql写入规避严格模式
                    }
                }
                
                // sqlite时自动启动事务
                if ($cfg['type'] == 'pdo_sqlite' && ! $this->begin) {
                    $this->begin();
                } elseif ($cfg['type'] == 'pdo_mysql' && Config::get('database.transaction') && ! $this->begin) { // 根据配置开启mysql事务，注意需要是InnoDB引擎
                    $this->begin();
                }
                
                $result = $this->master->query($sql);
                if ($result === false) {
                    $this->error($sql, 'master');
                }
                break;
            case 'slave':
                if (! $this->slave) {
                    // 未设置从服务器时直接读取主数据库配置
                    if (! $cfg = Config::get('database.slave')) {
                        $cfg = Config::get('database');
                    } else {
                        // 随机选择从数据库
                        if (is_multi_array($cfg)) {
                            $count = count($cfg);
                            $cfg = $cfg['slave' . mt_rand(1, $count)];
                        }
                    }
                    $this->slave = $this->conn($cfg);
                }
                $result = $this->slave->query($sql) or $this->error($sql, 'slave');
                break;
        }
        return $result;
    }

    // 数据是否存在模型，接受完整SQL语句，返回boolean数据
    public function isExist($sql)
    {
        $result = $this->query($sql, 'slave');
        if ($result->fetch()) {
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
        if (! ! $row = $result->fetch(\PDO::FETCH_NUM)) {
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
            return $result->columnCount();
        } else {
            return false;
        }
    }

    /**
     * 获取表信息,接受数据库表名，返回表字段信息数组
     *
     * @param $table 表名
     */
    public function tableFields($table)
    {
        $rows = array();
        switch (Config::get('database.type')) {
            case 'pdo_mysql':
                $sql = "describe $table";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->Field;
                }
                break;
            case 'pdo_sqlite':
                $sql = "pragma table_info($table)";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->name;
                }
                break;
            case 'pdo_pgsql':
                $sql = "SELECT column_name FROM information_schema.columns WHERE table_name ='$table'";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->column_name;
                }
                break;
            default:
                return array();
        }
        return $rows;
    }

    /**
     * 查询一条数据模型，接受完整SQL语句，有数据返回对象数组，否则空数组
     * @$type 可以是MYSQLI_ASSOC(FETCH_ASSOC) ,MYSQLI_NUM(FETCH_NUM) ,MYSQLI_BOTH(FETCH_BOTH),不设置则返回对象模式
     */
    public function one($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $row = array();
        if ($type) {
            $type ++; // 与mysqli统一返回类型设置
            $row = $result->fetch($type);
        } else {
            $row = $result->fetchObject();
        }
        return $row;
    }

    /**
     * 查询多条数据模型，接受完整SQL语句，有数据返回二维对象数组，否则空数组
     * @$type 可以是MYSQLI_ASSOC(FETCH_ASSOC) ,MYSQLI_NUM(FETCH_NUM) ,MYSQLI_BOTH(FETCH_BOTH),不设置则返回对象模式
     */
    public function all($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $rows = array();
        if ($type) {
            $type ++; // 与mysqli统一返回类型设置
            $rows = $result->fetchAll($type);
        } else {
            while (! ! $row = $result->fetchObject()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    // 数据增、删、改模型，接受完整SQL语句，返回影响的行数的int数据
    public function amd($sql)
    {
        $result = $this->query($sql, 'master');
        if ($result > 0) {
            return $result;
        } else {
            return 0;
        }
    }

    // 最近一次插入数据的自增字段值，返回int数据
    public function insertId()
    {
        return $this->master->lastInsertId();
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
        $errs = $this->$conn->errorInfo();
        $err = '错误：' . $errs[2];
        if (preg_match('/XPATH/i', $err)) {
            $err = '';
        }
        if ($this->begin) { // 如果是事务模式，发生错误，则回滚
            $this->$conn->rollBack();
            $this->begin = false;
        }
        // error('执行SQL发生错误！' . $err . '语句：' . $sql);
        error('执行SQL发生错误！' . $err);
    }

    //返回对象结果集
    public function fetchQuery($obj){
        return $obj->fetchAll();
    }
}
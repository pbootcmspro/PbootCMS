<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  应用开发继承模型类
 */

namespace core\basic;

use core\database\Mysqli;
use core\database\Pdo;
use core\database\Sqlite;
use core\view\Paging;

class Model
{

    // 数据库表名
    public $table;

    // 表主键
    public $pk = 'id';

    // 是否自动设置时间戳
    public $autoTimestamp = false;

    // 是否数字时间戳格式
    public $intTimeFormat = false;

    // 更新时间字段名
    public $updateTimeField = 'update_time';

    // 创建时间字段名
    public $createTimeField = 'create_time';

    // 程序执行的SQL语句记录
    private $exeSql = array();

    // 是否解密转义
    private $decode = false;

    // 查询语句构建
    private $sql = array();

    // 直接显示SQL语句
    private $showSql = false;

    // 直接显示结果
    private $showRs = false;

    // 数据库驱动对象
    private $dbDriver;

    // 查询语句
    private $selectSql = "SELECT %distinct% %field% FROM %table% %join% %where% %group% %having% %order% %union% %limit%";

    // 计数语句
    private $countSql = "SELECT COUNT(*) AS sum FROM %table% %join% %where% %group% %having% %union%";

    private $countSql2 = "SELECT %distinct% %field% FROM %table% %join% %where% %group% %having% %union%";

    // 插入语句
    private $insertSql = "INSERT INTO %table% %field% VALUES %value%";

    // 多条插入语句
    private $insertMultSql = "INSERT INTO %table% %field% %value%";

    // 复制插入语句
    private $insertFromSql = "INSERT INTO %table% %field% %from%";

    // 删除语句
    private $deleteSql = "DELETE FROM %table% %join% %where%";

    // 更新语句
    private $updateSql = "UPDATE %table% SET %value% %join% %where%";

    // 查询索引
    private $checkIndex = "show index from %table%";

    private $replaceSql = 'REPLACE INTO %table% %field% values %value%';

    // 自动表名
    public function __construct()
    {
        if (!$this->table) {
            $table_name = Config::get('database.prefix') . hump_to_underline(str_replace('Model', '', basename(get_called_class())));
            $this->table = $table_name;
        }
    }

    // 对象方式动态调用数据库操作方法
    public function __call($methed, $args)
    {
        if (method_exists($this->getDb(), $methed)) {
            $result = call_user_func_array(array(
                $this->getDb(),
                $methed
            ), $args);
            return $result;
        } else {
            error('不存在数据库操作方法“' . $methed . '”，请核对再试！');
        }
    }

    // 获取数据库连接对象
    private function getDb()
    {
        if (!$this->dbDriver) {
            $type = Config::get('database.type');
            switch ($type) {
                case 'mysqli': // 使用mysqli连接数据库
                    $this->dbDriver = Mysqli::getInstance();
                    break;
                case 'sqlite': // 使用sqlite连接数据库
                    $this->dbDriver = Sqlite::getInstance();
                    break;
                default: // 默认使用PDO连接数据库
                    $this->dbDriver = Pdo::getInstance();
            }
        }
        return $this->dbDriver;
    }

    // 执行SQL构造替换
    private function buildSql($sql, $clear = true)
    {
        preg_match_all('/\%([\w]+)\%/', $sql, $matches);
        foreach ($matches[1] as $key => $value) {
            if (isset($this->sql[$value]) && $this->sql[$value]) {
                $sql = str_replace("%$value%", $this->sql[$value], $sql);
            } else {
                if ($value == 'table') {
                    $sql = str_replace("%$value%", $this->table, $sql);
                } else {
                    $sql = str_replace("%$value%", '', $sql);
                }
            }
        }

        $this->exeSql[] = $sql;
        if ($clear) {
            $this->pk = 'id';
            $this->autoTimestamp = false;
            $this->intTimeFormat = false;
            $this->updateTimeField = 'update_time';
            $this->createTimeField = 'create_time';
            $this->sql = array();
        }

        if ($this->showSql && $clear) {
            exit($sql);
        } else {
            return $sql;
        }
    }

    /**
     * 关闭自动提交，开启事务模式（非连贯，直接调用）
     */
    final public function begin()
    {
        $this->getDb()->begin();
    }

    /**
     * 提交事务（非连贯，直接调用）
     *
     * @return \core\basic\Model
     */
    final public function commit()
    {
        $this->getDb()->commit();
    }

    /**
     * 内容输出
     *
     * @param mixed $data
     * @return mixed
     */
    final protected function outData($result)
    {
        if ($this->decode) {
            $result = decode_string($result);
            $this->decode = false;
        } else {
            $result = decode_slashes($result);
        }
        if ($this->showRs) {
            print_r($result);
            exit();
        } else {
            return $result;
        }
    }

    /**
     * 连贯操作：是否解码转义数据
     */
    final public function decode($flag = true)
    {
        if ($flag === true)
            $this->decode = true;
        return $this;
    }

    /**
     * 连贯操作：设置返回SQL语句，不真正执行,优先级高于showRS()
     *
     * @param string $flag
     *            调用默认为true
     * @return \core\basic\Model
     */
    final public function showSql($flag = true)
    {
        if ($flag === true)
            $this->showSql = true;
        return $this;
    }

    /**
     * 连贯操作：设置显示结果到页面
     *
     * @param string $flag
     *            调用默认为true
     * @return \core\basic\Model
     */
    final public function showRs($flag = true)
    {
        if ($flag === true)
            $this->showRs = true;
        return $this;
    }

    /**
     * 连贯操作：是否自动插入时间
     *
     * @param string $flag
     * @return \core\basic\Model
     */
    final public function autoTime($flag = true)
    {
        if ($flag === true)
            $this->sql['auto_time'] = true;
        return $this;
    }

    /**
     * 连贯操作：设置查询表全名
     *
     * @param mixed $table
     *            可以是字符串、数组,
     *            字符串：如"ay_user as a",
     *            如传递多个表：array('ay_user','ay_role'),
     *            传递多个表并设置别名：array('ay_user'=>'u','ay_role'=>'r')
     * @return \core\basic\Model
     */
    final public function table($table)
    {
        if (is_array($table)) {
            $table_string = '';
            foreach ($table as $key => $value) {
                if (is_int($key)) {
                    $table_string .= '`' . $value . '`,';
                } else {
                    $table_string .= '`' . $key . '` AS ' . $value . ',';
                }
            }
            $this->sql['table'] = substr($table_string, 0, -1);
        } else {
            $this->sql['table'] = $table;
        }
        return $this;
    }

    /**
     * 连贯操作：设置查询表名
     *
     * @param mixed $table
     *            可以是字符串、数组,
     *            字符串：如"user as a",
     *            如传递多个表：array('user','role'),
     *            传递多个表并设置别名：array('user'=>'u','role'=>'r')
     * @return \core\basic\Model
     */
    final public function name($table)
    {
        $prefix = Config::get('database.prefix');
        if (is_array($table)) {
            $table_string = '';
            foreach ($table as $key => $value) {
                if (is_int($key)) {
                    $table_string .= '`' . $prefix . $value . '`,';
                } else {
                    $table_string .= '`' . $prefix . $key . '` AS ' . $value . ',';
                }
            }
            $this->table = substr($table_string, 0, -1);
        } else {
            $this->table = $prefix . $table;
        }
        return $this;
    }

    /**
     * 连贯操作：设置数据库别名
     *
     * @param string $alias
     *            设置的别名名字，接收字符串，如：a
     * @return \core\basic\Model
     */
    final public function alias($alias)
    {
        if ($alias) {
            if (!isset($this->table))
                error('调用alias之前必须先设置table');
            if (strpos($this->table, ' AS ') === false) {
                $this->table = $this->table . ' AS ' . $alias;
            }
        }
        return $this;
    }

    /**
     * 连贯操作：设置返回唯一不同的值
     *
     * @param string $flag
     *            调用时默认为true,如果传递false则不使用
     * @return \core\basic\Model
     */
    final public function distinct($flag = true)
    {
        if ($flag === true)
            $this->sql['distinct'] = 'DISTINCT';
        return $this;
    }

    /**
     * 连贯操作：设置字段
     *
     * @param mixed $field
     *            可以为字符串、数组,
     *            如字符串："name,password as pw",
     *            数组设置字段：array('name','password')，如果为非数字数组则设置别名,
     *            数组设置字段并设置别名：array('username'=>'name','password'=>'pw')
     * @return \core\basic\Model
     */
    final public function field($field)
    {
        if (is_array($field)) {
            $field_string = '';
            foreach ($field as $key => $value) {
                if (is_int($key)) {
                    $field_string .= $value . ',';
                } else {
                    $field_string .= $key . ' AS ' . $value . ',';
                }
            }
            $this->sql['field'] = substr($field_string, 0, -1);
        } elseif ($field) {
            $this->sql['field'] = $field;
        }
        return $this;
    }

    /**
     * 连贯操作：设置查询条件
     *
     * @param mixed $where
     *            设置条件，可以为字符串、数组,
     *            字符串模式：如"id<1","name like %1",
     *            数组模式：array('username'=>'xie',"realname like '%谢%'")
     * @param string $connect
     *            调用本方法时与前面条件使用AND连接，$where参数数组内部的条件默认使用AND连接
     * @return \core\basic\Model
     */

    /**
     * 连贯操作：设置查询条件
     *
     * @param mixed $where
     *            设置条件，可以为字符串、数组,
     *            字符串模式：如"id<1","name like %1",
     *            数组模式：array('username'=>'xie',"realname like '%谢%'")
     * @param string $inConnect
     *            调用本方法时$where参数数组内部的条件默认使用AND连接
     * @param string $outConnect
     *            调用本方法时与前面条件使用AND连接
     * @param boolean $fuzzy
     *            条件是否为模糊匹配，即in匹配
     * @return \core\basic\Model
     */
    final public function where($where, $inConnect = 'AND', $outConnect = 'AND', $fuzzy = false)
    {
        if (!$where) {
            return $this;
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= ' ' . $outConnect . '(';
        } else {
            $this->sql['where'] = 'WHERE(';
        }
        if (is_array($where)) {
            $where_string = '';
            $flag = false;
            foreach ($where as $key => $value) {
                if ($flag) { // 条件之间内部AND连接
                    $where_string .= ' ' . $inConnect . ' ';
                } else {
                    $flag = true;
                }
                if (!is_int($key)) {
                    if ($fuzzy) {
                        $where_string .= $key . " like '%" . $value . "%' ";
                    } else {
                        $where_string .= $key . "='" . $value . "' ";
                    }
                } else {
                    $where_string .= $value;
                }
            }
            $this->sql['where'] .= $where_string . ')';
        } else {
            $this->sql['where'] .= $where . ')';
        }
        return $this;
    }

    /**
     * 连贯操作：设置EXISTS查询
     *
     * @param string $subsql
     *            传递子查询
     * @return \core\basic\Model
     */
    final public function exists($subSql)
    {
        if (is_callable($subSql)) { // 闭包子查询
            $subSql = $subSql(new self());
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND EXISTS ($subSql)";
        } else {
            $this->sql['where'] = "WHERE EXISTS ($subSql)";
        }
        return $this;
    }

    /**
     * 连贯操作：设置NOT EXISTS查询
     *
     * @param string $subSql
     *            传递子查询
     * @return \core\basic\Model
     */
    final public function notExists($subSql)
    {
        if (is_callable($subSql)) { // 闭包子查询
            $subSql = $subSql(new self());
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND NOT EXISTS ($subSql)";
        } else {
            $this->sql['where'] = "WHERE NOT EXISTS ($subSql)";
        }
        return $this;
    }

    /**
     * 连贯操作：设置IN查询
     *
     * @param string $field
     *            传递需要比对的字段，如: 'username'
     * @param mixed $range
     *            字符串、数组、子查询,如：'1,2,3',array(1,2,3);
     * @return \core\basic\Model
     */
    final public function in($field, $range)
    {
        if (!$field)
            return $this;
        if (is_array($range)) {
            if (count($range) == 1) { // 单只有一个值时使用直接使用等于，提高读取性能
                $in_string = "$field='$range[0]'";
            } else {
                $in_string = "$field IN (" . implode_quot(',', $range) . ")";
            }
        } else {
            if (preg_match('/,/', $range)) {
                $in_string = "$field IN (" . implode_quot(',', explode(',', $range)) . ")";
            } else { // 传递单个字符串时直接相等处理
                $in_string = "$field = '$range'";
            }
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND $in_string";
        } else {
            $this->sql['where'] = "WHERE $in_string";
        }
        return $this;
    }

    /**
     * 连贯操作：设置NOT IN查询
     *
     * @param string $field
     *            传递需要比对的字段，如: id NOT IN (1,2,3)
     * @param mixed $range
     *            字符串、数组、子查询
     * @return \core\basic\Model
     */
    final public function notIn($field, $range)
    {
        if (!$field)
            return $this;
        if (is_array($range)) {
            $in_string = implode_quot(',', $range);
        } else {
            if (preg_match('/,/', $range)) {
                $in_string = implode_quot(',', explode(',', $range));
            } else {
                $in_string = "'$range'";
            }
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND $field NOT IN ($in_string)";
        } else {
            $this->sql['where'] = "WHERE $field NOT IN ($in_string)";
        }
        return $this;
    }

    /**
     * 连贯操作：设置关键字条件匹配
     *
     * @param string $field
     *            字段名,支持数组传递多个字段或多个字段用逗号隔开
     * @param string $keyword
     *            匹配关键字
     * @param string $matchType
     *            匹配模式，默认为all,可选left,right,equal
     * @return \core\database\Operate
     */
    final public function like($field, $keyword, $matchType = "all")
    {
        if (!$field)
            return $this;
        switch ($matchType) {
            case 'left':
                $keyword = "$keyword%";
                break;
            case 'right':
                $keyword = "%$keyword";
                break;
            case 'equal':
            case '==':
                $keyword = "$keyword";
                break;
            default:
                $keyword = "%$keyword%";
        }
        if (is_array($field) || preg_match('/,/', $field)) {
            if (!is_array($field)) {
                $field = explode(',', $field);
            }
            foreach ($field as $value) {
                if (isset($sqlStr)) {
                    $sqlStr .= " OR $value LIKE '$keyword'";
                } else {
                    $sqlStr = "$value LIKE '$keyword'";
                }
            }
        } else {
            $sqlStr = "$field LIKE '$keyword'";
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND ($sqlStr)";
        } else {
            $this->sql['where'] = "WHERE ($sqlStr)";
        }
        return $this;
    }

    /**
     * 连贯操作：设置关键字条件不匹配
     *
     * @param string $field
     *            字段名
     * @param string $keyword
     *            匹配关键字
     * @param string $matchType
     *            匹配模式，默认为all,可选left,right
     * @return \core\database\Operate
     */
    final public function notLike($field, $keyword, $matchType = "all")
    {
        if (!$field)
            return $this;
        switch ($matchType) {
            case 'left':
                $keyword = "$keyword%";
                break;
            case 'right':
                $keyword = "%$keyword";
                break;
            case 'equal':
            case '==':
                $keyword = "$keyword";
                break;
            default:
                $keyword = "%$keyword%";
        }
        if (is_array($field) || preg_match('/,/', $field)) {
            if (!is_array($field)) {
                $field = explode(',', $field);
            }
            foreach ($field as $value) {
                if (isset($sqlStr)) {
                    $sqlStr .= " AND $value NOT LIKE '$keyword'";
                } else {
                    $sqlStr = "$value NOT LIKE '$keyword'";
                }
            }
        } else {
            $sqlStr = "$field NOT LIKE '$keyword'";
        }
        if (isset($this->sql['where']) && $this->sql['where']) {
            $this->sql['where'] .= " AND ($sqlStr)";
        } else {
            $this->sql['where'] = "WHERE ($sqlStr)";
        }
        return $this;
    }

    /**
     * 连贯操作：设置查询排序
     *
     * @param mixed $order
     *            可以为字符串、数组,
     *            字符串模式：如"id DESC,name",
     *            数组模式：array('id'=>'DESC','name')
     * @return \core\basic\Model
     */
    final public function order($order)
    {
        if (is_array($order)) {
            $order_string = 'ORDER BY ';
            foreach ($order as $key => $value) {
                if (is_int($key)) {
                    $order_string .= $value . ',';
                } else {
                    $order_string .= $key . ' ' . $value . ',';
                }
            }
            $this->sql['order'] = substr($order_string, 0, -1);
        } else {
            $this->sql['order'] = 'ORDER BY ' . $order;
        }
        return $this;
    }

    /**
     * 连贯操作：设置查询数量
     *
     * @param string $limit
     *            设置限制语句，可接受：
     *            单个参数数，如 1,条数
     *            单个参数字符串，如"1,10"
     *            两个参数数字，如：1,10
     *            注意：当使用了分页时会无效
     * @return \core\basic\Model
     */
    final public function limit($limit)
    {
        $var_num = func_num_args();
        if ($var_num > 1 || preg_match('/,/', $limit)) {
            if ($var_num > 1) {
                $var_arr = func_get_args();
            } else {
                $var_arr = explode(',', $limit);
            }
            switch (get_db_type()) {
                case 'mysql':
                    $this->sql['limit'] = 'LIMIT ' . $var_arr[0] . ',' . $var_arr[1];
                    break;
                case 'sqlite':
                    $this->sql['limit'] = 'LIMIT ' . $var_arr[1] . ' OFFSET ' . $var_arr[0];
                    break;
                case 'pgsql':
                    $this->sql['limit'] = 'LIMIT ' . $var_arr[1] . ' OFFSET ' . $var_arr[0];
                    break;
            }
        } else {
            $this->sql['limit'] = 'LIMIT ' . $limit;
        }
        return $this;
    }

    /**
     * 连贯操作：设置分组查询
     *
     * @param mixed $group
     *            可以传递字符串、数字数组，如"name,sex",array('name','sex')
     * @return \core\basic\Model
     */
    final public function group($group)
    {
        if (is_array($group)) {
            $group_string = 'GROUP BY ';
            foreach ($group as $key => $value) {
                $group_string .= $value . ',';
            }
            $this->sql['group'] = substr($group_string, 0, -1);
        } else {
            $this->sql['group'] = 'GROUP BY ' . $group;
        }
        return $this;
    }

    /**
     * 连贯操作：设置查询条件having
     * 在 SQL 中增加 HAVING 子句原因是，WHERE 关键字无法与合计函数一起使用。
     *
     * @param string $having
     *            传入字符串，需要完整having语句
     * @return \core\basic\Model
     */
    final public function having($having, $inConnect = 'AND', $outConnect = 'AND')
    {
        if (isset($this->sql['having']) && $this->sql['having']) {
            $this->sql['having'] .= ' ' . $outConnect . '(';
        } else {
            $this->sql['having'] = 'HAVING(';
        }
        if (is_array($having)) {
            $having_string = '';
            $flag = false;
            foreach ($having as $key => $value) {
                if ($flag) { // 条件之间内部AND连接
                    $having_string .= ' ' . $inConnect . ' ';
                } else {
                    $flag = true;
                }
                if (!is_int($key)) {
                    $having_string .= $key . "='" . $value . "' ";
                } else {
                    $having_string .= $value;
                }
            }
            $this->sql['having'] .= $having_string . ')';
        } else {
            $this->sql['having'] .= $having . ')';
        }
        return $this;
    }

    /**
     * 连贯操作：设置连接查询
     *
     * @param array $join
     *            可以为一维或二维数组，array('table','a.id=b.id','LEFT'),
     *            array第一个参数为数据表，第二个参数为ON条件，第三个参数为类型,
     *            二维模式：array(
     *            array('table b','a.id=b.aid','LEFT'),
     *            array('table c','a.id=c.aid','LEFT')
     *            )
     * @return \core\basic\Model
     */
    final public function join(array $join)
    {
        if (count($join) == count($join, 1)) {
            $join_string = '';
            if (isset($join[2])) {
                $join_string .= ' ' . $join[2] . ' JOIN ';
            } else {
                $join_string .= ' LEFT JOIN ';
            }
            $join_string .= $join[0] . ' ON ' . $join[1];
            if (isset($this->sql['join'])) {
                $this->sql['join'] .= $join_string;
            } else {
                $this->sql['join'] = $join_string;
            }
        } else {
            foreach ($join as $key => $value) {
                $this->join($value);
            }
        }
        return $this;
    }

    /**
     * 连贯操作：合并两个或多个 SELECT 语句的结果集
     *
     * @param array $subSql
     *            子查询可以使用数字数组传递多个
     * @param string $isAll
     *            是否UNION ALL
     * @return \core\basic\Model
     */
    final public function union($subSql, $isAll = false)
    {
        if (!isset($this->sql['union'])) {
            $this->sql['union'] = '';
        }
        if (is_callable($subSql)) { // 闭包子查询
            $subSql = $subSql(new self());
        }
        if (is_array($subSql)) {
            foreach ($subSql as $key => $value) {
                $this->union($value, $isAll);
            }
        } else {
            if ($isAll) {
                $this->sql['union'] .= " UNION ALL($subSql)";
            } else {
                $this->sql['union'] .= " UNION ($subSql)";
            }
        }
        return $this;
    }

    /**
     * 连贯操作：数据分页
     * 传递一个参数时为页数，传递两个参数时第二个为分页大小
     *
     * @return \core\basic\Model
     */
    final public function page($args = 1)
    {
        if ($args === false) {
            $this->sql['paging'] = false;
            return $this;
        }
        $this->sql['paging'] = true;
        $paging = Paging::getInstance();
        $var_num = func_num_args();
        if ($var_num > 1 || preg_match('/,/', $args)) {
            if ($var_num > 1) {
                $var_arr = func_get_args();
            } else {
                $var_arr = explode(',', $args);
            }
            $paging->page = $var_arr[0];
            $paging->pageSize = $var_arr[1];
            if (isset($var_arr[2])) {
                $paging->start = $var_arr[2];
            }
        } else {
            $paging->page = $args;
        }
        return $this;
    }

    /**
     * 连贯操作：待插入或更新数据数组，分解insert、update函数，实现 table($table)->data($data)->insert();
     *
     * @param array $data
     * @return \core\basic\Model
     */
    final public function data($data)
    {
        $this->sql['data'] = $data;
        return $this;
    }

    /**
     * 连贯操作：生成关联插入数据,如 用户对应多个角色，relation($ucode,$rcodes)
     *
     * @param string $field
     *            第一个字段的值，如用户编码
     * @param array $array
     *            第二个字段的值数组，如用户所属多个角色数组
     * @return \core\basic\Model
     */
    final public function relation($field, array $array)
    {
        if ($array) {
            foreach ($array as $value) {
                $data[] = array(
                    $field,
                    $value
                );
            }
            $this->sql['data'] = $data;
        }
        return $this;
    }

    /**
     * 连贯操作：用于从一个表复制信息到另一个表 ，实现INSERT INTO SELECT的功能
     *
     * @param string $subSql
     */
    final public function from($subSql)
    {
        if (is_callable($subSql)) { // 闭包子查询
            $subSql = $subSql(new self());
        }
        $this->sql['from'] = $subSql;
        return $this;
    }

    // ********************************数据查询************************************************************

    /**
     * 多条数据查询模型，select方法查询结果不存在，返回空数组
     *
     * @param string $type
     *            可选传递1,2,3返回不同格式数据数组
     * @return array
     */
    final public function select($type = null)
    {
        // 闭包查询
        if (is_callable($type)) {
            $type($this);
            return $this->select();
        }

        if (!isset($this->sql['field']) || !$this->sql['field'])
            $this->sql['field'] = '*';

        // 如果调用了分页函数且分页，则执行分页处理
        if (isset($this->sql['paging']) && $this->sql['paging']) {
            if ($this->sql['group'] || $this->sql['distinct']) { // 解决使用分组时count(*)分页不准问题
                if (get_db_type() == 'mysql') {
                    $this->limit(Paging::getInstance()->quikLimit()); // 分页
                    $this->sql['field'] = 'SQL_CALC_FOUND_ROWS ' . $this->sql['field']; // 添加查询总记录
                    $sql = $this->buildSql($this->selectSql);
                    $result = $this->getDb()->all($sql, $type);
                    $count_sql = "select FOUND_ROWS() as sum";
                    if (!!$rs = $this->getDb()->one($count_sql)) {
                        $total = $rs->sum;
                        // 分页内容
                        $limit = Paging::getInstance()->limit($total, true); // 生成分页代码
                    }
                } else {
                    $count_sql = $this->buildSql($this->countSql2, false);
                    // 获取记录总数
                    if (!!$rs = $this->getDb()->all($count_sql)) {
                        $total = count($rs);
                        // 分页内容
                        $limit = Paging::getInstance()->limit($total, true);
                        // 获取分页参数并设置分页
                        $this->limit($limit);
                    }
                }
            } else {
                // 生成总数计算语句
                $count_sql = $this->buildSql($this->countSql, false);
                // 获取记录总数
                if (!!$rs = $this->getDb()->one($count_sql)) {
                    $total = $rs->sum;
                    // 分页内容
                    $limit = Paging::getInstance()->limit($total, true);
                    // 获取分页参数并设置分页
                    $this->limit($limit);
                }
            }
        }
        // 构建查询语句
        if ($type === false) {
            return $this->buildSql($this->selectSql, false);
        } else {
            $sql = $this->buildSql($this->selectSql);
        }
        if (!isset($result)) {
            $result = $this->getDb()->all($sql, $type);
        }
        return $this->outData($result);
    }

    /**
     * 单条数据查询模型，find 方法查询结果不存在，返回 null
     *
     * @param string $type
     *            可选传递1,2,3返回不同格式数据数组
     * @return string|boolean|string
     */
    final public function find($type = null)
    {
        // 闭包查询
        if (is_callable($type)) {
            $type($this);
            return $this->find();
        }
        if (!isset($this->sql['field']))
            $this->sql['field'] = '*';
        $this->limit(1); // 强制查询一条
        $sql = $this->buildSql($this->selectSql); // 构建语句

        if ($type === false) {
            return $sql;
        }
        $result = $this->getDb()->one($sql, $type);
        return $this->outData($result);
    }

    /**
     * 返回指定字段数据数组
     *
     * @param string $name
     *            字段名字符串或数字数组，单个字段，返回一维数组，如果多个字段，返回二维数组数
     * @param string $key
     *            指定返回数组的键值字段
     * @return array
     */
    final public function column($fields, $key = null)
    {
        // 配置传递的字段
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }

        // 如果传递字段不含指定键则添加
        if ($key && !preg_match('/(.*,|^)(' . $key . ')(,.*|$)/', $fields)) {
            $this->sql['field'] = $key . ',' . $fields;
        } else {
            $this->sql['field'] = $fields;
        }

        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->all($sql, 1);
        $data = array();
        foreach ($result as $vkey => $value) {
            if ($key) {
                $key_value = $value[$key];
                if (is_array($value) && count($value) == 2) {
                    unset($value[$key]);
                    $data[$key_value] = current($value);
                } else {
                    $data[$key_value] = $value;
                }
            } else {
                if (is_array($value) && count($value) == 1) {
                    $data[] = current($value);
                } else {
                    $data[] = $value;
                }
            }
        }
        return $this->outData($data);
    }

    /**
     * 返回指定字段的一条数据的值模式
     *
     * @param string $name
     *            字段名
     * @return string 返回字段值
     */
    final public function value($field)
    {
        $this->sql['field'] = $field;
        $this->limit(1);
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        if (isset($result[0])) {
            return $this->outData($result[0]);
        } else {
            return null;
        }
    }

    /**
     * 返回指定列最大值
     *
     * @param string $name
     *            字段名
     * @return boolean
     */
    final public function max($field)
    {
        $this->sql['field'] = "MAX(`$field`)";
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        return $this->outData($result[0]);
    }

    /**
     * 返回指定列最小值
     *
     * @param string $name
     *            字段名
     * @return boolean
     */
    final public function min($field)
    {
        $this->sql['field'] = "MIN(`$field`)";
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        return $this->outData($result[0]);
    }

    /**
     * 返回指定列平均值
     *
     * @param string $name
     *            字段名
     * @return boolean
     */
    final public function avg($field)
    {
        $this->sql['field'] = "AVG(`$field`)";
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        return $this->outData($result[0]);
    }

    /**
     * 返回指定列合计值
     *
     * @param string $name
     *            字段名
     * @return boolean
     */
    final public function sum($field)
    {
        $this->sql['field'] = "SUM(`$field`)";
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        if ($result[0]) {
            return $this->outData($result[0]);
        } else {
            return 0;
        }
    }

    // 返回统计数据
    final public function count()
    {
        $this->sql['field'] = "COUNT(*)";
        $sql = $this->buildSql($this->selectSql);
        $result = $this->getDb()->one($sql, 2);
        if ($result[0]) {
            return $this->outData($result[0]);
        } else {
            return 0;
        }
    }

    // ******************************数据插入*******************************************************

    /**
     * 数据插入模型
     *
     * @param array $data
     *            可以为一维或二维数组，
     *            一维数组：array('username'=>"xsh",'sex'=>'男'),
     *            二维数组：array(
     *            array('username'=>"xsh",'sex'=>'男'),
     *            array('username'=>"gmx",'sex'=>'女')
     *            )
     * @param boolean $batch
     *            是否启用批量一次插入功能，默认true
     * @return boolean|boolean|array
     */
    final public function insert(array $data = array(), $batch = true)
    {
        // 未传递数据时，使用data函数插入数据
        if (!$data && isset($this->sql['data'])) {
            return $this->insert($this->sql['data']);
        }
        if (is_array($data)) {

            if (!$data)
                return;
            if (count($data) == count($data, 1)) { // 单条数据
                $keys = '';
                $values = '';
                foreach ($data as $key => $value) {
                    $this->checkKey($key);
                    if (!is_numeric($key)) {
                        $keys .= "`" . $key . "`,";
                        $values .= "'" . $value . "',";
                    }
                }
                if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
                    $keys .= "`" . $this->createTimeField . "`,`" . $this->updateTimeField . "`,";
                    if ($this->intTimeFormat) {
                        $values .= "'" . time() . "','" . time() . "',";
                    } else {
                        $values .= "'" . date('Y-m-d H:i:s') . "','" . date('Y-m-d H:i:s') . "',";
                    }
                }
                if ($keys) { // 如果插入数据关联字段,则字段以关联数据为准,否则以设置字段为准
                    $this->sql['field'] = '(' . substr($keys, 0, -1) . ')';
                } elseif (isset($this->sql['field']) && $this->sql['field']) {
                    $this->sql['field'] = "({$this->sql['field']})";
                }
                $this->sql['value'] = "(" . substr($values, 0, -1) . ")";
                $sql = $this->buildSql($this->insertSql);
            } else { // 多条数据
                if ($batch) { // 批量一次性插入
                    $key_string = '';
                    $value_string = '';
                    $flag = false;
                    foreach ($data as $keys => $value) {
                        if (!$flag) {
                            $value_string .= ' SELECT ';
                        } else {
                            $value_string .= ' UNION All SELECT ';
                        }
                        foreach ($value as $key2 => $value2) {
                            // 字段获取只执行一次
                            if (!$flag && !is_numeric($key2)) {
                                $this->checkKey($key2);
                                $key_string .= "`" . $key2 . "`,";
                            }
                            $value_string .= "'" . $value2 . "',";
                        }
                        $flag = true;
                        if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
                            if ($this->intTimeFormat) {
                                $value_string .= "'" . time() . "','" . time() . "',";
                            } else {
                                $value_string .= "'" . date('Y-m-d H:i:s') . "','" . date('Y-m-d H:i:s') . "',";
                            }
                        }
                        $value_string = substr($value_string, 0, -1);
                    }
                    if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
                        $key_string .= "`" . $this->createTimeField . "`,`" . $this->updateTimeField . "`,";
                    }
                    if ($key_string) { // 如果插入数据关联字段,则字段以关联数据为准,否则以设置字段为准
                        $this->sql['field'] = '(' . substr($key_string, 0, -1) . ')';
                    } elseif (isset($this->sql['field']) && $this->sql['field']) {
                        $this->sql['field'] = "({$this->sql['field']})";
                    }
                    $this->sql['value'] = $value_string;
                    $sql = $this->buildSql($this->insertMultSql);
                    // 判断SQL语句是否超过数据库设置
                    if (get_db_type() == 'mysql') {
                        $max_allowed_packet = $this->getDb()->one('SELECT @@global.max_allowed_packet', 2);
                    } else {
                        $max_allowed_packet = 1 * 1024 * 1024; // 其他类型数据库按照1M限制
                    }
                    if (strlen($sql) > $max_allowed_packet) { // 如果要插入的数据过大，则转换为一条条插入
                        return $this->insert($data, false);
                    }
                } else { // 批量一条条插入
                    foreach ($data as $keys => $value) {
                        $result = $this->insert($value);
                    }
                    return $result;
                }
            }
        } elseif ($this->sql['from']) {
            if (isset($this->sql['field']) && $this->sql['field']) { // 表指定字段复制
                $this->sql['field'] = "({$this->sql['field']})";
            }
            $sql = $this->buildSql($this->insertFromSql);
        } else {
            return;
        }

        $sql = preg_replace_r('/pboot:if/i', 'pboot@if', $sql); // 过滤插入cms条件语句
        $sql = preg_replace_r('/pboot:sql/i', 'pboot@sql', $sql); // 过滤插入cms条件语句
        return $this->getDb()->amd($sql);
    }

    /**
     * 插入数据并返回自增ID值
     *
     * @param array $data
     *            可以为一维或二维数组
     * @param boolean $batch
     *            是否启用批量一次插入功能，默认true
     * @return number|string|boolean
     */
    final public function insertGetId(array $data = null, $batch = true)
    {
        if ($this->insert($data, $batch)) {
            return $this->getDb()->insertId();
        } else {
            return false;
        }
    }

    // ******************************数据更新*******************************************************

    /**
     * 数据更新
     *
     * @param array $data
     *            传入字符串、数组
     *            字符串如："username='xsh'"
     *            数组如：array('username'=>'test','sex'=>'女')
     * @return void|boolean|boolean|array
     */
    final public function update($data = null)
    {
        // 未传递数据时使用data函数插入数据
        if (!$data && $this->sql['data']) {
            return $this->update($this->sql['data']);
        }
        $update_string = '';
        if (is_array($data)) {
            if (!$data)
                return;
            foreach ($data as $key => $value) {
                $this->checkKey($key);
                $temp_v_start = substr($value, 0, 2);
                $temp_v_end = substr($value, 2);
                if (is_numeric($temp_v_end) && $temp_v_start == "-=") {
                    $update_string .= "`$key`= $key-$temp_v_end,"; // 自减
                } elseif (is_numeric($temp_v_end) && $temp_v_start == "+=") {
                    $update_string .= "`$key`= $key+$temp_v_end,"; // 自加
                } else {
                    $update_string .= "`$key`='$value',";
                }
            }
            $update_string = substr($update_string, 0, -1);
        } else {
            $update_string = $data;
        }
        if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
            if ($this->intTimeFormat) {
                $update_string .= ",`" . $this->updateTimeField . "`=' " . time() . "'";
            } else {
                $update_string .= ",`" . $this->updateTimeField . "`=' " . date('Y-m-d H:i:s') . "'";
            }
        }
        $this->sql['value'] = $update_string;
        $sql = $this->buildSql($this->updateSql);
        $sql = preg_replace_r('/pboot:if/i', 'pboot@if', $sql); // 过滤插入cms条件语句
        $sql = preg_replace_r('/pboot:sql/i', 'pboot@sql', $sql); // 过滤插入cms条件语句
        return $this->getDb()->amd($sql);
    }

    /**
     * 更新指定字段
     *
     * @param string $field
     *            字段名
     * @param string $value
     *            值
     * @return boolean|boolean|array
     */
    final public function setField($field, $value)
    {
        $this->checkKey($field);
        $this->sql['value'] = "`$field`='$value'";
        if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
            if ($this->intTimeFormat) {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . time() . "'";
            } else {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . date('Y-m-d H:i:s') . "'";
            }
        }
        $sql = $this->buildSql($this->updateSql);
        return $this->getDb()->amd($sql);
    }

    /**
     * 字段自增
     *
     * @param string $field
     *            字段
     * @param number $value
     *            值
     * @return boolean|boolean|array
     */
    final public function setInc($field, $value = 1)
    {
        $this->checkKey($field);
        $this->sql['value'] = " `$field`= $field+$value";
        if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
            if ($this->intTimeFormat) {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . time() . "'";
            } else {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . date('Y-m-d H:i:s') . "'";
            }
        }
        $sql = $this->buildSql($this->updateSql);
        return $this->getDb()->amd($sql);
    }

    /**
     * 字段自减
     *
     * @param string $field
     *            字段
     * @param number $value
     *            值
     * @return boolean|boolean|array
     */
    final public function setDec($field, $value = 1)
    {
        $this->checkKey($field);
        $this->sql['value'] = " `$field`= $field-$value";
        if ($this->autoTimestamp || (isset($this->sql['auto_time']) && $this->sql['auto_time'] == true)) {
            if ($this->intTimeFormat) {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . time() . "'";
            } else {
                $this->sql['value'] .= ",`" . $this->updateTimeField . "`=' " . date('Y-m-d H:i:s') . "'";
            }
        }
        $sql = $this->buildSql($this->updateSql);
        return $this->getDb()->amd($sql);
    }

    // ***************************数据删除*******************************************************

    /**
     * 数据删除
     *
     * @param mixed $data
     *            可以为字符串或数组，如"1,2,3",array(1,2,3)
     * @param string $key
     *            字段名，默认为id
     * @return boolean|boolean|array
     */
    final public function delete($data = null, $key = null)
    {
        if ($data) {
            if (!$key)
                $key = $this->pk;
            $this->checkKey($key);
            if (is_array($data) || preg_match('/,/', $data)) {
                $this->in($key, $data);
            } else {
                $this->where("$key='$data'");
            }
        }
        $sql = $this->buildSql($this->deleteSql);
        return $this->getDb()->amd($sql);
    }

    // 检测key值
    private function checkKey($key)
    {
        if (!$key)
            return;
        if (!preg_match('/^[\w\.\-]+$/', $key)) {
            error('传递的SQL数据中含有非法字符:' . $key);
        }
    }

    //查询索引
    public function checkIndexSql(): array
    {
        $sql = $this->buildSql($this->checkIndex);
        $result = $this->getDb()->query($sql);
        return $this->getDb()->fetchQuery($result);
    }

    /**
     * 获取数组中的键值
     * @param array $data
     * @return array
     */
    public function getKeysFromArray(Array $data = []): array
    {
        $keys = [];
        foreach ($data as $key => $val) {
            $this->checkKey($key);
            $keys[] = $key;
        }
        return $keys;
    }

    /**
     * 批量插入
     * @param array $data
     * @return false|int|mixed|\PDOStatement
     */
    public function replace(array $data = [])
    {
        $columns = $this->getKeysFromArray($data[0]);

        $this->sql['field'] = '(`'.implode("`, `", $columns).'`)';

        $values = [];

        foreach ($data as $item) {
            $rowValues = [];

            foreach ($columns as $column) {
                $value = $item[$column];

                // Apply appropriate escaping to the value to prevent SQL injection
                $value = $this->check_value_escape($value);

                $rowValues[] = "'$value'";
            }

            $values[] = "(".implode(", ", $rowValues).")";
        }

        $this->sql['value'] = implode(", ", $values);

        $sql = $this->buildSql($this->replaceSql);

        return $this->getDb()->amd($sql);

    }


    function check_value_escape($value) {
        $type = Config::get('database.type');

        if($type=='mysqli'){
            // Escape the value using mysqli_real_escape_string
            return mysqli_real_escape_string($this->getDb()->conn, $value);
        }else if($type=='sqlite'){

            ## should debug
            // Escape the value using SQLite3::escapeString
            return $this->getDb()->conn->escapeString($value);
        }
        return $value;
    }

}



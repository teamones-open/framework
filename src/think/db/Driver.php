<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think\db;

use PDO;
use think\exception\PDOException;

abstract class Driver
{
    /**
     * PDO操作实例
     * @var \PDOStatement
     */
    protected $PDOStatement = null;

    /**
     * 当前连接ID
     * @var \PDO
     */
    protected $_linkID = null;

    // 当前操作所属的模型名
    protected $model = '_think_';

    // 当前SQL指令
    protected $queryStr = '';

    // 模型SQL
    protected $modelSql = [];

    // 最后插入ID
    protected $lastInsID = null;

    // 返回或者影响记录数
    protected $numRows = 0;

    // 事物操作PDO实例
    protected $transPDO = null;

    // 事务指令数
    protected $transTimes = 0;

    // 错误信息
    protected $error = '';

    // 数据库连接ID 支持多个连接
    protected $linkID = [];

    // 数据库连接参数配置
    protected $config = [
        'type' => '', // 数据库类型
        'hostname' => '127.0.0.1', // 服务器地址
        'database' => '', // 数据库名
        'username' => '', // 用户名
        'password' => '', // 密码
        'hostport' => '', // 端口
        'dsn' => '', //
        'params' => [], // 数据库连接参数
        'charset' => 'utf8', // 数据库编码默认采用utf8
        'prefix' => '', // 数据库表前缀
        'debug' => false, // 数据库调试模式
        'deploy' => 0, // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'rw_separate' => false, // 数据库读写是否分离 主从式有效
        'master_num' => 1, // 读写分离后 主服务器数量
        'slave_no' => '', // 指定从服务器序号
        'db_like_fields' => '',
    ];

    // 数据库表达式
    protected $exp = [
        'eq' => '=',
        'neq' => '<>',
        'gt' => '>',
        'egt' => '>=',
        'lt' => '<',
        'elt' => '<=',
        'notlike' => 'NOT LIKE',
        'like' => 'LIKE',
        'in' => 'IN',
        'notin' => 'NOT IN',
        'not in' => 'NOT IN',
        'between' => 'BETWEEN',
        'not between' => 'NOT BETWEEN',
        'notbetween' => 'NOT BETWEEN'
    ];

    // 查询表达式
    protected $selectSql = '%HINT% SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';

    // 查询次数
    protected $queryTimes = 0;

    // 执行次数
    protected $executeTimes = 0;

    // PDO连接参数
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * 服务器断线标识字符
     * @var array
     */
    protected $breakMatchStr = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'failed with errno',
        'child connection forced to terminate due to client_idle_limit',
        'query_wait_timeout',
        'reset by peer',
        'Physical connection is not usable',
        'TCP Provider: Error code 0x68',
        'ORA-03114',
        'Packets out of order. Expected',
        'Adaptive Server connection failed',
        'Communication link failure',
        'connection is no longer usable',
        'Login timeout expired',
        'SQLSTATE[HY000] [2002] Connection refused',
        'running with the --read-only option so it cannot execute this statement',
        'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
        'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
        'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
        'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
        'SQLSTATE[HY000] [2002] Connection timed out',
        'SSL: Connection timed out',
        'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
    ];

    // 参数绑定
    protected $bind = [];

    /**
     * Driver constructor.
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
            if (is_array($this->config['params'])) {
                $this->options = $this->config['params'] + $this->options;
            }
        }
    }

    /**
     * 连接数据库方法
     * @param array $config
     * @param int $linkNum
     * @param bool $autoConnection
     * @return mixed
     * @throws \Exception
     */
    public function connect($config = [], $linkNum = 0, $autoConnection = false)
    {
        if (!isset($this->linkID[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            }
            try {
                if (empty($config['dsn'])) {
                    $config['dsn'] = $this->parseDsn($config);
                }
                if (version_compare(PHP_VERSION, '5.3.6', '<=')) {
                    // 禁用模拟预处理语句
                    $this->options[PDO::ATTR_EMULATE_PREPARES] = false;
                }
                $this->linkID[$linkNum] = new PDO($config['dsn'], $config['username'], $config['password'], $this->options);
            } catch (\PDOException $e) {
                if ($autoConnection) {
                    trace($e->getMessage(), 'ERR');
                    return $this->connect($autoConnection, $linkNum);
                } elseif ($config['debug']) {
                    StrackE($e->getMessage());
                }
            }
        }
        return $this->linkID[$linkNum];
    }

    /**
     * 解析pdo连接的dsn信息
     * @param $config
     * @return string
     */
    protected function parseDsn($config): string
    {

    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free()
    {
        $this->PDOStatement = null;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $str sql指令
     * @param boolean $fetchSql 不执行只是获取SQL
     * @param boolean $master 是否在主服务器读操作
     * @return array|bool|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function query($str, $fetchSql = false, $master = false)
    {
        $this->initConnect($master);
        if (!$this->_linkID) {
            return false;
        }
        $this->queryStr = $str;
        if (!empty($this->bind)) {
            $that = $this;
            $this->queryStr = strtr($this->queryStr, array_map(function ($val) use ($that) {
                return '\'' . $that->escapeString($val) . '\'';
            }, $this->bind));
        }
        if ($fetchSql) {
            return $this->queryStr;
        }
        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
        $this->queryTimes++;

        try {
            // 调试开始
            $this->debug(true);
            $this->PDOStatement = $this->_linkID->prepare($str);
            if (false === $this->PDOStatement) {
                $this->error();
                return false;
            }
            foreach ($this->bind as $key => $val) {
                if (is_array($val)) {
                    $this->PDOStatement->bindValue($key, $val[0], $val[1]);
                } else {
                    $this->PDOStatement->bindValue($key, $val);
                }
            }
            $this->bind = [];

            $result = $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);
            if (false === $result) {
                $this->error();
                return false;
            } else {
                return $this->getResult();
            }
        } catch (\PDOException | \Exception | \Throwable $e) {
            if ($this->transTimes > 0) {
                // 当前是开启了事务 避免多层事务失效 直接重置事务计数
                if ($this->isBreak($e)) {
                    $this->transTimes = 0;
                }
            } else {
                if ($this->isBreak($e)) {
                    return $this->close()->query($str, $fetchSql, $master);
                }
            }
            // 针对pdoException 进行错误信息定制
            if ($e instanceof \PDOException) {
                throw new PDOException($e, $this->config, $this->getLastsql());
            } else {
                throw $e;
            }
        }
    }

    /**
     * 执行语句
     * @access public
     * @param string $str sql指令
     * @param boolean $fetchSql 不执行只是获取SQL
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function execute($str, $fetchSql = false)
    {
        $this->initConnect(true);
        if (!$this->_linkID) {
            return false;
        }
        $this->queryStr = $str;
        if (!empty($this->bind)) {
            $that = $this;
            $this->queryStr = strtr($this->queryStr, array_map(function ($val) use ($that) {
                return '\'' . $that->escapeString($val) . '\'';
            }, $this->bind));
        }
        if ($fetchSql) {
            return $this->queryStr;
        }
        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
        $this->executeTimes++;

        try {

            // 记录开始执行时间
            $this->debug(true);
            $this->PDOStatement = $this->_linkID->prepare($str);
            if (false === $this->PDOStatement) {
                $this->error();
                return false;
            }
            foreach ($this->bind as $key => $val) {
                if (is_array($val)) {
                    $this->PDOStatement->bindValue($key, $val[0], $val[1]);
                } else {
                    $this->PDOStatement->bindValue($key, $val);
                }
            }
            $this->bind = [];

            $result = $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);
            if (false === $result) {
                $this->error();
                return false;
            } else {
                $this->numRows = $this->PDOStatement->rowCount();
                if (preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                    $this->lastInsID = $this->_linkID->lastInsertId();
                }
                return $this->numRows;
            }
        } catch (\PDOException | \Exception | \Throwable $e) {
            if ($this->transTimes > 0) {
                // 当前是开启了事务 避免多层事务失效 直接重置事务计数
                if ($this->isBreak($e)) {
                    $this->transTimes = 0;
                }
            } else {
                if ($this->isBreak($e)) {
                    return $this->close()->execute($str, $fetchSql);
                }
            }
            // 针对pdoException 进行错误信息定制
            if ($e instanceof \PDOException) {
                throw new PDOException($e, $this->config, $this->getLastsql());
            } else {
                throw $e;
            }
        }
    }

    /**
     * 启动事务
     * @return bool|void
     * @throws \Exception
     */
    public function startTrans()
    {
        $this->transTimes++;
        $this->initConnect(true);

        if (!$this->_linkID) return false;

        try {
            //数据rollback 支持
            if ($this->transTimes == 1) {
                $this->_linkID->beginTransaction();
            }
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                --$this->transTimes;
                return $this->close()->startTrans();
            }
            throw $e;
        }


        return;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @return bool
     * @throws \Exception
     */
    public function commit()
    {
        if ($this->transTimes == 1) {
            $result = $this->_linkID->commit();
            $this->transTimes = 0;
            if (!$result) {
                $this->error();
                return false;
            }
        } else {
            --$this->transTimes;
        }
        return true;
    }

    /**
     * 事务回滚
     * @return bool
     * @throws \Exception
     */
    public function rollback()
    {
        if ($this->transTimes == 1) {
            $result = $this->_linkID->rollback();
            $this->transTimes = 0;
            if (!$result) {
                $this->error();
                return false;
            }
        } else {
            --$this->transTimes;
        }
        return true;
    }

    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getResult()
    {
        //返回数据集
        $result = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->numRows = count($result);
        return $result;
    }

    /**
     * 获得查询次数
     * @access public
     * @param boolean $execute 是否包含所有查询
     * @return integer
     */
    public function getQueryTimes($execute = false)
    {
        return $execute ? $this->queryTimes + $this->executeTimes : $this->queryTimes;
    }

    /**
     * 获得执行次数
     * @access public
     * @return integer
     */
    public function getExecuteTimes()
    {
        return $this->executeTimes;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close()
    {
        $this->linkID = null;

        // 释放查询
        $this->free();
        /**
         * 释放事务计数
         * 事务是不能跨连接执行的 避免下一次事务被污染
         */
        $this->transTimes = 0;
        return $this;
    }

    /**
     * 是否断线
     * @access protected
     * @param \PDOException|\Exception $e 异常对象
     * @return bool
     */
    protected function isBreak($e)
    {
        $error = $e->getMessage();

        foreach ($this->breakMatchStr as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 数据库错误信息，并显示当前的SQL语句
     * @return string
     * @throws \Exception
     */
    public function error()
    {
        if ($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $this->error = $error[1] . ':' . $error[2];
        } else {
            $this->error = '';
        }
        if ('' != $this->queryStr) {
            $this->error .= "\n [ SQL语句 ] : " . $this->queryStr;
        }
        // 记录错误日志
        trace($this->error, 'ERR');
        if ($this->config['debug']) {
            // 开启数据库调试模式
            StrackE($this->error);
        } else {
            return $this->error;
        }
    }

    /**
     * 设置锁机制
     * @param bool $lock
     * @return string
     */
    protected function parseLock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * set分析
     * @access protected
     * @param array $data
     * @return string
     */
    protected function parseSet($data)
    {
        $set = [];
        foreach ($data as $key => $val) {
            if (isset($val[0]) && 'exp' == $val[0]) {
                $set[] = $this->parseKey($key) . '=' . $val[1];
            } elseif (is_null($val)) {
                $set[] = $this->parseKey($key) . '=NULL';
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                    $set[] = $this->parseKey($key) . '=' . $val;
                } else {
                    $name = count($this->bind);
                    $set[] = $this->parseKey($key) . '=:' . $key . '_' . $name;
                    $this->bindParam($key . '_' . $name, $val);
                }
            }
        }
        return ' SET ' . implode(',', $set);
    }

    /**
     * 参数绑定
     * @access protected
     * @param string $name 绑定参数名
     * @param mixed $value 绑定值
     * @return void
     */
    protected function bindParam($name, $value)
    {
        $this->bind[':' . $name] = $value;
    }

    /**
     * 字段名分析
     * @param $key
     * @param bool $strict
     * @return mixed
     */
    protected function parseKey($key, $strict = false)
    {
        return $key;
    }

    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value)
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && in_array($value, array_keys($this->bind)) ? $this->escapeString($value) : '\'' . $this->escapeString($value) . '\'';
        } elseif (isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp') {
            $value = $this->escapeString($value[1]);
        } elseif (is_array($value)) {
            $value = array_map([$this, 'parseValue'], $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        return $value;
    }

    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields)
    {
        if (is_string($fields) && '' !== $fields) {
            $fields = explode(',', $fields);
        }
        if (is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];
            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($key) . ' AS ' . $this->parseKey($field);
                } else {
                    $array[] = $this->parseKey($field);
                }
            }
            $fieldsStr = implode(',', $array);
        } else {
            $fieldsStr = '*';
        }

        return $fieldsStr;
    }

    /**
     * table分析
     * @access protected
     * @param mixed $tables
     * @return string
     */
    protected function parseTable($tables)
    {
        if (is_array($tables)) {
            // 支持别名定义
            $array = [];
            foreach ($tables as $table => $alias) {
                if (!is_numeric($table)) {
                    $array[] = $this->parseKey($table) . ' ' . $this->parseKey($alias);
                } else {
                    $array[] = $this->parseKey($alias);
                }
            }
            $tables = $array;
        } elseif (is_string($tables)) {
            $tables = array_map([$this, 'parseKey'], explode(',', $tables));
        }
        return implode(',', $tables);
    }

    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return string
     * @throws \Exception
     */
    protected function parseWhere($where)
    {
        $whereStr = '';
        if (is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        } else {
            // 使用数组表达式
            $operate = isset($where['_logic']) ? strtoupper($where['_logic']) : '';
            if (in_array($operate, ['AND', 'OR', 'XOR'])) {
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' ' . $operate . ' ';
                unset($where['_logic']);
            } else {
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach ($where as $key => $val) {
                if (is_numeric($key)) {
                    $key = '_complex';
                }
                if (0 === strpos($key, '_')) {
                    // 解析特殊条件表达式
                    $whereStr .= $this->parseThinkWhere($key, $val);
                } else {
                    // 查询字段的安全过滤
                    // if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/',trim($key))){
                    //     StrackE(L('_EXPRESS_ERROR_').':'.$key);
                    // }
                    // 多条件支持
                    $multi = is_array($val) && isset($val['_multi']);
                    $key = trim($key);
                    $str = [];
                    if (strpos($key, '|')) {
                        // 支持 name|title|nickname 方式定义查询字段
                        $array = explode('|', $key);
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = $this->parseWhereItem($this->parseKey($k), $v);
                        }
                        $whereStr .= '( ' . implode(' OR ', $str) . ' )';
                    } elseif (strpos($key, '&')) {
                        $array = explode('&', $key);
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = '(' . $this->parseWhereItem($this->parseKey($k), $v) . ')';
                        }
                        $whereStr .= '( ' . implode(' AND ', $str) . ' )';
                    } else {
                        $whereStr .= $this->parseWhereItem($this->parseKey($key), $val);
                    }
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr, 0, -strlen($operate));
        }
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * where子单元分析
     * @param $key
     * @param $val
     * @return string
     * @throws \Exception
     */
    protected function parseWhereItem($key, $val)
    {
        $whereStr = '';
        if (is_array($val)) {
            if (is_string($val[0])) {
                $exp = strtolower($val[0]);
                if (preg_match('/^(eq|neq|gt|egt|lt|elt)$/', $exp)) {
                    // 比较运算
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                } elseif (preg_match('/^(notlike|like)$/', $exp)) {
                    // 模糊查找
                    if (is_array($val[1])) {
                        $likeLogic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                        if (in_array($likeLogic, ['AND', 'OR', 'XOR'])) {
                            $like = [];
                            foreach ($val[1] as $item) {
                                $like[] = $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($item);
                            }
                            $whereStr .= '(' . implode(' ' . $likeLogic . ' ', $like) . ')';
                        }
                    } else {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                    }
                } elseif ('bind' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' = :' . $val[1];
                } elseif ('find_in_set' == $exp) {
                    $whereStr .= 'FIND_IN_SET(\'' . $val[1] . '\',' . $key . ')';
                } elseif ('exp' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' ' . $val[1];
                } elseif (preg_match('/^(notin|not in|in)$/', $exp)) {
                    // IN 运算
                    if (isset($val[2]) && 'exp' == $val[2]) {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $val[1];
                    } else {
                        if (is_string($val[1])) {
                            $val[1] = explode(',', $val[1]);
                        }
                        $zone = implode(',', $this->parseValue($val[1]));
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' (' . $zone . ')';
                    }
                } elseif (preg_match('/^(notbetween|not between|between)$/', $exp)) {
                    // BETWEEN运算
                    $data = is_string($val[1]) ? explode(',', $val[1]) : $val[1];
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]);
                } else {
                    StrackE(L('_EXPRESS_ERROR_') . ':' . $val[0]);
                }
            } else {
                $count = count($val);
                $rule = isset($val[$count - 1]) ? (is_array($val[$count - 1]) ? strtoupper($val[$count - 1][0]) : strtoupper($val[$count - 1])) : '';
                if (in_array($rule, ['AND', 'OR', 'XOR'])) {
                    $count = $count - 1;
                } else {
                    $rule = 'AND';
                }
                for ($i = 0; $i < $count; $i++) {
                    $data = is_array($val[$i]) ? $val[$i][1] : $val[$i];
                    if ('exp' == strtolower($val[$i][0])) {
                        $whereStr .= $key . ' ' . $data . ' ' . $rule . ' ';
                    } else {
                        $whereStr .= $this->parseWhereItem($key, $val[$i]) . ' ' . $rule . ' ';
                    }
                }
                $whereStr = '( ' . substr($whereStr, 0, -4) . ' )';
            }
        } else {
            //对字符串类型字段采用模糊匹配
            $likeFields = $this->config['db_like_fields'];
            if ($likeFields && preg_match('/^(' . $likeFields . ')$/i', $key)) {
                $whereStr .= $key . ' LIKE ' . $this->parseValue('%' . $val . '%');
            } else {
                $whereStr .= $key . ' = ' . $this->parseValue($val);
            }
        }
        return $whereStr;
    }

    /**
     * 特殊条件分析
     * @access protected
     * @param $key
     * @param $val
     * @return string
     * @throws \Exception
     */
    protected function parseThinkWhere($key, $val)
    {
        $whereStr = '';
        switch ($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = substr($this->parseWhere($val), 6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val, $where);
                if (isset($where['_logic'])) {
                    $op = ' ' . strtoupper($where['_logic']) . ' ';
                    unset($where['_logic']);
                } else {
                    $op = ' AND ';
                }
                $array = [];
                foreach ($where as $field => $data) {
                    $array[] = $this->parseKey($field) . ' = ' . $this->parseValue($data);
                }
                $whereStr = implode($op, $array);
                break;
        }
        return '( ' . $whereStr . ' )';
    }

    /**
     * limit分析
     * @param $limit
     * @return string
     */
    protected function parseLimit($limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * join分析
     * @access protected
     * @param mixed $join
     * @return string
     */
    protected function parseJoin($join)
    {
        $joinStr = '';
        if (!empty($join)) {
            $joinStr = ' ' . implode(' ', $join) . ' ';
        }
        return $joinStr;
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order)
    {
        if (empty($order)) {
            return '';
        }
        $array = [];
        if (is_array($order)) {
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if (false === strpos($val, '(')) {
                        $array[] = $this->parseKey($val);
                    } else {
                        // 复杂order条件 包含 () JSON_EXTRACT
                        $array[] = $val;
                    }
                } else {
                    $sort = in_array(strtolower($val), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = $this->parseKey($key, true) . $sort;
                }
            }
        } elseif ('[RAND]' == $order) {
            // 随机排序
            $array[] = $this->parseRand();
        } else {
            foreach (explode(',', $order) as $val) {
                if (preg_match('/\s+(ASC|DESC)$/i', rtrim($val), $match, PREG_OFFSET_CAPTURE)) {
                    $array[] = $this->parseKey(ltrim(substr($val, 0, $match[0][1]))) . ' ' . $match[1][0];
                } elseif (false === strpos($val, '(')) {
                    $array[] = $this->parseKey($val);
                }
            }
        }
        $order = implode(',', $array);
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }

    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group)
    {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }

    /**
     * having分析
     * @access protected
     * @param string $having
     * @return string
     */
    protected function parseHaving($having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * 增加 Hint
     * @param $hint
     * @return string
     */
    protected function parseHint($hint)
    {
        return !empty($hint) ? '/*' . $hint . '*/' : '';
    }

    /**
     * distinct分析
     * @access protected
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * union分析
     * @access protected
     * @param $union
     * @return string
     * @throws \Exception
     */
    protected function parseUnion($union)
    {
        if (empty($union)) {
            return '';
        }
        if (isset($union['_all'])) {
            $str = 'UNION ALL ';
            unset($union['_all']);
        } else {
            $str = 'UNION ';
        }
        foreach ($union as $u) {
            $sql[] = $str . (is_array($u) ? $this->buildSelectSql($u) : $u);
        }
        return implode(' ', $sql);
    }

    /**
     * 参数绑定分析
     * @param $bind
     */
    protected function parseBind($bind)
    {
        $this->bind = array_merge($this->bind, $bind);
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param mixed $index
     * @return string
     */
    protected function parseForce($index)
    {
        if (empty($index)) {
            return '';
        }
        if (is_array($index)) {
            $index = join(",", $index);
        }
        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * ON DUPLICATE KEY UPDATE 分析
     * @access protected
     * @param mixed $duplicate
     * @return string
     */
    protected function parseDuplicate($duplicate)
    {
        return '';
    }

    /**
     * 插入记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function insert($data, $options = [], $replace = false)
    {
        // 防止内存溢出，执行操作前清空sql历史记录
        $this->modelSql = [];

        $values = $fields = [];
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        foreach ($data as $key => $val) {
            if (isset($val[0]) && 'exp' == $val[0]) {
                $fields[] = $this->parseKey($key);
                $values[] = $val[1];
            } elseif (is_null($val)) {
                $fields[] = $this->parseKey($key);
                $values[] = 'NULL';
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                $fields[] = $this->parseKey($key);
                if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                    $values[] = $val;
                } else {
                    $name = count($this->bind);
                    $values[] = ':' . $key . '_' . $name;
                    $this->bindParam($key . '_' . $name, $val);
                }
            }
        }
        // 兼容数字传入方式
        $replace = (is_numeric($replace) && $replace > 0) ? true : $replace;
        $sql = (true === $replace ? 'REPLACE' : 'INSERT') . ' INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')' . $this->parseDuplicate($replace);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 批量插入记录
     * @access public
     * @param mixed $dataSet 数据集
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function insertAll($dataSet, $options = [], $replace = false)
    {
        // 防止内存溢出，执行操作前清空sql历史记录
        $this->modelSql = [];

        $values = [];
        $this->model = $options['model'];
        if (!is_array($dataSet[0])) {
            return false;
        }
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $fields = array_map([$this, 'parseKey'], array_keys($dataSet[0]));
        foreach ($dataSet as $data) {
            $value = [];
            foreach ($data as $key => $val) {
                if (is_array($val) && 'exp' == $val[0]) {
                    $value[] = $val[1];
                } elseif (is_null($val)) {
                    $value[] = 'NULL';
                } elseif (is_scalar($val)) {
                    if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                        $value[] = $val;
                    } else {
                        $name = count($this->bind);
                        $value[] = ':' . $key . '_' . $name;
                        $this->bindParam($key . '_' . $name, $val);
                    }
                }
            }
            $values[] = 'SELECT ' . implode(',', $value);
        }
        $sql = 'INSERT INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') ' . implode(' UNION ALL ', $values);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 通过Select方式插入记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $options 查询数据参数
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function selectInsert($fields, $table, $options = [])
    {
        // 防止内存溢出，执行操作前清空sql历史记录
        $this->modelSql = [];

        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        $fields = array_map([$this, 'parseKey'], $fields);
        $sql = 'INSERT INTO ' . $this->parseTable($table) . ' (' . implode(',', $fields) . ') ';
        $sql .= $this->buildSelectSql($options);
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function update($data, $options)
    {
        // 防止内存溢出，执行操作前清空sql历史记录
        $this->modelSql = [];

        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $table = $this->parseTable($options['table']);
        $sql = 'UPDATE ' . $table . $this->parseSet($data);
        if (strpos($table, ',')) {
            // 多表更新支持JOIN操作
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            //  单表更新支持order和lmit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '')
                . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 删除记录
     * @access public
     * @param array $options 表达式
     * @return bool|int|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function delete($options = [])
    {
        // 防止内存溢出，执行操作前清空sql历史记录
        $this->modelSql = [];

        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $table = $this->parseTable($options['table']);
        $sql = 'DELETE FROM ' . $table;
        if (strpos($table, ',')) {
            // 多表删除支持USING和JOIN操作
            if (!empty($options['using'])) {
                $sql .= ' USING ' . $this->parseTable($options['using']) . ' ';
            }
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            // 单表删除支持order和limit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '')
                . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return array|bool|string
     * @throws PDOException
     * @throws \Throwable
     */
    public function select($options = [])
    {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $sql = $this->buildSelectSql($options);

        return $this->query($sql, !empty($options['fetch_sql']) ? true : false, !empty($options['master']) ? true : false);
    }

    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string|string[]
     * @throws \Exception
     */
    public function buildSelectSql($options = [])
    {
        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page = $page > 0 ? $page : 1;
            $listRows = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows * ($page - 1);
            $options['limit'] = $offset . ',' . $listRows;
        }

        return $this->parseSql($this->selectSql, $options);
    }

    /**
     * 替换SQL语句中表达式
     * @access public
     * @param string $sql sql语句
     * @param array $options 表达式
     * @return string|string[]
     * @throws \Exception
     */
    public function parseSql($sql, $options = [])
    {
        $sql = str_replace(
            [
                '%HINT%',
                '%TABLE%',
                '%DISTINCT%',
                '%FIELD%',
                '%JOIN%',
                '%WHERE%',
                '%GROUP%',
                '%HAVING%',
                '%ORDER%',
                '%LIMIT%',
                '%UNION%',
                '%LOCK%',
                '%COMMENT%',
                '%FORCE%'
            ],
            [
                $this->parseHint(!empty($options['hint']) ? $options['hint'] : ''),
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct']) ? $options['distinct'] : false),
                $this->parseField(!empty($options['field']) ? $options['field'] : '*'),
                $this->parseJoin(!empty($options['join']) ? $options['join'] : ''),
                $this->parseWhere(!empty($options['where']) ? $options['where'] : ''),
                $this->parseGroup(!empty($options['group']) ? $options['group'] : ''),
                $this->parseHaving(!empty($options['having']) ? $options['having'] : ''),
                $this->parseOrder(!empty($options['order']) ? $options['order'] : ''),
                $this->parseLimit(!empty($options['limit']) ? $options['limit'] : ''),
                $this->parseUnion(!empty($options['union']) ? $options['union'] : ''),
                $this->parseLock(isset($options['lock']) ? $options['lock'] : false),
                $this->parseComment(!empty($options['comment']) ? $options['comment'] : ''),
                $this->parseForce(!empty($options['force']) ? $options['force'] : ''),
            ],
            $sql
        );
        return $sql;
    }

    /**
     * 获取最近一次查询的sql语句
     * @param string $model 模型名
     * @access public
     * @return string
     */
    public function getLastSql($model = '')
    {
        return $model ? $this->modelSql[$model] : $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
    {
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     * @access public
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str SQL字符串
     * @return string
     */
    public function escapeString($str)
    {
        return addslashes($str);
    }

    /**
     * 设置当前操作模型
     * @access public
     * @param string $model 模型名
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * 数据库调试 记录当前SQL
     * @access protected
     * @param boolean $start 调试开始标记 true 开始 false 结束
     */
    protected function debug($start)
    {
        if ($this->config['debug']) {
            // 开启数据库调试模式
            if ($start) {
                G('queryStartTime');
            } else {
                $this->modelSql[$this->model] = $this->queryStr;
                //$this->model  =   '_think_';
                // 记录操作结束时间
                G('queryEndTime');
                trace($this->queryStr . ' [ RunTime:' . G('queryStartTime', 'queryEndTime') . 's ]', 'SQL');
            }
        }
    }

    /**
     * 初始化数据库连接
     * @param bool $master
     * @return null
     * @throws \Exception
     */
    protected function initConnect($master = true)
    {
        // 开启事物时用同一个连接进行操作
        if ($this->transPDO) {
            return $this->transPDO;
        }
        if (!empty($this->config['deploy'])) // 采用分布式数据库
        {
            $this->_linkID = $this->multiConnect($master);
        } else
            // 默认单数据库
            if (!$this->_linkID) {
                $this->_linkID = $this->connect();
            }
    }

    /**
     * 连接分布式服务器
     * @access protected
     * @param bool $master 主服务器
     * @return mixed
     * @throws \Exception
     */
    protected function multiConnect($master = false)
    {
        // 分布式数据库配置解析
        $_config['username'] = explode(',', $this->config['username']);
        $_config['password'] = explode(',', $this->config['password']);
        $_config['hostname'] = explode(',', $this->config['hostname']);
        $_config['hostport'] = explode(',', $this->config['hostport']);
        $_config['database'] = explode(',', $this->config['database']);
        $_config['dsn'] = explode(',', $this->config['dsn']);
        $_config['charset'] = explode(',', $this->config['charset']);
        $m = floor(mt_rand(0, $this->config['master_num'] - 1));
        // 数据库读写是否分离
        if ($this->config['rw_separate']) {
            // 主从式采用读写分离
            if ($master) {
                // 主服务器写入
                $r = $m;
            } else {
                if (is_numeric($this->config['slave_no'])) {
                    // 指定服务器读
                    $r = $this->config['slave_no'];
                } else {
                    // 读操作连接从服务器
                    $r = floor(mt_rand($this->config['master_num'], count($_config['hostname']) - 1)); // 每次随机连接的数据库
                }
            }
        } else {
            // 读写操作不区分服务器
            $r = floor(mt_rand(0, count($_config['hostname']) - 1)); // 每次随机连接的数据库
        }

        $dbMaster = [];
        if ($m != $r) {
            $dbMaster = [
                'username' => isset($_config['username'][$m]) ? $_config['username'][$m] : $_config['username'][0],
                'password' => isset($_config['password'][$m]) ? $_config['password'][$m] : $_config['password'][0],
                'hostname' => isset($_config['hostname'][$m]) ? $_config['hostname'][$m] : $_config['hostname'][0],
                'hostport' => isset($_config['hostport'][$m]) ? $_config['hostport'][$m] : $_config['hostport'][0],
                'database' => isset($_config['database'][$m]) ? $_config['database'][$m] : $_config['database'][0],
                'dsn' => isset($_config['dsn'][$m]) ? $_config['dsn'][$m] : $_config['dsn'][0],
                'charset' => isset($_config['charset'][$m]) ? $_config['charset'][$m] : $_config['charset'][0],
            ];
        }
        $dbConfig = [
            'username' => isset($_config['username'][$r]) ? $_config['username'][$r] : $_config['username'][0],
            'password' => isset($_config['password'][$r]) ? $_config['password'][$r] : $_config['password'][0],
            'hostname' => isset($_config['hostname'][$r]) ? $_config['hostname'][$r] : $_config['hostname'][0],
            'hostport' => isset($_config['hostport'][$r]) ? $_config['hostport'][$r] : $_config['hostport'][0],
            'database' => isset($_config['database'][$r]) ? $_config['database'][$r] : $_config['database'][0],
            'dsn' => isset($_config['dsn'][$r]) ? $_config['dsn'][$r] : $_config['dsn'][0],
            'charset' => isset($_config['charset'][$r]) ? $_config['charset'][$r] : $_config['charset'][0],
        ];
        return $this->connect($dbConfig, $r, $r == $m ? false : $dbMaster);
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 释放查询
        if ($this->PDOStatement) {
            $this->free();
        }
        // 关闭连接
        $this->close();
    }
}

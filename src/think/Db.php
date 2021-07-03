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

namespace think;

use think\exception\ErrorCode;

/**
 * Class Db
 * @package think
 * @method mixed select(mixed $data = null) static 查询多个记录
 * @method integer insert(array $data, array $options, bool $replace = false) static 插入一条记录
 * @method integer insertAll(array $dataSet, $options = [], $replace = false) static 插入多条记录
 * @method integer update(array $data, array $options) static 更新记录
 * @method integer delete(mixed $data = null) static 删除记录
 * @method mixed query(string $sql, array $bind = [], boolean $master = false, bool $pdo = false) static SQL查询
 * @method integer execute(string $sql, array $bind = [], boolean $fetch = false, boolean $getLastInsID = false, string $sequence = null) static SQL执行
 * @method void startTrans() static 启动事务
 * @method boolean commit() static 用于非自动提交状态下面的查询提交
 * @method boolean rollback() static 事务回滚
 * @method string getLastInsID($sequence = null) static 获取最近插入的ID
 * @method array getTables($dbName = '') static 取得数据库的表信息
 * @method array getFields($tableName) static 取得数据表的字段信息
 * @method string setModel($model) 设置当前操作模型
 * @method mixed selectInsert($fields, $table, $options = []) 通过Select方式插入记录
 * @method mixed parseSql($sql, $options = []) 替换SQL语句中表达式
 * @method string getError()
 * @method string getLastSql($model = '')
 */
class Db
{

    /**
     * 数据库连接实例
     * @var array
     */
    private static $instance = [];

    /**
     * 当前数据库连接实例
     * @var null
     */
    private static $_instance = null;

    /**
     * 数据库配置
     * @var array
     */
    protected static $config = [];

    /**
     * 取得数据库类实例
     * @param array $config
     * @return mixed|null
     * @throws \Exception
     */
    public static function getInstance($config = [])
    {
        // 解析连接参数 支持数组和字符串
        $options = self::parseConfig($config);
        $md5 = md5(serialize($options));

        if (!isset(self::$instance[$md5])) {
            // 兼容mysqli
            if ('mysqli' == $options['type']) {
                $options['type'] = 'mysql';
            }

            // 如果采用lite方式 仅支持原生SQL 包括query和execute方法
            $class = 'think\\db\\driver\\' . ucwords(strtolower($options['type']));
            if (class_exists($class)) {
                self::$instance[$md5] = new $class($options);
            } else {
                // 类没有定义
                StrackE(L('_NO_DB_DRIVER_') . ': ' . $class, ErrorCode::UNABLE_TO_LOAD_DATABASE_DRIVER);
            }
        }
        self::$_instance = self::$instance[$md5];
        return self::$_instance;
    }

    /**
     * 清空初始化
     */
    public static function clearInstance(): void
    {
        // 解析连接参数 支持数组和字符串
        self::$instance = [];
        self::$_instance = null;
    }

    /**
     * 初始化配置参数
     * @throws \Exception
     */
    public static function setInstance(): void
    {
        // 解析连接参数 支持数组和字符串
        self::getInstance();
    }

    /**
     * 获取数据库配置
     * @param string $name
     * @return array|mixed|null
     */
    public static function getConfig($name = '')
    {
        if ('' === $name) {
            return self::$config;
        }

        return self::$config[$name] ?? null;
    }

    /**
     * 数据库连接参数解析
     * @param array $config
     * @param string $driver
     * @return array|bool
     * @throws \Exception
     */
    public static function parseConfig($config = [], $driver = 'default')
    {
        if (!empty($config)) {
            if (is_string($config)) {
                return self::parseDsn($config);
            }
            $config = array_change_key_case($config);
            $config = [
                'type' => $config['db_type'],
                'username' => $config['db_user'],
                'password' => $config['db_pwd'],
                'hostname' => $config['db_host'],
                'hostport' => $config['db_port'],
                'database' => $config['db_name'],
                'dsn' => $config['db_dsn'] ?? null,
                'params' => $config['db_params'] ?? null,
                'charset' => $config['db_charset'] ?? 'utf8',
                'deploy' => $config['db_deploy_type'] ?? 0,
                'rw_separate' => $config['db_rw_separate'] ?? false,
                'master_num' => $config['db_master_num'] ?? 1,
                'slave_no' => $config['db_slave_no'] ?? '',
                'debug' => $config['db_debug'] ?? APP_DEBUG,
                'lite' => $config['db_lite'] ?? false,
            ];
        } else {
            $databaseConfig = C('database');
            if (!empty($databaseConfig)) {
                $type = $databaseConfig[$driver];
                $config = [
                    'type' => $databaseConfig[$driver],
                    'username' => $databaseConfig['connections'][$type]['username'],
                    'password' => $databaseConfig['connections'][$type]['password'],
                    'hostname' => $databaseConfig['connections'][$type]['host'],
                    'hostport' => $databaseConfig['connections'][$type]['port'],
                    'database' => $databaseConfig['connections'][$type]['database'],
                    'dsn' => '',
                    'params' => C('DB_PARAMS'),
                    'charset' => $databaseConfig['connections'][$type]['charset'],
                    'deploy' => C('DB_DEPLOY_TYPE'),
                    'rw_separate' => C('DB_RW_SEPARATE'),
                    'master_num' => C('DB_MASTER_NUM'),
                    'slave_no' => C('DB_SLAVE_NO'),
                    'debug' => $databaseConfig['connections'][$type]['debug'] ?? APP_DEBUG,
                    'lite' => C('DB_LITE'),
                ];
            } else {
                StrackE('There is no database configuration.', ErrorCode::DATABASE_CONFIG_NOT_FOUND);
            }
        }


        self::$config = $config;

        return $config;
    }

    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @static
     * @access private
     * @param string $dsnStr
     * @return array|bool
     */
    private static function parseDsn($dsnStr)
    {
        if (empty($dsnStr)) {
            return false;
        }
        $info = parse_url($dsnStr);
        if (!$info) {
            return false;
        }
        $dsn = [
            'type' => $info['scheme'],
            'username' => $info['user'] ?? '',
            'password' => $info['pass'] ?? '',
            'hostname' => $info['host'] ?? '',
            'hostport' => $info['port'] ?? '',
            'database' => isset($info['path']) ? substr($info['path'], 1) : '',
            'charset' => $info['fragment'] ?? 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }
        return $dsn;
    }

    /**
     * 调用驱动类的方法
     * @param $method
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::getInstance(), $method], $params);
    }
}

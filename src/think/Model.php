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
 * ThinkPHP Model模型类
 * 实现了ORM和ActiveRecords模式
 *
 * @method $this alias(string $alias)
 * @method $this strict(bool $strict)
 * @method $this order(mixed $order)
 * @method $this having(string $having)
 * @method $this group(string $group)
 * @method $this lock(bool $lock)
 * @method $this distinct(bool $distinct)
 * @method $this auto(array $auto)
 * @method $this count()
 * @method $this sum(string $field)
 * @method $this avg(string $field)
 * @method $this min(string $field)
 * @method $this max(string $field)
 */
class Model
{
    // 操作状态
    const MODEL_INSERT = 1; //  插入模型数据
    const MODEL_UPDATE = 2; //  更新模型数据
    const MODEL_BOTH = 3; //  包含上面两种方式
    const MUST_VALIDATE = 1; // 必须验证
    const EXISTS_VALIDATE = 0; // 表单存在字段则验证
    const VALUE_VALIDATE = 2; // 表单值不为空则验证

    /**
     * 当前数据库操作对象
     * @var \think\Db
     */
    protected $db = null;

    // 数据库对象池
    private $_db = [];

    // 主键名称
    protected $pk = 'id';

    // 主键是否自动增长
    protected $autoinc = false;

    // 数据表前缀
    protected $tablePrefix = null;

    // 模型名称
    protected $name = '';

    // 数据库名称
    protected $dbName = '';

    //数据库配置
    protected $connection = '';

    //数据更新新数据
    protected $newUpdateData = [];

    //数据更新旧数据
    protected $oldUpdateData = [];

    //数据更新主键记录
    protected $oldUpdateKey = null;

    //数据删除旧数据
    protected $oldDeleteData = [];

    //数据删除主键记录
    protected $oldDeleteKey = '';

    // 数据表名（不包含表前缀）
    protected $tableName = '';

    // 实际数据表名（包含表前缀）
    protected $trueTableName = '';

    // 最近错误信息
    protected $error = '';

    // 最近错误码
    protected $errorCode = '';

    // 检查唯一性存在值
    protected $checkUniqueExitData = [];

    // 成功消息
    protected $successMsg = '';

    // 字段信息
    protected $fields = [];

    // 数据信息
    protected $data = [];

    // 查询表达式参数
    protected $options = [];
    protected $_validate = []; // 自动验证定义
    protected $_validate_after_auto = []; // 自动完成后验证
    protected $_auto = []; // 自动完成定义
    protected $_map = []; // 字段映射定义
    protected $_scope = []; // 命名范围定义

    // 显示属性
    protected $visible = [];

    // 隐藏属性
    protected $hidden = [];

    // 追加属性
    protected $append = [];

    // 追加自定义字段属性
    protected $appendCustomField = [];

    // 是否自动检测数据表字段信息
    protected $autoCheckFields = true;

    // 是否批处理验证
    protected $patchValidate = false;

    // 链操作方法列表
    protected $methods = ['strict', 'order', 'alias', 'having', 'group', 'lock', 'distinct', 'auto', 'filter', 'validate', 'result', 'token', 'index', 'force', 'master'];

    // 支持的验证规则
    protected $typeMsg = [
        'unique' => 'Validate_Unique',
        'confirm' => 'Validate_Confirm',
        'in' => 'Validate_In',
        'notin' => 'Validate_NotIn',
        'between' => 'Validate_Between',
        'notbetween' => 'Validate_NotBetween',
        'equal' => 'Validate_Eq',
        'notequal' => 'Validate_NotEq',
        'length' => 'Validate_Length',
        'expire' => 'Validate_Expire',
        'ip_allow' => 'Validate_AllowIp',
        'ip_deny' => 'Validate_DenyIp',
        'array' => 'Validate_Array',
        'number' => 'Validate_Number',
        'integer' => 'Validate_Integer',
        'require' => 'Validate_Require',
        'accepted' => 'Validate_Accepted',
        'date' => 'Validate_Date',
        'phone' => 'Validate_Phone',
        'password_strength' => 'Validate_Password_Strength',
        'activeUrl' => 'Validate_ActiveUrl',
        'alpha' => 'Validate_Alpha',
        'alphaNum' => 'Validate_AlphaNum',
        'alphaDash' => 'Validate_AlphaDash',
        'chs' => 'Validate_Chs',
        'chsAlpha' => 'Validate_ChsAlpha',
        'chsAlphaNum' => 'Validate_ChsAlphaNum',
        'chsDash' => 'Validate_ChsDash',
        'AuthMethod' => 'Validate_AuthMethod',
        'ip' => 'Validate_Ip',
        'url' => 'Validate_Url',
        'float' => 'Validate_Float',
        'email' => 'Validate_Email',
        'boolean' => 'Validate_Boolean',
        'regex' => 'Validate_Regex'
    ];

    // 定义返回数据
    public $_resData = [];

    // 字段配置字典数据
    public $fieldConfigDictionary = [];


    // 查询模块模型关联
    protected $queryModuleRelation = [];

    // 查询需要join查询的模块
    protected $queryModuleLfetJoinRelation = [];

    // 查询通过自定义字段水平关联的模块
    protected $queryModuleHorizontalRelation = [];

    // 查询实体关联的模块
    protected $queryModuleEntityRelation = [];

    // 查询一对多的模块
    protected $queryModuleHasManyRelation = [];

    // 查询实体关联的模块查询字段列表
    protected $queryModuleRelationFields = [];

    // 临时存储当前模块字段映射数据
    protected $queryModuleFieldDict = [];

    // 查询主键IDs
    protected $queryModulePrimaryKeyIds = [];

    // 复杂查询字段映射
    protected $queryComplexModuleMapping = [];

    // 复杂查询自定义字段映射
    protected $queryComplexCustomFieldMapping = [];

    // 复杂查询水平关联自定义字段映射
    protected $queryComplexHorizontalCustomFieldMapping = [];

    // 复杂查询关联模型自定义字段映射
    protected $queryComplexRelationCustomFields = [];

    // 复杂过滤条件涉及到的关联模块
    protected $complexFilterRelatedModule = [];

    // 是否是复杂过滤条件
    protected $isComplexFilter = false;

    // 当前模块code
    protected $currentModuleCode = '';

    // 查询model对象
    protected $moduleModel = null;

    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     * @access public
     * @param string $name 模型名称
     * @param string $tablePrefix 表前缀
     * @param mixed $connection 数据库连接信息
     * @throws \Exception
     */
    public function __construct($name = '', $tablePrefix = '', $connection = '')
    {
        // 模型初始化
        $this->_initialize();

        // 获取模型名称
        if (!empty($name)) {
            if (strpos($name, '.')) {
                // 支持 数据库名.模型名的 定义
                list($this->dbName, $this->name) = explode('.', $name);
            } else {
                $this->name = $name;
            }
        } elseif (empty($this->name)) {
            $this->name = $this->getModelName();
        }
        // 设置表前缀
        if (is_null($tablePrefix)) {
            // 前缀为Null表示没有前缀
            $this->tablePrefix = '';
        } elseif ('' != $tablePrefix) {
            $this->tablePrefix = $tablePrefix;
        } elseif (!isset($this->tablePrefix)) {
            $this->tablePrefix = C('DB_PREFIX');
        }

        // 数据库初始化操作
        // 获取数据库操作对象
        // 当前模型有独立的数据库连接信息
        $this->db(0, empty($this->connection) ? $connection : $this->connection, true);
    }


    /**
     * 格式化字段查询条件值类型
     * @param $tableName
     * @param $fieldName
     * @param $value
     * @return int
     */
    protected function formatFieldQueryValue($tableName, $fieldName, $value)
    {
        // 转换驼峰名称
        $moduleCode = un_camelize($tableName);

        // 判断值
        if ($this->fieldConfigDictionary[$moduleCode][$fieldName]["type"] === "int") {
            return intval($value);
        } else {
            return $value;
        }
    }

    /**
     * 自动检测数据表信息
     * @access protected
     * @return void
     */
    protected function _checkTableInfo()
    {
        // 如果不是Model类 自动记录数据表信息
        // 只在第一次执行记录
        if (empty($this->fields)) {

            // 每次都会读取数据表信息
            $fields = $this->flush();

            if (!empty($fields)) {
                $this->fields = $fields;
                if (!empty($fields['_pk'])) {
                    $this->pk = $fields['_pk'];
                }
                return;
            }
        }
    }

    /**
     * 获取字段信息并缓存
     * @param string $tableName
     * @param bool $must 是否必须刷新
     * @return mixed
     */
    public function flush($tableName = '', $must = false)
    {
        if (empty($tableName)) {
            $tableName = $this->getTableName();
        }

        if ($must === false && C('DB_FIELDS_CACHE')) {
            $fieldsCache = S('fields_' . strtolower($tableName));
            if (!empty($fieldsCache)) {
                return $fieldsCache;
            }
        }

        // 缓存不存在则查询数据表信息
        $this->db->setModel($this->name);
        $fields = $this->db->getFields($tableName);

        if (!empty($fields)) {
            $this->fields = array_keys($fields);
            unset($this->fields['_pk']);
            foreach ($fields as $key => $val) {
                // 记录字段类型
                $type[$key] = $val['type'];
                if ($val['primary']) {
                    // 增加复合主键支持
                    if (isset($this->fields['_pk']) && null != $this->fields['_pk']) {
                        if (is_string($this->fields['_pk'])) {
                            $this->pk = [$this->fields['_pk']];
                            $this->fields['_pk'] = $this->pk;
                        }
                        $this->pk[] = $key;
                        $this->fields['_pk'][] = $key;
                    } else {
                        $this->pk = $key;
                        $this->fields['_pk'] = $key;
                    }
                    if ($val['autoinc']) {
                        $this->autoinc = true;
                    }

                }
            }
            // 记录字段类型信息
            $this->fields['_type'] = $type;

            // 增加缓存开关控制
            if (C('DB_FIELDS_CACHE')) {
                // 永久缓存数据表信息, 缓存一个小时
                S('fields_' . strtolower($tableName), $this->fields, 3600);
            }
        }
    }

    /**
     * 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        // 设置数据对象属性
        $this->data[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * 利用__call方法实现一些特殊的Model方法
     * @access public
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return $this|mixed|Model
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (in_array(strtolower($method), $this->methods, true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] = $args[0];
            return $this;
        } elseif (in_array(strtolower($method), ['count', 'sum', 'min', 'max', 'avg'], true)) {
            // 统计查询的实现
            $field = $args[0] ?? '*';
            return $this->getField(strtoupper($method) . '(' . $field . ') AS tp_' . $method);
        } elseif (strtolower(substr($method, 0, 5)) == 'getby') {
            // 根据某个字段获取记录
            $field = parse_name(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') {
            // 根据某个字段获取记录的某个值
            $name = parse_name(substr($method, 10));
            $where[$name] = $args[0];
            return $this->where($where)->getField($args[1]);
        } elseif (isset($this->_scope[$method])) {
            // 命名范围的单独调用支持
            return $this->scope($method, $args[0]);
        } else {
            StrackE(__CLASS__ . ':' . $method . L('_METHOD_NOT_EXIST_'), ErrorCode::METHOD_NOT_EXIST);
        }
    }

    // 回调方法 初始化模型
    protected function _initialize()
    {
    }

    /**
     * 对保存到数据库的数据进行处理
     * @access protected
     * @param mixed $data 要操作的数据
     * @return array
     * @throws \Exception
     */
    protected function _facade($data)
    {

        // 检查数据字段合法性
        if (!empty($this->fields)) {
            if (!empty($this->options['field'])) {
                $fields = $this->options['field'];
                unset($this->options['field']);
                if (is_string($fields)) {
                    $fields = explode(',', $fields);
                }
            } else {
                $fields = $this->fields;
            }
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields, true)) {
                    if (!empty($this->options['strict'])) {
                        StrackE(L('_DATA_TYPE_INVALID_') . ':[' . $key . '=>' . $val . ']', ErrorCode::DATA_TYPE_INVALID);
                    }
                    unset($data[$key]);
                } elseif (is_scalar($val)) {
                    // 字段类型检查 和 强制转换
                    $this->_parseType($data, $key);
                }
            }
        }

        // 安全过滤
        if (!empty($this->options['filter'])) {
            $data = array_map($this->options['filter'], $data);
            unset($this->options['filter']);
        }
        $this->_before_write($data);
        return $data;
    }

    /**
     * 写入数据前的回调方法 包括新增和更新
     * @param $data
     */
    protected function _before_write(&$data)
    {
    }

    /**
     * 新增数据
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @param boolean $replace 是否replace
     * @return bool|int|string
     * @throws \Exception
     */
    public function add($data = '', $options = [], $replace = false)
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                // 重置数据
                $this->data = [];
            } else {
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
        // 数据处理
        $data = $this->_facade($data);

        // 分析表达式
        $options = $this->_parseOptions($options);
        if (false === $this->_before_insert($data, $options)) {
            return false;
        }

        // 写入数据到数据库
        $result = $this->db->insert($data, $options, $replace);
        if (false !== $result && is_numeric($result)) {
            $pk = $this->getPk();
            // 增加复合主键支持
            if (is_array($pk)) {
                return $result;
            }

            $insertId = $this->getLastInsID();
            if ($insertId) {
                // 自增主键返回插入ID
                $data[$pk] = $insertId;
                if (false === $this->_after_insert($insertId, $pk, $data, $options)) {
                    return false;
                }
                return $insertId;
            }
            if (false === $this->_after_insert($insertId, $pk, $data, $options)) {
                return false;
            }
        }
        return $result;
    }

    /**
     * 插入数据前的回调方法
     * @param $data
     * @param $options
     */
    protected function _before_insert(&$data, $options)
    {
    }

    /**
     * 插入成功后的回调方法
     * @param $pk
     * @param $pkName
     * @param $data
     * @param $options
     */
    protected function _after_insert($pk, $pkName, $data, $options)
    {

    }

    /**
     * 批量新增
     * @param $dataList
     * @param array $options
     * @param bool $replace
     * @return bool|int|string
     * @throws \Exception
     */
    public function addAll($dataList, $options = [], $replace = false)
    {
        if (empty($dataList)) {
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
        // 数据处理
        foreach ($dataList as $key => $data) {
            $dataList[$key] = $this->_facade($data);
        }
        // 分析表达式
        $options = $this->_parseOptions($options);
        // 写入数据到数据库
        $result = $this->db->insertAll($dataList, $options, $replace);
        if (false !== $result) {
            $insertId = $this->getLastInsID();
            if ($insertId) {
                return $insertId;
            }
        }
        return $result;
    }

    /**
     * 批量更新数据
     * @param $allData
     */
    public function saveAll($allData)
    {
        $sql = ''; //Sql
        $pk = $this->getPk();
        $tableName = $this->getTableName();

        $lists = []; //记录集$lists
        $ids = [];
        foreach ($allData as $data) {
            foreach ($data as $key => $value) {
                if ($pk === $key) {
                    $ids[] = $value;
                } else {
                    if (array_key_exists($key, $lists)) {
                        $lists[$key] .= sprintf("WHEN %u THEN '%s' ", $data[$pk], $value);
                    } else {
                        $lists[$key] = sprintf("WHEN %u THEN '%s' ", $data[$pk], $value);
                    }
                }
            }
        }

        foreach ($lists as $key => $value) {
            $sql .= sprintf("`%s` = CASE `%s` %s END,", $key, $pk, $value);
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s IN ( %s )', $tableName, rtrim($sql, ','), $pk, implode(',', $ids));

        M()->execute($sql);

        unset($sql);
    }

    /**
     * 通过Select方式添加记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $options 表达式
     * @return boolean
     */
    public function selectAdd($fields = '', $table = '', $options = [])
    {
        // 分析表达式
        $options = $this->_parseOptions($options);
        // 写入数据到数据库
        if (false === $result = $this->db->selectInsert($fields ?: $options['field'], $table ?: $this->getTableName(), $options)) {
            // 数据库插入操作失败
            $this->error = L('_OPERATION_WRONG_');
            return false;
        } else {
            // 插入成功
            return $result;
        }
    }

    /**
     * 保存数据
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @param bool $writeEvent
     * @return bool|int
     * @throws \Exception
     */
    public function save($data = '', $options = [], $writeEvent = true)
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                // 重置数据
                $this->data = [];
            } else {
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
        // 数据处理
        $data = $this->_facade($data);
        if (empty($data)) {
            // 没有数据则不执行
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
        // 分析表达式
        $options = $this->_parseOptions($options);
        $pk = $this->getPk();
        if (!isset($options['where'])) {
            // 如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($data[$pk])) {
                $where[$pk] = $data[$pk];
                unset($data[$pk]);
            } elseif (is_array($pk)) {
                // 增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $where[$field] = $data[$field];
                    } else {
                        // 如果缺少复合主键数据则不执行
                        $this->error = L('_OPERATION_WRONG_');
                        return false;
                    }
                    unset($data[$field]);
                }
            }
            if (!isset($where)) {
                // 如果没有任何更新条件则不执行
                $this->error = L('_OPERATION_WRONG_');
                return false;
            } else {
                $options['where'] = $where;
            }
        }

        if (is_array($options['where']) && isset($options['where'][$pk])) {
            $pkValue = $options['where'][$pk];
        }
        if (false === $this->_before_update($pk, $data, $options, $writeEvent)) {
            return false;
        }
        $result = $this->db->update($data, $options);
        if (false !== $result && is_numeric($result)) {
            if (isset($pkValue)) {
                $data[$pk] = $pkValue;
            }
            $this->_after_update($result, $pk, $data, $options, $writeEvent);
        }
        return $result;
    }

    /**
     * 更新数据前的回调方法
     * @param $pk
     * @param $data
     * @param $options
     * @param $writeEvent
     */
    protected function _before_update($pk, &$data, $options, $writeEvent)
    {
        $this->oldUpdateData = [];
        $this->newUpdateData = [];
        if ($options["model"] != "EventLog" && $writeEvent) {
            $oldData = $this->where($options["where"])->find();
            foreach ($data as $key => $value) {
                if ($oldData[$key] != $value) {
                    //仅记录变化字段
                    $this->oldUpdateData[$key] = $oldData[$key];
                    $this->newUpdateData[$key] = $value;
                }
            }
            $this->oldUpdateKey = $oldData[$pk];
        }
    }

    /**
     * 更新成功后的回调方法
     * @param $result
     * @param $pkName
     * @param $data
     * @param $options
     * @param $writeEvent
     */
    protected function _after_update($result, $pkName, $data, $options, $writeEvent)
    {

    }

    /**
     * 删除数据
     * @access public
     * @param mixed $options 表达式
     * @return mixed
     */
    public function delete($options = [])
    {
        $where = [];
        $pk = $this->getPk();
        if (empty($options) && empty($this->options['where'])) {
            // 如果删除条件为空 则删除当前数据对象所对应的记录
            if (!empty($this->data) && isset($this->data[$pk])) {
                return $this->delete($this->data[$pk]);
            } else {
                return false;
            }

        }
        if (is_numeric($options) || is_string($options)) {
            // 根据主键删除记录
            if (strpos($options, ',')) {
                $where[$pk] = ['IN', $options];
            } else {
                $where[$pk] = $options;
            }
            $this->options['where'] = $where;
        }
        // 根据复合主键删除记录
        if (is_array($options) && (count($options) > 0) && is_array($pk)) {
            $count = 0;
            foreach (array_keys($options) as $key) {
                if (is_int($key)) {
                    $count++;
                }

            }
            if (count($pk) == $count) {
                $i = 0;
                foreach ($pk as $field) {
                    $where[$field] = $options[$i];
                    unset($options[$i++]);
                }
                $this->options['where'] = $where;
            } else {
                return false;
            }
        }
        // 分析表达式
        $options = $this->_parseOptions();
        if (empty($options['where'])) {
            // 如果条件为空 不进行删除操作 除非设置 1=1
            return false;
        }
        if (is_array($options['where']) && isset($options['where'][$pk])) {
            $pkValue = $options['where'][$pk];
        }

        if (false === $this->_before_delete($pk, $options)) {
            return false;
        }
        $result = $this->db->delete($options);
        if (false !== $result && is_numeric($result)) {
            $data = [];
            if (isset($pkValue)) {
                $data[$pk] = $pkValue;
            }

            $this->_after_delete($result, $pk, $data, $options);
        }
        // 返回删除记录个数
        return $result;
    }

    /**
     * 删除数据前的回调方法
     * @param $pk
     * @param $options
     */
    protected function _before_delete($pk, $options)
    {
        if ($options["model"] != "EventLog") {
            $this->oldDeleteData = $this->where($options["where"])->select();
        }
    }

    /**
     * 删除成功后的回调方法
     * @param $result
     * @param $pkName
     * @param $data
     * @param $options
     */
    protected function _after_delete($result, $pkName, $data, $options)
    {

    }

    /**
     * 查询数据集
     * @access public
     * @param array $options 表达式参数
     * @return mixed
     */
    public function select($options = [])
    {
        $where = [];
        $pk = $this->getPk();
        if (is_string($options) || is_numeric($options)) {
            // 根据主键查询
            if (strpos($options, ',')) {
                $where[$pk] = ['IN', $options];
            } else {
                $where[$pk] = $options;
            }
            $this->options['where'] = $where;
        } elseif (is_array($options) && (count($options) > 0) && is_array($pk)) {
            // 根据复合主键查询
            $count = 0;
            foreach (array_keys($options) as $keyIndex) {
                if (is_int($keyIndex)) {
                    $count++;
                }

            }
            if (count($pk) == $count) {
                $i = 0;
                foreach ($pk as $field) {
                    $where[$field] = $options[$i];
                    unset($options[$i++]);
                }
                $this->options['where'] = $where;
            } else {
                return false;
            }
        } elseif (false === $options) {
            // 用于子查询 不查询只返回SQL
            $this->options['fetch_sql'] = true;
        }

        // 分析表达式
        $options = $this->_parseOptions();

        // 判断查询缓存
        $key = "";
        if (isset($options['cache'])) {
            $cache = $options['cache'];
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $data = S($key, '', $cache);
            if (false !== $data) {
                return $data;
            }
        }

        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        if (!empty($resultSet)) {
            // 有查询结果
            if (is_string($resultSet)) {
                return $resultSet;
            }

            $resultSet = array_map([$this, '_read_data'], $resultSet);
            $this->_after_select($resultSet, $options);
            if (isset($options['index'])) {
                // 对数据集进行索引
                $index = explode(',', $options['index']);
                $cols = [];
                foreach ($resultSet as $result) {
                    $_key = $result[$index[0]];
                    if (isset($index[1]) && isset($result[$index[1]])) {
                        $cols[$_key] = $result[$index[1]];
                    } else {
                        $cols[$_key] = $result;
                    }
                }
                $resultSet = $cols;
            }
        }

        if (isset($cache)) {
            S($key, $resultSet, $cache);
        }
        return $resultSet;
    }

    // 查询成功后的回调方法
    protected function _after_select(&$resultSet, $options)
    {
    }

    /**
     * 生成查询SQL 可用于子查询
     * @access public
     * @return string
     */
    public function buildSql()
    {
        return '( ' . $this->fetchSql(true)->select() . ' )';
    }

    /**
     * 分析表达式
     * @access protected
     * @param array $options 表达式参数
     * @return array
     */
    protected function _parseOptions($options = [])
    {
        if (is_array($options)) {
            $options = array_merge($this->options, $options);
        }

        if (!isset($options['table'])) {
            // 自动获取表名
            $options['table'] = $this->getTableName();
            $fields = $this->fields;
        } else {
            // 指定数据表 则重新获取字段列表 但不支持类型检测
            $fields = $this->getDbFields();
        }

        // 数据表别名
        if (!empty($options['alias'])) {
            $options['table'] .= ' ' . $options['alias'];
        }

        // 记录操作的模型名称
        $options['model'] = $this->name;

        // 字段类型验证
        if (isset($options['where']) && is_array($options['where']) && !empty($fields) && !isset($options['join'])) {
            // 对数组查询条件进行字段类型检查
            foreach ($options['where'] as $key => $val) {
                $key = trim($key);
                if (in_array($key, $fields, true)) {
                    $this->_parseType($options['where'], $key);
                }
            }
        } else {
            // 包含join，数组查询条件进行字段类型独立检查
            if (isset($options['where']) && is_array($options['where']) && !empty($fields)) {
                foreach ($options['where'] as $keyName => $val) {
                    $keyName = trim($keyName);
                    if (strpos($keyName, '.')) {
                        $keyNameArray = explode(".", $keyName);
                        $key = $keyNameArray[1];
                    } else {
                        $key = $keyName;
                    }
                    if (in_array($key, $fields, true)) {
                        $this->_parseType($options['where'], $key, true, $keyName);
                    }
                }
            }
        }

        // 查询过后清空sql表达式组装 避免影响下次查询
        $this->options = [];

        // 表达式过滤
        $this->_options_filter($options);

        return $options;
    }

    /**
     * 表达式过滤回调方法
     * @param $options
     */
    protected function _options_filter(&$options)
    {
    }

    /**
     * 数据类型检测
     * @access protected
     * @param mixed $data 数据
     * @param string $key 字段名
     * @param bool $hasJoin
     * @param string $joinKey
     */
    protected function _parseType(&$data, $key, $hasJoin = false, $joinKey = "")
    {
        $valKey = $hasJoin ? $joinKey : $key;
        if (!isset($this->options['bind'][':' . $key]) && isset($this->fields['_type'][$key])) {
            $fieldType = strtolower($this->fields['_type'][$key]);
            if (false !== strpos($fieldType, 'enum')) {
                // 支持ENUM类型优先检测
                return;
            } elseif (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')) {
                $data[$valKey] = $this->_parseTypeValue($data, 'int', $valKey);

            } elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')) {
                $data[$valKey] = $this->_parseTypeValue($data, 'float', $valKey);
            } elseif (false !== strpos($fieldType, 'bool')) {
                $data[$valKey] = $this->_parseTypeValue($data, 'bool', $valKey);
            }
        }
    }

    /**
     * 处理类型检测后值
     * @param $data
     * @param $type
     * @param $valKey
     * @return array|int
     */
    protected function _parseTypeValue($data, $type, $valKey)
    {
        $tmpVal = $data[$valKey];
        if (is_array($tmpVal)) {
            // 数组只处理 EQ，NEQ， GT，EGT， LT，ELT 类型
            if (in_array(strtolower($tmpVal[0]), ["eq", "neq", "gt", "egt", "lt", "elt"])) {
                $tmpVal[1] = $this->_parseTypeFormatValue($tmpVal[1], $type);
            }
        } else if (is_scalar($tmpVal)) {
            // 值是有效值
            $tmpVal = $this->_parseTypeFormatValue($tmpVal, $type);
        }
        return $tmpVal;
    }

    /**
     * 格式化数值
     * @param $val
     * @param $type
     * @return bool|float|int
     */
    protected function _parseTypeFormatValue($val, $type)
    {
        switch ($type) {
            default:
            case "enum":
                return $val;
            case "int":
                return intval($val);
            case "float":
                return floatval($val);
            case "bool":
                return (bool)$val;
        }
    }

    /**
     * 数据读取后的处理
     * @access protected
     * @param array $data 当前数据
     * @return array
     */
    protected function _read_data($data)
    {
        // 检查字段映射
        if (!empty($this->_map) && C('READ_DATA_MAP')) {
            foreach ($this->_map as $key => $val) {
                if (isset($data[$val])) {
                    $data[$key] = $data[$val];
                    unset($data[$val]);
                }
            }
        }
        return $data;
    }

    /**
     * 查询数据
     * @access public
     * @param mixed $options 表达式参数
     * @return mixed
     */
    public function find($options = [])
    {
        $where = [];
        if (is_numeric($options) || is_string($options)) {
            $where[$this->getPk()] = $options;
            $this->options['where'] = $where;
        }
        // 根据复合主键查找记录
        $pk = $this->getPk();
        if (is_array($options) && (count($options) > 0) && is_array($pk)) {
            // 根据复合主键查询
            $count = 0;
            foreach (array_keys($options) as $keyIndex) {
                if (is_int($keyIndex)) {
                    $count++;
                }

            }
            if (count($pk) == $count) {
                $i = 0;
                foreach ($pk as $field) {
                    $where[$field] = $options[$i];
                    unset($options[$i++]);
                }
                $this->options['where'] = $where;
            } else {
                return false;
            }
        }
        // 总是查找一条记录
        $this->options['limit'] = 1;
        // 分析表达式
        $options = $this->_parseOptions();
        // 判断查询缓存
        $key = "";
        if (isset($options['cache'])) {
            $cache = $options['cache'];
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $data = S($key, '', $cache);
            if (false !== $data) {
                $this->data = $data;
                return $data;
            }
        }
        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        if (empty($resultSet)) {
            // 查询结果为空
            return null;
        }
        if (is_string($resultSet)) {
            return $resultSet;
        }

        // 读取数据后的处理
        $data = $this->_read_data($resultSet[0]);
        $this->_after_find($data, $options);
        if (!empty($this->options['result'])) {
            return $this->returnResult($data, $this->options['result']);
        }
        $this->data = $data;
        if (isset($cache)) {
            S($key, $data, $cache);
        }
        return $this->data;
    }

    // 查询成功的回调方法
    protected function _after_find(&$result, $options)
    {
    }

    protected function returnResult($data, $type = '')
    {
        if ($type) {
            if (is_callable($type)) {
                return call_user_func($type, $data);
            }
            switch (strtolower($type)) {
                case 'json':
                    return json_encode($data);
                case 'xml':
                    return xml_encode($data);
            }
        }
        return $data;
    }

    /**
     * 处理字段映射
     * @access public
     * @param array $data 当前数据
     * @param integer $type 类型 0 写入 1 读取
     * @return array
     */
    public function parseFieldsMap($data, $type = 1)
    {
        // 检查字段映射
        if (!empty($this->_map)) {
            foreach ($this->_map as $key => $val) {
                if (1 == $type) {
                    // 读取
                    if (isset($data[$val])) {
                        $data[$key] = $data[$val];
                        unset($data[$val]);
                    }
                } else {
                    if (isset($data[$key])) {
                        $data[$val] = $data[$key];
                        unset($data[$key]);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field 字段名
     * @param string $value 字段值
     * @return bool|int
     * @throws \Exception
     */
    public function setField($field, $value = '')
    {
        if (is_array($field)) {
            $data = $field;
        } else {
            $data[$field] = $value;
        }
        return $this->save($data);
    }

    /**
     * 字段值增长
     * @access public
     * @param string $field 字段名
     * @param integer $step 增长值
     * @param integer $lazyTime 延时时间(s)
     * @return bool|int
     * @throws \Exception
     */
    public function setInc($field, $step = 1, $lazyTime = 0)
    {
        if ($lazyTime > 0) {
            // 延迟写入
            $condition = $this->options['where'];
            $guid = md5($this->name . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, $step, $lazyTime);
            if (empty($step)) {
                return true; // 等待下次写入
            } elseif ($step < 0) {
                $step = '-' . $step;
            }
        }
        return $this->setField($field, ['exp', $field . '+' . $step]);
    }

    /**
     * 字段值减少
     * @access public
     * @param string $field 字段名
     * @param integer $step 减少值
     * @param integer $lazyTime 延时时间(s)
     * @return bool|int
     * @throws \Exception
     */
    public function setDec($field, $step = 1, $lazyTime = 0)
    {
        if ($lazyTime > 0) {
            // 延迟写入
            $condition = $this->options['where'];
            $guid = md5($this->name . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, -$step, $lazyTime);
            if (empty($step)) {
                return true; // 等待下次写入
            } elseif ($step > 0) {
                $step = '-' . $step;
            }
        }
        return $this->setField($field, ['exp', $field . '-' . $step]);
    }

    /**
     * 延时更新检查 返回false表示需要延时
     * 否则返回实际写入的数值
     * @access public
     * @param string $guid 写入标识
     * @param integer $step 写入步进值
     * @param integer $lazyTime 延时时间(s)
     * @return false|integer
     */
    protected function lazyWrite($guid, $step, $lazyTime)
    {
        if (false !== ($value = S($guid))) {
            // 存在缓存写入数据
            if (NOW_TIME > S($guid . '_time') + $lazyTime) {
                // 延时更新时间到了，删除缓存数据 并实际写入数据库
                S($guid, null);
                S($guid . '_time', null);
                return $value + $step;
            } else {
                // 追加数据到缓存
                S($guid, $value + $step);
                return false;
            }
        } else {
            // 没有缓存数据
            S($guid, $step);
            // 计时开始
            S($guid . '_time', NOW_TIME);
            return false;
        }
    }

    /**
     * 获取一条记录的某个字段值
     * @access public
     * @param string $field 字段名
     * @param null $sepa 字段数据间隔符号 NULL返回数组
     * @return array|mixed|null
     */
    public function getField($field, $sepa = null)
    {
        $options['field'] = $field;
        $options = $this->_parseOptions($options);
        $key = "";
        // 判断查询缓存
        if (isset($options['cache'])) {
            $cache = $options['cache'];
            $key = is_string($cache['key']) ? $cache['key'] : md5($sepa . serialize($options));
            $data = S($key, '', $cache);
            if (false !== $data) {
                return $data;
            }
        }
        $field = trim($field);
        if (strpos($field, ',') && false !== $sepa) {
            // 多字段
            if (!isset($options['limit'])) {
                $options['limit'] = is_numeric($sepa) ? $sepa : '';
            }
            $resultSet = $this->db->select($options);
            if (!empty($resultSet)) {
                if (is_string($resultSet)) {
                    return $resultSet;
                }
                $_field = explode(',', $field);
                $field = array_keys($resultSet[0]);
                $key1 = array_shift($field);
                $key2 = array_shift($field);
                $cols = [];
                $count = count($_field);
                foreach ($resultSet as $result) {
                    $name = $result[$key1];
                    if (2 == $count) {
                        $cols[$name] = $result[$key2];
                    } else {
                        $cols[$name] = is_string($sepa) ? implode($sepa, array_slice($result, 1)) : $result;
                    }
                }
                if (isset($cache)) {
                    S($key, $cols, $cache);
                }
                return $cols;
            }
        } else {
            // 查找一条记录
            // 返回数据个数
            if (true !== $sepa) {
                // 当sepa指定为true的时候 返回所有数据
                $options['limit'] = is_numeric($sepa) ? $sepa : 1;
            }
            $result = $this->db->select($options);
            $array = [];
            if (!empty($result)) {
                if (is_string($result)) {
                    return $result;
                }
                if (true !== $sepa && 1 == $options['limit']) {
                    $data = reset($result[0]);
                    if (isset($cache)) {
                        S($key, $data, $cache);
                    }
                    return $data;
                }
                foreach ($result as $val) {
                    $array[] = reset($val);
                }
                if (isset($cache)) {
                    S($key, $array, $cache);
                }
                return $array;
            }
        }
        return null;
    }

    /**
     * 创建数据对象 但不保存到数据库
     * @access public
     * @param mixed $data 创建数据
     * @param string $type 状态
     * @return mixed
     */
    public function create($data = '', $type = '')
    {
        // 如果没有传值默认取POST数据
        if (empty($data)) {
            $data = \request()->post();
        } elseif (is_object($data)) {
            $data = get_object_vars($data);
        }
        // 验证数据
        if (empty($data) || !is_array($data)) {
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }

        // 状态
        $type = $type ?: (!empty($data[$this->getPk()]) ? self::MODEL_UPDATE : self::MODEL_INSERT);
        // 检查字段映射
        $data = $this->parseFieldsMap($data, 0);

        // 检测提交字段的合法性
        if (isset($this->options['field'])) {
            // $this->field('field1,field2...')->create()
            $fields = $this->options['field'];
            unset($this->options['field']);
        } elseif (self::MODEL_INSERT == $type && isset($this->insertFields)) {
            $fields = $this->insertFields;
        } elseif (self::MODEL_UPDATE == $type && isset($this->updateFields)) {
            $fields = $this->updateFields;
        }

        if (isset($fields)) {
            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }
            // 判断令牌验证字段
            if (C('TOKEN_ON')) {
                $fields[] = C('TOKEN_NAME', null, '__hash__');
            }

            foreach ($data as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($data[$key]);
                }
            }
        }

        // 数据自动验证
        if (!$this->autoValidation($data, $type)) {
            return false;
        }

        // 表单令牌验证
        if (!$this->autoCheckToken($data)) {
            $this->error = L('_TOKEN_ERROR_');
            return false;
        }

        // 验证完成生成数据对象
        if ($this->autoCheckFields) {
            // 开启字段检测 则过滤非法字段数据
            $fields = $this->getDbFields();
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($data[$key]);
                } elseif (MAGIC_QUOTES_GPC && is_string($val)) {
                    $data[$key] = stripslashes($val);
                }
            }
        }

        // 创建完成对数据进行自动处理
        $this->autoOperation($data, $type);

        // 创建完成对数据进行验证处理
        if (!empty($this->_validate_after_auto) && !$this->autoValidation($data, $type, $this->_validate_after_auto)) {
            return false;
        }

        // 赋值当前数据对象
        $this->data = $data;
        // 返回创建的数据以供其他调用
        return $data;
    }

    /**
     * 自动表单令牌验证
     * @param $data
     * @return bool
     */
    public function autoCheckToken($data)
    {
        // 支持使用token(false) 关闭令牌验证
        if (isset($this->options['token']) && !$this->options['token']) {
            return true;
        }

        if (C('TOKEN_ON')) {
            $name = C('TOKEN_NAME', null, '__hash__');
            if (!isset($data[$name]) || !isset($_SESSION[$name])) {
                // 令牌数据无效
                return false;
            }

            // 令牌验证
            list($key, $value) = explode('_', $data[$name]);
            if (isset($_SESSION[$name][$key]) && $value && $_SESSION[$name][$key] === $value) {
                // 防止重复提交
                unset($_SESSION[$name][$key]); // 验证完成销毁session
                return true;
            }
            // 开启TOKEN重置
            if (C('TOKEN_RESET')) {
                unset($_SESSION[$name][$key]);
            }

            return false;
        }
        return true;
    }

    /**
     * 使用正则验证数据
     * @access public
     * @param string $value 要验证的数据
     * @param string $rule 验证规则
     * @return boolean
     */
    public function regex($value, $rule)
    {
        $validate = [
            'require' => '/\S+/',
            'email' => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url' => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
            'currency' => '/^\d+(\.\d+)?$/',
            'number' => '/^\d+$/',
            'zip' => '/^\d{6}$/',
            'integer' => '/^[-\+]?\d+$/',
            'double' => '/^[-\+]?\d+(\.\d+)?$/',
            'english' => '/^[A-Za-z]+$/',
        ];
        // 检查是否有内置的正则表达式
        if (isset($validate[strtolower($rule)])) {
            $rule = $validate[strtolower($rule)];
        }

        return preg_match($rule, $value) === 1;
    }

    /**
     * 自动表单处理
     * @access public
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return mixed
     */
    private function autoOperation(&$data, $type)
    {
        if (isset($this->options['auto']) && false === $this->options['auto']) {
            // 关闭自动完成
            return $data;
        }
        if (!empty($this->options['auto'])) {
            $_auto = $this->options['auto'];
            unset($this->options['auto']);
        } elseif (!empty($this->_auto)) {
            $_auto = $this->_auto;
        }
        // 自动填充
        if (isset($_auto)) {
            foreach ($_auto as $auto) {
                // 填充因子定义格式
                // array('field','填充内容','填充条件','附加规则',[额外参数])
                if ($auto[2] !== 0 && empty($auto[2])) {
                    $auto[2] = self::MODEL_INSERT;
                }

                // 默认为新增的时候自动填充
                if ($type == $auto[2] || self::MODEL_BOTH == $auto[2] || (self::EXISTS_VALIDATE == $auto[2] && array_key_exists($auto[0], $data))) {
                    if (empty($auto[3])) {
                        $auto[3] = 'string';
                    }

                    switch (trim($auto[3])) {
                        case 'function'://  使用函数进行填充 字段的值作为参数
                        case 'callback':    // 使用回调方法
                            $args = isset($auto[4]) ? (array)$auto[4] : [];
                            if (isset($data[$auto[0]])) {
                                array_unshift($args, $data[$auto[0]]);
                            }
                            if ('function' == $auto[3]) {
                                $data[$auto[0]] = call_user_func_array($auto[1], $args);
                                if ($auto[1] === 'strtotime' && $data[$auto[0]] < 0) {
                                    // 时区问题会导致时间戳偏移成负值
                                    $data[$auto[0]] = 0;
                                }
                            } else {
                                $data[$auto[0]] = call_user_func_array([&$this, $auto[1]], $args);
                            }
                            break;
                        case 'callback_with_data':
                            $args = isset($auto[4]) ? (array)$auto[4] : [];
                            array_unshift($args, $auto[0]);
                            array_unshift($args, $data);
                            $data[$auto[0]] = call_user_func_array([&$this, $auto[1]], $args);
                            break;
                        case 'function_with_data':
                            // 使用函数进行填充，传入数组值和字段名
                            $data[$auto[0]] = call_user_func_array($auto[1], ['data' => $data, "field" => $auto[0]]);
                            break;
                        case 'field':    // 用其它字段的值进行填充
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'ignore':    // 为空忽略
                            if ($auto[1] === $data[$auto[0]]) {
                                unset($data[$auto[0]]);
                            }

                            break;
                        case 'string':
                        default:    // 默认作为字符串填充
                            $data[$auto[0]] = $auto[1];
                    }
                    if (isset($data[$auto[0]]) && false === $data[$auto[0]]) {
                        unset($data[$auto[0]]);
                    }

                }
            }
        }
        return $data;
    }

    /**
     * 自动表单验证
     * @access protected
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @param array $validate
     * @return bool
     */
    protected function autoValidation($data, $type, $validate = [])
    {
        if (isset($this->options['validate']) && false === $this->options['validate']) {
            // 关闭自动验证
            return true;
        }

        if (!empty($validate)) {
            $_validate = $validate;
        } elseif (!empty($this->options['validate'])) {
            $_validate = $this->options['validate'];
            unset($this->options['validate']);
        } elseif (!empty($this->_validate)) {
            $_validate = $this->_validate;
        }

        // 属性验证
        if (isset($_validate)) {
            // 如果设置了数据自动验证则进行数据验证
            if ($this->patchValidate) {
                // 重置验证错误信息
                $this->error = [];
            }
            foreach ($_validate as $key => $val) {
                // 验证因子定义格式
                // array(field,rule,message,condition,type,when,params)
                // 判断是否需要执行验证
                if (empty($val[5]) || (self::MODEL_BOTH == $val[5] && $type < 3) || $val[5] == $type) {
                    if (0 == strpos($val[2], '{%') && strpos($val[2], '}')) // 支持提示信息的多语言 使用 {%语言定义} 方式
                    {
                        $langMsg = L(substr($val[2], 2, -1));
                    } else {
                        $langMsg = $val[2];
                    }

                    $val[2] = $this->getRuleMsg($val[0], $val[4], $val[1], $langMsg);

                    $val[3] = $val[3] ?? self::EXISTS_VALIDATE;
                    $val[4] = $val[4] ?? 'regex';
                    // 判断验证条件
                    switch ($val[3]) {
                        case self::MUST_VALIDATE:    // 必须验证 不管表单是否有设置该字段
                            if (false === $this->_validationField($data, $val)) {
                                return false;
                            }
                            break;
                        case self::VALUE_VALIDATE:    // 值不为空的时候才验证
                            if ('' != trim($data[$val[0]])) {
                                if (false === $this->_validationField($data, $val)) {
                                    return false;
                                }
                            }
                            break;
                        default:    // 默认表单存在该字段就验证
                            if (isset($data[$val[0]])) {
                                if (false === $this->_validationField($data, $val)) {
                                    return false;
                                }
                            }

                    }
                }
            }

            // 批量验证的时候最后返回错误
            if (!empty($this->error)) {
                return false;
            }

        }
        return true;
    }


    /**
     * 获取验证规则的错误提示信息
     * @access protected
     * @param string $attribute 字段英文名
     * @param string $ruleName 字段描述名
     * @param mixed $rule 验证规则数据
     * @param string $massge 自定义消息
     * @return string
     */
    protected function getRuleMsg($attribute, $ruleName, $rule, $massge = '')
    {
        //消息支持类型
        if ($massge) {
            $msg = $massge;
        } else {
            if (array_key_exists($ruleName, $this->typeMsg)) {
                //内置消息提示
                $msg = L($this->typeMsg[$ruleName]);

                if (is_string($msg) && false !== strpos($msg, ':')) {

                    // 变量替换
                    if (is_string($rule) && strpos($rule, ',')) {
                        $array = array_pad(explode(',', $rule), 3, '');
                    } else {
                        $array = array_pad([], 3, '');
                    }

                    if (is_array($rule)) {
                        $rule = join(",", $rule);
                    }

                    $msg = str_replace(
                        [':attribute', ':rule', ':1', ':2', ':3'],
                        [$attribute, (string)$rule, $array[0], $array[1], $array[2]],
                        $msg);
                }
            } else {
                $msg = '';
            }
        }

        return '[' . $this->name . '] ' . $msg;
    }

    /**
     * 验证表单字段 支持批量验证
     * 如果批量验证返回错误的数组信息
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return bool|void
     */
    protected function _validationField($data, $val)
    {

        if ($this->patchValidate && isset($this->error[$val[0]])) {
            //当前字段已经有规则验证没有通过
            return;
        }

        if (false === $this->_validationFieldItem($data, $val)) {
            if (empty($this->errorCode)) {
                $this->errorCode = ErrorCode::MODEL_VALIDATE_ERROR;
            }
            if ($this->patchValidate) {
                $this->error[$val[0]] = $val[2];
            } else {
                $this->error = $val[2];
                return false;
            }
        }
        return;
    }

    /**
     * 根据验证因子验证字段
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function _validationFieldItem($data, $val)
    {
        switch (strtolower(trim($val[4]))) {
            case 'function':// 使用函数进行验证
            case 'callback':    // 调用方法进行验证
                $args = isset($val[6]) ? (array)$val[6] : [];
                if (is_string($val[0]) && strpos($val[0], ',')) {
                    $val[0] = explode(',', $val[0]);
                }

                if (is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field) {
                        $_data[$field] = $data[$field];
                    }

                    array_unshift($args, $_data);
                } else {
                    array_unshift($args, $data[$val[0]]);
                }
                if ('function' == $val[4]) {
                    return call_user_func_array($val[1], $args);
                } else {
                    return call_user_func_array([&$this, $val[1]], $args);
                }
            case 'confirm':    // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            case 'unique':    // 验证某个值是否唯一
                if (is_string($val[0]) && strpos($val[0], ',')) {
                    $val[0] = explode(',', $val[0]);
                }
                $pk = $this->getPk();
                $map = [];
                if (is_array($val[0])) {
                    // 支持多个字段验证
                    $needCheckFields = [];
                    foreach ($val[0] as $field) {
                        if (!array_key_exists($field, $data)) {
                            // 多个字段如果值不存在，则去数据库查找，更新
                            if (array_key_exists($pk, $data)) {
                                $needCheckFields[] = $field;
                            } else {
                                // 主键不存直接返回 false
                                return false;
                            }
                        } else {
                            $map[$field] = $data[$field];
                        }
                    }
                    // 检查字段
                    if (count($needCheckFields) > 0) {
                        $checkData = $this->where([$pk => $data[$pk]])->field($needCheckFields)->find();
                        if (is_array($checkData)) {
                            foreach ($needCheckFields as $checkField) {
                                if (array_key_exists($checkField, $checkData)) {
                                    $map[$checkField] = $checkData[$checkField];
                                }
                            }
                        }
                    }
                } else {
                    $map[$val[0]] = $data[$val[0]];
                }
                if (!empty($data[$pk]) && is_string($pk)) {
                    // 完善编辑的时候验证唯一
                    $map[$pk] = ['neq', $data[$pk]];
                }
                $options = $this->options;
                $uniqueFindData = $this->where($map)->find();

                if ($uniqueFindData) {
                    $this->errorCode = ErrorCode::DATA_ALREADY_EXISTS;
                    $this->checkUniqueExitData = $uniqueFindData;
                    return false;
                }
                $this->options = $options;
                return true;
            default:    // 检查附加规则
                if (array_key_exists($val[0], $data)) {
                    //判断当前key值是否存在
                    return $this->check($data[$val[0]], $val[1], $val[4], $data);
                } else {
                    return false;
                }
        }
    }

    /**
     * 验证数据 支持 in between equal length regex expire ip_allow ip_deny
     * @access public
     * @param string $value 验证数据
     * @param mixed $rule 验证表达式
     * @param string $type 验证方式 默认为正则验证
     * @param array $data 新增数值
     * @return boolean
     */
    public function check($value, $rule, $type = 'regex', $data = [])
    {
        $type = trim($type);
        switch ($type) {
            case 'in':// 验证是否在某个指定范围之内 逗号分隔字符串或者数组
            case 'notin':
                $range = is_array($rule) ? $rule : explode(',', $rule);
                return 'in' == $type ? in_array($value, $range) : !in_array($value, $range);
            case 'between':// 验证是否在某个范围
            case 'notbetween':    // 验证是否不在某个范围
                if (is_array($rule)) {
                    $min = $rule[0];
                    $max = $rule[1];
                } else {
                    list($min, $max) = explode(',', $rule);
                }
                return 'between' == $type ? $value >= $min && $value <= $max : $value < $min || $value > $max;
            case 'equal':// 验证是否等于某个值
            case 'notequal':    // 验证是否等于某个值
                return 'equal' == $type ? $value == $rule : $value != $rule;
            case 'length':    // 验证长度
                $length = mb_strlen($value, 'utf-8');     // 当前数据长度
                if (strpos($rule, ',')) {
                    // 长度区间
                    list($min, $max) = explode(',', $rule);
                    return $length >= $min && $length <= $max;
                } else {
                    // 指定长度
                    return $length == $rule;
                }
            case 'expire':
                list($start, $end) = explode(',', $rule);
                if (!is_numeric($start)) {
                    $start = strtotime($start);
                }

                if (!is_numeric($end)) {
                    $end = strtotime($end);
                }
                return NOW_TIME >= $start && NOW_TIME <= $end;
            case 'ip_allow':    // IP 操作许可验证
                return in_array(get_client_ip(), explode(',', $rule));
            case 'ip_deny':    // IP 操作禁止验证
                return !in_array(get_client_ip(), explode(',', $rule));
            case 'array':
                // 是否为数组
                return is_array($value);
            case 'number':
                return is_numeric($value);
            case 'integer':
                // 是否为整型
                return $this->filter($value, FILTER_VALIDATE_INT);
            case 'require':
                // 必须
                return !empty($value) || '0' == $value;
            case 'accepted':
                // 接受
                return in_array($value, ['1', 'on', 'yes']);
            case 'date':
                // 是否是一个有效日期
                if (!empty($value)) {
                    return false !== strtotime($value);
                }
                return false;
            case 'phone':
                return check_tel_number($value);
            case 'password_strength':
                if (strlen($value) < 8) {
                    // 密码长度必须大于8位
                    return false;
                }

                $score = 0;

                if (preg_match('/[0-9]+/', $value) === 1) {
                    //包含数字
                    $score++;
                }

                if (preg_match('/[a-z]+/', $value) === 1) {
                    //包含小写字母
                    $score++;
                }

                if (preg_match('/[A-Z]+/', $value) === 1) {
                    //包含大写写字母
                    $score++;
                }

                if (preg_match('/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]+/', $value) === 1) {
                    //包含特殊字符
                    $score++;
                }

                //判断密码强度，必须大于三分
                if ($score < 3) {
                    $this->errorCode = ErrorCode::PASSWORD_STRENGTH_NOT_MATCH;
                    return false;
                } else {
                    return true;
                }
                break;
            case 'activeUrl':
                // 是否为有效的网址
                return checkdnsrr($value);
            case 'alpha':
                // 只允许字母
                return $this->regex($value, '/^[A-Za-z]+$/');
            case 'alphaNum':
                // 只允许字母和数字
                return $this->regex($value, '/^[A-Za-z0-9]+$/');
            case 'alphaDash':
                // 只允许字母、数字和下划线 破折号
                return $this->regex($value, '/^[A-Za-z0-9\-\_]+$/');
            case 'chs':
                // 只允许汉字
                return $this->regex($value, '/^[\x{4e00}-\x{9fa5}]+$/u');
            case 'chsAlpha':
                // 只允许汉字、字母
                return $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u');
            case 'chsAlphaNum':
                // 只允许汉字、字母和数字
                return $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u');
            case 'chsDash':
                // 只允许汉字、字母、数字和下划线_及破折号-
                return $this->regex($value, '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9\_\-]+$/u');
            case 'AuthMethod':
                // 只允许字母和下划线_及破折号-
                return $this->regex($value, '/^[A-Za-z\-\_]+\/+[A-Za-z\-\_]+\/+[A-Za-z\-\_]+$/u');
            case 'ip':
                // 是否为IP地址
                return $this->filter($value, [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6]);
            case 'url':
                // 是否为一个URL地址
                return $this->filter($value, FILTER_VALIDATE_URL);
            case 'float':
                // 是否为float
                return $this->filter($value, FILTER_VALIDATE_FLOAT);
            case 'email':
                // 是否为邮箱地址
                return $this->filter($value, FILTER_VALIDATE_EMAIL);
            case 'boolean':
                // 是否为布尔值
                return in_array($value, [true, false, 0, 1, '0', '1'], true);
            default:    // 默认使用正则验证 可以使用验证类中定义的验证名称
                // 检查附加规则
                return $this->regex($value, $rule);
        }
    }

    /**
     * 使用filter_var方式验证
     * @access protected
     * @param mixed $value 字段值
     * @param mixed $rule 验证规则
     * @return bool
     */
    protected function filter($value, $rule)
    {
        if (is_string($rule) && strpos($rule, ',')) {
            list($rule, $param) = explode(',', $rule);
        } elseif (is_array($rule)) {
            $param = $rule[1] ?? null;
            $rule = $rule[0];
        } else {
            $param = null;
        }
        return false !== filter_var($value, is_int($rule) ? $rule : filter_id($rule), $param);
    }

    /**
     * @param $sql
     * @param ...$parse
     * @return mixed
     */
    public function query($sql, $parse = false)
    {
        $sql = $this->parseSql($sql, $parse);
        return $this->db->query($sql);
    }

    /**
     * 执行SQL语句
     * @access public
     * @param string $sql SQL指令
     * @param mixed $parse 是否需要解析SQL
     * @return int
     */
    public function execute($sql, $parse = false)
    {
        $sql = $this->parseSql($sql, $parse);
        return $this->db->execute($sql);
    }

    /**
     * 解析SQL语句
     * @access public
     * @param string $sql SQL指令
     * @param mixed $parse 是否需要解析SQL
     * @return string
     */
    protected function parseSql($sql, $parse)
    {
        // 分析表达式
        if (true === $parse) {
            $options = $this->_parseOptions();
            $sql = $this->db->parseSql($sql, $options);
        } elseif (is_array($parse)) {
            // SQL预处理
            $parse = array_map([$this->db, 'escapeString'], $parse);
            $sql = vsprintf($sql, $parse);
        } else {
            $sql = strtr($sql, ['__TABLE__' => $this->getTableName(), '__PREFIX__' => $this->tablePrefix]);
            $prefix = $this->tablePrefix;
            $sql = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $sql);
        }
        $this->db->setModel($this->name);
        return $sql;
    }

    /**
     * 切换当前的数据库连接
     * @access public
     * @param integer $linkNum 连接序号
     * @param mixed $config 数据库连接信息
     * @param boolean $force 强制重新连接
     * @return $this|Db|void
     * @throws \Exception
     */
    public function db($linkNum = 0, $config = [], $force = false)
    {
        if ('' === $linkNum && $this->db) {
            return $this->db;
        }

        if (!isset($this->_db[$linkNum]) || $force) {
            // 创建一个新的实例
            if (!empty($config) && is_string($config) && false === strpos($config, '/')) {
                // 支持读取配置参数
                $config = C($config);
            }
            $this->_db[$linkNum] = Db::getInstance($config);
        } elseif (null === $config) {
            $this->_db[$linkNum]->close(); // 关闭数据库连接
            unset($this->_db[$linkNum]);
            return;
        }

        // 切换数据库连接
        $this->db = $this->_db[$linkNum];
        $this->_after_db();
        // 字段检测
        if (!empty($this->name) && $this->autoCheckFields) {
            $this->_checkTableInfo();
        }

        return $this;
    }

    // 数据库切换后回调方法
    protected function _after_db()
    {
    }

    /**
     * 得到当前的数据对象名称
     * @access public
     * @return string
     */
    public function getModelName()
    {
        if (empty($this->name)) {
            $name = substr(get_class($this), 0, -strlen(C('DEFAULT_M_LAYER')));
            if ($pos = strrpos($name, '\\')) {
                //有命名空间
                $this->name = substr($name, $pos + 1);
            } else {
                $this->name = $name;
            }
        }
        return $this->name;
    }

    /**
     * 得到完整的数据表名
     * @access public
     * @return string
     */
    public function getTableName()
    {
        if (empty($this->trueTableName)) {
            $tableName = !empty($this->tablePrefix) ? $this->tablePrefix : '';
            if (!empty($this->tableName)) {
                $tableName .= $this->tableName;
            } else {
                $tableName .= parse_name($this->name);
            }
            $this->trueTableName = strtolower($tableName);
        }
        return (!empty($this->dbName) ? $this->dbName . '.' : '') . $this->trueTableName;
    }

    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
    {
        $this->db->startTrans();
        return;
    }

    /**
     * 提交事务
     * @access public
     * @return boolean
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     */
    public function rollback()
    {
        return $this->db->rollback();
    }

    /**
     * 返回模型的错误信息
     * @access public
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 返回模型错误码
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 获取重复的记录数据
     * @return array
     */
    public function getCheckUniqueExitData()
    {
        return $this->checkUniqueExitData;
    }

    /**
     * 还原默认值
     */
    public function resetDefault()
    {
        //清除上次操作错误
        $this->error = '';
        $this->errorCode = 0;
        $this->successMsg = '';
        $this->options = [];
        $this->_resData = [];
        $this->appendCustomField = [];
        $this->queryModuleLfetJoinRelation = [];
        $this->queryModuleHorizontalRelation = [];
        $this->queryModuleEntityRelation = [];
        $this->queryModuleHasManyRelation = [];
        $this->queryModuleRelationFields = [];
        $this->queryModuleFieldDict = [];
        $this->queryModulePrimaryKeyIds = [];
        $this->queryComplexModuleMapping = [];
        $this->queryComplexCustomFieldMapping = [];
        $this->queryComplexHorizontalCustomFieldMapping = [];
        $this->queryComplexRelationCustomFields = [];
        $this->complexFilterRelatedModule = [];
        $this->queryModuleRelation = [];
        $this->oldUpdateData = [];
        $this->newUpdateData = [];
    }

    /**
     * 返回成功信息
     * @return string
     */
    public function getSuccessMessage()
    {
        return $this->successMsg;
    }

    /**
     * 返回数据库的错误信息
     * @access public
     * @return string
     */
    public function getDbError()
    {
        return $this->db->getError();
    }

    /**
     * 返回最后插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
    {
        return $this->db->getLastInsID();
    }

    /**
     * 返回最后执行的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->db->getLastSql($this->name);
    }

    // 鉴于getLastSql比较常用 增加_sql 别名
    public function _sql()
    {
        return $this->getLastSql();
    }

    /**
     * 获取主键名称
     * @access public
     * @return string
     */
    public function getPk()
    {
        return $this->pk;
    }

    /**
     * 获取数据表字段信息
     * @return array|bool
     */
    public function getDbFields()
    {
        if (isset($this->options['table'])) {
            // 动态指定表名
            if (is_array($this->options['table'])) {
                $table = key($this->options['table']);
            } else {
                $table = $this->options['table'];
                if (strpos($table, ')')) {
                    // 子查询
                    return false;
                }
            }

            $fields = $this->flush($table);

            if (!empty($fields)) {
                unset($fields['_type'], $fields['_pk']);
                return $fields;
            }

            return false;
        }
        if ($this->fields) {
            $fields = $this->fields;
            unset($fields['_type'], $fields['_pk']);
            return $fields;
        }
        return false;
    }

    /**
     * 设置数据对象值
     * @access public
     * @param mixed $data 数据
     * @return $this|array
     * @throws \Exception
     */
    public function data($data)
    {
        if ('' === $data && !empty($this->data)) {
            return $this->data;
        }
        if (is_object($data)) {
            $this->data = get_object_vars($data);
        } elseif (is_string($data)) {
            parse_str($data, $this->data);
        } elseif (!is_array($data)) {
            StrackE(L('_DATA_TYPE_INVALID_'), ErrorCode::DATA_TYPE_INVALID);
        }
        return $this;
    }

    /**
     * 指定当前的数据表
     * @access public
     * @param mixed $table
     * @return Model
     */
    public function table($table)
    {
        $prefix = $this->tablePrefix;
        if (is_array($table)) {
            $this->options['table'] = $table;
        } elseif (!empty($table)) {
            //将__TABLE_NAME__替换成带前缀的表名
            $table = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $table);
            $this->options['table'] = $table;
        }
        return $this;
    }

    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using
     * @return Model
     */
    public function using($using)
    {
        $prefix = $this->tablePrefix;
        if (is_array($using)) {
            $this->options['using'] = $using;
        } elseif (!empty($using)) {
            //将__TABLE_NAME__替换成带前缀的表名
            $using = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $using);
            $this->options['using'] = $using;
        }
        return $this;
    }

    /**
     * 查询SQL组装 join
     * @access public
     * @param mixed $join
     * @param string $type JOIN类型
     * @return Model
     */
    public function join($join, $type = 'INNER')
    {
        $prefix = $this->tablePrefix;
        if (is_array($join)) {
            foreach ($join as $key => &$_join) {
                $_join = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                    return $prefix . strtolower($match[1]);
                }, $_join);
                $_join = false !== stripos($_join, 'JOIN') ? $_join : $type . ' JOIN ' . $_join;
            }
            $this->options['join'] = $join;
        } elseif (!empty($join)) {
            //将__TABLE_NAME__字符串替换成带前缀的表名
            $join = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $join);
            $this->options['join'][] = false !== stripos($join, 'JOIN') ? $join : $type . ' JOIN ' . $join;
        }
        return $this;
    }

    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed $union
     * @param boolean $all
     * @return $this
     * @throws \Exception
     */
    public function union($union, $all = false)
    {
        if (empty($union)) {
            return $this;
        }

        if ($all) {
            $this->options['union']['_all'] = true;
        }
        if (is_object($union)) {
            $union = get_object_vars($union);
        }


        $options = [];
        // 转换union表达式
        if (is_string($union)) {
            $prefix = $this->tablePrefix;
            //将__TABLE_NAME__字符串替换成带前缀的表名
            $options = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $union);
        } elseif (is_array($union)) {
            if (isset($union[0])) {
                $this->options['union'] = array_merge($this->options['union'], $union);
                return $this;
            } else {
                $options = $union;
            }
        } else {
            StrackE(L('_DATA_TYPE_INVALID_'), ErrorCode::DATA_TYPE_INVALID);
        }
        $this->options['union'][] = $options;
        return $this;
    }

    /**
     * 查询缓存
     * @access public
     * @param mixed $key
     * @param integer $expire
     * @param string $type
     * @return Model
     */
    public function cache($key = true, $expire = null, $type = '')
    {
        // 增加快捷调用方式 cache(10) 等同于 cache(true, 10)
        if (is_numeric($key) && is_null($expire)) {
            $expire = $key;
            $key = true;
        }
        if (false !== $key) {
            $this->options['cache'] = ['key' => $key, 'expire' => $expire, 'type' => $type];
        }

        return $this;
    }

    /**
     * 指定查询字段 支持字段排除
     * @access public
     * @param mixed $field
     * @param boolean $except 是否排除
     * @return Model
     */
    public function field($field, $except = false)
    {
        if (true === $field) {
            // 获取全部字段
            $fields = $this->getDbFields();
            $field = $fields ?: '*';
        } elseif ($except) {
            // 字段排除
            if (is_string($field)) {
                $field = explode(',', $field);
            }
            $fields = $this->getDbFields();
            $field = $fields ? array_diff($fields, $field) : $field;
        }
        $this->options['field'] = $field;
        return $this;
    }

    /**
     * 调用命名范围
     * @access public
     * @param mixed $scope 命名范围名称 支持多个 和直接定义
     * @param array $args 参数
     * @return Model
     */
    public function scope($scope = '', $args = null)
    {
        $options = [];
        if ('' === $scope) {
            if (isset($this->_scope['default'])) {
                // 默认的命名范围
                $options = $this->_scope['default'];
            } else {
                return $this;
            }
        } elseif (is_string($scope)) {
            // 支持多个命名范围调用 用逗号分割
            $scopes = explode(',', $scope);
            foreach ($scopes as $name) {
                if (!isset($this->_scope[$name])) {
                    continue;
                }

                $options = array_merge($options, $this->_scope[$name]);
            }
            if (!empty($args) && is_array($args)) {
                $options = array_merge($options, $args);
            }
        } elseif (is_array($scope)) {
            // 直接传入命名范围定义
            $options = $scope;
        }

        if (is_array($options) && !empty($options)) {
            $this->options = array_merge($this->options, array_change_key_case($options));
        }
        return $this;
    }

    /**
     * 指定查询条件 支持安全过滤
     * @access public
     * @param mixed $where 条件表达式
     * @param mixed $parse 预处理参数
     * @return Model
     */
    public function where($where, ...$parse)
    {
        if (!is_null($parse) && is_string($where)) {
            $parse = array_map([$this->db, 'escapeString'], $parse);
            $where = vsprintf($where, $parse);
        } elseif (is_object($where)) {
            $where = get_object_vars($where);
        }
        if (is_string($where) && '' != $where) {
            $map = [];
            $map['_string'] = $where;
            $where = $map;
        }
        if (isset($this->options['where'])) {
            $this->options['where'] = array_merge($this->options['where'], $where);
        } else {
            $this->options['where'] = $where;
        }

        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    /**
     * 指定分页
     * @access public
     * @param mixed $page 页数
     * @param mixed $listRows 每页数量
     * @return Model
     */
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list($page, $listRows) = explode(',', $page);
        }
        $this->options['page'] = [intval($page), intval($listRows)];
        return $this;
    }

    /**
     * 查询注释
     * @access public
     * @param string $comment 注释
     * @return Model
     */
    public function comment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 强制走主DB查询
     * @return $this
     */
    public function forceMasterDB()
    {
        return $this->hint('FORCE_MASTER');
    }

    /**
     * 增加hint标识
     * @param $hintContent
     * @return $this
     */
    public function hint($hintContent)
    {
        $this->options['hint'] = $hintContent;
        return $this;
    }

    /**
     * 获取执行的SQL语句
     * @access public
     * @param boolean $fetch 是否返回sql
     * @return Model
     */
    public function fetchSql($fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }

    /**
     * 参数绑定
     * @param $key
     * @param mixed ...$params
     * @return $this
     */
    public function bind($key, ...$params)
    {
        if (is_array($key)) {
            $this->options['bind'] = $key;
        } else {
            $this->options['bind'][$key] = $params;
        }
        return $this;
    }

    /**
     * 设置模型的属性值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return Model
     */
    public function setProperty($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }

        return $this;
    }

    /**
     * 列查询
     * @param string $field
     * @return array|false|mixed|string
     */
    public function column(string $field)
    {
        // 分析表达式
        $options = $this->_parseOptions();
        // 标记是列查询
        $options['column_select_field'] = $field;
        // 判断查询缓存
        $key = "";
        if (isset($options['cache'])) {
            $cache = $options['cache'];
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $data = S($key, '', $cache);
            if (false !== $data) {
                return $data;
            }
        }
        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        if (!empty($resultSet)) {
            // 有查询结果
            if (is_string($resultSet)) {
                return $resultSet;
            }
            $resultSet = array_column($resultSet, $field);
        }
        if (isset($cache)) {
            S($key, $resultSet, $cache);
        }
        return $resultSet;
    }

    /**
     * 值查询
     * @param string $field
     * @return false|mixed|string|null
     */
    public function value(string $field)
    {
        // 标记是值查询
        $this->options['value_find_field'] = $field;
        // 总是查找一条记录
        $this->options['limit'] = 1;
        // 分析表达式
        $options = $this->_parseOptions();
        // 判断查询缓存
        $key = "";
        if (isset($options['cache'])) {
            $cache = $options['cache'];
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $data = S($key, '', $cache);
            if (false !== $data) {
                $this->data = $data;
                return $data;
            }
        }
        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        if (empty($resultSet)) {
            // 查询结果为空
            return null;
        }
        if (is_string($resultSet)) {
            return $resultSet;
        }
        // 读取数据后的处理
        $this->data = $resultSet[0][$field] ?? null;
        if (isset($cache)) {
            S($key, $this->data, $cache);
        }
        return $this->data;
    }
}

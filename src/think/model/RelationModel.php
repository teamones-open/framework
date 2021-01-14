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
namespace think\model;

use think\Model;
use think\Hook;
use think\Db;
use think\module\Module;

/**
 * ThinkPHP关联模型扩展
 */
class RelationModel extends Model
{

    const HAS_ONE = 1;
    const BELONGS_TO = 2;
    const HAS_MANY = 3;
    const MANY_TO_MANY = 4;

    // 关联定义
    protected $_link = array();

    // 定义返回数据
    public $_resData = [];

    // 字段数据源映射源数据字段
    public $_fieldFromDataDict = [];

    // 字段类型或者格式转换
    protected $type = [];

    // 是否是空值查询
    protected $isNullOrEmptyFilter = false;

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

    public function __construct($name = '', $tablePrefix = '', $connection = '')
    {
        parent::__construct($name, $tablePrefix, $connection);
        $this->currentModuleCode = to_under_score($this->name);
    }

    /**
     * 获取或者实例化模型对象
     * @param $table
     * @return  \think\Model object
     */
    public function getModelObj($table)
    {
        if (!isset($this->moduleModel)) {
            $this->moduleModel = new Model();
        }
        return $this->moduleModel->table($table);
    }

    /**
     * 手动指定当前模块code
     * @param $moduleCode
     */
    public function setCurrentModuleCode($moduleCode)
    {
        $this->currentModuleCode = $moduleCode;
    }

    /**
     * 动态方法实现
     * @access public
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (strtolower(substr($method, 0, 8)) == 'relation') {
            $type = strtoupper(substr($method, 8));
            if (in_array($type, array('ADD', 'SAVE', 'DEL'), true)) {
                array_unshift($args, $type);
                return call_user_func_array(array(&$this, 'opRelation'), $args);
            }
        } else {
            return parent::__call($method, $args);
        }
    }

    /**
     * 数据库Event log Hook
     */
    protected function databaseEventLogHook($param)
    {
        Hook::listen('event_log', $param);
    }

    /**
     * 得到关联的数据表名
     * @param $relation
     * @return string
     */
    public function getRelationTableName($relation)
    {
        $relationTable = !empty($this->tablePrefix) ? $this->tablePrefix : '';
        $relationTable .= $this->tableName ? $this->tableName : $this->name;
        $relationTable .= '_' . $relation->getModelName();
        return strtolower($relationTable);
    }

    /**
     * 查询成功后的回调方法
     * @param $result
     * @param $options
     */
    protected function _after_find(&$result, $options)
    {
        // 获取关联数据 并附加到结果中
        if (!empty($options['link'])) {
            $this->getRelation($result, $options['link']);
        }
    }

    /**
     * 查询数据集成功后的回调方法
     * @param $result
     * @param $options
     */
    protected function _after_select(&$result, $options)
    {
        // 获取关联数据 并附加到结果中
        if (!empty($options['link'])) {
            $this->getRelations($result, $options['link']);
        }

    }

    /**
     * 写入成功后的回调方法
     * @param $pk
     * @param $pkName
     * @param $data
     * @param $options
     */
    protected function _after_insert($pk, $pkName, $data, $options)
    {
        //写入事件日志
        if ($options["model"] != "EventLog") {
            $this->databaseEventLogHook([
                'level' => 'info',
                'operate' => 'create',
                'primary_id' => $pk,
                'primary_field' => $pkName,
                'data' => $data,
                'param' => $options,
                'table' => $this->getTableName()
            ]);
        }

        // 关联写入
        if (!empty($options['link'])) {
            $this->opRelation('ADD', $data, $options['link']);
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
        //写入事件日志
        if ($result > 0 && $options["model"] != "EventLog" && $writeEvent) {
            $this->databaseEventLogHook([
                'level' => 'info',
                'operate' => 'update',
                'primary_id' => $this->oldUpdateKey,
                'primary_field' => $pkName,
                'data' => ["old" => $this->oldUpdateData, "new" => $this->newUpdateData],
                'param' => $options,
                'table' => $this->getTableName()
            ]);
        }

        // 关联更新
        if (!empty($options['link'])) {
            $this->opRelation('SAVE', $data, $options['link']);
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
        //写入事件日志
        if ($result > 0 && $options["model"] != "EventLog") {
            $this->databaseEventLogHook([
                'level' => 'info',
                'operate' => 'delete',
                'primary_id' => $this->oldDeleteKey,
                'primary_field' => $pkName,
                'data' => $this->oldDeleteData,
                'param' => $options,
                'table' => $this->getTableName()
            ]);
        }

        // 关联删除
        if (!empty($options['link'])) {
            $this->opRelation('DEL', $data, $options['link']);
        }

    }

    /**
     * 对保存到数据库的数据进行处理
     * @access protected
     * @param mixed $data 要操作的数据
     * @return boolean
     */
    protected function _facade($data)
    {
        $this->_before_write($data);
        return $data;
    }

    /**
     * 获取返回数据集的关联记录
     * @access protected
     * @param array $resultSet 返回数据
     * @param string|array $name 关联名称
     * @return array
     */
    protected function getRelations(&$resultSet, $name = '')
    {
        // 获取记录集的主键列表
        foreach ($resultSet as $key => $val) {
            $val = $this->getRelation($val, $name);
            $resultSet[$key] = $val;
        }
        return $resultSet;
    }

    /**
     * 获取返回数据的关联记录
     * @access protected
     * @param mixed $result 返回数据
     * @param string|array $name 关联名称
     * @param boolean $return 是否返回关联数据本身
     * @return array
     */
    protected function getRelation(&$result, $name = '', $return = false)
    {
        if (!empty($this->_link)) {
            foreach ($this->_link as $key => $val) {
                $mappingName = !empty($val['mapping_name']) ? $val['mapping_name'] : $key; // 映射名称
                if (empty($name) || true === $name || $mappingName == $name || (is_array($name) && in_array($mappingName, $name))) {
                    $mappingType = !empty($val['mapping_type']) ? $val['mapping_type'] : $val; //  关联类型
                    $mappingClass = !empty($val['class_name']) ? $val['class_name'] : $key; //  关联类名
                    $mappingFields = !empty($val['mapping_fields']) ? $val['mapping_fields'] : '*'; // 映射字段
                    $mappingCondition = !empty($val['condition']) ? $val['condition'] : '1=1'; // 关联条件
                    $mappingKey = !empty($val['mapping_key']) ? $val['mapping_key'] : $this->getPk(); // 关联键名
                    if (strtoupper($mappingClass) == strtoupper($this->name)) {
                        // 自引用关联 获取父键名
                        $mappingFk = !empty($val['parent_key']) ? $val['parent_key'] : 'parent_id';
                    } else {
                        $mappingFk = !empty($val['foreign_key']) ? $val['foreign_key'] : strtolower($this->name) . '_id'; //  关联外键
                    }
                    // 获取关联模型对象
                    $model = D($mappingClass);
                    switch ($mappingType) {
                        case self::HAS_ONE:
                            $pk = $result[$mappingKey];
                            $mappingCondition .= " AND {$mappingFk}='{$pk}'";
                            $relationData = $model->where($mappingCondition)->field($mappingFields)->find();
                            if (!empty($val['relation_deep'])) {
                                $model->getRelation($relationData, $val['relation_deep']);
                            }
                            break;
                        case self::BELONGS_TO:
                            if (strtoupper($mappingClass) == strtoupper($this->name)) {
                                // 自引用关联 获取父键名
                                $mappingFk = !empty($val['parent_key']) ? $val['parent_key'] : 'parent_id';
                            } else {
                                $mappingFk =
                                    !empty($val['foreign_key']) ? $val['foreign_key'] : strtolower($model->getModelName()) . '_id'; //  关联外键
                            }
                            $fk = $result[$mappingFk];
                            $mappingCondition .= " AND {$model->getPk()}='{$fk}'";
                            $relationData = $model->where($mappingCondition)->field($mappingFields)->find();
                            if (!empty($val['relation_deep'])) {
                                $model->getRelation($relationData, $val['relation_deep']);
                            }
                            break;
                        case self::HAS_MANY:
                            $pk = $result[$mappingKey];
                            $mappingCondition .= " AND {$mappingFk}='{$pk}'";
                            $mappingOrder = !empty($val['mapping_order']) ? $val['mapping_order'] : '';
                            $mappingLimit = !empty($val['mapping_limit']) ? $val['mapping_limit'] : '';
                            // 延时获取关联记录
                            $relationData = $model->where($mappingCondition)->field($mappingFields)->order($mappingOrder)->limit($mappingLimit)->select();
                            if (!empty($val['relation_deep'])) {
                                foreach ($relationData as $key => $data) {
                                    $model->getRelation($data, $val['relation_deep']);
                                    $relationData[$key] = $data;
                                }
                            }
                            break;
                        case self::MANY_TO_MANY:
                            $pk = $result[$mappingKey];
                            $prefix = $this->tablePrefix;
                            $mappingCondition = " {$mappingFk}='{$pk}'";
                            $mappingOrder = $val['mapping_order'];
                            $mappingLimit = $val['mapping_limit'];
                            $mappingRelationFk = $val['relation_foreign_key'] ? $val['relation_foreign_key'] : $model->getModelName() . '_id';
                            if (isset($val['relation_table'])) {
                                $mappingRelationTable = preg_replace_callback("/__([A-Z_-]+)__/sU", function ($match) use ($prefix) {
                                    return $prefix . strtolower($match[1]);
                                }, $val['relation_table']);
                            } else {
                                $mappingRelationTable = $this->getRelationTableName($model);
                            }
                            $sql = "SELECT b.{$mappingFields} FROM {$mappingRelationTable} AS a, " . $model->getTableName() . " AS b WHERE a.{$mappingRelationFk} = b.{$model->getPk()} AND a.{$mappingCondition}";
                            if (!empty($val['condition'])) {
                                $sql .= ' AND ' . $val['condition'];
                            }
                            if (!empty($mappingOrder)) {
                                $sql .= ' ORDER BY ' . $mappingOrder;
                            }
                            if (!empty($mappingLimit)) {
                                $sql .= ' LIMIT ' . $mappingLimit;
                            }
                            $relationData = $this->query($sql);
                            if (!empty($val['relation_deep'])) {
                                foreach ($relationData as $key => $data) {
                                    $model->getRelation($data, $val['relation_deep']);
                                    $relationData[$key] = $data;
                                }
                            }
                            break;
                    }
                    if (!$return) {
                        if (isset($val['as_fields']) && in_array($mappingType, array(self::HAS_ONE, self::BELONGS_TO))) {
                            // 支持直接把关联的字段值映射成数据对象中的某个字段
                            // 仅仅支持HAS_ONE BELONGS_TO
                            $fields = explode(',', $val['as_fields']);
                            foreach ($fields as $field) {
                                if (strpos($field, ':')) {
                                    list($relationName, $nick) = explode(':', $field);
                                    $result[$nick] = $relationData[$relationName];
                                } else {
                                    $result[$field] = $relationData[$field];
                                }
                            }
                        } else {
                            $result[$mappingName] = $relationData;
                        }
                        unset($relationData);
                    } else {
                        return $relationData;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 操作关联数据
     * @access protected
     * @param string $opType 操作方式 ADD SAVE DEL
     * @param mixed $data 数据对象
     * @param string $name 关联名称
     * @return mixed
     */
    protected function opRelation($opType, $data = '', $name = '')
    {
        $result = false;
        if (empty($data) && !empty($this->data)) {
            $data = $this->data;
        } elseif (!is_array($data)) {
            // 数据无效返回
            return false;
        }
        if (!empty($this->_link)) {
            // 遍历关联定义
            foreach ($this->_link as $key => $val) {
                // 操作制定关联类型
                $mappingName = $val['mapping_name'] ? $val['mapping_name'] : $key; // 映射名称
                if (empty($name) || true === $name || $mappingName == $name || (is_array($name) && in_array($mappingName, $name))) {
                    // 操作制定的关联
                    $mappingType = !empty($val['mapping_type']) ? $val['mapping_type'] : $val; //  关联类型
                    $mappingClass = !empty($val['class_name']) ? $val['class_name'] : $key; //  关联类名
                    $mappingKey = !empty($val['mapping_key']) ? $val['mapping_key'] : $this->getPk(); // 关联键名
                    // 当前数据对象主键值
                    $pk = $data[$mappingKey];
                    if (strtoupper($mappingClass) == strtoupper($this->name)) {
                        // 自引用关联 获取父键名
                        $mappingFk = !empty($val['parent_key']) ? $val['parent_key'] : 'parent_id';
                    } else {
                        $mappingFk = !empty($val['foreign_key']) ? $val['foreign_key'] : strtolower($this->name) . '_id'; //  关联外键
                    }
                    if (!empty($val['condition'])) {
                        $mappingCondition = $val['condition'];
                    } else {
                        $mappingCondition = array();
                        $mappingCondition[$mappingFk] = $pk;
                    }
                    // 获取关联model对象
                    $model = D($mappingClass);
                    $mappingData = isset($data[$mappingName]) ? $data[$mappingName] : false;
                    if (!empty($mappingData) || 'DEL' == $opType) {
                        switch ($mappingType) {
                            case self::HAS_ONE:
                                switch (strtoupper($opType)) {
                                    case 'ADD': // 增加关联数据
                                        $mappingData[$mappingFk] = $pk;
                                        $result = $model->add($mappingData);
                                        break;
                                    case 'SAVE': // 更新关联数据
                                        $result = $model->where($mappingCondition)->save($mappingData);
                                        break;
                                    case 'DEL': // 根据外键删除关联数据
                                        $result = $model->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                            case self::BELONGS_TO:
                                break;
                            case self::HAS_MANY:
                                switch (strtoupper($opType)) {
                                    case 'ADD': // 增加关联数据
                                        $model->startTrans();
                                        foreach ($mappingData as $val) {
                                            $val[$mappingFk] = $pk;
                                            $result = $model->add($val);
                                        }
                                        $model->commit();
                                        break;
                                    case 'SAVE': // 更新关联数据
                                        $model->startTrans();
                                        $pk = $model->getPk();
                                        foreach ($mappingData as $vo) {
                                            if (isset($vo[$pk])) {
                                                // 更新数据
                                                $mappingCondition = "$pk ={$vo[$pk]}";
                                                $result = $model->where($mappingCondition)->save($vo);
                                            } else {
                                                // 新增数据
                                                $vo[$mappingFk] = $data[$mappingKey];
                                                $result = $model->add($vo);
                                            }
                                        }
                                        $model->commit();
                                        break;
                                    case 'DEL': // 删除关联数据
                                        $result = $model->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                            case self::MANY_TO_MANY:
                                $mappingRelationFk = $val['relation_foreign_key'] ? $val['relation_foreign_key'] : $model->getModelName() . '_id'; // 关联
                                $prefix = $this->tablePrefix;
                                if (isset($val['relation_table'])) {
                                    $mappingRelationTable = preg_replace_callback("/__([A-Z_-]+)__/sU", function ($match) use ($prefix) {
                                        return $prefix . strtolower($match[1]);
                                    }, $val['relation_table']);
                                } else {
                                    $mappingRelationTable = $this->getRelationTableName($model);
                                }
                                if (is_array($mappingData)) {
                                    $ids = array();
                                    foreach ($mappingData as $vo) {
                                        $ids[] = $vo[$mappingKey];
                                    }

                                    $relationId = implode(',', $ids);
                                }
                                switch (strtoupper($opType)) {
                                    case 'ADD': // 增加关联数据
                                        if (isset($relationId)) {
                                            $this->startTrans();
                                            // 插入关联表数据
                                            $sql = 'INSERT INTO ' . $mappingRelationTable . ' (' . $mappingFk . ',' . $mappingRelationFk . ') SELECT a.' . $this->getPk() . ',b.' . $model->getPk() . ' FROM ' . $this->getTableName() . ' AS a ,' . $model->getTableName() . " AS b where a." . $this->getPk() . ' =' . $pk . ' AND  b.' . $model->getPk() . ' IN (' . $relationId . ") ";
                                            $result = $model->execute($sql);
                                            if (false !== $result) // 提交事务
                                            {
                                                $this->commit();
                                            } else // 事务回滚
                                            {
                                                $this->rollback();
                                            }
                                        }
                                        break;
                                    case 'SAVE': // 更新关联数据
                                        if (isset($relationId)) {
                                            $this->startTrans();
                                            // 删除关联表数据
                                            $this->table($mappingRelationTable)->where($mappingCondition)->delete();
                                            // 插入关联表数据
                                            $sql = 'INSERT INTO ' . $mappingRelationTable . ' (' . $mappingFk . ',' . $mappingRelationFk . ') SELECT a.' . $this->getPk() . ',b.' . $model->getPk() . ' FROM ' . $this->getTableName() . ' AS a ,' . $model->getTableName() . " AS b where a." . $this->getPk() . ' =' . $pk . ' AND  b.' . $model->getPk() . ' IN (' . $relationId . ") ";
                                            $result = $model->execute($sql);
                                            if (false !== $result) // 提交事务
                                            {
                                                $this->commit();
                                            } else // 事务回滚
                                            {
                                                $this->rollback();
                                            }

                                        }
                                        break;
                                    case 'DEL': // 根据外键删除中间表关联数据
                                        $result = $this->table($mappingRelationTable)->where($mappingCondition)->delete();
                                        break;
                                }
                                break;
                        }
                        if (!empty($val['relation_deep'])) {
                            $model->opRelation($opType, $mappingData, $val['relation_deep']);
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 进行关联查询
     * @access public
     * @param mixed $name 关联名称
     * @return Model
     */
    public function relation($name)
    {
        $this->options['link'] = $name;
        return $this;
    }

    /**
     * 关联数据获取 仅用于查询后
     * @access public
     * @param string $name 关联名称
     * @return array
     */
    public function relationGet($name)
    {
        if (empty($this->data)) {
            return false;
        }

        return $this->getRelation($this->data, $name, true);
    }


    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name 字符串
     * @param integer $type 转换类型
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    /**
     * 数据读取 类型转换
     * @access public
     * @param mixed $value 值
     * @param string|array $type 要转换的类型
     * @return mixed
     */
    protected function readTransform($value, $type)
    {
        if (is_array($type)) {
            list($type, $param) = $type;
        } elseif (strpos($type, ':')) {
            list($type, $param) = explode(':', $type, 2);
        }
        switch ($type) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float)$value;
                } else {
                    $value = (float)number_format($value, $param);
                }
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'timestamp':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = date($format, $value);
                }
                break;
            case 'datetime':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = date($format, strtotime($value));
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'array':
                $value = is_null($value) ? [] : json_decode($value, true);
                break;
            case 'object':
                $value = empty($value) ? new \stdClass() : json_decode($value);
                break;
            case 'serialize':
                $value = unserialize($value);
                break;
        }
        return $value;
    }

    /**
     * 获取对象原始数据 如果不存在指定字段返回false
     * @access public
     * @param string $name 字段名 留空获取全部
     * @return mixed
     * @throws \Exception
     */
    public function getData($name = null)
    {
        if (is_null($name)) {
            return $this->data;
        } elseif (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        } else {
            StrackE('property not exists:' . $name);
        }
    }

    /**
     * 获取器 获取数据对象的值
     * @param $name
     * @param string $model
     * @return mixed|null
     */
    public function getAttr($name)
    {
        try {
            $value = $this->getData($name);
        } catch (\Exception $e) {
            $value = null;
        }

        // 检测属性获取器
        $method = 'get' . $this->parseName($name, 1) . 'Attr';

        if (method_exists($this, $method)) {
            $value = $this->$method($value, $this->data);
        } elseif (isset($this->type[$name])) {
            // 类型转换
            $value = $this->readTransform($value, $this->type[$name]);
        }

        return $value;
    }

    /**
     * 获取复杂数据对象值
     * @param $field
     * @param $inputValue
     * @param $moduleCode
     * @return mixed
     */
    public function getComplexAttr($field, $inputValue, $moduleCode)
    {
        $class = get_module_model_name(Module::$moduleDictData['module_index_by_code'][$moduleCode]);

        //获取一个单例model 重置model
        $classObject = model($class);
        $classObject->resetDefault();

        // 检测属性获取器
        $method = 'get' . $classObject->parseName($field, 1) . 'Attr';

        if (method_exists($classObject, $method)) {
            $value = $classObject->$method($inputValue, []);
        } elseif (isset($classObject->type[$field])) {
            // 类型转换
            $value = $classObject->readTransform($inputValue, $classObject->type[$field]);
        } else {
            return $inputValue;
        }

        return $value;
    }

    /**
     * 处理查询数据
     * @param $data
     * @param string $model
     * @return array
     */
    protected function handleQueryData($data)
    {
        $item = [];
        $this->data = !empty($data) ? $data : $this->data;
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $this->data = $val;
                $arr = [];
                foreach ($val as $k => $v) {
                    $arr[$k] = $this->getAttr($k);
                }
                $item[$key] = $arr;
            } else {
                $item[$key] = $this->getAttr($key);
            }
        }

        return !empty($item) ? $item : [];
    }

    /**
     * @param $newReturnData
     * @param $field
     * @param $value
     * @return int
     */
    private function parsehandleReturnComplexData(&$newReturnData, &$primaryKeyId, $field, $value)
    {
        $fieldArray = explode('__', $field);

        if (
            $fieldArray[0] === $this->currentModuleCode ||
            (!empty($this->queryModuleLfetJoinRelation[$fieldArray[0]]) && in_array($this->queryModuleLfetJoinRelation[$fieldArray[0]]['relation_type'], ['belong_to', 'has_one']))
            || !empty($this->queryModuleHorizontalRelation[$fieldArray[0]])
        ) {

            if (array_key_exists($fieldArray[0], $newReturnData)) {
                $newReturnData[$fieldArray[0]][$fieldArray[1]] = $this->getComplexAttr($fieldArray[1], $value, $this->queryComplexModuleMapping[$fieldArray[0]]);
            } else {
                $newReturnData[$fieldArray[0]] = [$fieldArray[1] => $this->getComplexAttr($fieldArray[1], $value, $this->queryComplexModuleMapping[$fieldArray[0]])];
            }

            if ($fieldArray[0] === $this->currentModuleCode && $fieldArray[1] === "id") {
                // 保存主键ids
                $this->queryModulePrimaryKeyIds[] = $value;
                $primaryKeyId = $value;
            }

        } else {
            $newReturnData[$field] = $value;

            if ($field === "id") {
                $this->queryModulePrimaryKeyIds[] = $value;
                $primaryKeyId = $value;
            }
        }
    }

    /**
     * 展开entity关联配置
     * @param $relationUnfold
     * @param $relationItem
     * @return array
     */
    private function unfoldEntityRelationConfig(&$relationUnfold, $relationItem)
    {
        $relationUnfold[] = $relationItem;
        if (array_key_exists('child', $relationItem)) {
            $this->unfoldEntityRelationConfig($relationUnfold, $relationItem['child']);
        }

        return $relationUnfold;
    }

    /**
     * 生成实体数据回插字典
     * @param $preMapping
     * @param $crruentData
     */
    private function generateQueryEntityPlugBackIdMapping(&$preMapping, $crruentData)
    {
        $newData = [];
        $crruentDataDict = array_column($crruentData, null, 'id');

        $newPreMapping = [];
        foreach ($preMapping as $preMappingKey => $preMappingVal) {
            if (!empty($crruentDataDict[$preMappingVal])) {
                $newPreMapping[$preMappingKey] = $crruentDataDict[$preMappingVal]['entity_id'];
            } else {
                $newPreMapping[$preMappingKey] = 0;
            }
        }

        $preMapping = $newPreMapping;
    }

    /**
     * 处理查询查询的模块自定义字段
     */
    private function handleQueryModuleCustomFields(&$fileds, $moduleCode)
    {
        if (!empty($fileds) && !empty(Module::$moduleDictData['field_index_by_code'])) {
            // 处理查询的自定义字段
            $moduleFields = Module::$moduleDictData['field_index_by_code'][$moduleCode];
            $newFileds = [];

            foreach ($fileds as $field) {
                if (array_key_exists($field, $moduleFields['custom'])) {
                    $newFileds[] = "JSON_UNQUOTE(json_extract(json, '$.{$field}' )) as {$field}";
                } else {
                    $newFileds[] = $field;
                }

                $fileds = $newFileds;
            }
        }
    }

    /**
     * 处理实体关联复杂查询数据
     * @param $newReturnData
     * @param string $type
     */
    private function handleEntityRelationReturnComplexData(&$newReturnData, $type = 'find')
    {
        // 实体处理只需要处理最深的那一个结构
        if (!empty($this->queryModuleEntityRelation)) {
            $maxDepthRelation = [];
            $maxDepthRelationConfig = [];
            $maxDepth = 0;
            foreach ($this->queryModuleEntityRelation as $module => $relationItem) {
                $currentDepth = array_depth($relationItem);
                if ($currentDepth > $maxDepth) {
                    $maxDepth = $currentDepth;
                    $maxDepthRelationConfig = $relationItem;
                    $maxDepthRelation = [
                        "depth" => (int)($maxDepth - 2),
                        "module_code" => $module,
                        "config" => []
                    ];
                }
            }

            $relationUnfold = [];
            $this->unfoldEntityRelationConfig($relationUnfold, $maxDepthRelationConfig);

            // 查询关联数据
            $middleEntityData = $this->getModelObj('entity')->field('id,entity_id')->where(['id' => ['IN', join(',', $this->queryModulePrimaryKeyIds)]])->select();

            $middleRelationIds = array_column($middleEntityData, 'entity_id');

            // 需要回插的entity数据
            $plugBackData = [];
            $middleRelationIdMapping = array_column($middleEntityData, 'entity_id', 'id');

            for ($i = $maxDepthRelation['depth']; $i >= 0; $i--) {

                if ($relationUnfold[$i]['belong_module'] !== $this->currentModuleCode) {
                    if (array_key_exists($relationUnfold[$i]['belong_module'], $this->queryModuleRelationFields)) {
                        $fileds = $this->queryModuleRelationFields[$relationUnfold[$i]['belong_module']];
                    } else {
                        $fileds = [];
                    }

                    if (!in_array('id', $fileds)) {
                        array_unshift($fileds, 'id');
                    }

                    if (!in_array('entity_id', $fileds)) {
                        array_push($fileds, 'entity_id');
                    }

                    if (!empty($middleRelationIds)) {

                        $this->handleQueryModuleCustomFields($fileds, $relationUnfold[$i]['belong_module']);

                        $entityData = $this->getModelObj('entity')->field(join(',', $fileds))->where([
                            'id' => ['IN', join(',', $middleRelationIds)],
                            'module_id' => $relationUnfold[$i]['src_module_id'],
                        ])->select();


                        if (!empty($entityData)) {
                            $middleRelationIds = array_column($entityData, 'entity_id');
                            $plugBackData[$relationUnfold[$i]['belong_module']] = [
                                'mapping' => $middleRelationIdMapping,
                                'data' => array_column($entityData, null, 'id')
                            ];
                            $this->generateQueryEntityPlugBackIdMapping($middleRelationIdMapping, $entityData);
                        } else {
                            $middleRelationIds = [];
                            $plugBackData[$relationUnfold[$i]['belong_module']] = [];
                        }

                    } else {
                        $plugBackData[$relationUnfold[$i]['belong_module']] = [];
                        $middleRelationIds = [];
                    }
                }
            }

            // 回插数据
            foreach ($newReturnData as &$newReturnDataItem) {
                foreach ($plugBackData as $modulCode => $mappingData) {
                    $tempData = [];
                    if (!empty($mappingData['data'])
                        && !empty($mappingData['mapping'][$newReturnDataItem[$this->currentModuleCode]['id']])
                        && !empty($mappingData['data'][$mappingData['mapping'][$newReturnDataItem[$this->currentModuleCode]['id']]])) {
                        $newReturnDataItem[$modulCode] = $mappingData['data'][$mappingData['mapping'][$newReturnDataItem[$this->currentModuleCode]['id']]];
                    } else {
                        foreach ($this->queryModuleRelationFields[$modulCode] as $field) {
                            $tempData[$field] = '';
                        }

                        $newReturnDataItem[$modulCode] = $tempData;
                    }
                }
            }
        }
    }

    /**
     * 处理复杂查询数据
     * @param $queryData
     * @param string $type
     * @return array
     */
    private function handleReturnComplexData($queryData, $type = 'find')
    {

        $newReturnData = [];
        if ($type === 'find') {
            $newReturnItem = [];
            $primaryKeyId = 0;
            foreach ($queryData as $field => $value) {
                $this->parsehandleReturnComplexData($newReturnItem, $primaryKeyId, $field, $value);
            }

            $newReturnData[$primaryKeyId] = $newReturnItem;
        } else {
            foreach ($queryData as $queryItem) {
                $newReturnItem = [];
                $primaryKeyId = 0;
                foreach ($queryItem as $field => $value) {
                    $this->parsehandleReturnComplexData($newReturnItem, $primaryKeyId, $field, $value);
                }
                $newReturnData[$primaryKeyId] = $newReturnItem;
            }
        }

        // 回插entity关联数据
        $this->handleEntityRelationReturnComplexData($newReturnData, $type);

        // 回插1对n关联数据
        $this->handleHasManyRelationReturnComplexData($newReturnData, $type);

        return array_values($newReturnData);
    }

    /**
     * 处理一对多关联数据插入
     * @param $queryData
     * @param string $type
     */
    private function handleHasManyRelationReturnComplexData(&$queryData, $type = 'find')
    {
        $hasManyMapping = [];
        foreach ($this->queryModuleHasManyRelation as $moduleCode => $modulConfig) {
            if ($modulConfig['type'] === 'horizontal') {
                // 一对多水平自定义关联处理
                $relationIds = [];
                $relationIdMapping = [];
                foreach ($queryData as $queryItem) {
                    if (!empty($queryItem[$moduleCode]['link'])) {
                        $linkIds = explode(',', $queryItem[$moduleCode]['link']);
                        foreach ($linkIds as $linkId) {
                            if (!in_array($linkId, $relationIds)) {
                                $relationIds[] = (int)$linkId;
                            }
                        }
                        $relationIdMapping[$queryItem[$this->currentModuleCode]['id']] = $linkIds;
                    } else {
                        $relationIdMapping[$queryItem[$this->currentModuleCode]['id']] = [];
                    }
                }

                $modelObjectClass = get_module_model_name(Module::$moduleDictData['module_index_by_code'][$modulConfig['module_code']]);
                $newModelObject = model($modelObjectClass);


                if (array_key_exists($moduleCode, $this->queryModuleRelationFields)) {
                    $queryFields = $this->queryModuleRelationFields[$moduleCode];
                } else {
                    $queryFields = [];
                }

                if (!in_array('id', $queryFields)) {
                    array_unshift($queryFields, 'id');
                }

                foreach ($queryFields as &$queryField) {
                    $queryField = $this->handleQueryCustomFields($queryField) . " AS $queryField";
                }

                $horizontalData = $newModelObject->field(join(',', $queryFields))->where(['id' => ['IN', join(',', $relationIds)]])->select();

                $horizontalDataDict = [];
                if (!empty($horizontalData)) {
                    foreach ($horizontalData as &$selectItem) {
                        $selectItem = $newModelObject->handleReturnData(false, $selectItem);
                    }
                    $horizontalDataDict = array_column($horizontalData, null, 'id');
                }

                foreach ($relationIdMapping as $masterId => &$relationIds) {
                    $tempData = [];
                    foreach ($relationIds as $relationId) {
                        if (array_key_exists($relationId, $horizontalDataDict)) {
                            $tempData[] = $horizontalDataDict[$relationId];
                        }
                    }
                    $relationIds = $tempData;
                }

                $hasManyMapping[$moduleCode] = $relationIdMapping;

                unset($relationIds);
            } else {
                // 一对多处理

            }
        }

        // 回插数据
        foreach ($queryData as &$queryItem) {
            foreach ($hasManyMapping as $hasManyModule => $hasManyItem) {
                $queryItem[$hasManyModule] = $hasManyItem[$queryItem[$this->currentModuleCode]['id']];
            }
        }
    }

    /**
     * 处理返回数据
     * @param $data
     * @param bool $first
     * @return array
     */
    public function handleReturnData($first = true, $data = [])
    {
        $dealData = !empty($data) ? $data : $this->data;

        $this->data = $this->handleQueryData($dealData);

        if ($first && is_many_dimension_array($this->data)) {
            $item = [];
            foreach ($this->data as $value) {
                $this->data = $value;
                $item[] = $this->handleReturnData(false);
            }
            return $item;
        } else {
            //过滤属性
            if (!empty($this->visible)) {
                $data = array_intersect_key($this->data, array_flip($this->visible));
            } elseif (!empty($this->hidden)) {
                $data = array_diff_key($this->data, array_flip($this->hidden));
            } else {
                $data = $this->data;
            }

            // 追加属性自定义字段
            if (!empty($this->appendCustomField)) {
                foreach ($this->appendCustomField as $field => $value) {
                    $data[$field] = $value;
                }
            }

            // 追加属性（必须定义获取器）
            if (!empty($this->append)) {
                foreach ($this->append as $name) {
                    $data[$name] = $this->getAttr($name);
                }
            }
            return !empty($data) ? $data : [];
        }
    }

    /**
     * 新增数据，成功返回当前添加的一条完整数据
     * @param array $param 新增数据参数
     * @return array|bool|mixed
     */
    public function addItem($param = [])
    {
        $this->resetDefault();
        if ($this->create($param, self::MODEL_INSERT)) {
            $result = $this->add();
            if (!$result) {
                //新增失败
                return false;
            } else {
                //新增成功，返回当前添加的一条完整数据
                $pk = $this->getPk();
                $this->_resData = $this->where([$pk => $result])->find();
                $this->successMsg = "Add {$this->name} items successfully.";
                return $this->handleReturnData(false, $this->_resData);
            }
        } else {
            //数据验证失败，返回错误
            return false;
        }
    }

    /**
     * 修改数据，必须包含主键，成功返回当前修改的一条完整数据
     * @param array $param 修改数据参数
     * @return array|bool|mixed
     */
    public function modifyItem($param = [])
    {

        $this->resetDefault();
        if ($this->create($param, self::MODEL_UPDATE)) {
            $result = $this->save();
            if (!$result) {
                // 修改失败
                if ($result === 0) {
                    // 没有数据被更新
                    $this->error = 'No data has been changed.';
                    $this->errorCode = -411112;
                    return false;
                } else {
                    return false;
                }
            } else {
                // 修改成功，返回当前修改的一条完整数据
                $pk = $this->getPk();
                $this->_resData = $this->where([$pk => $param[$pk]])->find();
                $this->successMsg = "Modify {$this->name} items successfully.";
                return $this->handleReturnData(false, $this->_resData);
            }
        } else {
            // 数据验证失败，返回错误
            return false;
        }
    }

    /**
     * 更新单个组件基础方法
     * @param $data
     * @return array|bool|mixed
     */
    public function updateWidget($data)
    {
        $this->resetDefault();
        if ($this->create($data, self::MODEL_UPDATE)) {
            $result = $this->save();
            if (!$result) {
                if ($result === 0) {
                    // 没有数据被更新
                    $this->error = 'No data has been changed.';
                    return false;
                } else {
                    return false;
                }
            } else {
                $pk = $this->getPk();
                $this->_resData = $this->where([$pk => $data[$pk]])->find();
                return $this->handleReturnData(false, $this->_resData);
            }
        } else {
            // 数据验证失败，返回错误
            return false;
        }
    }


    /**
     * 删除数据
     * @param array $param
     * @return mixed
     */
    public function deleteItem($param = [])
    {
        $this->resetDefault();
        $result = $this->where($param)->delete();
        if (!$result) {
            // 数据删除失败，返回错误
            if ($result == 0) {
                // 没有数据被删除
                $this->error = 'No data has been changed.';
                return false;
            } else {
                return false;
            }
        } else {
            // 删除成功，返回当前添加的一条完整数据
            $this->successMsg = "Delete {$this->name} items successfully.";
            return true;
        }
    }


    /**
     * 处理过滤字段值
     * @param $filterItem
     * @param $key
     * @param $value
     */
    private function parserFilterParamValue(&$filterItem, $key, $value)
    {
        if (strpos($key, '.') === false) {
            throw_strack_exception('The field format must contain a dot symbol.', -400002);
        }

        $fieldsParam = explode('.', $key);
        if (!in_array($fieldsParam[0], $this->complexFilterRelatedModule)) {
            $this->complexFilterRelatedModule[] = $fieldsParam[0];
        }

        if (!array_key_exists($fieldsParam[0], $filterItem)) {
            $filterItem[$fieldsParam[0]] = [
                $fieldsParam[1] => $this->buildWidgetFilter($fieldsParam[0], $fieldsParam[1], $value)
            ];
        } else {
            $filterItem[$fieldsParam[0]][$fieldsParam[1]] = $this->buildWidgetFilter($fieldsParam[0], $fieldsParam[1], $value);
        }
    }

    /**
     * 排序数据
     * @param $filter
     * @return array
     */
    private function sortFilterParam($filter)
    {
        $order = [];
        foreach ($filter as $key => $value) {
            if (is_array($value)) {
                $currentItemDepth = array_depth($value);
            } else {
                $currentItemDepth = 0;
            }

            $order[$key] = $currentItemDepth;
        }

        arsort($order);

        $sortFilter = [];
        foreach ($order as $orderKey => $depth) {
            $sortFilter[$orderKey] = $filter[$orderKey];
        }

        return $sortFilter;
    }

    /**
     * 获取最深路径过滤条件路径
     * @param $key
     * @param $value
     * @return array
     */
    private function parserFilterItemParam(&$filterItem, $key, $value)
    {
        if (strpos($key, '.')) {
            $this->parserFilterParamValue($filterItem, $key, $value);
        } else {
            $str = json_encode($value);
            if (strpos($str, '.')) {
                $valuKey = join('', array_keys($value));
                $valuParam = array_values($value);
                $this->parserFilterParamValue($filterItem, $valuKey, $valuParam[0]);
            } else {
                throw_strack_exception('Parameter format error.', -400001);
            }
        }

        return $filterItem;
    }


    /**
     * 处理过滤条件数据结构
     * @param $result
     * @param $filter
     * @param $currentFilter
     */
    private function parserFilterParam(&$result, $filter, $currentFilter, $index = 1)
    {
        // 对过滤条件按深度排序
        $sortFilter = $this->sortFilterParam($currentFilter);

        $filterItem = [];
        foreach ($sortFilter as $key => $value) {
            if (strpos($key, '.') === false && is_array($value)) {
                if (count($value) > 1) {
                    $index++;
                    $this->parserFilterParam($result, $filter, $value, $index);
                } else {
                    $this->parserFilterItemParam($filterItem, $key, $value);
                }
            } else {
                if ($key === '_logic') {
                    $filterItem[$key] = $value;
                } else {
                    $this->parserFilterItemParam($filterItem, $key, $value);
                }
            }
        }

        if (!array_key_exists('_logic', $filterItem)) {
            $filterItem['_logic'] = 'AND';
        }

        $result[] = $filterItem;
    }

    /**
     * 递归处理实体entity 子级路径
     * @param $result
     * @param $moduleCode
     * @param $moduleDictByDstModuleId
     * @param $moduleDictBySrcModuleId
     */
    private function recurrenceEntityChildHierarchy(&$result, $moduleCode, $moduleDictByDstModuleId, $moduleDictBySrcModuleId, $isChild = false)
    {
        $moduleData = Module::$moduleDictData['module_index_by_code'][$moduleCode];
        $masterModuleData = Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode];

        if ($moduleData['type'] === 'entity') {

            $result = [
                "belong_module" => $moduleData['code'],
                "relation_type" => "belong_to",
                "src_module_id" => $moduleData['id'],
                "dst_module_id" => $masterModuleData['id'],
                "link_id" => "entity_id,entity_module_id",
                "type" => "fixed",
                "module_code" => $masterModuleData['code'],
                "filter_type" => "entity"
            ];

            foreach ($moduleDictBySrcModuleId[$moduleData['id']] as $moduleDictSrcItem) {
                $dstModuleData = Module::$moduleDictData['module_index_by_id'][$moduleDictSrcItem['dst_module_id']];
                if ($dstModuleData['type'] === 'entity') {
                    $this->recurrenceEntityChildHierarchy($result['child'], $dstModuleData['code'], $moduleDictByDstModuleId, $moduleDictBySrcModuleId, true);
                    continue;
                }
            }
        }
    }

    /**
     * 递归处理实体entity 父级路径
     * @param $result
     * @param $moduleCode
     * @param $moduleDictByDstModuleId
     * @param $moduleDictBySrcModuleId
     * @param bool $isParent
     */
    private function recurrenceEntityParentHierarchy(&$result, $moduleCode, $moduleDictByDstModuleId, $moduleDictBySrcModuleId, $isParent = false)
    {
        $moduleData = Module::$moduleDictData['module_index_by_code'][$moduleCode];
        $masterModuleData = Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode];
        if ($moduleData['type'] === 'entity') {

            if ($isParent) {
                $parentData = [
                    "belong_module" => $moduleData['code'],
                    "relation_type" => "belong_to",
                    "src_module_id" => $moduleData['id'],
                    "dst_module_id" => $masterModuleData['id'],
                    "link_id" => "entity_id,entity_module_id",
                    "type" => "fixed",
                    "module_code" => $masterModuleData['code'],
                    "filter_type" => "entity",
                    'child' => $result
                ];
                $result = $parentData;
            }

            if (array_key_exists($moduleData['id'], $moduleDictByDstModuleId)) {
                foreach ($moduleDictByDstModuleId[$moduleData['id']] as $moduleDictSrcItem) {
                    $srcModuleData = Module::$moduleDictData['module_index_by_id'][$moduleDictSrcItem['src_module_id']];
                    if ($srcModuleData['type'] === 'entity') {
                        $this->recurrenceEntityParentHierarchy($result, $srcModuleData['code'], $moduleDictByDstModuleId, $moduleDictBySrcModuleId, true);
                        continue;
                    }
                }
            } else {
                return;
            }
        }
    }

    /**
     * 获取entity模块父子结构
     * @param $complexFilterRelatedModule
     * @param $moduleDictBySrcModuleId
     */
    private function getEntityParentChildHierarchy($complexFilterRelatedModule, $moduleDictByDstModuleId, $moduleDictBySrcModuleId)
    {
        $resultDict = [];

        foreach ($complexFilterRelatedModule as $module => $moduleCode) {
            if (array_key_exists($module, Module::$moduleDictData['module_index_by_code'])) {
                $moduleData = Module::$moduleDictData['module_index_by_code'][$moduleCode];
                if ($moduleData['type'] === 'entity') {
                    // 找到所有儿子
                    $childResult = [];
                    $this->recurrenceEntityChildHierarchy($childResult, $moduleCode, $moduleDictByDstModuleId, $moduleDictBySrcModuleId);

                    if ($this->currentModuleCode === 'task') {
                        if (!empty($childResult)) {
                            $resultDict[$moduleCode] = $childResult;
                        }
                    } else {
                        // 找到所有爸爸
                        if ($moduleCode === $this->currentModuleCode) {
                            $this->recurrenceEntityParentHierarchy($childResult, $moduleCode, $moduleDictByDstModuleId, $moduleDictBySrcModuleId);
                        }
                        if (!empty($childResult)) {
                            $resultDict[$moduleCode] = $childResult;
                        }
                    }
                }
            }
        }
        return $resultDict;
    }

    /**
     * 递归处理过滤条件实体的链路关系
     * @param $data
     * @param $entityParentChildHierarchyData
     */
    private function recurrenceFilterModuleEntityRelation(&$data, $entityParentChildHierarchyData)
    {
        $moduleItem = [];
        foreach ($entityParentChildHierarchyData as $key => $entityParentChildHierarchyItem) {
            if ($key !== 'child') {
                $moduleItem[$key] = $entityParentChildHierarchyItem;
            }
        }

        $data = $moduleItem;
        if (array_key_exists('child', $entityParentChildHierarchyData) && !empty($entityParentChildHierarchyData['child'])) {
            $this->recurrenceFilterModuleEntityRelation($data['child'], $entityParentChildHierarchyData['child']);
        }
    }

    /**
     * 递归处理过滤条件的链路关系
     * @param $filterModuleLinkRelation
     * @param $module
     * @param $moduleCode
     * @param $horizontalModuleList
     * @param $moduleDictBySrcModuleId
     * @param $moduleDictByDstModuleId
     * @param $entityParentChildHierarchyData
     */
    private function recurrenceFilterModuleRelation(&$filterModuleLinkRelation, $module, $moduleCode, $horizontalModuleList, $moduleDictBySrcModuleId, $moduleDictByDstModuleId, $entityParentChildHierarchyData)
    {
        // 对于实体和任务特殊关系每层实体下面都可以挂任务
        $moduleData = Module::$moduleDictData['module_index_by_code'][$moduleCode];

        if (in_array($moduleData['code'], $horizontalModuleList)) {
            // 判断是否是水平自定义关联模块
            $moduleDictByDstModuleId[$moduleData['id']][0]['filter_type'] = 'direct';
            $filterModuleLinkRelation[$module] = $moduleDictByDstModuleId[$moduleData['id']][0];
        } else {
            if ($moduleData['type'] === 'entity') {
                // 实体类型 如果 对方是任务模块需要独立处理，因为每个实体下面都有任务
                foreach ($moduleDictBySrcModuleId[$moduleData['id']] as $relationModuleItem) {
                    if (Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode]['id'] === $relationModuleItem['dst_module_id']) {
                        // 仅仅处理任务模型
                        $filterModuleLinkEmtityTemp = [];
                        $this->recurrenceFilterModuleEntityRelation($filterModuleLinkEmtityTemp, $entityParentChildHierarchyData[$moduleData['code']]);
                        $filterModuleLinkRelation[$module] = $filterModuleLinkEmtityTemp;
                    } else {
                        // 实体模型
                        $filterModuleLinkEmtityTemp = [];
                        $this->recurrenceFilterModuleEntityRelation($filterModuleLinkEmtityTemp, $entityParentChildHierarchyData[$moduleData['code']]);
                        $filterModuleLinkRelation[$module] = $filterModuleLinkEmtityTemp;
                    }
                }
            } else {
                if ($moduleData['code'] !== $this->currentModuleCode) {
                    // 不是当前自己模块
                    foreach ($moduleDictByDstModuleId[$moduleData['id']] as $relationModuleItem) {
                        // dst_module_id src_module_id 都匹配就保存到关联关系中去
                        if ($relationModuleItem['dst_module_id'] === $moduleData['id'] && $relationModuleItem['src_module_id'] === Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode]['id']) {
                            $relationModuleItem['filter_type'] = 'direct';
                            $filterModuleLinkRelation[$module] = $relationModuleItem;
                        }
                    }
                }
            }
        }
    }

    /**
     * 处理当前模块自定义字段
     * @param $queryModuleList
     */
    public function parserFilterModuleCustomFields($queryModuleList)
    {
        $queryModuleIds = [];
        foreach ($queryModuleList as $moduleCode) {
            $moduleId = Module::$moduleDictData['module_index_by_code'][$moduleCode]['id'];
            if (!in_array($moduleId, $queryModuleIds)) {
                $queryModuleIds[] = $moduleId;
            }
        }

        $customFieldData = $this->getModelObj('field')->field('id,table,module_id,config')
            ->where([
                'type' => 'custom',
                'is_horizontal' => 0,
                'module_id' => ['IN', join(',', $queryModuleIds)]
            ])
            ->select();

        if (!empty($customFieldData)) {
            foreach ($customFieldData as $customFieldItem) {
                $customFieldItemConfig = json_decode($customFieldItem['config'], true);
                $customFieldItemConfig['query_module_code'] = Module::$moduleDictData['module_index_by_id'][$customFieldItem['module_id']]['code'];
                $this->queryComplexCustomFieldMapping[$customFieldItemConfig['field']] = $customFieldItemConfig;
            }
        }
    }

    /**
     * 获取关联模块自定义字段
     * @param $modules
     * @return array
     */
    private function getRelationModuleCustomFields($modules)
    {
        $relationModule = [];
        $entityModuleList = $this->getModelObj('module')->field('code')->where(['type' => 'entity'])->select();

        foreach ($modules as $moduleKey => $config) {
            if ($config['type'] !== 'horizontal') {
                if ($moduleKey === 'entity') {
                    $entityModuleCustomFields = [];
                    foreach ($entityModuleList as $entityModuleCode) {
                        $entityModuleCustomFields = array_merge($entityModuleCustomFields, Module::$moduleDictData['field_index_by_code'][$entityModuleCode['code']]['custom']);
                    }
                    $relationModule[$moduleKey] = $entityModuleCustomFields;
                } else {
                    $relationModule[$moduleKey] = Module::$moduleDictData['field_index_by_code'][$moduleKey]['custom'];
                }
            }
        }

        $this->queryComplexRelationCustomFields = $relationModule;

        return $relationModule;
    }

    /**
     * 获取过滤条件的模块关联关系
     * @param bool $allModuleBack
     * @return array
     */
    public function parserFilterModuleRelation($allModuleBack = false)
    {
        if (!empty($this->queryModuleRelation)) {
            return $this->queryModuleRelation;
        }

        // 获取所有关联模块
        $moduleRelationData = $this->getModelObj('module_relation')->field('id,type as relation_type,src_module_id,dst_module_id,link_id')->select();

        // 当前模块的水平关联自定义字段
        $horizontalFieldData = $this->getModelObj('field')->field('id,table,config')
            ->where([
                'type' => 'custom',
                'is_horizontal' => 1,
                'module_id' => Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode]['id']
            ])
            ->select();

        // 获取任务与当前模块的关系
        $moduleDictByDstModuleId = [];
        $horizontalModuleList = [];

        if (!empty($horizontalFieldData)) {
            foreach ($horizontalFieldData as $horizontalFieldItem) {

                // 当前水平关联自定义字段配置
                $horizontalFieldItemConfig = json_decode($horizontalFieldItem['config'], true);

                // 判断当前查询关联模块是存在
                $dstModuleData = Module::$moduleDictData['module_index_by_id'][$horizontalFieldItemConfig['data_source']['dst_module_id']];

                $moduleDictByDstModuleId[$horizontalFieldItemConfig['data_source']['dst_module_id']][] = [
                    'type' => 'horizontal', // 自定义关系
                    'module_code' => $dstModuleData['code'],
                    'src_module_id' => $horizontalFieldItemConfig['data_source']['src_module_id'],
                    'dst_module_id' => $horizontalFieldItemConfig['data_source']['dst_module_id'],
                    'relation_type' => $horizontalFieldItemConfig['data_source']['relation_type'],
                    'link_id' => $horizontalFieldItemConfig['field']
                ];

                $horizontalModuleList[$horizontalFieldItemConfig['field']] = $dstModuleData['code'];
            }

            $this->queryComplexHorizontalCustomFieldMapping = $horizontalModuleList;
        }

        // 涉及的固定字段模块关系  按照 src_module_id dst_module_id 索引
        $moduleDictBySrcModuleId = [];

        foreach ($moduleRelationData as $moduleRelationItem) {
            $moduleRelationItem['type'] = 'fixed';
            $moduleRelationData = Module::$moduleDictData['module_index_by_id'][$moduleRelationItem['dst_module_id']];
            $moduleRelationItem['module_code'] = $moduleRelationData['code'];
            $moduleDictByDstModuleId[$moduleRelationItem['dst_module_id']][] = $moduleRelationItem;
            $moduleDictBySrcModuleId[$moduleRelationItem['src_module_id']][] = $moduleRelationItem;
        }

        $queryModuleList = [];
        if ($allModuleBack) {
            // 取所有关联模块
            $queryModuleList = $horizontalModuleList;

            // 自己当前模块
            $queryModuleList[$this->currentModuleCode] = $this->currentModuleCode;

            foreach ($moduleDictBySrcModuleId[Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode]['id']] as $fixedModuleItem) {
                $queryModuleList[$fixedModuleItem['module_code']] = $fixedModuleItem['module_code'];
            }
        } else {
            // 取所有关联模块
            $queryModuleList = $horizontalModuleList;

            foreach ($this->complexFilterRelatedModule as $complexFilterRelatedModule) {
                if (array_key_exists($complexFilterRelatedModule, $horizontalModuleList)) {
                    $queryModuleList[$complexFilterRelatedModule] = $horizontalModuleList[$complexFilterRelatedModule];
                } else {
                    $queryModuleList[$complexFilterRelatedModule] = $complexFilterRelatedModule;
                }
            }
        }

        // 获取entity链路关系
        $entityParentChildHierarchyData = $this->getEntityParentChildHierarchy($queryModuleList, $moduleDictByDstModuleId, $moduleDictBySrcModuleId);

        // 递归处理过滤条件的链路关系
        $filterModuleLinkRelation = [];
        foreach ($queryModuleList as $module => $moduleCode) {
            $this->recurrenceFilterModuleRelation($filterModuleLinkRelation, $module, $moduleCode, $horizontalModuleList, $moduleDictBySrcModuleId, $moduleDictByDstModuleId, $entityParentChildHierarchyData);
        }

        // 模块的自定义字段
        $this->parserFilterModuleCustomFields($queryModuleList);

        $this->queryComplexModuleMapping = $queryModuleList;

        $this->queryModuleRelation = $filterModuleLinkRelation;

        // 获取关联模块的自定义字段
        if (!empty($filterModuleLinkRelation)) {
            $this->getRelationModuleCustomFields($filterModuleLinkRelation);
        }

        return $filterModuleLinkRelation;
    }

    /**
     * 格式化过滤条件
     * @param $filter
     * @return array
     */
    private function formatFilterCondition($filter)
    {
        foreach ($filter as &$condition) {
            switch (strtolower($condition[0])) {
                case 'like':
                    $condition[1] = "%{$condition[1]}%";
                    break;
            }
        }
        return $filter;
    }

    /**
     * 处理过滤条件复杂值
     * @param $masterModuleCode
     * @param $itemModule
     * @param $selectData
     * @param $idsString
     * @return array
     */
    private function parserFilterItemComplexValue($masterModuleCode, $itemModule, $selectData, $idsString, $isComplex = true)
    {
        $filterData = [];
        if (strpos($itemModule['link_id'], ',') !== false) {
            $linkIds = explode(',', $itemModule['link_id']);
            $filterItem = [];
            foreach ($linkIds as $linkIdKey) {
                if (strpos($linkIdKey, '_id')) {
                    if (!empty($selectData)) {
                        $filterItem["{$masterModuleCode}.{$linkIdKey}"] = ["IN", $idsString];
                    } else {
                        $filterItem["{$masterModuleCode}.{$linkIdKey}"] = 0;
                    }
                }

                if (strpos($linkIdKey, '_module_id')) {
                    $filterItem["{$masterModuleCode}.{$linkIdKey}"] = $itemModule['src_module_id'];
                }
            }

            if ($isComplex) {
                $filterData['_complex'] = $filterItem;
            } else {
                $filterData = $filterItem;
            }

        } else {
            if (!empty($selectData)) {
                $filterData["{$masterModuleCode}.{$itemModule['link_id']}"] = ["IN", $idsString];
            } else {
                $filterData["{$masterModuleCode}.{$itemModule['link_id']}"] = 0;
            }
        }

        return $filterData;
    }

    /**
     * 预处理实体任务过滤关联
     * @param $filterData
     * @param $masterModuleCode
     * @param $itemModule
     * @param $filter
     */
    private function parserFilterItemEntityTaskRelated(&$filterData, $masterModuleCode, $itemModule, $filter)
    {
        $selectData = $this->getModelObj('entity')->where($this->formatFilterCondition($filter))->select();
        if (!empty($selectData)) {
            $ids = array_column($selectData, 'id');
            $idsString = join(',', $ids);
            $filterItemData = $this->parserFilterItemComplexValue($masterModuleCode, $itemModule, $selectData, $idsString, false);
            if (empty($filterData)) {
                $filterData = $filterItemData;
            } else {
                $newfilterData = [];
                $newfilterData[] = $filterData;
                $newfilterData[] = $filterItemData;
                $newfilterData['_logic'] = 'OR';
                $filterData = $newfilterData;
            }

            if (array_key_exists('child', $itemModule)) {
                $entityFilter = [];
                foreach ($filterItemData as $filed => $value) {
                    $filedKey = explode('.', $filed)[1];
                    $entityFilter[$filedKey] = $value;
                }

                $this->parserFilterItemEntityTaskRelated($filterData, $masterModuleCode, $itemModule['child'], $entityFilter);
            }
        } else {
            if (empty($filterData)) {
                $filterData = $this->parserFilterItemComplexValue($masterModuleCode, $itemModule, $selectData, 0, false);
            }
        }

        return $filterData;
    }

    /**
     * 解析字段模块
     * @param $fields
     */
    private function parserFieldModule($fields)
    {
        $fieldsArr = explode(',', $fields);
        foreach ($fieldsArr as $fieldsItem) {
            if (strpos($fieldsItem, '.')) {
                $fieldsParam = explode('.', $fieldsItem);
                if (!in_array($fieldsParam[0], $this->complexFilterRelatedModule)) {
                    $this->isComplexFilter = true;
                    $this->complexFilterRelatedModule[] = $fieldsParam[0];
                }
            }
        }
    }


    /**
     * 处理水平自定义字段查询
     * @param $filterData
     * @param $field
     * @param $condition
     * @param string $moduleCode
     * @param $mapModule
     */
    private function parserFilterCutsomHorizontalCondition(&$filterData, $field, $condition, $moduleCode = '', $mapModule, $isMaster = true)
    {
        if ($isMaster) {
            // 主表关联
            if (is_array($condition)) {
                list($itemCondition, $itemValue) = $condition;
            } else {
                $itemValue = $condition;
            }

            if (is_array($itemValue)) {
                $itemValueStr = "";
                foreach ($itemValue as $item) {
                    $itemValueStr .= "\"{$item}\"" . ",";
                }
                $itemValueStr = substr($itemValueStr, 0, -1);
            } else {
                $itemValueStr = "\"{$itemValue}\"";
            }

            if (!empty($moduleCode)) {
                $filterData['_string'] = "JSON_CONTAINS('[{$itemValueStr}]' , json_extract({$moduleCode}.json, '$.{$field}' ))";
            } else {
                $filterData['_string'] = "JSON_CONTAINS('[{$itemValueStr}]' , json_extract(json, '$.{$field}' ))";
            }
        } else {
//        echo json_encode($field);
//        echo json_encode($condition);
//        $class = '\\common\\model\\' . string_initial_letter($mapModule) . 'Model';
//        $selectData = (new $class())->where($this->formatFilterCondition($filter))->select();
//        if (!empty($selectData)) {
//            $ids = array_column($selectData, 'id');
//            $idsString = join(',', $ids);
//        } else {
//            $idsString = 'null';
//        }
        }
    }

    /**
     * 处理自定义字段集合查询
     * @param $filterData
     * @param $field
     * @param $condition
     * @param $moduleCode
     */
    private function parserFilterCutsomItemCondition(&$filterData, $field, $condition, $moduleCode = '')
    {
        if (is_array($condition)) {
            list($itemCondition, $itemValue) = $condition;
            if (in_array(strtolower($itemCondition), ['in', 'not in'])) {
                if (is_string($itemValue)) {
                    $inArray = json_encode(explode(',', $itemValue), JSON_UNESCAPED_UNICODE);
                } else {
                    $inArray = json_encode($itemValue, JSON_UNESCAPED_UNICODE);
                }

                if (!empty($moduleCode)) {
                    $filterData["_string"] = "json_contains('{$inArray}', JSON_EXTRACT({$moduleCode}.json,'$.{$field}'))";
                } else {
                    $filterData["_string"] = "json_contains('{$inArray}', JSON_EXTRACT(json,'$.{$field}'))";
                }
            } else {
                if (!empty($moduleCode)) {
                    $filterData["json_extract({$moduleCode}.json, '$.{$field}' )"] = $condition;
                } else {
                    $filterData["json_extract(json, '$.{$field}' )"] = $condition;
                }
            }
        } else {
            if (!empty($moduleCode)) {
                $filterData["json_extract({$moduleCode}.json, '$.{$field}' )"] = $condition;
            } else {
                $filterData["json_extract(json, '$.{$field}' )"] = $condition;
            }
        }

        return $filterData;
    }

    /**
     * 预处理过滤条件项的值
     * @param $masterModuleCode
     * @param $itemModule
     * @param $filter
     * @return array
     */
    private function parserFilterItemValue($masterModuleCode, $itemModule, $filter)
    {
        $filterData = [];

        switch ($itemModule['filter_type']) {
            case 'master':
                // 主键查询只需要加上字段别名
                foreach ($filter as $field => $condition) {
                    if (array_key_exists($field, $this->queryComplexHorizontalCustomFieldMapping)) {
                        $this->parserFilterCutsomHorizontalCondition($filterData, $field, $condition, $masterModuleCode, $this->queryComplexHorizontalCustomFieldMapping[$field]);
                    } else {
                        if (array_key_exists($field, $this->queryComplexCustomFieldMapping)) {
                            $this->parserFilterCutsomItemCondition($filterData, $field, $condition, $masterModuleCode);
                        } else {
                            $filterData["{$masterModuleCode}.{$field}"] = $condition;
                        }
                    }

                }
                break;
            case 'direct':
                $selectData = $this->getModelObj($itemModule['module_code'])->where($this->formatFilterCondition($filter))->select();
                if (!empty($selectData)) {
                    $ids = array_column($selectData, 'id');
                    $idsString = join(',', $ids);
                } else {
                    $idsString = 'null';
                }

                if ($itemModule['type'] === 'horizontal') {
                    // 水平关联为自定义字段
                    if (empty($idsString) || $idsString == 'null') {
                        $filterData['_string'] = "JSON_EXTRACT({$masterModuleCode}.json, '$.{$itemModule['link_id']}' ) IS NULL";
                    } else {
                        $filterData['_string'] = "JSON_CONTAINS('[{$idsString}]', JSON_UNQUOTE(JSON_EXTRACT({$masterModuleCode}.json, '$.{$itemModule['link_id']}' ) ) )";
                    }
                } else {
                    // 普通直接查询条件
                    $filterData = $this->parserFilterItemComplexValue($masterModuleCode, $itemModule, $selectData, $idsString);
                }
                break;
            case 'entity':
                // 只有用entity查询task时候需要特殊处理
                if ($masterModuleCode === 'task') {
                    // 得到各个层级的id
                    $filterEntityTaskData = [];
                    $filterData['_complex'] = $this->parserFilterItemEntityTaskRelated($filterEntityTaskData, $masterModuleCode, $itemModule, $filter);
                } else {
                    $tableName = get_module_table_name([
                        "type" => $itemModule['filter_type'],
                        "code" => $itemModule['module_code']
                    ]);

                    $selectData = $this->getModelObj($tableName)->where($this->formatFilterCondition($filter))->select();
                    if (!empty($selectData)) {
                        $ids = array_column($selectData, 'id');
                        $idsString = join(',', $ids);
                    } else {
                        $idsString = 'null';
                    }

                    $filterData = $this->parserFilterItemComplexValue($masterModuleCode, $itemModule, $selectData, $idsString);
                }
                break;
        }

        return $filterData;
    }

    /**
     * 预处理过滤条件值
     * @param $complexFilter
     * @param $filterReverse
     * @param $filterModuleLinkRelation
     * @return array
     */
    private function parserFilterValue(&$complexFilter, $filterReverse, $filterModuleLinkRelation)
    {
        foreach ($filterReverse as $filterGroupItem) {

            $filterTemp = [];
            foreach ($filterGroupItem as $key => $filterItem) {
                if ($key !== '_logic') {
                    if ($key === $this->currentModuleCode) {
                        // 当前模块
                        $filterTempItem = $this->parserFilterItemValue($this->currentModuleCode, [
                            "type" => "",
                            "module_code" => $this->currentModuleCode,
                            "relation_type" => "",
                            "src_module_id" => Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode]['id'],
                            "dst_module_id" => 0,
                            "link_id" => "id",
                            "filter_type" => "master"
                        ], $filterItem);
                    } else {
                        $filterTempItem = $this->parserFilterItemValue($this->currentModuleCode, $filterModuleLinkRelation[$key], $filterItem);
                    }

                    foreach ($filterTempItem as $filterTempItemKey => $filterTempItemVal) {
                        if (array_key_exists($filterTempItemKey, $filterTemp)) {
                            $filterTemp[] = $filterTempItemVal;
                        } else {
                            switch ($filterTempItemKey) {
                                case '_complex':
                                    $filterTemp['_complex'] = $filterTempItemVal;
                                    break;
                                case '_string':
                                    $filterTemp['_string'] = $filterTempItemVal;
                                    break;
                                default:
                                    $filterTemp[$filterTempItemKey] = $filterTempItemVal;
                                    break;
                            }
                        }
                    }
                }
            }


            if (array_key_exists('_logic', $filterGroupItem)) {
                $logic = $filterGroupItem['_logic'];
            } else {
                $logic = 'AND';
            }


            if (!empty($complexFilter)) {
                if (!empty($filterTemp)) {
                    $filterMid = $complexFilter;
                    $complexFilter = [];
                    $complexFilter[] = $filterMid;
                    $complexFilter[] = $filterTemp;
                    $complexFilter['_logic'] = $logic;
                } else {
                    $complexFilter['_logic'] = $logic;
                }
            } else {
                $filterTemp['_logic'] = $logic;
                $complexFilter = $filterTemp;
            }
        }

        return $complexFilter;
    }

    /**
     * 处理第一层过滤条件键值
     * @param $filter
     * @return array
     */
    private function parseComplexFilterKey($filter)
    {
        $newFilter = [];
        $index = 1;
        foreach ($filter as $filterKey => $filterVal) {
            if ($filterKey === '_logic') {
                $newFilter[$filterKey] = $filterVal;
            } else {
                if ($filterKey === 0 || (int)$filterKey > 0) {
                    $newFilter[(string)($filterKey + 1)] = $filterVal;
                } else {
                    $newFilter[(string)$index] = [$filterKey => $filterVal];
                }
            }
            $index++;
        }

        return $newFilter;
    }


    /**
     * 替换过滤条件中的方法名
     * @param $val
     */
    private function checkIsComplexFilterFields(&$val, $key)
    {
        if (strpos($val, '.') !== false) {
            // 是复杂过滤条件
            $this->isComplexFilter = true;
        }
    }


    /**
     * 处理简单过滤条件
     * @param $filter
     */
    private function parseSimpleFilter(&$filter)
    {
        foreach ($filter as $key => &$val) {
            if (is_array($val) && (is_many_dimension_array($val) || count($val) > 1)) {
                $this->parseSimpleFilter($val);
            } else {
                if (is_array($val) && array_key_exists('0', $val)) {
                    $val = $this->buildWidgetFilter($this->currentModuleCode, $key, $val);
                }
            }
        }
    }

    /**
     * 处理过滤条件
     * @param $filterfields
     * @return array
     */
    private function buildFilter($filter, $fields)
    {
        if ($this->isComplexFilter) {
            // 复杂过滤条件处理
            $filterReverse = [];
            $filter = $this->parseComplexFilterKey($filter);

            // 处理所有 module relation 链路数据
            $filterModuleLinkRelation = $this->parserFilterModuleRelation();

            $this->parserFilterParam($filterReverse, $filter, $filter);

            // 预处理过滤条件值
            $complexFilter = [];
            $this->parserFilterValue($complexFilter, $filterReverse, $filterModuleLinkRelation);

            return $complexFilter;
        } else {
            // 普通过滤条件处理
            $this->parseSimpleFilter($filter);
        }

        return $filter;
    }

    /**
     * 处理复杂查询自定义字段字段
     * @param $field
     * @return string
     */
    private function handleQueryCustomFields($field)
    {
        if (strpos($field, '.') !== false) {
            $fieldArray = explode('.', $field);
            if (array_key_exists($fieldArray[1], $this->queryComplexCustomFieldMapping)) {
                $fieldConfig = $this->queryComplexCustomFieldMapping[$fieldArray[1]];
                return "JSON_UNQUOTE(JSON_EXTRACT({$fieldConfig['query_module_code']}.json, '$.{$fieldConfig['field']}'))";
            }
        } else {
            if (array_key_exists($field, $this->queryComplexCustomFieldMapping)) {
                $fieldConfig = $this->queryComplexCustomFieldMapping[$field];
                return "JSON_UNQUOTE(JSON_EXTRACT(json, '$.{$fieldConfig['field']}'))";
            }
        }

        return $field;
    }


    /**
     * 处理复杂查询字段
     * @param $fieldsArr
     * @return array
     */
    private function handleComplexFields($fieldsArr)
    {
        $newFields = [];
        $filterModuleLinkRelation = $this->parserFilterModuleRelation();

        foreach ($fieldsArr as $fieldItem) {
            // 找的可以belong_to的字段
            $moduleArray = explode('.', $fieldItem);

            if (array_key_exists($moduleArray[0], $this->queryModuleRelationFields)) {
                $this->queryModuleRelationFields[$moduleArray[0]][] = $moduleArray[1];
            } else {
                $this->queryModuleRelationFields[$moduleArray[0]] = [$moduleArray[1]];
            }

            if ($this->currentModuleCode !== $moduleArray[0]) {
                if ($filterModuleLinkRelation[$moduleArray[0]]['filter_type'] === "direct") {
                    switch ($filterModuleLinkRelation[$moduleArray[0]]['relation_type']) {
                        case "belong_to":
                            if (!empty($this->queryComplexRelationCustomFields[$moduleArray[0]])
                                && array_key_exists($moduleArray[1], $this->queryComplexRelationCustomFields[$moduleArray[0]])) {
                                $newFields[] = "JSON_UNQUOTE(JSON_EXTRACT({$moduleArray[0]}.json, '$.{$moduleArray[1]}')) AS {$moduleArray[0]}__{$moduleArray[1]}";
                            } else {
                                $newFields[] = "{$fieldItem} AS {$moduleArray[0]}__{$moduleArray[1]}";
                            }
                            if (!array_key_exists($moduleArray[0], $this->queryModuleLfetJoinRelation)) {
                                $this->queryModuleLfetJoinRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                            }
                            break;
                        case "has_one":
                            if ($filterModuleLinkRelation[$moduleArray[0]]['type'] === 'horizontal') {
                                // 水平关联自定义字段
                                $newFields[] = "{$fieldItem} AS {$moduleArray[0]}__{$moduleArray[1]}";
                                if (!array_key_exists($moduleArray[0], $this->queryModuleLfetJoinRelation)) {
                                    $filterModuleLinkRelation[$moduleArray[0]]['link_id'] = "JSON_UNQUOTE(JSON_EXTRACT({$this->currentModuleCode}.json, '$.{$filterModuleLinkRelation[$moduleArray[0]]['link_id']}'))";
                                    $this->queryModuleLfetJoinRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                                }
                            } else if ($filterModuleLinkRelation[$moduleArray[0]]['type'] === 'fixed') {
                                // 水平关联 固定字段
                                $newFields[] = "{$fieldItem} AS {$moduleArray[0]}__{$moduleArray[1]}";
                                if (!array_key_exists($moduleArray[0], $this->queryModuleLfetJoinRelation)) {
                                    $this->queryModuleLfetJoinRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                                }
                            }
                            break;
                        case "has_many":
                            // 一对多查询
                            $this->queryModuleHasManyRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                            if ($filterModuleLinkRelation[$moduleArray[0]]['type'] === 'horizontal') {
                                if (!array_key_exists($moduleArray[0], $this->queryModuleHorizontalRelation)) {
                                    $this->queryModuleHorizontalRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                                    $newFields[] = "JSON_UNQUOTE(JSON_EXTRACT({$this->currentModuleCode}.json, '$.{$filterModuleLinkRelation[$moduleArray[0]]['link_id']}')) AS {$moduleArray[0]}__link";
                                }
                            }
                            break;
                    }

                } else {
                    // 实体模块处理
                    $this->queryModuleEntityRelation[$moduleArray[0]] = $filterModuleLinkRelation[$moduleArray[0]];
                }
            } else {
                // 需要判断是不是水平关联字段
                if (array_key_exists($moduleArray[1], $this->queryComplexHorizontalCustomFieldMapping)) {
                    if ($filterModuleLinkRelation[$moduleArray[1]]["relation_type"] === "has_many") {
                        // 一对多水平自定义字段关联
                        $this->queryModuleHasManyRelation[$moduleArray[1]] = $filterModuleLinkRelation[$moduleArray[1]];
                        if ($filterModuleLinkRelation[$moduleArray[1]]['type'] === 'horizontal') {
                            if (!array_key_exists($moduleArray[1], $this->queryModuleHorizontalRelation)) {
                                $this->queryModuleHorizontalRelation[$moduleArray[1]] = $filterModuleLinkRelation[$moduleArray[1]];
                                $newFields[] = "JSON_UNQUOTE(JSON_EXTRACT({$this->currentModuleCode}.json, '$.{$filterModuleLinkRelation[$moduleArray[1]]['link_id']}')) AS {$moduleArray[1]}__link";
                            }
                        }
                    } else {
                        if (!array_key_exists($moduleArray[1], $this->queryModuleLfetJoinRelation)) {

                            // 默认增加id name code 字段
                            $newFields[] = "{$moduleArray[1]}.id AS {$moduleArray[1]}__id";
                            $newFields[] = "{$moduleArray[1]}.name AS {$moduleArray[1]}__name";
                            $newFields[] = "{$moduleArray[1]}.code AS {$moduleArray[1]}__code";

                            $filterModuleLinkRelation[$moduleArray[1]]['link_id'] = "JSON_UNQUOTE(JSON_EXTRACT({$this->currentModuleCode}.json, '$.{$filterModuleLinkRelation[$moduleArray[1]]['link_id']}'))";
                            $this->queryModuleLfetJoinRelation[$moduleArray[1]] = $filterModuleLinkRelation[$moduleArray[1]];
                        }
                    }
                } else {
                    $newFields[] = "{$this->handleQueryCustomFields($fieldItem)} AS {$moduleArray[0]}__{$moduleArray[1]}";
                }
            }
        }

        if (array_key_exists($this->currentModuleCode, $this->queryModuleRelationFields) && !in_array('id', $this->queryModuleRelationFields[$this->currentModuleCode])) {
            // 主表必须查询返回ID字段
            array_unshift($newFields, "{$this->currentModuleCode}.id AS {$this->currentModuleCode}__id");
        }

        return $newFields;
    }

    /**
     * 构建查询字段
     * @param $field
     * @return array
     */
    public function buildFields($field)
    {
        if ($this->isComplexFilter) {
            // 处理所有 module relation 链路数据
            $fieldsArr = explode(',', $field);

            if (strpos($field, '.') === false) {
                // 仅查询主表字段，但需要复杂查询

                foreach ($fieldsArr as &$fieldItem) {
                    $fieldItem = "{$this->currentModuleCode}.{$fieldItem}";
                }
            }

            $newFields = $this->handleComplexFields($fieldsArr);

            return join(',', $newFields);
        } else {
            $fieldsArr = explode(',', $field);
            $this->handleQueryModuleCustomFields($fieldsArr, $this->currentModuleCode);

            return join(',', $fieldsArr);
        }
    }

    /**
     * 自动填充实体当前模块过滤条件ID
     * @param $filter
     * @return array
     */
    private function autoFillEntityModuleIdFilter($filter)
    {
        $newFilter = [];
        $currentModuleData = Module::$moduleDictData['module_index_by_code'][$this->currentModuleCode];
        if ($this->isComplexFilter) {
            // 关联查询
            if (!empty($filter)) {
                $newFilter = [
                    $filter,
                    [
                        "{$this->currentModuleCode}.module_id" => $currentModuleData['id'],
                    ],
                    "_logic" => "AND"
                ];
            } else {
                $newFilter["{$this->currentModuleCode}.module_id"] = $currentModuleData['id'];
            }
        } else {
            // 非关联查询
            if (!empty($filter)) {
                $newFilter = [
                    $filter,
                    "module_id" => $currentModuleData['id'],
                    "_logic" => "AND"
                ];
            } else {
                $newFilter["module_id"] = $currentModuleData['id'];
            }
        }

        return $newFilter;
    }

    /**
     * 自动填充当前租户过滤条件ID
     * @param $filter
     * @return array
     */
    private function autoFillTenantIdFilter($filter)
    {
        $newFilter = [];
        $tenantId = \request()->tenantId;
        if ($this->isComplexFilter) {
            // 关联查询
            if (!empty($filter)) {
                $newFilter = [
                    $filter,
                    [
                        "{$this->currentModuleCode}.tenant_id" => $tenantId,
                    ],
                    "_logic" => "AND"
                ];
            } else {
                $newFilter["{$this->currentModuleCode}.tenant_id"] = $tenantId;
            }
        } else {
            // 非关联查询
            if (!empty($filter)) {
                $newFilter = [
                    $filter,
                    "tenant_id" => $tenantId,
                    "_logic" => "AND"
                ];
            } else {
                $newFilter["tenant_id"] = $tenantId;
            }
        }

        return $newFilter;
    }


    /**
     * 判断是否是复杂过滤条件
     * @param $options
     */
    private function checkIsComplexFilter(&$options)
    {
        if (!empty($options)) {

            if (array_key_exists('filter', $options)) {
                $filterStr = json_encode($options['filter']);
                if (strpos($filterStr, '.') !== false) {
                    // 是复杂过滤条件
                    $this->isComplexFilter = true;
                }

                //处理筛选字段
                $this->parserFilterModule($options['filter']);

            }

            // 处理查询字段
            if (!empty($options['fields'])) {
                $this->parserFieldModule($options['fields']);
            }
        }

        // entity模块需要加入module_id 默认过滤条件
        if (in_array(strtolower($this->name), ['entity'])) {
            $filter = !empty($options['filter']) ? $options['filter'] : [];
            $options['filter'] = $this->autoFillEntityModuleIdFilter($filter);
        }

        // 存在租户的模块需要自动增加租户过滤条件
        if (in_array(strtolower($this->name), [
            'calendar', 'entity', 'filter', 'media', 'note', 'onset', 'plan', 'project', 'project_user', 'task', 'timelog'
        ])) {
            $filter = !empty($options['filter']) ? $options['filter'] : [];
            $options['filter'] = $this->autoFillTenantIdFilter($filter);
        }
    }

    /**
     * 处理关联查询jion sql 组装
     */
    private function parseQueryRelationDataJoinSql()
    {
        if (!empty($this->queryModuleLfetJoinRelation)) {

            // left join
            foreach ($this->queryModuleLfetJoinRelation as $joinMoudleCode => $joinItem) {

                if ($joinItem['type'] === 'horizontal') {
                    $linkIds = [$joinItem['link_id']];
                } else {
                    $linkIds = explode(',', $joinItem['link_id']);
                }


                $queryJoin = [
                    'type' => 'one',
                    'condition' => []
                ];

                foreach ($linkIds as $linkId) {
                    if (strpos($linkId, 'module_id') !== false) {
                        if ($this->currentModuleCode === 'task') {
                            $queryJoin['condition'][] = "{$joinMoudleCode}.id = {$this->currentModuleCode}.entity_module_id";
                        } else {
                            $queryJoin['condition'][] = "{$joinMoudleCode}.id = {$this->currentModuleCode}.{$linkId}";
                        }
                    } else {
                        if ($linkId) {
                            if (in_array($linkId, ['assignee', 'executor', 'created_by'])) {
                                // 需要分为多个join
                                $queryJoin['type'] = 'multiple';
                            }

                            if ($joinItem['type'] === 'horizontal') {
                                $queryJoin['condition'][] = "{$joinMoudleCode}.id = {$linkId}";
                            } else {
                                // 区分belong_to 和has_one
                                if ($joinItem['relation_type'] == "has_one") {
                                    $queryJoin['condition'][] = "{$joinMoudleCode}.{$linkId} = {$this->currentModuleCode}.id";
                                } else if ($joinItem['relation_type'] == "belong_to") {
                                    $queryJoin['condition'][] = " {$joinMoudleCode}.id = {$this->currentModuleCode}.{$linkId} ";
                                }
                            }

                        }
                    }
                }

                $joinModuleSourceCode = $joinItem['module_code'];
                if (!empty(Module::$moduleDictData['module_index_by_code'][$joinItem['module_code']])) {
                    if (Module::$moduleDictData['module_index_by_code'][$joinItem['module_code']]['type'] === 'entity') {
                        $joinModuleSourceCode = 'entity';
                    }
                }

                if ($queryJoin['type'] === 'one') {
                    $conditionString = join('AND', $queryJoin['condition']);
                    substr($conditionString, 0, -strlen('AND'));

                    $this->join("LEFT JOIN `{$joinModuleSourceCode}` AS `{$joinMoudleCode}` ON {$conditionString}");
                } else {
                    foreach ($queryJoin['condition'] as $conditionItem) {
                        $this->join("LEFT JOIN `{$joinModuleSourceCode}` AS `{$joinMoudleCode}` ON {$conditionItem}");
                    }
                }
            }
        }
        return $this;
    }


    /**
     * 获取一条数据
     * @param array $options
     * @param bool $needFormat
     * @return array|mixed
     */
    public function findData($options = [], $needFormat = true)
    {
        $this->checkIsComplexFilter($options);

        if ($this->isComplexFilter) {
            $this->alias($this->currentModuleCode);
        }

        if (array_key_exists("fields", $options)) {
            // 有字段参数
            $this->field($this->buildFields($options["fields"]));
        }

        if (array_key_exists("filter", $options) && !empty($options['filter'])) {
            //有过滤条件
            $fields = !empty($options["fields"]) ? $options["fields"] : [];
            $filter = $this->buildFilter($options["filter"], $fields);
            $this->where($filter);
        }

        // 处理join查询
        $this->parseQueryRelationDataJoinSql();

        $findData = $this->find();

        if (empty($findData)) {
            $this->error = 'Data does not exist.';
            return [];
        }

        // 数据格式化
        if ($needFormat) {
            if ($this->isComplexFilter) {
                $handleFindData = $this->handleReturnComplexData($findData, 'find');
                return $handleFindData[0];
            } else {
                return $this->handleReturnData(false, $findData);
            }
        } else {
            return $findData;
        }
    }


    /**
     * 获取多条数据
     * @param array $options
     * @param bool $needFormat
     * @return array
     */
    public function selectData($options = [], $needFormat = true)
    {

        $this->checkIsComplexFilter($options);

        if ($this->isComplexFilter) {
            $this->alias($this->currentModuleCode);
        }

        $filter = [];
        if (array_key_exists("filter", $options) && !empty($options['filter'])) {
            // 有过滤条件
            $fields = !empty($options["fields"]) ? $options["fields"] : [];
            $filter = $this->buildFilter($options["filter"], $fields);
            $this->where($filter);
        }

        // 统计个数
        $total = $this->count();


        // 获取数据
        if ($total >= 0) {

            if ($this->isComplexFilter) {
                $this->alias($this->currentModuleCode);
            }

            if (array_key_exists("fields", $options)) {
                // 有字段参数
                $this->field($this->buildFields($options["fields"]));
            }

            // 处理join查询
            $this->parseQueryRelationDataJoinSql();

            if (array_key_exists("filter", $options)) {
                // 有过滤条件
                $this->where($filter);
            }

            if (array_key_exists("page", $options)) {
                // 有分页参数
                $pageSize = $options["page"][1] > C("DB_MAX_SELECT_ROWS") ? C("DB_MAX_SELECT_ROWS") : $options["page"][1];
                $this->page($options["page"][0], $pageSize);
            } else {
                if (array_key_exists("limit", $options) && $options["limit"] <= C("DB_MAX_SELECT_ROWS")) {
                    // 有limit参数
                    $this->limit($options["limit"]);
                } else {
                    $this->limit(C("DB_MAX_SELECT_ROWS"));
                }
            }

            if (array_key_exists("order", $options)) {
                // 有order参数
                $this->order($options["order"]);
            }

            $selectData = $this->select();

        } else {
            $selectData = [];
        }

        if (empty($selectData)) {
            $this->error = 'Data does not exist.';
            return ["total" => 0, "rows" => []];
        }

        // 数据格式化
        if ($needFormat) {
            if ($this->isComplexFilter) {
                $selectData = $this->handleReturnComplexData($selectData, 'select');
            } else {
                foreach ($selectData as &$selectItem) {
                    $selectItem = $this->handleReturnData(false, $selectItem);
                }
            }

            return ["total" => $total, "rows" => $selectData];
        } else {
            return ["total" => $total, "rows" => $selectData];
        }
    }


    /**
     * 获取字段数据源映射
     */
    private function getFieldFromDataDict()
    {
        // 用户数据映射
        $allUserData = M("User")->field("id,name")->select();
        $allUserDataMap = array_column($allUserData, null, "id");
        $this->_fieldFromDataDict["user"] = $allUserDataMap;

        // 模块数据映射
        $allModuleData = M("Module")->field("id,name,code,type")->select();
        $moduleMapData = [];
        $moduleCodeMapData = [];
        foreach ($allModuleData as $allModuleDataItem) {
            $moduleMapData[$allModuleDataItem["id"]] = $allModuleDataItem;
            $moduleCodeMapData[$allModuleDataItem["code"]] = $allModuleDataItem;
        }

        $this->_fieldFromDataDict["module"] = $moduleMapData;
        $this->_fieldFromDataDict["module_code"] = $moduleCodeMapData;;
    }

    /**
     * 关联模型查询
     * @param array $param
     * @param string $formatMode
     * @return array
     */
    public function getRelationData($param = [])
    {

    }

    /**
     * 生成排序规则
     * @param $sortRule
     * @param $groupRule
     * @return string
     */
    private function buildSortRule($sortRule, $groupRule = [])
    {

    }

    /**
     * 生成模块字段
     * @param $moduleCode
     */
    private function generateQueryModuleFieldDict($moduleCode)
    {
        if (!empty($this->queryModuleFieldDict[$moduleCode])) {
            return $this->queryModuleFieldDict[$moduleCode];
        }

        if (array_key_exists($moduleCode, $this->queryComplexHorizontalCustomFieldMapping)) {
            $moduleCode = $this->queryComplexHorizontalCustomFieldMapping[$moduleCode];
        }

        if (!array_key_exists($moduleCode, Module::$moduleDictData['field_index_by_code'])) {
            throw_strack_exception("{$moduleCode} module does not exist.", -400003);
        }

        $currentModuleFields = Module::$moduleDictData['field_index_by_code'][$moduleCode];

        $this->queryModuleFieldDict[$moduleCode] = array_merge($currentModuleFields['fixed'], $currentModuleFields['custom']);

        return $this->queryModuleFieldDict[$moduleCode];
    }

    /**
     * 生成控件过滤条件
     * @param $moduleCode
     * @param $filed
     * @param $value
     * @return array
     */
    public function buildWidgetFilter($moduleCode, $filed, $value)
    {

        // 获取模块字段
        $currentModuleFieldsDict = $this->generateQueryModuleFieldDict($moduleCode);

        // 判断 value 是否为条件表达式
        if (is_array($value)) {
            list($condition, $filterVal) = $value;
        } else {
            return $value;
        }

        switch ($currentModuleFieldsDict[$filed]['editor']) {
            case "input":
            case "text_area":
            case "rich_text":
            case "link":
            case "select":
            case "tag":
            case "switch":
            case "radio":
            case "checkbox":
                switch ($condition) {
                    case "LIKE":
                    case "NOTLIKE":
                        $conditionValue = "%" . $filterVal . "%";
                        break;
                    default:
                        $conditionValue = $filterVal;
                        break;
                }
                return [$condition, $conditionValue];
                break;
            case "times":
            case "date":
                switch ($condition) {
                    case "BETWEEN":
                        $dateBetween = explode(",", $filterVal);
                        return [$condition, [strtotime($dateBetween[0]), strtotime($dateBetween[1])]];
                        break;
                    default:
                        return $value;
                        break;
                }
                break;
            default:
                return $value;
                break;
        }
    }

    /**
     * 判断过滤条件值是否是Null或者空
     * @param $condition
     * @param $value
     * @return array
     */
    protected function checkFilterValWeatherNullOrEmpty($condition, $value)
    {
        if (is_array($value)) {
            // 为数组
            $len = count($value);
            if ($len === 1 && $value[$len - 1] == 0) {
                $this->isNullOrEmptyFilter = true;
                $condition = 'NEQ';
                $value = 0;
            }
        } else if ((int)$value === 0) {
            // 当前过滤条件值为零
            $condition = 'NEQ';
            $this->isNullOrEmptyFilter = true;
        }

        return [$condition, $value];
    }

    /**
     * 解析过滤器的模块
     * @param $filter
     */
    private function parserFilterModule($filter)
    {
        foreach ($filter as $key => $value) {
            if (strpos($key, '.') === false && is_array($value) && count($value) > 1) {
                $this->parserFilterModule($value);
                continue;
            }

            if (strpos($key, '.') > 0) {
                $fieldsParam = explode('.', $key);
                if (!in_array($fieldsParam[0], $this->complexFilterRelatedModule)) {
                    $this->complexFilterRelatedModule[] = $fieldsParam[0];
                }
            }
        }
    }

}

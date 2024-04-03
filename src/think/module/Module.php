<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\module;

use think\Cache;
use think\Db;
use think\exception\ErrorCode;

class Module
{
    // 必须包含模块
    protected static $requireModules = ['entity', 'module', 'module_relation', 'field', 'schema'];

    // 模块字典缓存数据
    public static $moduleDictData = [];

    // 模块关联字典缓存数据
    public static $moduleRelationData = [];

    // 水平关联模块自定义字段字典缓存数据
    public static $moduleCustomHorizontalFieldsDict = [];

    // 包含租户id的模块列表数据
    public static $includeTenantIdModules = [];

    // 刷新缓存锁
    protected static $refreshLock = false;

    /**
     * 初始化数据
     * @return void
     * @throws \Exception
     */
    private static function generateModuleData()
    {
        self::getModuleData();
        self::generateModuleFieldCache(self::$moduleDictData['module_index_by_id']);
        self::generateCustomHorizontalFieldsCache();
    }

    /**
     * 模块初始化
     * @throws \Exception
     */
    public static function init()
    {
        if (empty(self::$moduleDictData)) {
            self::generateModuleData();
        }
    }

    /**
     * _ 名称转驼峰
     * @param $unCamelizeWords
     * @param string $separator
     * @return mixed
     */
    public static function camelize($unCamelizeWords, $separator = '_')
    {
        $unCamelizeWords = $separator . str_replace($separator, " ", strtolower($unCamelizeWords));
        return str_replace(" ", "", ucwords(ltrim($unCamelizeWords, $separator)));
    }

    /**
     * 获取所有模块数据
     * @param $tempDb
     * @return array
     */
    public static function getAllModuleData($tempDb)
    {
        $result = $tempDb->query("select * from module");
        $info = [];
        foreach ($result as $key => $val) {
            $info[$val['code']] = $val;
        }
        return $info;
    }

    /**
     * 修正数据结构
     * @param $fixedModule
     * @param $tempDb
     */
    public static function fixedModuleConfig($fixedModule, $tempDb)
    {

        // 获取所有存在的表
        $moduleMap = self::getAllModuleData($tempDb);

        foreach ($fixedModule as $fixedItem) {
            if (!array_key_exists($fixedItem, $moduleMap)) {
                $tables[] = [
                    'type' => 'fixed',
                    'name' => $fixedItem
                ];
            }
        }

        if (!empty($tables)) {
            // 存在新模块添加到module表
            self::saveNewModule($tempDb, $tables);
        }
    }

    /**
     * 保存新模块
     * @param $tempDb
     * @param $tables ['type' => 'fixed','name' => ‘xxx’]
     * @return void
     */
    public static function saveNewModule($tempDb, $tables)
    {
        $result = $tempDb->query("select max(id) as max_id from module");
        $insertId = !empty($result[0]['max_id']) ? $result[0]['max_id'] + 1 : 1;

        foreach ($tables as $tableItem) {

            if ($tableItem["name"] !== "phinxlog") {

                // 组装注册固定模块数据
                $modelName = $tableItem["type"] === "entity" ? 'Entity' : self::camelize($tableItem["name"]);

                $moduleRows[] = [
                    'type' => $tableItem["type"],
                    'active' => 'yes',
                    'name' => self::camelize($tableItem["name"]),
                    'code' => $tableItem["name"],
                    'icon' => '',
                    'uuid' => \Webpatser\Uuid\Uuid::generate()->string
                ];

                // 组装当前模块字段配置数据
                $tableName = $tableItem["type"] === "entity" ? 'entity' : $tableItem["name"];
                $currentTableFields = $tempDb->getFields($tableName);

                $tempConfig = [];
                foreach ($currentTableFields as $field => $param) {
                    $tempConfig[] = Fields::generateFieldConfig($modelName, $tableItem["name"], $insertId, $field, $param);
                }

                $fieldsRows[] = [
                    'table' => $tableName,
                    'module_id' => $insertId,
                    'config' => json_encode($tempConfig),
                    'uuid' => \Webpatser\Uuid\Uuid::generate()->string
                ];

                $insertId++;
            }
        }

        // 写入模块数据表
        if (!empty($moduleRows)) {
            $tempDb->insertAll($moduleRows, ['table' => 'module', 'model' => 'module']);
        }


        // 写入字段数据表
        if (!empty($fieldsRows)) {
            $tempDb->insertAll($fieldsRows, ['table' => 'field', 'model' => 'module']);
        }
    }

    /**
     * 检测数据结构，使用本框架必须包含
     * @throws \Exception
     */
    public static function checkSchema()
    {
        $options = Db::parseConfig();
        $class = 'think\\db\\driver\\' . ucwords(strtolower($options['type']));
        if (class_exists($class)) {
            $tempDb = new $class($options);
        } else {
            // 类没有定义
            throw new \RuntimeException('Class is not defined ' . $class, ErrorCode::CLASS_NOT_DEFINED);
        }

        $tables = $tempDb->getTables();
        if (!empty($tables) && is_array($tables)) {
            foreach (self::$requireModules as $requireModule) {
                if (!in_array($requireModule, $tables)) {
                    throw new \RuntimeException('Data structure error. Need module ' . join(',', self::$requireModules), ErrorCode::DATA_STRUCTURE_NEED_MODULE);
                }
            }

            // 修正module配置
            self::fixedModuleConfig($tables, $tempDb);

            // 销毁对象
            unset($tempDb);

            // 清除数据表字段缓存
            Cache::init(config('redis'));
            foreach ($tables as $tableName) {
                S('fields_' . strtolower($tableName), null);
            }
            Cache::destroy(config('redis'));
        } else {
            throw new \RuntimeException('Data structure error. Need module ' . join(',', self::$requireModules), ErrorCode::DATA_STRUCTURE_NEED_MODULE);
        }
    }

    /**
     * 获取模块数据
     * @throws \Exception
     */
    protected static function getModuleData()
    {
        // 获取所有注册模块
        $moduleList = Db::getInstance()->query("SELECT id,type,name,code,uuid FROM module");

        self::$moduleDictData['module_index_by_id'] = array_column($moduleList, null, 'id');
        self::$moduleDictData['module_index_by_code'] = array_column($moduleList, null, 'code');

        // 获取所有实体code列表
        self::$moduleDictData['entity_code_list'] = Db::getInstance()->query("SELECT code FROM module WHERE type='entity'");

        // 获取模块关联数据
        self::$moduleRelationData = Db::getInstance()->query("SELECT id,type as relation_type,src_module_id,dst_module_id,link_id FROM module_relation");
    }

    /**
     * 生成模块字段缓存
     * @param $moduleIndexById
     * @throws \Exception
     */
    protected static function generateModuleFieldCache($moduleIndexById)
    {
        // 获取所有模块字段
        self::$moduleDictData['field_index_by_code'] = Fields::getAllModuleFieldsMapData($moduleIndexById);
    }

    /**
     * 生成模块 水平自定义字段 缓存
     */
    protected static function generateCustomHorizontalFieldsCache()
    {
        self::$moduleCustomHorizontalFieldsDict = Fields::generateCustomHorizontalFieldsCache();
    }

    /**
     * 设置租户id模块数据
     * @param string $moduleCOde
     */
    public static function setTenantIdModules($moduleCOde = '')
    {
        if (!in_array($moduleCOde, self::$includeTenantIdModules)) {
            self::$includeTenantIdModules[] = $moduleCOde;
        }
    }

    /**
     * 刷新模块配置
     * @return bool
     * @throws \Exception
     */
    public static function refreshModuleConfig()
    {
        if (self::$refreshLock) {
            return false;
        }
        self::$refreshLock = true;
        try {
            self::generateModuleData();
        } finally {
            self::$refreshLock = false;
        }
        return true;
    }
}

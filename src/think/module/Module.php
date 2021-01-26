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

use think\Db;

class Module
{
    // 必须包含模块
    protected static $requireModules = ['entity', 'module', 'module_relation', 'field', 'schema'];

    // 模块字典缓存数据
    public static $moduleDictData = [];

    // 包含租户id的模块列表数据
    public static $includeTenantIdModules = [];

    /**
     * 模块初始化
     * @throws \think\Exception
     */
    public static function init()
    {
        if(empty(self::$moduleDictData)){
            self::getModuleData();
            self::generateModuleFieldCache(self::$moduleDictData['module_index_by_id']);

        }
    }


    /**
     * 检测数据结构，使用本框架必须包含
     * @param string $dbName
     * @throws \think\Exception
     */
    public static function checkSchema()
    {
        $options = Db::parseConfig();
        $class = 'think\\db\\driver\\' . ucwords(strtolower($options['type']));
        if (class_exists($class)) {
            $tempDb =  new $class($options);
        } else {
            // 类没有定义
            throw new \RuntimeException('Class is not defined '. $class);
        }

        $tables = $tempDb->getTables();
        unset($tempDb);

        if(!empty($tables) && is_array($tables)){
            foreach (self::$requireModules as $requireModule){
                if(!in_array($requireModule, $tables)){
                    throw new \RuntimeException('Data structure error. Need module '. join(',', self::$requireModules));
                }
            }
        }else{
            throw new \RuntimeException('Data structure error. Need module '. join(',', self::$requireModules));
        }
    }

    /**
     * 获取模块数据
     * @throws \think\Exception
     */
    protected static function getModuleData()
    {
        // 获取所有注册模块
        $moduleList = Db::getInstance()->query("SELECT id,type,name,code,uuid FROM module");

        self::$moduleDictData['module_index_by_id'] = array_column($moduleList, null, 'id');
        self::$moduleDictData['module_index_by_code'] = array_column($moduleList, null, 'code');
    }

    /**
     * 生成模块字段缓存
     * @param $moduleIndexById
     * @return array
     * @throws \think\Exception
     */
    protected static function generateModuleFieldCache($moduleIndexById)
    {
        // 获取所有模块字段
        self::$moduleDictData['field_index_by_code'] = Fields::getAllModuleFieldsMapData($moduleIndexById);
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
}

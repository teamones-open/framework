<?php
namespace think\module;

use think\Db;

class Fields
{
    /**
     * 获取所有模块字段按照模块id映射数据
     * @param array $moduleMapData
     * @return array
     * @throws \think\Exception
     */
    public static function getAllModuleFieldsMapData($moduleMapData = [])
    {
        $allFieldsData = Db::getInstance()->query("SELECT type,module_id,config FROM field");

        // 已经 module_id 为键值，按固定和自定义分组划分字段数据
        $fieldModuleMapData = [];
        foreach ($allFieldsData as $allFieldsItem) {

            if (!empty($moduleMapData) && isset($moduleMapData[$allFieldsItem['module_id']])) {
                $moduleKey = $moduleMapData[$allFieldsItem['module_id']]["code"];
            } else {
                $moduleKey = $allFieldsItem['module_id'];
            }

            if (!array_key_exists($moduleKey, $fieldModuleMapData)) {
                $fieldModuleMapData[$moduleKey] = [
                    'fixed' => [],
                    'custom' => []
                ];
            }

            $config = json_decode($allFieldsItem['config'], true);
            if ($allFieldsItem['type'] === 'built_in') {
                // 固定字段
                foreach ($config as $item) {
                    $fieldModuleMapData[$moduleKey]['fixed'][$item['field']] = $item;

                    if($item['field'] === "tenant_id"){
                        // 包含租户id
                        Module::setTenantIdModules($moduleKey);
                    }
                }
            } else {
                // 自定义字段
                $fieldModuleMapData[$moduleKey]['custom'][$config['field']] = $config;
            }
        }

        return $fieldModuleMapData;
    }
}
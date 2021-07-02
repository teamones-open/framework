<?php

declare(strict_types=1);

// +----------------------------------------------------------------------
// | The teamones framework runs on the workerman high performance framework
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// | Reviser: weijer <weiwei163@foxmail.com>
// +----------------------------------------------------------------------

namespace think\module;

use think\Db;

class Fields
{
    /**
     * 获取所有模块字段按照模块id映射数据
     * @param array $moduleMapData
     * @return array
     * @throws \Exception
     */
    public static function getAllModuleFieldsMapData($moduleMapData = []): array
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

                    if ($item['field'] === "tenant_id") {
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

    /**
     * 判断当前字段是否为必须
     * @param string $field
     * @return string
     */
    public static function checkFieldRequire(string $field): string
    {
        if (in_array($field, ['name', 'phone', 'password', 'value', 'ptype', 'status', 'man_hour', 'type', 'attribute_value'])) {
            return 'yes';
        }

        if (strpos($field, '_id')) {
            return 'yes';
        }

        return 'no';
    }

    /**
     * 判断当前字段是否能编辑
     * @param string $field
     * @return string
     */
    public static function checkFieldEdit(string $field): string
    {
        if (in_array($field, ['id', 'uuid', 'created_by', 'created', 'json', 'is_horizontal'])) {
            return 'deny';
        }

        if (strpos($field, '_id')) {
            return 'deny';
        }

        return 'allow';
    }

    /**
     * 判断当前字段是否能显示
     * @param string $field
     * @return string
     */
    public static function checkFieldShow(string $field): string
    {
        if (in_array($field, ['json', 'password'])) {
            return 'no';
        }

        return 'yes';
    }


    /**
     * 判断当前字段是否能排序
     * @param string $field
     * @return string
     */
    public static function checkFieldSort(string $field): string
    {
        if (in_array($field, ['name', 'code', 'attribute_id', 'start_time', 'end_time', 'type', 'created_by', 'created', 'project_id', 'category_id', 'step_category_id'])) {
            return 'allow';
        }


        return 'deny';
    }


    /**
     * 判断当前字段是否能分组
     * @param string $field
     * @return string
     */
    public static function checkFieldGroup(string $field): string
    {
        if (strpos($field, '_id')) {
            return 'allow';
        }

        return 'deny';
    }

    /**
     * 判断当前字段是否能过滤
     * @param string $field
     * @return string
     */
    public static function checkFieldFilter(string $field): string
    {
        if (in_array($field, ['id', 'uuid', 'json', 'config', 'param', 'admin_password', 'node_config'])) {
            return 'deny';
        }

        return 'allow';
    }

    /**
     * 判断当前字段是否能过滤
     * @param string $field
     * @return string
     */
    public static function checkFieldPrimaryKey(string $field): string
    {
        if ($field === 'id') {
            return 'yes';
        }

        return 'no';
    }

    /**
     * 判断当前字段是否能过滤
     * @param string $field
     * @return string
     */
    public static function checkFieldForeignKey(string $field): string
    {
        if (strpos($field, '_id')) {
            return 'yes';
        }

        return 'no';
    }

    /**
     * 获取固定字段的编辑器类型
     * @param string $field
     * @param string $type
     * @return string
     */
    public static function getFixedFieldEditor(string $field, string $type): string
    {
        if (
            strpos($field, '_id')
            || strpos($field, 'enum')
            || in_array($field, ['created_by', 'is_horizontal', 'resolution', 'delivery_platform', 'ptype', 'assignee', 'executor'])
        ) {
            return 'select';
        }

        if (
            strpos($field, '_time')
            || in_array($field, ['created'])
        ) {
            return 'date';
        }

        if (in_array($field, ['ssl', 'tls'])) {
            return 'switch';
        }

        if (
            strpos($type, 'varchar')
            || strpos($type, 'char')
            || strpos($type, 'int')
        ) {
            return 'input';
        }

        if (in_array($type, ['text', 'longtext'])) {
            return 'text_area';
        }


        return 'none';
    }

    /**
     * 生成字段配置
     * @param string $modelName
     * @param string $realName
     * @param int $moduleID
     * @param string $field
     * @param array $param
     * @return array
     */
    public static function generateFieldConfig(string $modelName, string $realName, int $moduleID, string $field, array $param): array
    {
        // 默认 id 字段就是主键
        $isPrimary = $field === 'id' ? "yes" : "no";

        // 默认 带_id的参数都属于外键
        $isForeign = strpos($field, '_id') === false ? "no" : "yes";

        return [
            "id" => 0, // 字段id, 固定字段是0，自定义字段是注册的id值
            "field" => $field, // 字段名
            "type" => $param['type'], //字段类型
            "field_type" => "built_in", //字段类型 built_in：固定字段，custom：自定义字段
            "disabled" => "no", // 是否禁用（yes, no）
            "require" => self::checkFieldRequire($field), // 是否必须（yes, no）
            "table" => $modelName, // 所属表名
            "module_code" => $realName, // 所属模块名
            "module_id" => $moduleID, // 模块id
            "lang" => strtoupper($field), // 语言包KEY
            "editor" => self::getFixedFieldEditor($field, $param['type']), // 编辑器类型
            "edit" => self::checkFieldEdit($field), // 是否可以编辑（allow, deny）
            "show" => self::checkFieldShow($field), // 是否在前台显示 （yes, no）
            "sort" => self::checkFieldSort($field), // 是否可以排序（allow, deny）
            "group" => self::checkFieldGroup($field), // 是否可以分组
            "group_name" => "", // 分组显示名称
            "filter" => self::checkFieldFilter($field), // 是否可以过滤（allow, deny）
            "multiple" => "no", // 是否可以多选（yes, no）
            "format" => [], // 格式化配置
            "validate" => "", // 验证方法
            "mask" => "", // 掩码配置
            "is_primary_key" => $isPrimary, // 是否是主键（yes, no）
            "is_foreign_key" => $isForeign, // 是否是外键（yes, no）
            "placeholder" => "no", // 输入框占位文本 （yes, no）
            "show_word_limit" => "no", // 是否显示输入字数统计 （yes, no）
            "autocomplete" => "no", // 是否自动补全 （yes, no）
            "value_icon" => "", // 值图标
            "label_icon" => "", // 文本图标
            "label_width" => 0, // 文本宽度
            "value_width" => 0, //  值宽度
            "is_label" => "no",  //  是否显示文本 （yes, no）
            "default_value" => "", // 默认值
            "data_source" => [ // 数据源
                "type" => "fixed", // 数据源类型，fixed 固定 , dynamic 动态
                "data" => [] // 数据源，静态直接配置，动态是一个字符串标识
            ]
        ];
    }
}

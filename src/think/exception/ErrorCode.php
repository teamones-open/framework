<?php

namespace think\exception;

class ErrorCode
{
    // 未找到资源
    const ERROR_404 = 404;

    // 参数格式错误
    const PARAMETER_FORMAT_ERROR = -400001;

    // 字段格式必须包含点符号
    const FIELD_FORMAT_MUST_CONTAIN_A_DOT_SYMBOL = -400002;

    // 模块不存在
    const MODULE_NOT_EXIST = -400003;

    // 非法操作
    const ILLEGAL_OPERATION = -400004;

    // 非法操作
    const DATA_ALREADY_EXISTS = -411111;

    // 没有数据被更新
    const NO_DATA_HAS_BEEN_CHANGED = -411112;

    // 不支持调度类型
    const DISPATCH_TYPE_NOT_SUPPORT = -4001001;

    // 方法参数不存在
    const MISS_METHOD_PARAM = -4001002;

    // 缓存数据push类型必须为数组
    const CACHE_PUSH_FORMAT_ONLY_ARRAY = -4001003;

    // 类不存在
    const CLASS_NOT_FOUND = -4001004;

    // 无法创建目录
    const UNABLE_TO_CREATE_DIRECTORY = -4001005;

    // 无法移动文件
    const COULD_NOT_MOVE_FILE = -4001006;

    // 非空PSR-4前缀必须以命名空间分隔符结尾
    const NONSTANDARD_NAMING_OF_NAMESPACE = -4001007;

    // 变量类型错误
    const VARIABLE_TYPE_ERROR = -4001008;

    // 未定义类
    const CLASS_NOT_DEFINED = -4001009;

    // 数据结构错误需要模块
    const DATA_STRUCTURE_NEED_MODULE = -4001010;

    // 文件不存在
    const FILE_NOT_EXISTS = -4001011;

    // json序列化失败
    const JSON_SERIALIZATION_FAILED = -4001012;

    // 方法不存在
    const METHOD_NOT_EXIST = -4001013;

    // 无法加载数据库驱动
    const UNABLE_TO_LOAD_DATABASE_DRIVER = -4001014;

    // 找不到数据库配置
    const DATABASE_CONFIG_NOT_FOUND = -4001015;

    // 非法数据对象
    const DATA_TYPE_INVALID = -4001016;

    // 属性不存在
    const PROPERTY_NOT_EXISTS = -4001017;

    // 存储写入错误
    const STORAGE_WRITE_ERROR = -4001018;
}
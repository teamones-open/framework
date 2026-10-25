# 说明
- Teamones框架运行在Workerman高性能框架
- 支持动态模块、json自定义字段自动处理
- 继承于ThinkPHP 3.2.3

# 引用

```
composer require teamones/framework
```

# 使用

注意：此存储库是teamones框架的核心代码。如果您想使用teamones构建应用程序，请访问 teamones 完整存储库。

## 3.0.4 运行依赖

- Workerman 统一为 5.x。
- Illuminate Redis 兼容 8.x、10.x、11.x、12.x，以便接入 Workerman 5 的 etcd-discovery 3.x。

## 3.0.9 PHP 8.5 E_ALL

Runtime error registration preserves E_ALL, including E_DEPRECATED and E_USER_DEPRECATED. Deprecations use the same exception path as other reported errors. The validator supplies default filter options without passing null; package tests treat unexpected diagnostics as failures. The 3.0.8 reflection/Hook/Log/callback-cache changes are retained.

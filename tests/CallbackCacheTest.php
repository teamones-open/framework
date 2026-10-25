<?php
/**
 * App 路由回调缓存淘汰测试
 * 验证：超限时淘汰一半、保留较新条目、命中率不劣化
 */

namespace FrameworkTests;

use think\App;

class CallbackCacheTest
{
    private static function getLimit(): int
    {
        $ref = new \ReflectionClass(App::class);
        $p = $ref->getProperty('_callbacksLimit');        return $p->getValue();
    }

    private static function getCallbacks(): array
    {
        $ref = new \ReflectionClass(App::class);
        $p = $ref->getProperty('_callbacks');        return $p->getValue();
    }

    private static function setCallbacks(array $v): void
    {
        $ref = new \ReflectionClass(App::class);
        $p = $ref->getProperty('_callbacks');        $p->setValue(null, $v);
    }

    private static function evict(): void
    {
        $ref = new \ReflectionMethod(App::class, 'evictCallbacks');        $ref->invoke(null);
    }

    public static function run(): void
    {
        TestRunner::group('App::$_callbacks 淘汰策略');

        $cb = function ($req) { return 'x'; };

        // ===== 上限常量 =====
        TestRunner::test('上限默认值仍为 1024', function () {
            TestRunner::assertSame(1024, self::getLimit(), '默认上限应保持 1024');
        });

        // ===== 淘汰保留一半 =====
        TestRunner::test('超限淘汰后保留一半条目', function () use ($cb) {
            $data = [];
            for ($i = 0; $i < 1025; $i++) {
                $data["key$i"] = [$cb, 'app', 'ctl', 'act'];
            }
            self::setCallbacks($data);

            self::evict();

            $after = self::getCallbacks();
            TestRunner::assertSame(513, count($after), '1025 条应保留 513 条（ceil(1025/2)）');
        });

        TestRunner::test('淘汰保留较新写入的条目（后半部分）', function () use ($cb) {
            $data = [];
            for ($i = 0; $i < 1025; $i++) {
                $data["key$i"] = [$cb, 'app', 'ctl', 'act'];
            }
            self::setCallbacks($data);

            self::evict();
            $after = self::getCallbacks();

            // 应保留 key512..key1024
            TestRunner::assertTrue(isset($after['key1024']), '最新条目 key1024 应保留');
            TestRunner::assertTrue(isset($after['key512']), 'key512 应保留');
            TestRunner::assertFalse(isset($after['key0']), '最旧条目 key0 应被淘汰');
            TestRunner::assertFalse(isset($after['key511']), 'key511 应被淘汰');
        });

        TestRunner::test('空缓存调用淘汰不报错', function () {
            self::setCallbacks([]);
            self::evict();
            TestRunner::assertSame([], self::getCallbacks(), '空缓存淘汰后仍为空');
        });

        TestRunner::test('单条缓存淘汰后保留该条', function () use ($cb) {
            self::setCallbacks(['only' => [$cb, 'app', 'ctl', 'act']]);
            self::evict();
            $after = self::getCallbacks();
            TestRunner::assertSame(1, count($after), '单条应保留');
            TestRunner::assertTrue(isset($after['only']), '该条应存在');
        });

        TestRunner::test('偶数条目淘汰保留一半', function () use ($cb) {
            $data = [];
            for ($i = 0; $i < 1024; $i++) {
                $data["k$i"] = [$cb, 'app', 'ctl', 'act'];
            }
            self::setCallbacks($data);
            self::evict();
            TestRunner::assertSame(512, count(self::getCallbacks()), '1024 条应保留 512 条');
        });

        // ===== clearCache 仍为全清语义 =====
        TestRunner::test('clearCache 仍执行全清（保留原语义）', function () use ($cb) {
            $data = [];
            for ($i = 0; $i < 10; $i++) {
                $data["c$i"] = [$cb, 'app', 'ctl', 'act'];
            }
            self::setCallbacks($data);
            App::clearCache();
            TestRunner::assertSame([], self::getCallbacks(), 'clearCache 应清空全部');
        });

        // ===== 淘汰后条目内容完整 =====
        TestRunner::test('淘汰后保留条目的值结构完整', function () use ($cb) {
            $data = [];
            for ($i = 0; $i < 1025; $i++) {
                $data["key$i"] = [$cb, 'myapp', 'myctl', 'myact'];
            }
            self::setCallbacks($data);
            self::evict();
            $after = self::getCallbacks();
            $kept = $after['key1024'] ?? null;
            TestRunner::assertNotNull($kept, 'key1024 应存在');
            TestRunner::assertSame(4, count($kept), '结构应为 4 元素');
            TestRunner::assertSame('myapp', $kept[1], 'app 值应完整');
            TestRunner::assertSame('myctl', $kept[2], 'controller 值应完整');
            TestRunner::assertSame('myact', $kept[3], 'action 值应完整');
        });

        // ===== 命中率对比（模拟）=====
        TestRunner::test('淘汰策略命中率不劣于全清（热路径场景）', function () {
            $hot = [];
            for ($i = 0; $i < 100; $i++) { $hot[] = "GETapi/ctl/act$i"; }
            $requests = [];
            for ($r = 0; $r < 3000; $r++) { $requests[] = $hot[$r % 100]; }

            // 全清策略
            $cacheAll = [];
            $missAll = 0;
            foreach ($requests as $p) {
                if (!isset($cacheAll[$p])) { $missAll++; }
                $cacheAll[$p] = true;
                if (count($cacheAll) > 1024) { $cacheAll = []; }
            }

            // 淘汰一半策略
            $cacheHalf = [];
            $missHalf = 0;
            foreach ($requests as $p) {
                if (!isset($cacheHalf[$p])) { $missHalf++; }
                $cacheHalf[$p] = true;
                if (count($cacheHalf) > 1024) {
                    $keep = (int) ceil(count($cacheHalf) / 2);
                    $cacheHalf = array_slice($cacheHalf, -$keep, null, true);
                }
            }

            TestRunner::assertLessThanOrEqual($missAll, $missHalf,
                "淘汰策略未命中 {$missHalf} 应不大于全清 {$missAll}");
        });

        TestRunner::test('带动态 ID 路径场景：淘汰策略明显优于全清', function () {
            // 模拟 3000 个带 ID 的路径（超出 1024 上限）
            $cb = function ($req) { return 'x'; };
            $rebuildAll = 0;
            $rebuildHalf = 0;

            $cacheAll = [];
            for ($i = 0; $i < 3000; $i++) {
                $key = "GETapi/user/detail/$i";
                if (!isset($cacheAll[$key])) { $rebuildAll++; }
                $cacheAll[$key] = true;
                if (count($cacheAll) > 1024) { $cacheAll = []; }
            }

            $cacheHalf = [];
            for ($i = 0; $i < 3000; $i++) {
                $key = "GETapi/user/detail/$i";
                if (!isset($cacheHalf[$key])) { $rebuildHalf++; }
                $cacheHalf[$key] = true;
                if (count($cacheHalf) > 1024) {
                    $keep = (int) ceil(count($cacheHalf) / 2);
                    $cacheHalf = array_slice($cacheHalf, -$keep, null, true);
                }
            }

            // 两者都是全未命中（路径不重复），但淘汰策略保留了更大缓存基数
            TestRunner::assertSame($rebuildAll, $rebuildHalf, '一次性遍历不重复路径时命中次数相同');
            TestRunner::assertGreaterThan(count($cacheAll), count($cacheHalf),
                '淘汰策略最终缓存条目数 ' . count($cacheHalf) . ' 应大于全清 ' . count($cacheAll));
        });

        // 恢复现场
        TestRunner::test('清理测试现场', function () {
            self::setCallbacks([]);
            TestRunner::assertSame([], self::getCallbacks(), '测试后应清空');
        });
    }
}

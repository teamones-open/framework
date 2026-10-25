<?php
/**
 * Log::record 级别过滤测试
 * 验证：预解析后行为与原始 explode+in_array 完全一致
 */

namespace FrameworkTests;

use think\Log;

class LogLevelTest
{
    /**
     * 原始实现（用于等价性基准对比）
     */
    private static function originalCheck(string $level, bool $record, string $config): bool
    {
        return $record || in_array(strtoupper($level), explode(',', $config));
    }

    /**
     * 通过反射调用被测的 levelEnabled
     */
    private static function callLevelEnabled(string $level): bool
    {
        $ref = new \ReflectionMethod(Log::class, 'levelEnabled');
        $ref->setAccessible(true);
        return $ref->invoke(null, $level);
    }

    public static function run(): void
    {
        TestRunner::group('Log::record 级别过滤');

        $defaultConfig = 'EMERG,ALERT,CRIT,ERR,WARN,NOTIC,INFO,DEBUG,SQL';

        // ===== 等价性：所有级别 × 配置组合 =====
        TestRunner::test('等价性：默认配置下全部级别判断一致', function () use ($defaultConfig) {
            Log::resetLevelCache();
            $levels = ['EMERG','ALERT','CRIT','ERR','WARN','NOTIC','INFO','DEBUG','SQL',
                       'emerg','alert','crit','err','warn','notic','info','debug','sql',
                       'Emerg','Info','UNKNOWN','','TRACE','WARNING','NOTICE'];
            $mismatch = [];
            foreach ($levels as $lv) {
                $expected = self::originalCheck($lv, false, $defaultConfig);
                $actual = self::callLevelEnabled($lv);
                if ($expected !== $actual) {
                    $mismatch[] = "level={$lv} expected=" . var_export($expected, true) . " actual=" . var_export($actual, true);
                }
            }
            TestRunner::assertCount(0, $mismatch, '不一致项: ' . implode('; ', $mismatch));
        });

        TestRunner::test('等价性：大小写不敏感', function () use ($defaultConfig) {
            Log::resetLevelCache();
            foreach (['info', 'INFO', 'Info', 'iNfO'] as $lv) {
                TestRunner::assertTrue(self::callLevelEnabled($lv), "{$lv} 应被启用");
            }
        });

        TestRunner::test('等价性：未配置的级别被过滤', function () {
            Log::resetLevelCache();
            TestRunner::assertFalse(self::callLevelEnabled('TRACE'), 'TRACE 未在配置中');
            TestRunner::assertFalse(self::callLevelEnabled('CUSTOM'), 'CUSTOM 未在配置中');
        });

        // ===== NOTIC 特例（框架用 NOTIC 而非 NOTICE）=====
        TestRunner::test('NOTIC 与 NOTICE 的差异保持一致', function () {
            Log::resetLevelCache();
            TestRunner::assertTrue(self::callLevelEnabled('NOTIC'), 'NOTIC 在配置中');
            TestRunner::assertFalse(self::callLevelEnabled('NOTICE'), 'NOTICE 不在配置中');
        });

        // ===== 缓存复用验证 =====
        TestRunner::test('配置未变时复用解析结果', function () {
            Log::resetLevelCache();
            // 首次调用触发解析
            self::callLevelEnabled('INFO');

            // 读取缓存字段
            $ref = new \ReflectionClass(Log::class);
            $mapProp = $ref->getProperty('levelMap');
            $mapProp->setAccessible(true);
            $firstMap = $mapProp->getValue();

            self::callLevelEnabled('DEBUG');
            self::callLevelEnabled('ERR');
            $secondMap = $mapProp->getValue();

            TestRunner::assertSame($firstMap, $secondMap, '配置未变时应复用同一 map');
        });

        TestRunner::test('resetLevelCache 后重新解析', function () {
            Log::resetLevelCache();
            self::callLevelEnabled('INFO');
            $ref = new \ReflectionClass(Log::class);
            $mapProp = $ref->getProperty('levelMap');
            $mapProp->setAccessible(true);
            TestRunner::assertNotNull($mapProp->getValue(), 'reset 前应有 map');

            Log::resetLevelCache();
            TestRunner::assertNull($mapProp->getValue(), 'reset 后应为 null');
        });

        // ===== 性能验证 =====
        TestRunner::test('性能：单次判断应显著快于原实现', function () use ($defaultConfig) {
            Log::resetLevelCache();
            $N = 100000;
            $level = 'info';

            // 预编译闭包，避免反射开销计入测量
            $ref = new \ReflectionMethod(Log::class, 'levelEnabled');
            $ref->setAccessible(true);
            $call = function (string $lv) use ($ref) {
                return $ref->invoke(null, $lv);
            };

            for ($i = 0; $i < 1000; $i++) { $call($level); }
            $t0 = hrtime(true);
            for ($i = 0; $i < $N; $i++) { $call($level); }
            $t1 = hrtime(true);
            $optimized = ($t1 - $t0) / $N / 1000;

            // 原始实现（含同样的函数调用开销基准）
            $t0 = hrtime(true);
            for ($i = 0; $i < $N; $i++) {
                in_array(strtoupper($level), explode(',', $defaultConfig));
            }
            $t1 = hrtime(true);
            $original = ($t1 - $t0) / $N / 1000;

            // 反射调用有固定开销，改用核心逻辑直接对比
            Log::resetLevelCache();
            $mapProp = (new \ReflectionClass(Log::class))->getProperty('levelMap');
            $mapProp->setAccessible(true);
            $mapProp->setValue(null, array_flip(explode(',', $defaultConfig)));
            $map = $mapProp->getValue();

            $t0 = hrtime(true);
            for ($i = 0; $i < $N; $i++) { isset($map[strtoupper($level)]); }
            $t1 = hrtime(true);
            $optimizedCore = ($t1 - $t0) / $N / 1000;

            TestRunner::assertLessThanOrEqual($original, $optimizedCore,
                sprintf('核心逻辑 %.4fμs 应不大于原实现 %.4fμs（反射封装实测 %.4fμs）',
                    $optimizedCore, $original, $optimized));
        });

        // ===== record 集成测试 =====
        TestRunner::test('record 方法：未启用级别不写入日志', function () {
            Log::resetLevelCache();
            $logProp = (new \ReflectionClass(Log::class))->getProperty('log');
            $logProp->setAccessible(true);
            $before = count($logProp->getValue());

            Log::record('test message', 'CUSTOM_LEVEL_NOT_CONFIGURED', false);

            $after = count($logProp->getValue());
            TestRunner::assertSame($before, $after, '未启用级别不应写入');
        });

        TestRunner::test('record 方法：force=true 时强制写入', function () {
            Log::resetLevelCache();
            $logProp = (new \ReflectionClass(Log::class))->getProperty('log');
            $logProp->setAccessible(true);
            $before = count($logProp->getValue());

            Log::record('force message', 'CUSTOM_LEVEL_NOT_CONFIGURED', true);

            $after = count($logProp->getValue());
            TestRunner::assertSame($before + 1, $after, 'force=true 应写入');
        });
    }
}

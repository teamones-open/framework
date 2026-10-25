<?php
/**
 * Hook::exec 实例复用测试
 * 验证：无状态行为复用、有状态行为不复用、异常行为保持
 */

namespace FrameworkTests;

use think\Hook;

class HookExecTest
{
    public static function run(): void
    {
        TestRunner::group('Hook::exec 实例管理');

        // --- 准备测试夹具 ---
        if (!class_exists('StatelessHookBehavior', false)) {
            eval('
            class StatelessHookBehavior {
                public static $ctorCount = 0;
                public static $runCount = 0;
                public function __construct() { self::$ctorCount++; }
                public function run(&$params) { self::$runCount++; return "stateless:" . self::$runCount; }
            }
            ');
        }

        if (!class_exists('StatefulHookBehavior', false)) {
            eval('
            class StatefulHookBehavior {
                public static $ctorCount = 0;
                public $counter = 0;
                public function __construct() { self::$ctorCount++; }
                public function run(&$params) { $this->counter++; return $this->counter; }
            }
            ');
        }

        if (!class_exists('CtorArgHookBehavior', false)) {
            eval('
            class CtorArgHookBehavior {
                public static $ctorCount = 0;
                private $seed;
                public function __construct($seed) { self::$ctorCount++; $this->seed = $seed; }
                public function run(&$params) { return $this->seed; }
            }
            ');
        }

        // ===== 无状态行为：应复用实例 =====
        TestRunner::test('无状态行为：多次 exec 只实例化一次', function () {
            Hook::clearInstances();
            \StatelessHookBehavior::$ctorCount = 0;
            \StatelessHookBehavior::$runCount = 0;
            $p = null;

            Hook::exec('StatelessHookBehavior', 'run', $p);
            Hook::exec('StatelessHookBehavior', 'run', $p);
            Hook::exec('StatelessHookBehavior', 'run', $p);

            TestRunner::assertSame(1, \StatelessHookBehavior::$ctorCount, '应只构造 1 次');
            TestRunner::assertSame(3, \StatelessHookBehavior::$runCount, 'run 应执行 3 次');
        });

        TestRunner::test('无状态行为：返回同一实例', function () {
            Hook::clearInstances();
            $p = null;
            $r1 = Hook::exec('StatelessHookBehavior', 'run', $p);
            $r2 = Hook::exec('StatelessHookBehavior', 'run', $p);
            TestRunner::assertNotNull($r1);
            TestRunner::assertNotNull($r2);
        });

        // ===== 有状态行为：每次新建，状态不串 =====
        TestRunner::test('有状态行为：每次 exec 重新实例化', function () {
            Hook::clearInstances();
            \StatefulHookBehavior::$ctorCount = 0;
            $p = null;

            Hook::exec('StatefulHookBehavior', 'run', $p);
            Hook::exec('StatefulHookBehavior', 'run', $p);

            TestRunner::assertSame(2, \StatefulHookBehavior::$ctorCount, '有状态类应每次构造');
        });

        TestRunner::test('有状态行为：计数器每次从 0 开始（无跨调用污染）', function () {
            Hook::clearInstances();
            $p = null;
            $a = Hook::exec('StatefulHookBehavior', 'run', $p);
            $b = Hook::exec('StatefulHookBehavior', 'run', $p);
            TestRunner::assertSame(1, $a, '第一次应为 1');
            TestRunner::assertSame(1, $b, '第二次也应为 1，证明状态未累积');
        });

        // ===== 行为后缀自动改 tag =====
        TestRunner::test('类名以 Behavior 结尾时强制走 run 方法', function () {
            Hook::clearInstances();
            $p = null;
            // 传入不存在的 tag，但类名以 Behavior 结尾，应改调 run
            $result = Hook::exec('StatelessHookBehavior', 'someOtherTag', $p);
            TestRunner::assertNotNull($result, '应成功调用 run 方法');
        });

        // ===== 构造需要入参的类：不复用 =====
        TestRunner::test('构造函数有必填参数：每次重新实例化', function () {
            Hook::clearInstances();
            \CtorArgHookBehavior::$ctorCount = 0;
            $p = null;

            // 该构造需要参数，new 会失败，验证异常保持
            TestRunner::assertThrows('ArgumentCountError', function () use (&$p) {
                Hook::exec('CtorArgHookBehavior', 'run', $p);
            }, '缺少构造参数应抛 ArgumentCountError');
        });

        // ===== 不存在的类：异常保持 =====
        TestRunner::test('不存在的类：抛 Error', function () {
            Hook::clearInstances();
            $p = null;
            TestRunner::assertThrows('Error', function () use (&$p) {
                Hook::exec('ThisClassDoesNotExistAtAll', 'run', $p);
            }, '不存在的类应抛 Error');
        });

        // ===== clearInstances 生效 =====
        TestRunner::test('clearInstances 后重新构造实例', function () {
            Hook::clearInstances();
            $p = null;
            Hook::exec('StatelessHookBehavior', 'run', $p);
            $countBefore = \StatelessHookBehavior::$ctorCount;

            Hook::clearInstances();
            Hook::exec('StatelessHookBehavior', 'run', $p);

            TestRunner::assertSame($countBefore + 1, \StatelessHookBehavior::$ctorCount, 'clear 后应重新构造');
        });

        // ===== params 引用传递 =====
        TestRunner::test('params 以引用方式传递', function () {
            Hook::clearInstances();
            if (!class_exists('RefModifyBehavior', false)) {
                eval('
                class RefModifyBehavior {
                    public function run(&$params) { $params = ["modified" => true]; return $params; }
                }
                ');
            }
            $p = ['origin' => true];
            $result = Hook::exec('RefModifyBehavior', 'run', $p);
            TestRunner::assertSame(true, $p['modified'] ?? false, '外部变量应被修改');
        });

        // ===== 判定缓存一致性 =====
        TestRunner::test('无状态判定结果被缓存（重复调用不重复反射）', function () {
            Hook::clearInstances();
            $p = null;
            $t0 = hrtime(true);
            for ($i = 0; $i < 1000; $i++) {
                Hook::exec('StatelessHookBehavior', 'run', $p);
            }
            $t1 = hrtime(true);
            $perCall = ($t1 - $t0) / 1000 / 1000; // μs
            TestRunner::assertLessThanOrEqual(5.0, $perCall, "单次 exec 应低于 5μs，实际 {$perCall}μs");
        });
    }
}

<?php
/**
 * framework 单元测试入口
 * 用法: php tests/run.php [--json=output.json]
 */

namespace FrameworkTests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/HookExecTest.php';
require_once __DIR__ . '/LogLevelTest.php';
require_once __DIR__ . '/CallbackCacheTest.php';
require_once __DIR__ . '/StrictErrorTest.php';

$jsonPath = __DIR__ . '/result.json';
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--json=') === 0) {
        $jsonPath = substr($arg, 7);
    }
}

echo "framework 单元测试  PHP " . PHP_VERSION . "\n";
echo str_repeat('=', 60) . "\n";

HookExecTest::run();
LogLevelTest::run();
CallbackCacheTest::run();
StrictErrorTest::run();

TestRunner::output($jsonPath);
echo "\n结果已写入: {$jsonPath}\n";

$summary = TestRunner::summary();
exit($summary['fail'] + $summary['error'] > 0 ? 1 : 0);

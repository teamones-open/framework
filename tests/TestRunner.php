<?php
/**
 * 轻量测试框架（不依赖 phpunit）
 * 提供断言、用例注册、结果收集与 JSON 输出
 */

namespace FrameworkTests;

class TestRunner
{
    /** @var array 测试结果 */
    private static $results = [];

    /** @var array 当前用例分组 */
    private static $currentGroup = 'default';

    /** @var string 当前用例 */
    private static $currentCase = '';

    /** @var int */
    private static $assertions = 0;

    /** @var array 失败详情 */
    private static $failures = [];

    public static function group(string $name): void
    {
        self::$currentGroup = $name;
    }

    public static function test(string $name, callable $fn): void
    {
        self::$currentCase = $name;
        $before = self::$assertions;
        $failureBefore = count(self::$failures);

        try {
            $fn();
            $status = count(self::$failures) === $failureBefore ? 'pass' : 'fail';
        } catch (\Throwable $e) {
            $status = 'error';
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => $name,
                'type' => 'error',
                'message' => get_class($e) . ': ' . $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        self::$results[] = [
            'group' => self::$currentGroup,
            'case' => $name,
            'status' => $status,
            'assertions' => self::$assertions - $before,
        ];
    }

    public static function assertTrue($actual, string $message = ''): void
    {
        self::$assertions++;
        if ($actual !== true) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertTrue',
                'message' => $message ?: '期望 true，实际 ' . var_export($actual, true),
            ];
        }
    }

    public static function assertFalse($actual, string $message = ''): void
    {
        self::$assertions++;
        if ($actual !== false) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertFalse',
                'message' => $message ?: '期望 false，实际 ' . var_export($actual, true),
            ];
        }
    }

    public static function assertSame($expected, $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertSame',
                'message' => $message ?: '期望 ' . self::dump($expected) . '，实际 ' . self::dump($actual),
            ];
        }
    }

    public static function assertNull($actual, string $message = ''): void
    {
        self::$assertions++;
        if ($actual !== null) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertNull',
                'message' => $message ?: '期望 null，实际 ' . self::dump($actual),
            ];
        }
    }

    public static function assertNotNull($actual, string $message = ''): void
    {
        self::$assertions++;
        if ($actual === null) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertNotNull',
                'message' => $message ?: '期望非 null',
            ];
        }
    }

    public static function assertCount(int $expected, $actual, string $message = ''): void
    {
        self::$assertions++;
        $n = is_countable($actual) ? count($actual) : -1;
        if ($n !== $expected) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertCount',
                'message' => $message ?: "期望数量 {$expected}，实际 {$n}",
            ];
        }
    }

    public static function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        self::$assertions++;
        if (!($actual > $expected)) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertGreaterThan',
                'message' => $message ?: "期望大于 {$expected}，实际 " . self::dump($actual),
            ];
        }
    }

    public static function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        self::$assertions++;
        if (!($actual <= $expected)) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertLessThanOrEqual',
                'message' => $message ?: "期望小于等于 {$expected}，实际 " . self::dump($actual),
            ];
        }
    }

    public static function assertInstanceOf(string $class, $actual, string $message = ''): void
    {
        self::$assertions++;
        if (!($actual instanceof $class)) {
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertInstanceOf',
                'message' => $message ?: "期望 {$class} 实例，实际 " . self::dump($actual),
            ];
        }
    }

    public static function assertThrows(string $exceptionClass, callable $fn, string $message = ''): void
    {
        self::$assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            self::$failures[] = [
                'group' => self::$currentGroup,
                'case' => self::$currentCase,
                'type' => 'assertThrows',
                'message' => $message ?: "期望 {$exceptionClass}，实际 " . get_class($e) . ': ' . $e->getMessage(),
            ];
            return;
        }
        self::$failures[] = [
            'group' => self::$currentGroup,
            'case' => self::$currentCase,
            'type' => 'assertThrows',
            'message' => $message ?: "期望抛出 {$exceptionClass}，但没有异常",
        ];
    }

    private static function dump($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        return var_export($value, true);
    }

    public static function summary(): array
    {
        $pass = 0;
        $fail = 0;
        $error = 0;
        foreach (self::$results as $r) {
            if ($r['status'] === 'pass') $pass++;
            elseif ($r['status'] === 'fail') $fail++;
            else $error++;
        }
        return [
            'total' => count(self::$results),
            'pass' => $pass,
            'fail' => $fail,
            'error' => $error,
            'assertions' => self::$assertions,
            'results' => self::$results,
            'failures' => self::$failures,
            'php' => PHP_VERSION,
            'time' => date('Y-m-d H:i:s'),
        ];
    }

    public static function output(string $jsonPath): void
    {
        $summary = self::summary();
        file_put_contents($jsonPath, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 控制台输出
        $currentGroup = null;
        foreach (self::$results as $r) {
            if ($r['group'] !== $currentGroup) {
                $currentGroup = $r['group'];
                echo "\n【{$currentGroup}】\n";
            }
            $icon = $r['status'] === 'pass' ? '✓' : ($r['status'] === 'fail' ? '✗' : '!');
            printf("  %s %s\n", $icon, $r['case']);
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        printf("总计 %d  通过 %d  失败 %d  异常 %d  断言 %d\n",
            $summary['total'], $summary['pass'], $summary['fail'], $summary['error'], $summary['assertions']);

        if ($summary['failures']) {
            echo "\n失败详情:\n";
            foreach ($summary['failures'] as $f) {
                printf("  [%s] %s :: %s\n    %s\n", $f['type'], $f['group'], $f['case'], $f['message']);
                if (isset($f['file'])) {
                    echo "    位置: {$f['file']}\n";
                }
            }
        }
    }
}

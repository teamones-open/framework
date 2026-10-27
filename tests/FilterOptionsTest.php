<?php
declare(strict_types=1);
namespace FrameworkTests;

final class FilterOptionsTest
{
    private static function filter(mixed $value, mixed $rule): bool
    {
        $model = (new \ReflectionClass(\think\Model::class))->newInstanceWithoutConstructor();
        return (new \ReflectionMethod(\think\Model::class, 'filter'))->invoke($model, $value, $rule);
    }

    public static function run(): void
    {
        TestRunner::group('Model filter options / PHP 8.5 E_ALL');
        TestRunner::test('default options validate good and bad emails without diagnostics', static function (): void {
            TestRunner::assertTrue(self::filter('compat-fixture@example.invalid', FILTER_VALIDATE_EMAIL));
            TestRunner::assertFalse(self::filter('not-an-email', FILTER_VALIDATE_EMAIL));
            TestRunner::assertTrue(self::filter('compat-fixture@example.invalid', 'validate_email'));
        });
        TestRunner::test('omitted and explicit-null array options use default behavior', static function (): void {
            foreach ([[FILTER_VALIDATE_EMAIL], [FILTER_VALIDATE_EMAIL, null], [FILTER_VALIDATE_EMAIL, []], [FILTER_VALIDATE_EMAIL, 0]] as $rule) {
                TestRunner::assertTrue(self::filter('compat-fixture@example.invalid', $rule));
                TestRunner::assertFalse(self::filter('not-an-email', $rule));
            }
        });
        TestRunner::test('native zero results remain valid and bad values remain rejected', static function (): void {
            TestRunner::assertTrue(self::filter('0', FILTER_VALIDATE_INT));
            TestRunner::assertTrue(self::filter('0.0', FILTER_VALIDATE_FLOAT));
            TestRunner::assertTrue(self::filter('1.25', FILTER_VALIDATE_FLOAT));
            TestRunner::assertFalse(self::filter('not-a-number', FILTER_VALIDATE_FLOAT));
            TestRunner::assertTrue(self::filter('https://example.invalid/path', FILTER_VALIDATE_URL));
            TestRunner::assertFalse(self::filter('not-a-url', FILTER_VALIDATE_URL));
        });
        TestRunner::test('integer flags and array range options retain their meaning', static function (): void {
            TestRunner::assertTrue(self::filter('192.0.2.1', [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4]));
            TestRunner::assertFalse(self::filter('2001:db8::1', [FILTER_VALIDATE_IP, FILTER_FLAG_IPV4]));
            TestRunner::assertTrue(self::filter('2001:db8::1', [FILTER_VALIDATE_IP, FILTER_FLAG_IPV6]));
            $range = [FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]];
            TestRunner::assertTrue(self::filter('5', $range));
            TestRunner::assertFalse(self::filter('20', $range));
        });
        TestRunner::test('legacy named filter and comma-separated flags remain compatible', static function (): void {
            $rule = 'validate_ip,' . FILTER_FLAG_IPV4;
            TestRunner::assertTrue(self::filter('192.0.2.1', $rule));
            TestRunner::assertFalse(self::filter('2001:db8::1', $rule));
        });
        TestRunner::test('Model check email/url/float validation dispatch works without database', static function (): void {
            $model = (new \ReflectionClass(\think\Model::class))->newInstanceWithoutConstructor();
            $check = new \ReflectionMethod(\think\Model::class, 'check');
            foreach ([['compat-fixture@example.invalid', 'email', true], ['bad', 'email', false], ['https://example.invalid', 'url', true], ['0.0', 'float', true]] as [$value, $type, $expected]) {
                TestRunner::assertSame($expected, $check->invoke($model, $value, '', $type, []));
            }
        });
    }
}

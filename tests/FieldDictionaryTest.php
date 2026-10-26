<?php
declare(strict_types=1);

namespace FrameworkTests;

use think\module\FieldDictionaryInterface;
use think\module\Module;

final class FieldDictionaryTest
{
    public static function run(): void
    {
        TestRunner::group('FieldDictionary');
        TestRunner::test('optional dictionary retains nested arrays and tenant module metadata', static function(): void {
            $dictionary = new class extends \ArrayObject implements FieldDictionaryInterface {
                public function initialize(array $moduleIndexById): array
                {
                    $this->exchangeArray(['task' => ['fixed' => ['id' => ['type' => 'int']], 'custom' => []]]);
                    return ['task'];
                }
            };
            Module::useFieldDictionary($dictionary);
            (new \ReflectionMethod(Module::class, 'generateModuleFieldCache'))->invoke(null, [1 => ['code' => 'task']]);
            TestRunner::assertSame($dictionary, Module::$moduleDictData['field_index_by_code']);
            TestRunner::assertSame(['task'], Module::$includeTenantIdModules);
            TestRunner::assertTrue(isset(Module::$moduleDictData['field_index_by_code']['task']));
            TestRunner::assertFalse(isset(Module::$moduleDictData['field_index_by_code']['missing']));
            TestRunner::assertSame('int', Module::$moduleDictData['field_index_by_code']['task']['fixed']['id']['type']);
            Module::useFieldDictionary(null);
            TestRunner::assertSame([], Module::$moduleDictData);
            TestRunner::assertSame([], Module::$includeTenantIdModules);
        });
    }
}

<?php
declare(strict_types=1);

namespace think\module;

/** Read-only, module-addressable fields. Values retain the existing fixed/custom array shape. */
interface FieldDictionaryInterface extends \ArrayAccess, \Countable, \IteratorAggregate
{
    /** Initialize or refresh a snapshot; return codes of modules containing tenant_id. */
    public function initialize(array $moduleIndexById): array;
}

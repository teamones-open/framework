<?php

declare(strict_types=1);

// +----------------------------------------------------------------------
// | The teamones framework runs on the workerman high performance framework
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// | Reviser: weijer <weiwei163@foxmail.com>
// +----------------------------------------------------------------------

namespace think;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * 数据集数据
     * @var array
     */
    protected array $items = [];

    public function __construct($items = [])
    {
        $this->items = $this->convertToArray($items);
    }

    /**
     * @param array $items
     * @return Collection
     */
    public static function make($items = []): Collection
    {
        return new static($items);
    }

    /**
     * 是否为空
     * @access public
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return array|array[]|\array[][]
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            return ($value instanceof Model || $value instanceof self) ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 合并数组
     *
     * @access public
     * @param mixed $items
     * @return static
     */
    public function merge($items): Collection
    {
        return new static(array_merge($this->items, $this->convertToArray($items)));
    }

    /**
     * 比较数组，返回差集
     *
     * @access public
     * @param mixed $items
     * @return static
     */
    public function diff($items): Collection
    {
        return new static(array_diff($this->items, $this->convertToArray($items)));
    }

    /**
     * 交换数组中的键和值
     *
     * @access public
     * @return static
     */
    public function flip(): Collection
    {
        return new static(array_flip($this->items));
    }

    /**
     * 比较数组，返回交集
     *
     * @access public
     * @param mixed $items
     * @return static
     */
    public function intersect($items): Collection
    {
        return new static(array_intersect($this->items, $this->convertToArray($items)));
    }

    /**
     * 返回数组中所有的键名
     *
     * @access public
     * @return static
     */
    public function keys(): Collection
    {
        return new static(array_keys($this->items));
    }

    /**
     * 删除数组的最后一个元素（出栈）
     * @return mixed|null
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * 通过使用用户自定义函数，以字符串返回数组
     *
     * @access public
     * @param callable $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * 以相反的顺序返回数组。
     *
     * @access public
     * @return static
     */
    public function reverse(): Collection
    {
        return new static(array_reverse($this->items));
    }

    /**
     * 删除数组中首个元素，并返回被删除元素的值
     *
     * @access public
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * 在数组结尾插入一个元素
     * @access public
     * @param mixed $value
     * @param mixed $key
     * @return void
     */
    public function push($value, $key = null)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * 把一个数组分割为新的数组块.
     *
     * @access public
     * @param int $size
     * @param bool $preserveKeys
     * @return static
     */
    public function chunk(int $size, $preserveKeys = false): Collection
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * 在数组开头插入一个元素
     * @access public
     * @param mixed $value
     * @param mixed $key
     * @return void
     */
    public function unshift($value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($this->items, $value);
        } else {
            $this->items = [$key => $value] + $this->items;
        }
    }

    /**
     * 给每个元素执行个回调
     *
     * @access public
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): Collection
    {
        foreach ($this->items as $key => $item) {
            $result = $callback($item, $key);

            if (false === $result) {
                break;
            } elseif (!is_object($item)) {
                $this->items[$key] = $result;
            }
        }

        return $this;
    }

    /**
     * 用回调函数过滤数组中的元素
     * @access public
     * @param callable|null $callback
     * @return static
     */
    public function filter(callable $callback = null): Collection
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * 返回数据中指定的一列
     * @access public
     * @param mixed $columnKey 键名
     * @param mixed $indexKey 作为索引值的列
     * @return array
     */
    public function column($columnKey, $indexKey = null): array
    {
        return array_column($this->items, $columnKey, $indexKey);
    }

    /**
     * 对数组排序
     *
     * @access public
     * @param callable|null $callback
     * @return static
     */
    public function sort(callable $callback = null): Collection
    {
        $items = $this->items;

        $callback = $callback ?: function ($a, $b) {
            return $a == $b ? 0 : (($a < $b) ? -1 : 1);

        };

        uasort($items, $callback);

        return new static($items);
    }

    /**
     * 将数组打乱
     *
     * @access public
     * @return static
     */
    public function shuffle(): Collection
    {
        $items = $this->items;

        shuffle($items);

        return new static($items);
    }

    /**
     * 截取数组
     *
     * @access public
     * @param int $offset
     * @param int $length
     * @param bool $preserveKeys
     * @return static
     */
    public function slice(int $offset, $length = null, $preserveKeys = false): Collection
    {
        return new static(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    /**
     * ArrayAccess
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    /**
     * Countable
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * IteratorAggregate
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * JsonSerializable
     * @return array|array[]|\array[][]
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 转换当前数据集为JSON字符串
     * @access public
     * @param integer $options json参数
     * @return string
     */
    public function toJson($options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * 转换成数组
     *
     * @access public
     * @param mixed $items
     * @return array
     */
    protected function convertToArray($items): array
    {
        if ($items instanceof self) {
            return $items->all();
        }

        return (array)$items;
    }
}

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

class Tree
{
    /**
     * @var array
     */
    private array $originalList;

    //主键字段名
    public string $pk;

    //上级id字段名
    public string $parentKey;

    //用来存储子分类的数组key名
    public string $childrenKey;

    /**
     * Tree constructor.
     * @param string $pk
     * @param string $parentKey
     * @param string $childrenKey
     */
    function __construct(string $pk = "id", string $parentKey = "pid", string $childrenKey = "children")
    {
        if (!empty($pk) && !empty($parentKey) && !empty($childrenKey)) {
            $this->pk = $pk;
            $this->parentKey = $parentKey;
            $this->childrenKey = $childrenKey;
        }
    }

    /**
     * 载入初始数组
     * @param array $data
     */
    function load(array $data): void
    {
        if (is_array($data)) {
            $this->originalList = $data;
        }
    }

    /**
     * 生成嵌套格式的树形数组
     * array(..."children"=>array(..."children"=>array(...)))
     * @param int $root
     * @return array|bool
     */
    function DeepTree($root = 0)
    {
        if (!$this->originalList) {
            return false;
        }
        $originalList = $this->originalList;
        $tree = [];//最终数组
        $refer = [];//存储主键与数组单元的引用关系
        //遍历
        foreach ($originalList as $k => $v) {
            if (!isset($v[$this->pk]) || !isset($v[$this->parentKey]) || isset($v[$this->childrenKey])) {
                unset($originalList[$k]);
                continue;
            }
            $refer[$v[$this->pk]] =& $originalList[$k];//为每个数组成员建立引用关系
        }
        //遍历2
        foreach ($originalList as $k => $v) {
            if ($v[$this->parentKey] == $root) {//根分类直接添加引用到tree中
                $tree[] =& $originalList[$k];
            } else {
                if (isset($refer[$v[$this->parentKey]])) {
                    $parent =& $refer[$v[$this->parentKey]];//获取父分类的引用
                    $parent[$this->childrenKey][] =& $originalList[$k];//在父分类的children中再添加一个引用成员
                }
            }
        }
        return $tree;
    }

    /**
     * 从末端节点开始遍历树
     * @param $tree
     * @param callable $callBack
     */
    public function traverseTree(&$tree, callable $callBack): void
    {
        foreach ($tree as &$item) {
            if (isset($item[$this->childrenKey])) {
                $this->traverseTree($item[$this->childrenKey], $callBack);
            }
            if (is_callable($callBack)) {
                $callBack($item);
            }
        }
    }
}

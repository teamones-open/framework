<?php
namespace FrameworkTests;
// Historical SQL generation baseline, before where/order restrictions.
// where: 99ab69c^; order: 4542fb6^ in teamones-open/framework.
class LegacyQueryParsing extends \think\db\driver\Mysql
{
    protected function parseWhere($where)
    {
        $whereStr = '';
        if (is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        } else {
            // 使用数组表达式
            $operate = isset($where['_logic']) ? strtoupper($where['_logic']) : '';
            if (in_array($operate, ['AND', 'OR', 'XOR'])) {
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' ' . $operate . ' ';
                unset($where['_logic']);
            } else {
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach ($where as $key => $val) {
                if (is_numeric($key)) {
                    $key = '_complex';
                }
                if (0 === strpos($key, '_')) {
                    // 解析特殊条件表达式
                    $whereStr .= $this->parseThinkWhere($key, $val);
                } else {
                    // 查询字段的安全过滤
                    // if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/',trim($key))){
                    //     StrackE(L('_EXPRESS_ERROR_').':'.$key);
                    // }
                    // 多条件支持
                    $multi = is_array($val) && isset($val['_multi']);
                    $key = trim($key);
                    $str = [];
                    if (strpos($key, '|')) {
                        // 支持 name|title|nickname 方式定义查询字段
                        $array = explode('|', $key);
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = $this->parseWhereItem($this->parseKey($k), $v);
                        }
                        $whereStr .= '( ' . implode(' OR ', $str) . ' )';
                    } elseif (strpos($key, '&')) {
                        $array = explode('&', $key);
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = '(' . $this->parseWhereItem($this->parseKey($k), $v) . ')';
                        }
                        $whereStr .= '( ' . implode(' AND ', $str) . ' )';
                    } else {
                        $whereStr .= $this->parseWhereItem($this->parseKey($key), $val);
                    }
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr, 0, -strlen($operate));
        }
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }
    protected function parseOrder($order, $trusted = false)
    {
        if (empty($order)) {
            return '';
        }
        $array = [];
        if (is_array($order)) {
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if (false === strpos($val, '(')) {
                        $array[] = $this->parseKey($val);
                    } else {
                        // 复杂order条件 包含 () JSON_EXTRACT
                        $array[] = $val;
                    }
                } else {
                    $sort = in_array(strtolower($val), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = $this->parseKey($key, true) . $sort;
                }
            }
        } elseif ('[RAND]' == $order) {
            // 随机排序
            $array[] = $this->parseRand();
        } else {
            foreach (explode(',', $order) as $val) {
                if (preg_match('/\s+(ASC|DESC)$/i', rtrim($val), $match, PREG_OFFSET_CAPTURE)) {
                    $array[] = $this->parseKey(ltrim(substr($val, 0, $match[0][1]))) . ' ' . $match[1][0];
                } elseif (false === strpos($val, '(')) {
                    $array[] = $this->parseKey($val);
                }
            }
        }
        $order = implode(',', $array);
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }
}

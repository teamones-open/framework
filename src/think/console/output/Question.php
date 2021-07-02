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

namespace think\console\output;

class Question
{

    private string $question;
    private ?int $attempts;
    private bool $hidden = false;
    private bool $hiddenFallback = true;
    private $autocompleterValues;
    private $validator;
    private $default;
    private $normalizer;

    /**
     * 构造方法
     * @param string $question 问题
     * @param mixed $default 默认答案
     */
    public function __construct(string $question, $default = null)
    {
        $this->question = $question;
        $this->default = $default;
    }

    /**
     * 获取问题
     * @return string
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    /**
     * 获取默认答案
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * 是否隐藏答案
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * 隐藏答案
     * @param bool $hidden
     * @return Question
     */
    public function setHidden(bool $hidden): Question
    {
        if ($this->autocompleterValues) {
            throw new \LogicException('A hidden question cannot use the autocompleter.');
        }

        $this->hidden = (bool)$hidden;

        return $this;
    }

    /**
     * 不能被隐藏是否撤销
     * @return bool
     */
    public function isHiddenFallback(): bool
    {
        return $this->hiddenFallback;
    }

    /**
     * 设置不能被隐藏的时候的操作
     * @param bool $fallback
     * @return Question
     */
    public function setHiddenFallback($fallback): Question
    {
        $this->hiddenFallback = (bool)$fallback;

        return $this;
    }

    /**
     * 获取自动完成
     * @return null|array|\Traversable
     */
    public function getAutocompleterValues()
    {
        return $this->autocompleterValues;
    }

    /**
     * 设置自动完成的值
     * @param null|array|\Traversable $values
     * @return Question
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function setAutocompleterValues($values): Question
    {
        if (is_array($values) && $this->isAssoc($values)) {
            $values = array_merge(array_keys($values), array_values($values));
        }

        if (null !== $values && !is_array($values)) {
            if (!$values instanceof \Traversable || $values instanceof \Countable) {
                throw new \InvalidArgumentException('Autocompleter values can be either an array, `null` or an object implementing both `Countable` and `Traversable` interfaces.');
            }
        }

        if ($this->hidden) {
            throw new \LogicException('A hidden question cannot use the autocompleter.');
        }

        $this->autocompleterValues = $values;

        return $this;
    }

    /**
     * 设置答案的验证器
     * @param callable|null $validator
     * @return Question The current instance
     */
    public function setValidator(?callable $validator): Question
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * 获取验证器
     * @return null|callable
     */
    public function getValidator(): ?callable
    {
        return $this->validator;
    }

    /**
     * 设置最大重试次数
     * @param int|null $attempts
     * @return Question
     * @throws \InvalidArgumentException
     */
    public function setMaxAttempts(?int $attempts): Question
    {
        if (null !== $attempts && $attempts < 1) {
            throw new \InvalidArgumentException('Maximum number of attempts must be a positive value.');
        }

        $this->attempts = $attempts;

        return $this;
    }

    /**
     * 获取最大重试次数
     * @return null|int
     */
    public function getMaxAttempts(): ?int
    {
        return $this->attempts;
    }

    /**
     * 设置响应的回调
     * @param string|\Closure $normalizer
     * @return Question
     */
    public function setNormalizer($normalizer): Question
    {
        $this->normalizer = $normalizer;

        return $this;
    }

    /**
     * 获取响应回调
     * The normalizer can ba a callable (a string), a closure or a class implementing __invoke.
     * @return string|\Closure
     */
    public function getNormalizer()
    {
        return $this->normalizer;
    }

    /**
     * @param $array
     * @return bool
     */
    protected function isAssoc($array): bool
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }
}

<?php

declare(strict_types=1);

namespace GraphQL\Executor\Promise;

use Throwable;

/**
 * 提供集成异步 PHP 平台的方法 ([相关文档](data-fetching.md#async-php))
 */
interface PromiseAdapter
{
    /**
     * 如果 value 是底层平台的 promise 或 deferred，则返回 true
     *
     * @param mixed $value
     *
     * @return bool
     *
     * @api
     */
    public function isThenable($value);

    /**
     * 将底层平台的 thenable 转换为 GraphQL\Executor\Promise\Promise 实例
     *
     * @param object $thenable
     *
     * @return Promise
     *
     * @api
     */
    public function convertThenable($thenable);

    /**
     * 接受我们的 Promise 包装器，从中提取所采用的 promise 并执行 Promises / A+ 规范中描述的实际`then`逻辑。
     *
     * 然后返回 GraphQL\Executor\Promise\Promise 的新包装实例
     *
     * @return Promise
     *
     * @api
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null);

    /**
     * 创造一个 Promise
     *
     * 期望的函数格式:
     *     function(callable $resolve, callable $reject)
     *
     * @return Promise
     *
     * @api
     */
    public function create(callable $resolver);

    /**
     * 如果 $value 不是 Promise 则为其创建 Promise
     *
     * @param mixed $value
     *
     * @return Promise
     *
     * @api
     */
    public function createFulfilled($value = null);

    /**
     * 如果 reason 不是 Promise，则创建被 reject 的 Promise
     *
     * 如果提供的 reason 就是 Promise 对象，则原样返回
     *
     * @param Throwable $reason
     *
     * @return Promise
     *
     * @api
     */
    public function createRejected($reason);

    /**
     * 给定一系列 Promise 或 value，返回在满足数组中所有项目时满足的 Promise
     *
     * @param Promise[]|mixed[] $promisesOrValues Promises or values.
     *
     * @return Promise
     *
     * @api
     */
    public function all(array $promisesOrValues);
}

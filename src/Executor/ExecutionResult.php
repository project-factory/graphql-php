<?php

declare(strict_types=1);

namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use JsonSerializable;
use function array_map;

/**
 * 查询结果定义 [阅读文档] (executing-queries.md)
 *
 * 两种响应都会返回同一种对象，成功执行的结果和失败的结果，错误收集在 errors 字段中
 *
 * 可以使用 toArray 转换为可序列化数组 [符合规范] (https://facebook.github.io/graphql/#sec-Response-Format)
 */
class ExecutionResult implements JsonSerializable
{
    /**
     * 在查询执行期间从解析器中收集的数据
     *
     * @api
     * @var mixed[]
     */
    public $data;

    /**
     * 在查询执行期间收集的错误
     *
     * 如果错误是由解析器中抛出的异常引起的，$error->getPrevious() 将包含原始异常
     *
     * @api
     * @var Error[]
     */
    public $errors;

    /**
     * 序列化结果中包含用户定义的可序列化扩展数组
     *
     * @api
     * @var mixed[]
     */
    public $extensions;

    /** @var callable */
    private $errorFormatter;

    /** @var callable */
    private $errorsHandler;

    /**
     * @param mixed[] $data
     * @param Error[] $errors
     * @param mixed[] $extensions
     */
    public function __construct($data = null, array $errors = [], array $extensions = [])
    {
        $this->data       = $data;
        $this->errors     = $errors;
        $this->extensions = $extensions;
    }

    /**
     * 自定义错误格式 (必须符合 http://facebook.github.io/graphql/#sec-Errors)
     *
     * 预期的回调函数: function (GraphQL\Error\Error $error): array
     *
     * 默认的回调函数: "GraphQL\Error\FormattedError::createFromException"
     *
     * 预期返回值必须是数组:
     * array(
     *    'message' => 'errorMessage',
     *    // ... other keys
     * );
     *
     * @return self
     *
     * @api
     */
    public function setErrorFormatter(callable $errorFormatter)
    {
        $this->errorFormatter = $errorFormatter;

        return $this;
    }

    /**
     * 定义错误处理的自定义逻辑 (filtering, logging, etc).
     *
     * 预期的回调函数: function (array $errors, callable $formatter): array
     *
     * 默认的回调函数:
     * function (array $errors, callable $formatter) {
     *     return array_map($formatter, $errors);
     * }
     *
     * @return self
     *
     * @api
     */
    public function setErrorsHandler(callable $handler)
    {
        $this->errorsHandler = $handler;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 使用提供的错误处理程序和格式化程序将 GraphQL 查询结果转换为符合规范的可序列化数组
     *
     * 如果传递了 debug 参数，则会调整错误格式器的输出，从而调试信息
     *
     * $debug 参数必须是 bool 类型 (只将 "debugMessage" 添加到结果) 或 GraphQL\Error\Debug 中的总和
     *
     * @param bool|int $debug
     *
     * @return mixed[]
     *
     * @api
     */
    public function toArray($debug = false)
    {
        $result = [];

        if (!empty($this->errors)) {
            $errorsHandler = $this->errorsHandler ?: static function (array $errors, callable $formatter) {
                return array_map($formatter, $errors);
            };

            $result['errors'] = $errorsHandler(
                $this->errors,
                FormattedError::prepareFormatter($this->errorFormatter, $debug)
            );
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if (!empty($this->extensions)) {
            $result['extensions'] = $this->extensions;
        }

        return $result;
    }
}

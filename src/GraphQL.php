<?php

declare(strict_types=1);

namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Executor\ReferenceExecutor;
use GraphQL\Experimental\Executor\CoroutineExecutor;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema as SchemaType;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\ValidationRule;
use function array_values;
use function trigger_error;
use const E_USER_DEPRECATED;

/**
 * 这是完成 GraphQL 操作的门面对象，查看 [相关文档] (executing-queries.md).
 */
class GraphQL
{
    /**
     * 执行 GraphQL 查询
     *
     * 更复杂的 GraphQL 服务器可能希望将验证和执行阶段分为静态时间工具步骤和服务运行时步骤。
     *
     * 参数说明:
     *
     * @param \GraphQL\Type\Schema $schema 验证和执行查询时使用的GraphQL类型系统
     * @param string|DocumentNode $source 解析并执行现有的 GraphQL 查询字符
     * @param mixed $rootValue 作为 Schema 字段解析传递的第一个参数
     * @param mixed $context 作为所有解析器的第三个参数提供的值，字段解析器之间的共享信息。 常用来传递已登录用户信息，位置详情等
     * @param array $variableValues 变量的映射，该值将随同查询字符串一起传递
     * @param string $operationName 包含多个操作时，要使用的操作名称，如果只包含一个操作，则可以省略
     * @param callable $fieldResolver 当 Scheam 未提供解析器函数时使用的解析器函数，如果未提供则使用默认字段解析程序
     * @param array $validationRules 查询验证步骤的一组规则，默认值是所有可用规则
     *          空数组将代表允许跳过查询验证（对于在持久化期间验证并在执行期间假定为有效的持久查询可能很方便）
     *
     * @return ExecutionResult
     *
     * @api
     */
    public static function executeQuery(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $context = null,
        $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null
    ): ExecutionResult
    {
        $promiseAdapter = new SyncPromiseAdapter();

        $promise = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $context,
            $variableValues,
            $operationName,
            $fieldResolver,
            $validationRules
        );

        return $promiseAdapter->wait($promise);
    }

    /**
     * Same as executeQuery(), but requires PromiseAdapter and always returns a Promise.
     * Useful for Async PHP platforms.
     *
     * @param string|DocumentNode $source
     * @param mixed $rootValue
     * @param mixed $context
     * @param mixed[]|null $variableValues
     * @param ValidationRule[]|null $validationRules
     *
     * @api
     */
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        SchemaType $schema,
        $source,
        $rootValue = null,
        $context = null,
        $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null
    ): Promise
    {
        try {
            if ($source instanceof DocumentNode) {
                $documentNode = $source;
            } else {
                $documentNode = Parser::parse(new Source($source ?: '', 'GraphQL'));
            }

            // FIXME
            if (empty($validationRules)) {
                /** @var QueryComplexity $queryComplexity */
                $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);
                $queryComplexity->setRawVariableValues($variableValues);
            } else {
                foreach ($validationRules as $rule) {
                    if (!($rule instanceof QueryComplexity)) {
                        continue;
                    }

                    $rule->setRawVariableValues($variableValues);
                }
            }

            $validationErrors = DocumentValidator::validate($schema, $documentNode, $validationRules);

            if (!empty($validationErrors)) {
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, $validationErrors)
                );
            }

            return Executor::promiseToExecute(
                $promiseAdapter,
                $schema,
                $documentNode,
                $rootValue,
                $context,
                $variableValues,
                $operationName,
                $fieldResolver
            );
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }
    }

    /**
     * @deprecated Use executeQuery()->toArray() instead
     *
     * @param string|DocumentNode $source
     * @param mixed $rootValue
     * @param mixed $contextValue
     * @param mixed[]|null $variableValues
     *
     * @return Promise|mixed[]
     */
    public static function execute(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        ?string $operationName = null
    )
    {
        trigger_error(
            __METHOD__ . ' is deprecated, use GraphQL::executeQuery()->toArray() as a quick replacement',
            E_USER_DEPRECATED
        );

        $promiseAdapter = Executor::getPromiseAdapter();
        $result         = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName
        );

        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result)->toArray();
        } else {
            $result = $result->then(static function (ExecutionResult $r) {
                return $r->toArray();
            });
        }

        return $result;
    }

    /**
     * @deprecated renamed to executeQuery()
     *
     * @param string|DocumentNode $source
     * @param mixed $rootValue
     * @param mixed $contextValue
     * @param mixed[]|null $variableValues
     *
     * @return ExecutionResult|Promise
     */
    public static function executeAndReturnResult(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        ?string $operationName = null
    )
    {
        trigger_error(
            __METHOD__ . ' is deprecated, use GraphQL::executeQuery() as a quick replacement',
            E_USER_DEPRECATED
        );

        $promiseAdapter = Executor::getPromiseAdapter();
        $result         = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName
        );

        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result);
        }

        return $result;
    }

    /**
     * Returns directives defined in GraphQL spec
     *
     * @return Directive[]
     *
     * @api
     */
    public static function getStandardDirectives(): array
    {
        return array_values(Directive::getInternalDirectives());
    }

    /**
     * Returns types defined in GraphQL spec
     *
     * @return Type[]
     *
     * @api
     */
    public static function getStandardTypes(): array
    {
        return array_values(Type::getStandardTypes());
    }

    /**
     * Replaces standard types with types from this list (matching by name)
     * Standard types not listed here remain untouched.
     *
     * @param Type[] $types
     *
     * @api
     */
    public static function overrideStandardTypes(array $types)
    {
        Type::overrideStandardTypes($types);
    }

    /**
     * Returns standard validation rules implementing GraphQL spec
     *
     * @return ValidationRule[]
     *
     * @api
     */
    public static function getStandardValidationRules(): array
    {
        return array_values(DocumentValidator::defaultRules());
    }

    /**
     * Set default resolver implementation
     *
     * @api
     */
    public static function setDefaultFieldResolver(callable $fn): void
    {
        Executor::setDefaultFieldResolver($fn);
    }

    public static function setPromiseAdapter(?PromiseAdapter $promiseAdapter = null): void
    {
        Executor::setPromiseAdapter($promiseAdapter);
    }

    /**
     * Experimental: Switch to the new executor
     */
    public static function useExperimentalExecutor()
    {
        Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
    }

    /**
     * Experimental: Switch back to the default executor
     */
    public static function useReferenceExecutor()
    {
        Executor::setImplementationFactory([ReferenceExecutor::class, 'create']);
    }

    /**
     * Returns directives defined in GraphQL spec
     *
     * @deprecated Renamed to getStandardDirectives
     *
     * @return Directive[]
     */
    public static function getInternalDirectives(): array
    {
        return self::getStandardDirectives();
    }
}

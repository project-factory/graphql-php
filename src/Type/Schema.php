<?php

declare(strict_types=1);

namespace GraphQL\Type;

use Generator;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;
use Traversable;
use function array_values;
use function implode;
use function is_array;
use function is_callable;
use function sprintf;

/**
 * Schema 定义 (查看 [相关文档](type-system/schema.md))
 *
 * 通过提供每种操作类型的根类型来创建 Schema 对象：
 *      query（必须） / mutation（可选） /  subscription（可选）
 *
 * 然后将 Schema 定义提供给验证器和执行器，用法示例：
 *
 *     $schema = new GraphQL\Type\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * 或者使用 Schema Config 实例:
 *
 *     $config = GraphQL\Type\SchemaConfig::create()
 *         ->setQuery($MyAppQueryRootType)
 *         ->setMutation($MyAppMutationRootType);
 *
 *     $schema = new GraphQL\Type\Schema($config);
 */
class Schema
{
    /**
     * 当前 Schema 的配置项
     *
     * @var SchemaConfig
     */
    private $config;

    /**
     * 包含当前已解析成功的 Schema 类型数组
     *
     * @var Type[]
     */
    private $resolvedTypes = [];

    /** @var Type[][]|null */
    private $possibleTypeMap;

    /**
     * True when $resolvedTypes contain all possible schema types
     *
     * @var bool
     */
    private $fullyLoaded = false;

    /** @var InvariantViolation[]|null */
    private $validationErrors;

    /**
     * 当前
     *
     * @var SchemaTypeExtensionNode[]
     */
    public $extensionASTNodes;

    /**
     * 构造方法，接收一个 SchemaConfig 对象，或者一个 Array 对象
     *
     * @param mixed[]|SchemaConfig $config 配置项
     *
     * @api
     *
     * @see https://webonyx.github.io/graphql-php/type-system/schema/
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            $config = SchemaConfig::create($config);
        }

        // 如果此 Schema 是从已知有效的源构建的，则可以使用 assumeValid 标记该 Schema 以避免其他类型的系统验证
        if ($config->getAssumeValid()) {
            $this->validationErrors = [];
        } else {
            // 否则，在构造新的 Schema 过程中检查常见错误，以产生明确的早期错误消息。
            Utils::invariant(
                $config instanceof SchemaConfig,
                'Schema constructor expects instance of GraphQL\Type\SchemaConfig or an array with keys: %s; but got: %s',
                implode(
                    ', ',
                    [
                        'query',
                        'mutation',
                        'subscription',
                        'types',
                        'directives',
                        'typeLoader',
                    ]
                ),
                Utils::getVariableType($config)
            );
            Utils::invariant(
                !$config->types || is_array($config->types) || is_callable($config->types),
                '"types" must be array or callable if provided but got: ' . Utils::getVariableType($config->types)
            );
            Utils::invariant(
                !$config->directives || is_array($config->directives),
                '"directives" must be Array if provided but got: ' . Utils::getVariableType($config->directives)
            );
        }

        $this->config = $config;
        $this->extensionASTNodes = $config->extensionASTNodes;

        if ($config->query) {
            $this->resolvedTypes[$config->query->name] = $config->query;
        }
        if ($config->mutation) {
            $this->resolvedTypes[$config->mutation->name] = $config->mutation;
        }
        if ($config->subscription) {
            $this->resolvedTypes[$config->subscription->name] = $config->subscription;
        }
        if (is_array($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                if (isset($this->resolvedTypes[$type->name])) {
                    Utils::invariant(
                        $type === $this->resolvedTypes[$type->name],
                        sprintf(
                            'Schema must contain unique named types but contains multiple types named "%s" (see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
                            $type
                        )
                    );
                }
                $this->resolvedTypes[$type->name] = $type;
            }
        }
        $this->resolvedTypes += Type::getStandardTypes() + Introspection::getTypes();

        // 是否延迟加载类型，类型加载概念与 PHP 类加载非常相似，但请记住，typeLoader 必须始终返回相同的类型实例
        if ($this->config->typeLoader) {
            return;
        }

        // 默认情况下，将执行 Schema 的完整扫描
        $this->getTypeMap();
    }

    /**
     * @return Generator
     */
    private function resolveAdditionalTypes()
    {
        $types = $this->config->types ?: [];

        if (is_callable($types)) {
            $types = $types();
        }

        if (!is_array($types) && !$types instanceof Traversable) {
            throw new InvariantViolation(sprintf(
                'Schema types callable must return array or instance of Traversable but got: %s',
                Utils::getVariableType($types)
            ));
        }

        foreach ($types as $index => $type) {
            if (!$type instanceof Type) {
                throw new InvariantViolation(sprintf(
                    'Each entry of schema types must be instance of GraphQL\Type\Definition\Type but entry at %s is %s',
                    $index,
                    Utils::printSafe($type)
                ));
            }
            yield $type;
        }
    }

    /**
     * 返回此 Schema 中所有类型的数组，此数组的键表示类型名称，值是相应类型定义的实例
     *
     * 此操作需要完整的 Schema 扫描，不要在生产环境中使用
     *
     * @return Type[]
     *
     * @api
     */
    public function getTypeMap()
    {
        if (!$this->fullyLoaded) {
            $this->resolvedTypes = $this->collectAllTypes();
            $this->fullyLoaded = true;
        }

        return $this->resolvedTypes;
    }

    /**
     * @return Type[]
     */
    private function collectAllTypes()
    {
        $typeMap = [];
        foreach ($this->resolvedTypes as $type) {
            $typeMap = TypeInfo::extractTypes($type, $typeMap);
        }
        foreach ($this->getDirectives() as $directive) {
            if (!($directive instanceof Directive)) {
                continue;
            }

            $typeMap = TypeInfo::extractTypesFromDirectives($directive, $typeMap);
        }
        // When types are set as array they are resolved in constructor
        if (is_callable($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                $typeMap = TypeInfo::extractTypes($type, $typeMap);
            }
        }

        return $typeMap;
    }

    /**
     * 返回此架构支持的指令列表
     *
     * @return Directive[]
     *
     * @api
     */
    public function getDirectives()
    {
        return $this->config->directives ?: GraphQL::getStandardDirectives();
    }

    /**
     * Returns schema query type
     *
     * @return ObjectType
     *
     * @api
     */
    public function getQueryType()
    {
        return $this->config->query;
    }

    /**
     * Returns schema mutation type
     *
     * @return ObjectType|null
     *
     * @api
     */
    public function getMutationType()
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription
     *
     * @return ObjectType|null
     *
     * @api
     */
    public function getSubscriptionType()
    {
        return $this->config->subscription;
    }

    /**
     * @return SchemaConfig
     *
     * @api
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns type by it's name
     *
     * @param string $name
     *
     * @return Type|null
     *
     * @api
     */
    public function getType($name)
    {
        if (!isset($this->resolvedTypes[$name])) {
            $type = $this->loadType($name);
            if (!$type) {
                return null;
            }
            $this->resolvedTypes[$name] = $type;
        }

        return $this->resolvedTypes[$name];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasType($name)
    {
        return $this->getType($name) !== null;
    }

    /**
     * @param string $typeName
     *
     * @return Type
     */
    private function loadType($typeName)
    {
        $typeLoader = $this->config->typeLoader;

        if (!$typeLoader) {
            return $this->defaultTypeLoader($typeName);
        }

        $type = $typeLoader($typeName);

        if (!$type instanceof Type) {
            throw new InvariantViolation(
                sprintf(
                    'Type loader is expected to return valid type "%s", but it returned %s',
                    $typeName,
                    Utils::printSafe($type)
                )
            );
        }
        if ($type->name !== $typeName) {
            throw new InvariantViolation(
                sprintf('Type loader is expected to return type "%s", but it returned "%s"', $typeName, $type->name)
            );
        }

        return $type;
    }

    /**
     * @param string $typeName
     *
     * @return Type
     */
    private function defaultTypeLoader($typeName)
    {
        // Default type loader simply fallbacks to collecting all types
        $typeMap = $this->getTypeMap();

        return $typeMap[$typeName] ?? null;
    }

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions)
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @return ObjectType[]
     *
     * @api
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        $possibleTypeMap = $this->getPossibleTypeMap();

        return isset($possibleTypeMap[$abstractType->name]) ? array_values($possibleTypeMap[$abstractType->name]) : [];
    }

    /**
     * @return Type[][]
     */
    private function getPossibleTypeMap()
    {
        if ($this->possibleTypeMap === null) {
            $this->possibleTypeMap = [];
            foreach ($this->getTypeMap() as $type) {
                if ($type instanceof ObjectType) {
                    foreach ($type->getInterfaces() as $interface) {
                        if (!($interface instanceof InterfaceType)) {
                            continue;
                        }

                        $this->possibleTypeMap[$interface->name][$type->name] = $type;
                    }
                } elseif ($type instanceof UnionType) {
                    foreach ($type->getTypes() as $innerType) {
                        $this->possibleTypeMap[$type->name][$innerType->name] = $innerType;
                    }
                }
            }
        }

        return $this->possibleTypeMap;
    }

    /**
     * Returns true if object type is concrete type of given abstract type
     * (implementation for interfaces and members of union type for unions)
     *
     * @return bool
     *
     * @api
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if ($abstractType instanceof InterfaceType) {
            return $possibleType->implementsInterface($abstractType);
        }

        /** @var UnionType $abstractType */
        return $abstractType->isPossibleType($possibleType);
    }

    /**
     * 按名称返回指令的实例
     *
     * @param string $name
     *
     * @return Directive
     *
     * @api
     */
    public function getDirective($name)
    {
        foreach ($this->getDirectives() as $directive) {
            if ($directive->name === $name) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * @return SchemaDefinitionNode
     */
    public function getAstNode()
    {
        return $this->config->getAstNode();
    }

    /**
     * 验证 schema.
     *
     * 此操作需要完整模式扫描，不要在生产环境中使用。
     *
     * @throws InvariantViolation
     *
     * @api
     *
     * @see https://webonyx.github.io/graphql-php/type-system/schema/
     */
    public function assertValid()
    {
        $errors = $this->validate();

        if ($errors) {
            throw new InvariantViolation(implode("\n\n", $this->validationErrors));
        }

        $internalTypes = Type::getStandardTypes() + Introspection::getTypes();
        foreach ($this->getTypeMap() as $name => $type) {
            if (isset($internalTypes[$name])) {
                continue;
            }

            $type->assertValid();

            // Make sure type loader returns the same instance as registered in other places of schema
            if (!$this->config->typeLoader) {
                continue;
            }

            Utils::invariant(
                $this->loadType($name) === $type,
                sprintf(
                    'Type loader returns different instance for %s than field/argument definitions. Make sure you always return the same instance for the same type name.',
                    $name
                )
            );
        }
    }

    /**
     * 验证 Schema
     *
     * 此操作需要完整的扫描 Schema 定义，请不要在生产环境下使用
     *
     * @return InvariantViolation[]|Error[]
     *
     * @api
     */
    public function validate()
    {
        // 如果此架构已经过验证，请返回先前的结果
        if ($this->validationErrors !== null) {
            return $this->validationErrors;
        }

        // 验证当前 Schema, 生成错误列表
        $context = new SchemaValidationContext($this);
        $context->validateRootTypes();
        $context->validateDirectives();
        $context->validateTypes();

        // 在返回之前保留验证结果，以确保此 Schema 的验证不会多次运行
        $this->validationErrors = $context->getErrors();

        return $this->validationErrors;
    }
}

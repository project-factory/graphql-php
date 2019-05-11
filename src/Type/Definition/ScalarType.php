<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Utils\Utils;
use function is_string;

/**
 * 标量类型定义
 *
 * 参数的任何请求和输入值的叶值都是 Scalar 或 Enums 类型，并使用名称和一系列用于确保有效性的强制函数进行定义
 *
 * Example:
 *
 * class OddType extends ScalarType
 * {
 *     public $name = 'Odd',
 *     public function serialize($value)
 *     {
 *         return $value % 2 === 1 ? $value : null;
 *     }
 * }
 */
abstract class ScalarType extends Type implements OutputType, InputType, LeafType, NullableType, NamedType
{
    /**
     * AST 节点对象
     *
     * @var ScalarTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * AST 扩展节点对象
     *
     * @var ScalarTypeExtensionNode[]
     */
    public $extensionASTNodes;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config = [])
    {
        $this->name = $config['name'] ?? $this->tryInferName();
        $this->description = $config['description'] ?? $this->description;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? null;
        $this->config = $config;

        Utils::invariant(is_string($this->name), 'Must provide name.');
    }
}

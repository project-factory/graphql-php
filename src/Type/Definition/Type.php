<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Type\Introspection;
use GraphQL\Utils\Utils;
use JsonSerializable;
use ReflectionClass;
use Throwable;
use function array_keys;
use function array_merge;
use function implode;
use function in_array;
use function preg_replace;
use function trigger_error;
use const E_USER_DEPRECATED;

/**
 * 标准 GraphQL 类型的注册表和所有其他类型的基类。
 */
abstract class Type implements JsonSerializable
{
    public const STRING = 'String';
    public const INT = 'Int';
    public const BOOLEAN = 'Boolean';
    public const FLOAT = 'Float';
    public const ID = 'ID';

    /**
     * 标准类型对象数组
     *
     * @var Type[]
     */
    private static $standardTypes;

    /**
     * 所有内置类型对象数组
     *
     * @var Type[]
     */
    private static $builtInTypes;

    /** @var string */
    public $name;

    /**
     * 类型名称
     *
     * @var string|null
     */
    public $description;

    /** @var TypeDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /** @var TypeExtensionNode[] */
    public $extensionASTNodes;

    /**
     * 获取 ID 类型对象
     *
     * @return IDType
     *
     * @api
     */
    public static function id()
    {
        return self::getStandardType(self::ID);
    }

    /**
     * 根据类型名称，获取标准类型对象
     *
     * @param string $name 如果不传递名称，则返回所有类型对象
     *
     * @return (IDType|StringType|FloatType|IntType|BooleanType)[]|IDType|StringType|FloatType|IntType|BooleanType
     */
    private static function getStandardType($name = null)
    {
        if (self::$standardTypes === null) {
            self::$standardTypes = [
                self::ID      => new IDType(),
                self::STRING  => new StringType(),
                self::FLOAT   => new FloatType(),
                self::INT     => new IntType(),
                self::BOOLEAN => new BooleanType(),
            ];
        }

        return $name ? self::$standardTypes[$name] : self::$standardTypes;
    }

    /**
     * 获取 String 类型对象
     *
     * @return StringType
     *
     * @api
     */
    public static function string()
    {
        return self::getStandardType(self::STRING);
    }

    /**
     * 获取 Boolean 类型对象
     *
     * @return BooleanType
     *
     * @api
     */
    public static function boolean()
    {
        return self::getStandardType(self::BOOLEAN);
    }

    /**
     * 获取 Int 类型对象
     *
     * @return IntType
     *
     * @api
     */
    public static function int()
    {
        return self::getStandardType(self::INT);
    }

    /**
     * 获取 Float 类型对象
     *
     * @return FloatType
     *
     * @api
     */
    public static function float()
    {
        return self::getStandardType(self::FLOAT);
    }

    /**
     * @param Type|ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType|NonNull $wrappedType
     *
     * @return ListOfType
     *
     * @api
     */
    public static function listOf($wrappedType)
    {
        return new ListOfType($wrappedType);
    }

    /**
     * 获取 NonNull 类型对象
     *
     * @param NullableType $wrappedType
     *
     * @return NonNull
     *
     * @api
     */
    public static function nonNull($wrappedType)
    {
        return new NonNull($wrappedType);
    }

    /**
     * 检查给定对象是否为内置类型
     *
     * @return bool
     */
    public static function isBuiltInType(Type $type)
    {
        return in_array($type->name, array_keys(self::getAllBuiltInTypes()), true);
    }

    /**
     * 返回所有内置类型，包括基本标量类型和 GraphQL 内置类型
     *
     * @return Type[]
     */
    public static function getAllBuiltInTypes()
    {
        if (self::$builtInTypes === null) {
            self::$builtInTypes = array_merge(
                Introspection::getTypes(),
                self::getStandardTypes()
            );
        }

        return self::$builtInTypes;
    }

    /**
     * 返回所有内置标准类型
     *
     * @return Type[]
     */
    public static function getStandardTypes()
    {
        return self::getStandardType();
    }

    /**
     * 已启用方法，请使用 Type::getStandardTypes
     *
     * @deprecated Use method getStandardTypes() instead
     *
     * @return Type[]
     */
    public static function getInternalTypes()
    {
        trigger_error(__METHOD__ . ' is deprecated. Use Type::getStandardTypes() instead', E_USER_DEPRECATED);

        return self::getStandardTypes();
    }

    /**
     * 重写标准类型，用新的对象类型覆盖标准类型
     *
     * @param Type[] $types
     */
    public static function overrideStandardTypes(array $types)
    {
        $standardTypes = self::getStandardTypes();
        foreach ($types as $type) {
            Utils::invariant(
                $type instanceof Type,
                'Expecting instance of %s, got %s',
                self::class,
                Utils::printSafe($type)
            );
            Utils::invariant(
                isset($type->name, $standardTypes[$type->name]),
                'Expecting one of the following names for a standard type: %s, got %s',
                implode(', ', array_keys($standardTypes)),
                Utils::printSafe($type->name ?? null)
            );
            $standardTypes[$type->name] = $type;
        }
        self::$standardTypes = $standardTypes;
    }

    /**
     * 判断给定的对象是
     *
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isInputType($type)
    {
        return $type instanceof InputType &&
            (
                !$type instanceof WrappingType ||
                self::getNamedType($type) instanceof InputType
            );
    }

    /**
     * @param Type $type
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     *
     * @api
     */
    public static function getNamedType($type)
    {
        if ($type === null) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        return $type;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isOutputType($type)
    {
        return $type instanceof OutputType &&
            (
                !$type instanceof WrappingType ||
                self::getNamedType($type) instanceof OutputType
            );
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isLeafType($type)
    {
        return $type instanceof LeafType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isAbstractType($type)
    {
        return $type instanceof AbstractType;
    }

    /**
     * @param mixed $type
     *
     * @return mixed
     */
    public static function assertType($type)
    {
        Utils::invariant(
            self::isType($type),
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL type.'
        );

        return $type;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isType($type)
    {
        return $type instanceof Type;
    }

    /**
     * @param Type $type
     *
     * @return NullableType
     *
     * @api
     */
    public static function getNullableType($type)
    {
        return $type instanceof NonNull ? $type->getWrappedType() : $type;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        Utils::assertValidName($this->name);
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (Exception $e) {
            echo $e;
        } catch (Throwable $e) {
            echo $e;
        }
    }

    /**
     * @return string|null
     */
    protected function tryInferName()
    {
        if ($this->name) {
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $tmp = new ReflectionClass($this);
        $name = $tmp->getShortName();

        if ($tmp->getNamespaceName() !== __NAMESPACE__) {
            return preg_replace('~Type$~', '', $name);
        }

        return null;
    }
}

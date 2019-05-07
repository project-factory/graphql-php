<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
 * 类型装饰者接口
 */
interface WrappingType
{
    /**
     * 获取被包裹的类型对象
     *
     * @param bool $recurse 递归
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType($recurse = false);
}

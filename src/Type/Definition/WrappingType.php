<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
 * 类型装饰者接口
 */
interface WrappingType
{
    /**
     * @param bool $recurse
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType($recurse = false);
}

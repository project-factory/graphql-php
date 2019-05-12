<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/*
export type GraphQLAbstractType =
GraphQLInterfaceType |
GraphQLUnionType;
*/

interface AbstractType
{
    /**
     * 为给定的对象值解析具体的 ObjectType
     *
     * @param object  $objectValue
     * @param mixed[] $context
     *
     * @return mixed
     */
    public function resolveType($objectValue, $context, ResolveInfo $info);
}

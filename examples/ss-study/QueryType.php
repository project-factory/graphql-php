<?php

use GraphQL\Type\Definition\ResolveInfo;

class QueryType extends \GraphQL\Type\Definition\ObjectType
{
    public function __construct()
    {
        $config = [
            "name"         => "Query",
            "fields"       => [
                "user" => ["type" => self::user()]
            ],
            "resolveField" => function ($val, $args, $context, ResolveInfo $info) {
                if ($info->fieldName == "user") {
                    return new UserType();
                }
            }
        ];
        parent::__construct($config);
    }

    public function user()
    {
        return new UserType();
    }
}
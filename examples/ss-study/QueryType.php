<?php

use GraphQL\Type\Definition\ResolveInfo;

class QueryType extends \GraphQL\Type\Definition\ObjectType
{
    public function __construct()
    {
        $config = [
            "name"         => "Query",
            "fields"       => [
                "user" => ["type" => TypesDef::user()],
                "post" => ["type" => TypesDef::post()]
            ],
            "resolveField" => function ($val, $args, $context, ResolveInfo $info) {
                if ($info->fieldName == "user") {
                    return new UserModel();
                } elseif ($info->fieldName == "post") {
                    return new PostModel();
                }
            }
        ];
        parent::__construct($config);
    }
}
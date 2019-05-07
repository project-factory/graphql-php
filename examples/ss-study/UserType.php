<?php

class UserType extends \GraphQL\Type\Definition\ObjectType
{
    public function __construct()
    {
        $config = [
            "name"   => "User",
            "fields" => [
                "name" => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function () {
                        return "王仔";
                    }
                ],
                "pass" => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function () {
                        return sha1(uniqid());
                    }
                ]
            ]
        ];
        parent::__construct($config);
    }
}
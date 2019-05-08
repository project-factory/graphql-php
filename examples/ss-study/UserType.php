<?php

class UserType extends \GraphQL\Type\Definition\ObjectType
{
    public function __construct()
    {
        $config = [
            "name"   => "User",
            "fields" => [
                "name"  => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function ($user) {
                        return $user->name;
                    }
                ],
                "pass"  => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function ($user) {
                        return $user->pass;
                    }
                ],
                "age"   => [
                    "type"    => \GraphQL\Type\Definition\Type::int(),
                    "resolve" => function ($user) {
                        return $user->age;
                    }
                ],
                "sex"   => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function ($user) {
                        return $user->sex;
                    }
                ],
                "posts" => [
                    "type"    => \GraphQL\Type\Definition\Type::listOf(TypesDef::post()),
                    "resolve" => function ($user) {
                        $result = [];
                        for ($i = 0; $i <= 2; $i++) {
                            $post = new PostModel();
                            $post->title = $user->name . " 发布的文章：" . $post->title;
                            $result[] = $post;
                        }
                        return $result;
                    }
                ]
            ]
        ];
        parent::__construct($config);
    }
}
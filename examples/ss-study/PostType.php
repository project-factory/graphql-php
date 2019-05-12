<?php

class PostType extends \GraphQL\Type\Definition\ObjectType
{
    public function __construct()
    {
        $config = [
            "name"  => "Post",
            "fields" => [
                "title"        => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function ($post) {
                        return $post->title;
                    }
                ],
                "publish_time" => [
                    "type"    => \GraphQL\Type\Definition\Type::string(),
                    "resolve" => function ($post) {
                        return $post->publish_time;
                    }
                ]
            ]
        ];

        parent::__construct($config);
    }
}
<?php

class TypesDef
{
    protected static $user;
    protected static $post;

    public static function user()
    {
        return self::$user ?: (self::$user = new UserType());
    }

    public static function post()
    {
        return self::$post ?: (self::$post = new PostType());
    }

}
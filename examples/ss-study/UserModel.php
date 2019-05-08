<?php

class UserModel
{
    public $name;
    public $pass;
    public $age;
    public $sex;

    public function __construct()
    {
        $this->name = "王小旺";
        $this->pass = sha1(uniqid());
        $this->age = 25;
        $this->sex = "男";
    }
}
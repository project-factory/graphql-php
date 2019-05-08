<?php

class PostModel
{
    public $title;

    public $publish_time;

    public function __construct()
    {
        $this->title = "测试功能 —— 固定标题";
        $this->publish_time = date("Y-m-d", time());
    }
}
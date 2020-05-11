<?php

use PHPUnit\Framework\TestCase;

final class ImageSizeTest extends TestCase
{
    public function testMaxSize1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 94371840]);
        $upload->max_size(1);
        $upload->check();

        $this->assertEquals($upload->get_error(false), $upload::ERR_LONG_SIZE);
    }

    public function testMaxSize2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 1048576]);
        $upload->max_size(1);
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }

    public function testMaxSize3()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 2097152]);
        $upload->max_size(10);
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }

    public function testMinSize1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 2097152]);
        $upload->min_size(4);
        $upload->check();

        $this->assertEquals($upload->get_error(false), $upload::ERR_SMALL_SIZE);
    }

    public function testMinSize2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 2097152]);
        $upload->min_size(2);
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }

    public function testMinSize3()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 20971520]);
        $upload->min_size(5);
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }
}

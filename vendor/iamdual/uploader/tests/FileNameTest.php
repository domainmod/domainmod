<?php

use PHPUnit\Framework\TestCase;

final class FileNameTest extends TestCase
{
    public function testName1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->name("bar");
        $upload->check();

        $this->assertEquals($upload->get_name(), "bar.png");
    }

    public function testName2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->check();

        $this->assertEquals($upload->get_name(), "foo.png");
    }

    public function testName3()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->name("bar.xyz", false);
        $upload->check();

        $this->assertEquals($upload->get_name(), "bar.xyz");
    }
}

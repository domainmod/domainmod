<?php

use PHPUnit\Framework\TestCase;

final class FilePathTest extends TestCase
{
    public function testPath1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->path("xyz/abc");
        $upload->name("hello");

        $this->assertEquals("hello.png", $upload->get_name());
        $this->assertEquals("xyz/abc/", $upload->get_path(null, false));
        $this->assertEquals("xyz/abc/456.png", $upload->get_path("456.png"));
    }

    public function testPath2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 1]);
        $upload->path("animals/cats");
        $upload->name("2020/08/Tekir");

        $this->assertEquals("2020/08/Tekir.jpg", $upload->get_name());
        $this->assertEquals("animals/cats/", $upload->get_path(null, false));
        $this->assertEquals("animals/cats/456.jpeg", $upload->get_path("456.jpeg"));
    }
}
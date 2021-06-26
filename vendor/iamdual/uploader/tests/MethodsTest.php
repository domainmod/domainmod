<?php

use PHPUnit\Framework\TestCase;

final class MethodsTest extends TestCase
{
    public function testGetName1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->name("foo");

        $this->assertEquals($upload->get_name(), "foo.jpg");
    }

    public function testGetName2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->name("foo");
        $upload->encrypt_name();

        $this->assertNotEquals($upload->get_name(), "foo.jpg");
    }

    public function testGetTmpName()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 1]);

        $this->assertNotNull($upload->get_tmp_name());
    }

    public function testGetDataUrl()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);

        $this->assertEquals(trim(file_get_contents(__DIR__ . "/assets/foo.base64")), $upload->get_data_url());
    }
}

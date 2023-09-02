<?php

use PHPUnit\Framework\TestCase;

final class FileNameTest extends TestCase
{
    public function testName1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->name("bar");

        $this->assertEquals("bar.png", $upload->get_name());
    }

    public function testName2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);

        $this->assertEquals("foo.png", $upload->get_name());
    }

    public function testName3()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->name("bar.xyz", false);

        $this->assertEquals("bar.xyz", $upload->get_name());
    }

    public function testName4()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->name("foo");

        $this->assertEquals("foo.jpg", $upload->get_name());
    }

    public function testName5()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->name("foo");
        $upload->encrypt_name();

        $this->assertNotEquals("foo.json", $upload->get_name());
        $this->assertEquals(false, $upload->encrypt_name); // Because it's setting to 'true' once
    }

    public function testName6()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->name("folder1/folder2/foo.xyz", false);

        $this->assertEquals("folder1/folder2/foo.xyz", $upload->get_name());
    }
}

<?php

use PHPUnit\Framework\TestCase;

final class FileExtensionTest extends TestCase
{
    public function testExtension1()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->allowed_extensions(array("png", "gif"));
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }

    public function testExtension2()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.json", "type" => "application/json", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->allowed_extensions(array("png", "gif"));
        $upload->check();

        $this->assertEquals($upload->get_error(false), $upload::ERR_INVALID_EXT);
    }

    public function testExtension3()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.json", "type" => "application/json", "tmp_name" => __DIR__ . "/assets/foo.json", "error" => 0, "size" => 1]);
        $upload->disallowed_extensions(array("png", "gif"));
        $upload->check();

        $this->assertEquals($upload->get_error(false), null);
    }
}

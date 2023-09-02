<?php

use PHPUnit\Framework\TestCase;

final class MethodsTest extends TestCase
{
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

    public function testGetType()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);

        $this->assertEquals("image/png", $upload->get_type());
    }

    public function testGetSize()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);

        $this->assertEquals(1, (int)$upload->get_size());
    }

    public function testValidateAspectRatio()
    {
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio("3:2", 300, 200));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio(3/2, 300, 200));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio(1.5, 300, 200));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio("1:1", 300, 300));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio(1, 300, 300));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio("16:9", 1920, 1080));
        $this->assertTrue(\iamdual\Uploader::validate_aspect_ratio(16/9, 1920, 1080));
        $this->assertFalse(\iamdual\Uploader::validate_aspect_ratio("1:1", 300, 300.1));
        $this->assertFalse(\iamdual\Uploader::validate_aspect_ratio(1, 300, 300.1));
        $this->assertFalse(\iamdual\Uploader::validate_aspect_ratio("1:1", 300, 399));
        $this->assertFalse(\iamdual\Uploader::validate_aspect_ratio(1, 300, 399));
    }
}
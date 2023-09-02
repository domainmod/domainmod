<?php

use PHPUnit\Framework\TestCase;

final class UploadTest extends TestCase
{
    public function testUploadFiles()
    {
        $uploaded_files = [];

        for ($i = 1; $i <= 10; $i++) {
            $upload = new \iamdual\Uploader(["name" => "foo.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 1]);
            $upload->must_be_image();
            $upload->path(__DIR__ . "/files");
            $upload->name("bar");

            $this->assertEquals(true, $upload->upload(true));
            $this->assertEquals(true, $upload->check());
            $suffix = $i > 1 ? "_{$i}" : "";
            $this->assertEquals("bar{$suffix}.jpg", $upload->get_name());
            $uploaded_files[] = $upload->get_path();
        }

        foreach ($uploaded_files as $file) {
            @unlink($file);
        }
    }

    public function testUploadFiles2()
    {
        $uploaded_files = [];

        for ($i = 1; $i <= 10; $i++) {
            $upload = new \iamdual\Uploader(["name" => "xyz.jpg", "type" => "image/jpeg", "tmp_name" => __DIR__ . "/assets/foo.jpg", "error" => 0, "size" => 1]);
            $upload->must_be_image();
            $upload->path(__DIR__ . "/files");

            $this->assertEquals(true, $upload->upload(true));
            $this->assertEquals(true, $upload->check());
            $suffix = $i > 1 ? "_{$i}" : "";
            $this->assertEquals("xyz{$suffix}.jpg", $upload->get_name());
            $uploaded_files[] = $upload->get_path();
        }

        foreach ($uploaded_files as $file) {
            @unlink($file);
        }
    }

    public function testFromBase64()
    {
        $base64_data = base64_encode(file_get_contents(__DIR__ . "/assets/foo.jpg"));
        $upload = new \iamdual\Uploader(\iamdual\Uploader::from_base64($base64_data));
        $upload->allowed_extensions(array("jpg", "jpeg"));
        $upload->allowed_types(array("image/jpeg"));
        $upload->max_dimensions(210, 60);
        $upload->min_dimensions(210, 60);
        $upload->name("hello.jpg", false);
        $this->assertEquals(true, $upload->upload(true));
        $this->assertEquals(true, $upload->check());
        $this->assertEquals("hello.jpg", $upload->get_name());
        @unlink($upload->get_path("hello.jpg"));
    }
}

<?php

use PHPUnit\Framework\TestCase;

final class CustomErrorsTest extends TestCase
{
    public function testInvalidExtension()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 1]);
        $upload->allowed_extensions(array("baz"));
        $upload->error_messages(array(
            $upload::ERR_LONG_SIZE => "Fil3 siz3 is t00 l0ng!",
            $upload::ERR_INVALID_EXT => "Inv4lid 3xt3nsi0n!",
        ));
        $upload->check();

        $this->assertEquals($upload->get_error(), "Inv4lid 3xt3nsi0n!");
    }

    public function testLongSize()
    {
        $upload = new \iamdual\Uploader(["name" => "foo.png", "type" => "image/png", "tmp_name" => __DIR__ . "/assets/foo.png", "error" => 0, "size" => 999999]);
        $upload->max_size(0.1);
        $upload->error_messages(array(
            $upload::ERR_LONG_SIZE => "Fil3 siz3 is t00 l0ng!",
            $upload::ERR_INVALID_EXT => "Inv4lid 3xt3nsi0n!",
        ));
        $upload->check();

        $this->assertEquals($upload->get_error(), "Fil3 siz3 is t00 l0ng!");
    }
}

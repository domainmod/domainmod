<?php
namespace GJClasses;

class Format
{
    public function obfusc($input)
    {
        return str_repeat("*", strlen($input));
    }
}

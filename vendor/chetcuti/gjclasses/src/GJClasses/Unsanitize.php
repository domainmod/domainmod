<?php
namespace GJClasses;

class Unsanitize
{

   public function text($text)
    {
        return htmlspecialchars_decode($text, ENT_QUOTES);

    }

}

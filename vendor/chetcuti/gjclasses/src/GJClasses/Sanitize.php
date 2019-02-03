<?php
namespace GJClasses;

class Sanitize
{

   public function text($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    }

}

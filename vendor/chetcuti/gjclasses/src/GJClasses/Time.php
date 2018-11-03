<?php
namespace GJClasses;

class Time
{

    public function stamp()
    {
        return gmdate('Y-m-d H:i:s', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeLong()
    {
        return gmdate('l, F jS', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeBasic()
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeBasicPlusDays($days)
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + $days, date("Y")));
    }

    public function timeBasicPlusYears($years)
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + $years));
    }

}

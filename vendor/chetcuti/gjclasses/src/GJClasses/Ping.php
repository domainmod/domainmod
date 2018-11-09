<?php
namespace GJClasses;

class Ping
{
    public function ping($host)
    {
        $is_valid_ip = $this->isValidIp($host);

        if ($is_valid_ip) {

            exec("ping -n 2 $host", $output, $result);

            if ($result === 0) {

                return true;

            } else {

                return false;

            }

        } else {

            $result_url = $this->isValidUrl($host);

            if ($result_url != $host) {

                return true;

            } else {

                return false;

            }

        }

    }

    public function isValidIp($host)
    {
        return gethostbyaddr($host);
    }

    public function isValidUrl($host)
    {
        return gethostbyname($host);
    }

}

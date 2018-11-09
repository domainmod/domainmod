<?php
namespace GJClasses;

class Remote
{
    public function getFileContents($filename)
    {
        if (ini_get('allow_url_fopen') && extension_loaded('openssl')) {

            return $this->getFileContFopen($filename);

        } elseif (extension_loaded('curl')) {

            return $this->getFileContCurl($filename);

        } else {

            return false;

        }
    }

    public function getFileContFopen($filename)
    {
        $scheme = $this->getUriScheme($filename);
        $context = stream_context_create(array($scheme => array('header' => 'Connection: close\r\n')));
        return file_get_contents($filename, false, $context);
    }

    public function getUriScheme($filename)
    {
        if (substr($filename, 0, 6) == 'https:') {

            return 'https';

        } elseif (substr($filename, 0, 5) == 'http:') {

            return 'http';

        } else {

            return false;

        }
    }

    public function getFileContCurl($filename)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_URL, $filename);
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }
}

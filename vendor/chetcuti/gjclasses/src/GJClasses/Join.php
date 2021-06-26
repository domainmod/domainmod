<?php
namespace GJClasses;

class Join
{
    public function push($api_key, $subject, $content, $url)
    {
        $push = new \GJClasses\Push('join');
        $type = $push->getPushType($url);

        if ($type == 'note') {

            $message = $this->pushNote($api_key, $subject, $content);

        } elseif ($type == 'url') {

            $message = $this->pushUrl($api_key, $subject, $url);

        } else {

            $message = 'Push type incorrect or not specified';

        }

        return $message;
    }

    public function getBaseUrl()
    {
        return 'https://joinjoaomgcd.appspot.com/_ah/api/messaging/v1/sendPush?';
    }

    public function pushNote($api_key, $subject, $content)
    {
        $full_url = $this->getBaseUrl() . 'apikey=' . $api_key . '&deviceId=group.all&title=' . urlencode($subject) . '&url=' . urlencode($content);
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($handle);
        curl_close($handle);
        return 'Note Sent';
    }

    public function pushUrl($api_key, $subject, $url)
    {
        $full_url = $this->getBaseUrl() . 'apikey=' . $api_key . '&deviceId=group.all&title=' . urlencode($subject) . '&url=' . urlencode($url);
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($handle);
        curl_close($handle);
        return 'URL Sent';
    }
}

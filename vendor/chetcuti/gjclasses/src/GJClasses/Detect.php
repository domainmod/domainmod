<?php
namespace GJClasses;

class Detect
{
    public function getBrowser()
    {
        // The order of the below user agent checks matters
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Browser_detection_using_the_user_agent#browser_name
        $agent_string = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($agent_string, 'chromium')) return 'chromium';
        if (strpos($agent_string, 'opr/')) return 'opera';
        if (strpos($agent_string, 'chrome')) return 'chrome';
        if (strpos($agent_string, 'seamonkey')) return 'seamonkey';
        if (strpos($agent_string, 'firefox')) return 'firefox';
        if (strpos($agent_string, 'version/')) return 'safari';
        return '';
    }

}

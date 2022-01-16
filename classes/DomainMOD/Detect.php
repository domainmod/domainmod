<?php
/**
 * /classes/DomainMOD/Detect.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

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

} //@formatter:on

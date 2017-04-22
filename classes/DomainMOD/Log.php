<?php
/**
 * /classes/DomainMOD/Log.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

class Log
{

    public function goal($goal, $old_version, $new_version)
    {
        if ($goal == 'install') {
            $base_url = 'https://domainmod.org/installed/index.php?v=' . urlencode($new_version);
        } elseif ($goal == 'upgrade') {
            $base_url = 'https://domainmod.org/upgraded/index.php?ov=' . urlencode($old_version) . '&nv=' . urlencode($new_version);
        } else {
            return;
        }
        $ip_address = urlencode($_SERVER['SERVER_ADDR']);
        $user_agent = urlencode($_SERVER['HTTP_USER_AGENT']);
        $language = urlencode($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $log_url = $base_url . '&ip=' . $ip_address . '&a=' . $user_agent . '&l=' . $language;
        $context = stream_context_create(array('https' => array('header' => 'Connection: close\r\n')));
        $result = file_get_contents($log_url, false, $context);
        if (!$result) {
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_URL, $log_url);
            curl_exec($handle);
            curl_close($handle);
        }
        return;
    }

} //@formatter:on

<?php
/**
 * /classes/DomainMOD/HttpClient.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

use Composer\CaBundle\CaBundle;

/*
 * Shared settings for outbound HTTPS calls (registrar APIs, the Data Warehouse, and system lookups): certificates are
 * always verified, connections and transfers time out, and only HTTPS is allowed. CA certificates come from the
 * server's own setup when it has one, otherwise from the copy bundled with composer/ca-bundle.
 */
class HttpClient
{
    private static $ca_option = null;
    private static $bundle_path = null;
    private static $deadline = null;
    private static $timeouts = null; // each handle's own connect and total timeouts, so exec() can cap them again

    // A cURL handle for $url with verification, timeouts, and HTTPS-only set. Only the Data Warehouse passes $verify =
    // false (servers whose certificate checks the user has switched off) or $allow_http = true (servers set to http).
    public static function init($url, $connect_timeout = 10, $timeout = 30, $verify = true, $allow_http = false)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        self::handleTimeouts()[$handle] = array($connect_timeout, $timeout);
        self::applyTimeouts($handle);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS_STR, $allow_http ? 'http,https' : 'https');
        } else {
            curl_setopt($handle, CURLOPT_PROTOCOLS, $allow_http ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS);
        }

        if ($verify) {

            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

            list($option, $path) = self::caOption();
            if ($option === 'file') {
                curl_setopt($handle, CURLOPT_CAINFO, $path);
            } elseif ($option === 'dir') {
                curl_setopt($handle, CURLOPT_CAPATH, $path);
                // cURL still loads a CA file alongside the directory (curl.cainfo or its built-in default), and fails if
                // that file is missing, so point it at the bundled copy
                curl_setopt($handle, CURLOPT_CAINFO, CaBundle::getBundledCaBundlePath());
            }

        } else {

            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

        }

        return $handle;
    }

    // Runs the request and returns the body, or false on failure (exactly what curl_exec() returns, so callers' existing
    // checks still apply). Failures are logged with $label and the cURL error, never the URL, which can carry API keys.
    public static function exec($handle, $label)
    {
        // Once an overall deadline has passed, no new request starts. It's logged and returned as a timeout.
        $remaining = self::remaining();
        if ($remaining !== null && $remaining <= 0) {

            $log = new Log('class.httpclient');
            $log->error("A remote server didn't respond in time", array('Service' => $label, 'cURL Error' => 'The overall time limit had already passed'));
            return false;

        }

        // Set the handle's timeouts again, since a deadline may have come closer, been set, or been cleared since init().
        // A handle init() didn't make is left as it is.
        if (isset(self::handleTimeouts()[$handle])) self::applyTimeouts($handle);

        $result = curl_exec($handle);

        if ($result === false) {

            $errno = curl_errno($handle);
            $extra = array('Service' => $label, 'cURL Error' => $errno . ': ' . curl_error($handle));

            if (in_array($errno, array(35, 51, 58, 59, 60, 77, 83, 90, 91), true)) {
                $message = 'Unable to verify the SSL certificate of a remote server (see the Troubleshooting section of the User Guide)';
            } elseif ($errno === 28) {
                $message = "A remote server didn't respond in time";
            } else {
                $message = 'Unable to connect to a remote server';
            }

            $log = new Log('class.httpclient');
            $log->error($message, $extra);

        }

        return $result;
    }

    /*
     * An optional overall deadline (an absolute time, as microtime(true) returns) for every call made until it's cleared,
     * so a task that makes several calls, or waits and retries, can't run past it. Refreshing a domain sets one around
     * its registrar call. With none set, calls behave exactly as before.
     */
    public static function setDeadline($deadline)
    {
        self::$deadline = (float) $deadline;
    }

    public static function clearDeadline()
    {
        self::$deadline = null;
    }

    // The seconds left before the overall deadline, or null when none is set.
    public static function remaining()
    {
        return self::$deadline === null ? null : self::$deadline - microtime(true);
    }

    // Sets a handle's own timeouts, each capped to the whole seconds left before an overall deadline when one is set (at
    // least one second, since cURL treats 0 as no limit).
    private static function applyTimeouts($handle)
    {
        list($connect_timeout, $timeout) = self::handleTimeouts()[$handle];

        $remaining = self::remaining();
        if ($remaining !== null) {
            $seconds = max(1, (int) floor($remaining));
            $connect_timeout = min($connect_timeout, $seconds);
            $timeout = min($timeout, $seconds);
        }

        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
    }

    // A WeakMap, so an entry goes away with its handle.
    private static function handleTimeouts()
    {
        if (self::$timeouts === null) self::$timeouts = new \WeakMap();
        return self::$timeouts;
    }

    // SSL options for a PHP stream context (file_get_contents() on an https:// URL). Streams don't read curl.cainfo, so
    // this always uses composer/ca-bundle's choice (openssl.cafile/capath, then the system bundle, then its own copy).
    public static function streamSslOptions()
    {
        $options = array('verify_peer' => true, 'verify_peer_name' => true);

        $path = self::bundlePath();
        if (is_dir($path)) {
            $options['capath'] = $path;
        } else {
            $options['cafile'] = $path;
        }

        return $options;
    }

    // Which CA certificates to point cURL at: none when the administrator has set curl.cainfo (cURL uses it), otherwise
    // the system bundle composer/ca-bundle finds (honouring openssl.cafile and openssl.capath), falling back to its own
    // bundled copy. Returns array('file' | 'dir' | 'none', path), worked out once per request.
    private static function caOption()
    {
        if (self::$ca_option !== null) return self::$ca_option;

        $configured = (string) ini_get('curl.cainfo');
        if ($configured !== '' && is_file($configured) && is_readable($configured)) {
            return self::$ca_option = array('none', '');
        }

        $path = self::bundlePath();

        if (is_dir($path)) {
            return self::$ca_option = array('dir', $path);
        }

        return self::$ca_option = array('file', $path);
    }

    private static function bundlePath()
    {
        if (self::$bundle_path === null) self::$bundle_path = CaBundle::getSystemCaRootBundlePath();
        return self::$bundle_path;
    }

} //@formatter:on

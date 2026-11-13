<?php
/**
 * /classes/DomainMOD/Csrf.php
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

/*
 * Protects against cross-site request forgery: every form and every link that changes data carries a token that only
 * this session knows, and the request is refused without it. It doesn't need the database, so the installer can use
 * it before the database exists.
 */
class Csrf
{
    const FIELD = 'csrf_token';

    // The session's token, created the first time it's needed.
    public function getToken()
    {
        if (!isset($_SESSION['s_csrf_token']) || !is_string($_SESSION['s_csrf_token']) || $_SESSION['s_csrf_token'] === '') {

            $_SESSION['s_csrf_token'] = bin2hex(random_bytes(32));

        }

        return $_SESSION['s_csrf_token'];
    }

    // Replaces the token, such as when a user signs in.
    public function newToken()
    {
        $_SESSION['s_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['s_csrf_token'];
    }

    // Whether the request carries this session's token (from a form, or from a link for a GET request).
    public function isValid()
    {
        $submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? ($_POST[self::FIELD] ?? '') : ($_GET[self::FIELD] ?? '');
        $expected = $_SESSION['s_csrf_token'] ?? '';

        return is_string($submitted) && is_string($expected) && $expected !== '' && hash_equals($expected, $submitted);
    }

    // Refuses the request, with a message, unless it carries this session's token.
    public function check($redirect_url)
    {
        if ($this->isValid()) return;

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';

        if ($this->isOversizedPost()) {

            $_SESSION['s_message_danger'] .= _('The upload is larger than this server allows.') . '<BR>';

        } else {

            $_SESSION['s_message_danger'] .= _("Your request couldn't be verified. Please try again.") . '<BR>';

        }

        // Only once DEBUG_MODE is known (Log reads it). During installation it's off, so nothing is logged there.
        if (defined('DEBUG_MODE')) {

            $log = new Log('class.csrf');
            $log_message = 'Refused a request without a valid security token';
            $log_extra = array('Page' => strtok((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '?'), 'Method' => $_SERVER['REQUEST_METHOD'] ?? '');
            $log->warning($log_message, $log_extra);

        }

        header('Location: ' . $this->safeRedirect($redirect_url));
        exit;
    }

    // The hidden form field.
    public function field()
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    // The token as a query string parameter, for links that change data.
    public function query()
    {
        return self::FIELD . '=' . rawurlencode($this->getToken());
    }

    /*
     * A POST that PHP emptied because it was larger than post_max_size: no form fields or files, and a body bigger than
     * the limit.
     */
    protected function isOversizedPost()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !empty($_POST) || !empty($_FILES)) return false;

        $limit = $this->toBytes((string) ini_get('post_max_size'));

        return $limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit;
    }

    // A php.ini size such as 8M or 512K, in bytes.
    protected function toBytes($value)
    {
        $value = trim($value);
        if ($value === '') return 0;

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        if ($unit === 'g') {
            $number *= 1073741824;
        } elseif ($unit === 'm') {
            $number *= 1048576;
        } elseif ($unit === 'k') {
            $number *= 1024;
        }

        return (int) $number;
    }

    // Only a page on this site, never another host, and never one with the refused token still in it.
    protected function safeRedirect($redirect_url)
    {
        $redirect_url = (string) $redirect_url;
        $parts = parse_url($redirect_url);

        if ($redirect_url === '' || $parts === false || isset($parts['scheme']) || isset($parts['host'])
            || strpos($redirect_url, '//') === 0 || strpos($redirect_url, '\\') !== false) {

            $redirect_url = (defined('WEB_ROOT') ? WEB_ROOT : '') . '/';

        }

        return $this->removeToken($redirect_url);
    }

    /*
     * A URL without its csrf_token parameter, however it's written. Each parameter is read with PHP's own parser
     * (parse_str()), so any spelling PHP would accept as csrf_token is removed too.
     */
    public function removeToken($url)
    {
        $url = (string) $url;
        $fragment = '';

        if (($hash = strpos($url, '#')) !== false) {

            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);

        }

        if (($question = strpos($url, '?')) === false) return $url . $fragment;

        $kept = array();

        foreach (explode('&', substr($url, $question + 1)) as $pair) {

            if ($pair === '') continue;

            $parsed = array();
            parse_str($pair, $parsed);

            if (!array_key_exists(self::FIELD, $parsed)) $kept[] = $pair;

        }

        return substr($url, 0, $question) . ($kept ? '?' . implode('&', $kept) : '') . $fragment;
    }
} //@formatter:on

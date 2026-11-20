<?php
/**
 * /classes/DomainMOD/UserMenu.php
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
 * The values the top bar's user menu shows (_includes/layout/header.inc.php), worked out from the session, so the menu
 * adds no database reads to the page.
 */
class UserMenu
{
    /*
     * The user's initials for the circle beside their name, ready for output: the first letter of their first and last
     * names, in capitals. The names are stored HTML-escaped, so they're decoded first and the result is escaped again.
     * Returns '' when neither name has a letter to use.
     */
    public static function initials($first_name, $last_name)
    {
        $initials = '';

        foreach (array($first_name, $last_name) as $name) {

            $name = trim(htmlspecialchars_decode((string) $name, ENT_QUOTES));
            // The first character, read as UTF-8 without needing the mbstring extension (a name that isn't valid
            // UTF-8 is skipped)
            if ($name !== '' && preg_match('/^./u', $name, $match) === 1) $initials .= Format::toUpper($match[0]);

        }

        return htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
    }

    /*
     * The user's role, as one or more labels for the menu's badges: Administrator for an admin, Read-Only for a
     * read-only user, and User for anyone who's neither. An admin can also be read-only, and then gets both.
     * $is_admin and $read_only are the session's s_is_admin (1, or not set) and s_read_only ('1' or '0').
     */
    public static function roleLabels($is_admin, $read_only)
    {
        $labels = array();
        if ((int) $is_admin === 1) $labels[] = _('Administrator');
        if ((string) $read_only === '1') $labels[] = _('Read-Only');
        if ($labels === array()) $labels[] = _('User');

        return $labels;
    }

    /*
     * When the user last signed in before this sign-in, in their time zone: the date as the app shows it elsewhere, a
     * 12-hour time, and the zone's abbreviation (such as 2026-11-19 4:12pm PST), or '' if it was never set (a first
     * sign-in) or can't be read. $stored is the UTC time checks.php keeps in the session (s_previous_login).
     */
    public static function lastSignIn($stored)
    {
        if (!is_string($stored) || Time::isUnset($stored)) return '';

        try {

            return (new Time())->toUserTimezone($stored, 'Y-m-d g:ia T');

        } catch (\Exception $e) {

            return '';

        }
    }

} //@formatter:on

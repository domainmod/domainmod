<?php
/**
 * /classes/DomainMOD/Time.php
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

class Time
{
    /*
     * Older time zone names that DomainMOD offers, mapped to the zone each one now points to. Servers whose PHP uses the
     * system's time zone list (such as PHP 8.4 on newer Linux systems without the legacy time zone package) no longer
     * accept these names, so zone() swaps them for the current ones.
     */
    const LEGACY_ZONES = array(
        'Africa/Asmera' => 'Africa/Nairobi', 'America/Argentina/ComodRivadavia' => 'America/Argentina/Catamarca',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Catamarca' => 'America/Argentina/Catamarca', 'America/Cordoba' => 'America/Argentina/Cordoba',
        'America/Fort_Wayne' => 'America/Indiana/Indianapolis', 'America/Godthab' => 'America/Nuuk',
        'America/Indianapolis' => 'America/Indiana/Indianapolis', 'America/Jujuy' => 'America/Argentina/Jujuy',
        'America/Knox_IN' => 'America/Indiana/Knox', 'America/Louisville' => 'America/Kentucky/Louisville',
        'America/Mendoza' => 'America/Argentina/Mendoza', 'America/Rosario' => 'America/Argentina/Cordoba',
        'Antarctica/South_Pole' => 'Pacific/Auckland', 'Asia/Ashkhabad' => 'Asia/Ashgabat',
        'Asia/Calcutta' => 'Asia/Kolkata', 'Asia/Choibalsan' => 'Asia/Ulaanbaatar',
        'Asia/Chungking' => 'Asia/Shanghai', 'Asia/Dacca' => 'Asia/Dhaka', 'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Macao' => 'Asia/Macau', 'Asia/Rangoon' => 'Asia/Yangon', 'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Asia/Thimbu' => 'Asia/Thimphu', 'Asia/Ujung_Pandang' => 'Asia/Makassar',
        'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar', 'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Australia/ACT' => 'Australia/Sydney', 'Australia/LHI' => 'Australia/Lord_Howe',
        'Australia/NSW' => 'Australia/Sydney', 'Australia/North' => 'Australia/Darwin',
        'Australia/Queensland' => 'Australia/Brisbane', 'Australia/South' => 'Australia/Adelaide',
        'Australia/Tasmania' => 'Australia/Hobart', 'Australia/Victoria' => 'Australia/Melbourne',
        'Australia/West' => 'Australia/Perth', 'Brazil/Acre' => 'America/Rio_Branco',
        'Brazil/DeNoronha' => 'America/Noronha', 'Brazil/East' => 'America/Sao_Paulo',
        'Brazil/West' => 'America/Manaus', 'Canada/Atlantic' => 'America/Halifax',
        'Canada/Central' => 'America/Winnipeg', 'Canada/East-Saskatchewan' => 'America/Regina',
        'Canada/Eastern' => 'America/Toronto', 'Canada/Mountain' => 'America/Edmonton',
        'Canada/Newfoundland' => 'America/St_Johns', 'Canada/Pacific' => 'America/Vancouver',
        'Canada/Saskatchewan' => 'America/Regina', 'Canada/Yukon' => 'America/Whitehorse',
        'Chile/Continental' => 'America/Santiago', 'Chile/EasterIsland' => 'Pacific/Easter',
        'Cuba' => 'America/Havana', 'Egypt' => 'Africa/Cairo', 'Eire' => 'Europe/Dublin',
        'Europe/Kiev' => 'Europe/Kyiv', 'Europe/Uzhgorod' => 'Europe/Kyiv', 'Europe/Zaporozhye' => 'Europe/Kyiv',
        'Greenwich' => 'Etc/GMT', 'Hongkong' => 'Asia/Hong_Kong', 'Iceland' => 'Africa/Abidjan',
        'Iran' => 'Asia/Tehran', 'Israel' => 'Asia/Jerusalem', 'Jamaica' => 'America/Jamaica', 'Japan' => 'Asia/Tokyo',
        'Kwajalein' => 'Pacific/Kwajalein', 'Libya' => 'Africa/Tripoli', 'Mexico/BajaNorte' => 'America/Tijuana',
        'Mexico/BajaSur' => 'America/Mazatlan', 'Mexico/General' => 'America/Mexico_City',
        'Pacific/Enderbury' => 'Pacific/Kanton', 'Pacific/Ponape' => 'Pacific/Guadalcanal',
        'Pacific/Truk' => 'Pacific/Port_Moresby', 'Poland' => 'Europe/Warsaw', 'Portugal' => 'Europe/Lisbon',
        'Singapore' => 'Asia/Singapore', 'Turkey' => 'Europe/Istanbul', 'US/Alaska' => 'America/Anchorage',
        'US/Aleutian' => 'America/Adak', 'US/Arizona' => 'America/Phoenix', 'US/Central' => 'America/Chicago',
        'US/East-Indiana' => 'America/Indiana/Indianapolis', 'US/Eastern' => 'America/New_York',
        'US/Hawaii' => 'Pacific/Honolulu', 'US/Indiana-Starke' => 'America/Indiana/Knox',
        'US/Michigan' => 'America/Detroit', 'US/Mountain' => 'America/Denver', 'US/Pacific' => 'America/Los_Angeles',
        'US/Pacific-New' => 'America/Los_Angeles', 'US/Samoa' => 'Pacific/Pago_Pago', 'Zulu' => 'Etc/UTC');

    // The time zone as a DateTimeZone. An older name this server's PHP doesn't accept is swapped for the zone it now
    // points to, and anything else PHP rejects (or an empty name) becomes UTC, so a saved setting never stops a page
    public static function zone($timezone)
    {
        return self::findZone($timezone) ?? new \DateTimeZone('UTC');
    }

    // Whether a time zone name can be used, directly or through LEGACY_ZONES
    public static function isKnownZone($timezone)
    {
        return self::findZone($timezone) !== null;
    }

    // The time zone, directly or through LEGACY_ZONES, or null if PHP accepts neither name
    protected static function findZone($timezone)
    {
        $timezone = (string) $timezone;

        foreach (array($timezone, self::LEGACY_ZONES[$timezone] ?? '') as $name) {

            if ($name === '') continue;

            try {

                return new \DateTimeZone($name);

            } catch (\Exception | \ValueError $e) {

                // a name PHP rejects, or one it can't read at all (such as one with a null byte): try the next name

            }

        }

        return null;
    }

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

    public function toUserTimezone($input_time, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime($input_time, new \DateTimeZone('UTC'));
        $date->setTimezone(self::zone($_SESSION['s_default_timezone'] ?? 'UTC'));
        return $date->format($format);
    }

    public function toUtcTimezone($input_time, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime($input_time, self::zone($_SESSION['s_default_timezone'] ?? 'UTC'));
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format($format);
    }

} //@formatter:on

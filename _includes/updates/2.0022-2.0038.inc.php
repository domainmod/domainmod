<?php
/**
 * /_includes/updates/2.0022-2.0038.inc.php
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
?>
<?php //@formatter:off

// upgrade database from 2.0022 to 2.0023
if ($current_db_version === '2.0022') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `timezones` (
            `id` INT(5) NOT NULL AUTO_INCREMENT,
            `timezone` VARCHAR(50) NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO `timezones`
        (`timezone`, `insert_time`)
        VALUES
        ('Africa/Abidjan', '" . $timestamp . "'), ('Africa/Accra', '" . $timestamp . "'), ('Africa/Addis_Ababa', '" . $timestamp . "'), ('Africa/Algiers', '" . $timestamp . "'), ('Africa/Asmara', '" . $timestamp . "'), ('Africa/Asmera', '" . $timestamp . "'), ('Africa/Bamako', '" . $timestamp . "'), ('Africa/Bangui', '" . $timestamp . "'), ('Africa/Banjul', '" . $timestamp . "'), ('Africa/Bissau', '" . $timestamp . "'), ('Africa/Blantyre', '" . $timestamp . "'), ('Africa/Brazzaville', '" . $timestamp . "'), ('Africa/Bujumbura', '" . $timestamp . "'), ('Africa/Cairo', '" . $timestamp . "'), ('Africa/Casablanca', '" . $timestamp . "'), ('Africa/Ceuta', '" . $timestamp . "'), ('Africa/Conakry', '" . $timestamp . "'), ('Africa/Dakar', '" . $timestamp . "'), ('Africa/Dar_es_Salaam', '" . $timestamp . "'), ('Africa/Djibouti', '" . $timestamp . "'), ('Africa/Douala', '" . $timestamp . "'), ('Africa/El_Aaiun', '" . $timestamp . "'), ('Africa/Freetown', '" . $timestamp . "'), ('Africa/Gaborone', '" . $timestamp . "'), ('Africa/Harare', '" . $timestamp . "'), ('Africa/Johannesburg', '" . $timestamp . "'), ('Africa/Juba', '" . $timestamp . "'), ('Africa/Kampala', '" . $timestamp . "'), ('Africa/Khartoum', '" . $timestamp . "'), ('Africa/Kigali', '" . $timestamp . "'), ('Africa/Kinshasa', '" . $timestamp . "'), ('Africa/Lagos', '" . $timestamp . "'), ('Africa/Libreville', '" . $timestamp . "'), ('Africa/Lome', '" . $timestamp . "'), ('Africa/Luanda', '" . $timestamp . "'), ('Africa/Lubumbashi', '" . $timestamp . "'), ('Africa/Lusaka', '" . $timestamp . "'), ('Africa/Malabo', '" . $timestamp . "'), ('Africa/Maputo', '" . $timestamp . "'), ('Africa/Maseru', '" . $timestamp . "'), ('Africa/Mbabane', '" . $timestamp . "'), ('Africa/Mogadishu', '" . $timestamp . "'), ('Africa/Monrovia', '" . $timestamp . "'), ('Africa/Nairobi', '" . $timestamp . "'), ('Africa/Ndjamena', '" . $timestamp . "'), ('Africa/Niamey', '" . $timestamp . "'), ('Africa/Nouakchott', '" . $timestamp . "'), ('Africa/Ouagadougou', '" . $timestamp . "'), ('Africa/Porto-Novo', '" . $timestamp . "'), ('Africa/Sao_Tome', '" . $timestamp . "'), ('Africa/Timbuktu', '" . $timestamp . "'), ('Africa/Tripoli', '" . $timestamp . "'), ('Africa/Tunis', '" . $timestamp . "'), ('Africa/Windhoek', '" . $timestamp . "'), ('America/Adak', '" . $timestamp . "'), ('America/Anchorage', '" . $timestamp . "'), ('America/Anguilla', '" . $timestamp . "'), ('America/Antigua', '" . $timestamp . "'), ('America/Araguaina', '" . $timestamp . "'), ('America/Argentina/Buenos_Aires', '" . $timestamp . "'), ('America/Argentina/Catamarca', '" . $timestamp . "'), ('America/Argentina/ComodRivadavia', '" . $timestamp . "'), ('America/Argentina/Cordoba', '" . $timestamp . "'), ('America/Argentina/Jujuy', '" . $timestamp . "'), ('America/Argentina/La_Rioja', '" . $timestamp . "'), ('America/Argentina/Mendoza', '" . $timestamp . "'), ('America/Argentina/Rio_Gallegos', '" . $timestamp . "'), ('America/Argentina/Salta', '" . $timestamp . "'), ('America/Argentina/San_Juan', '" . $timestamp . "'), ('America/Argentina/San_Luis', '" . $timestamp . "'), ('America/Argentina/Tucuman', '" . $timestamp . "'), ('America/Argentina/Ushuaia', '" . $timestamp . "'), ('America/Aruba', '" . $timestamp . "'), ('America/Asuncion', '" . $timestamp . "'), ('America/Atikokan', '" . $timestamp . "'), ('America/Atka', '" . $timestamp . "'), ('America/Bahia', '" . $timestamp . "'), ('America/Bahia_Banderas', '" . $timestamp . "'), ('America/Barbados', '" . $timestamp . "'), ('America/Belem', '" . $timestamp . "'), ('America/Belize', '" . $timestamp . "'), ('America/Blanc-Sablon', '" . $timestamp . "'), ('America/Boa_Vista', '" . $timestamp . "'), ('America/Bogota', '" . $timestamp . "'), ('America/Boise', '" . $timestamp . "'), ('America/Buenos_Aires', '" . $timestamp . "'), ('America/Cambridge_Bay', '" . $timestamp . "'), ('America/Campo_Grande', '" . $timestamp . "'), ('America/Cancun', '" . $timestamp . "'), ('America/Caracas', '" . $timestamp . "'), ('America/Catamarca', '" . $timestamp . "'), ('America/Cayenne', '" . $timestamp . "'), ('America/Cayman', '" . $timestamp . "'), ('America/Chicago', '" . $timestamp . "'), ('America/Chihuahua', '" . $timestamp . "'), ('America/Coral_Harbour', '" . $timestamp . "'), ('America/Cordoba', '" . $timestamp . "'), ('America/Costa_Rica', '" . $timestamp . "'), ('America/Creston', '" . $timestamp . "'), ('America/Cuiaba', '" . $timestamp . "'), ('America/Curacao', '" . $timestamp . "'), ('America/Danmarkshavn', '" . $timestamp . "'), ('America/Dawson', '" . $timestamp . "'), ('America/Dawson_Creek', '" . $timestamp . "'), ('America/Denver', '" . $timestamp . "'), ('America/Detroit', '" . $timestamp . "'), ('America/Dominica', '" . $timestamp . "'), ('America/Edmonton', '" . $timestamp . "'), ('America/Eirunepe', '" . $timestamp . "'), ('America/El_Salvador', '" . $timestamp . "'), ('America/Ensenada', '" . $timestamp . "'), ('America/Fort_Wayne', '" . $timestamp . "'), ('America/Fortaleza', '" . $timestamp . "'), ('America/Glace_Bay', '" . $timestamp . "'), ('America/Godthab', '" . $timestamp . "'), ('America/Goose_Bay', '" . $timestamp . "'), ('America/Grand_Turk', '" . $timestamp . "'), ('America/Grenada', '" . $timestamp . "'), ('America/Guadeloupe', '" . $timestamp . "'), ('America/Guatemala', '" . $timestamp . "'), ('America/Guayaquil', '" . $timestamp . "'), ('America/Guyana', '" . $timestamp . "'), ('America/Halifax', '" . $timestamp . "'), ('America/Havana', '" . $timestamp . "'), ('America/Hermosillo', '" . $timestamp . "'), ('America/Indiana/Indianapolis', '" . $timestamp . "'), ('America/Indiana/Knox', '" . $timestamp . "'), ('America/Indiana/Marengo', '" . $timestamp . "'), ('America/Indiana/Petersburg', '" . $timestamp . "'), ('America/Indiana/Tell_City', '" . $timestamp . "'), ('America/Indiana/Vevay', '" . $timestamp . "'), ('America/Indiana/Vincennes', '" . $timestamp . "'), ('America/Indiana/Winamac', '" . $timestamp . "'), ('America/Indianapolis', '" . $timestamp . "'), ('America/Inuvik', '" . $timestamp . "'), ('America/Iqaluit', '" . $timestamp . "'), ('America/Jamaica', '" . $timestamp . "'), ('America/Jujuy', '" . $timestamp . "'), ('America/Juneau', '" . $timestamp . "'), ('America/Kentucky/Louisville', '" . $timestamp . "'), ('America/Kentucky/Monticello', '" . $timestamp . "'), ('America/Knox_IN', '" . $timestamp . "'), ('America/Kralendijk', '" . $timestamp . "'), ('America/La_Paz', '" . $timestamp . "'), ('America/Lima', '" . $timestamp . "'), ('America/Los_Angeles', '" . $timestamp . "'), ('America/Louisville', '" . $timestamp . "'), ('America/Lower_Princes', '" . $timestamp . "'), ('America/Maceio', '" . $timestamp . "'), ('America/Managua', '" . $timestamp . "'), ('America/Manaus', '" . $timestamp . "'), ('America/Marigot', '" . $timestamp . "'), ('America/Martinique', '" . $timestamp . "'), ('America/Matamoros', '" . $timestamp . "'), ('America/Mazatlan', '" . $timestamp . "'), ('America/Mendoza', '" . $timestamp . "'), ('America/Menominee', '" . $timestamp . "'), ('America/Merida', '" . $timestamp . "'), ('America/Metlakatla', '" . $timestamp . "'), ('America/Mexico_City', '" . $timestamp . "'), ('America/Miquelon', '" . $timestamp . "'), ('America/Moncton', '" . $timestamp . "'), ('America/Monterrey', '" . $timestamp . "'), ('America/Montevideo', '" . $timestamp . "'), ('America/Montreal', '" . $timestamp . "'), ('America/Montserrat', '" . $timestamp . "'), ('America/Nassau', '" . $timestamp . "'), ('America/New_York', '" . $timestamp . "'), ('America/Nipigon', '" . $timestamp . "'), ('America/Nome', '" . $timestamp . "'), ('America/Noronha', '" . $timestamp . "'), ('America/North_Dakota/Beulah', '" . $timestamp . "'), ('America/North_Dakota/Center', '" . $timestamp . "'), ('America/North_Dakota/New_Salem', '" . $timestamp . "'), ('America/Ojinaga', '" . $timestamp . "'), ('America/Panama', '" . $timestamp . "'), ('America/Pangnirtung', '" . $timestamp . "'), ('America/Paramaribo', '" . $timestamp . "'), ('America/Phoenix', '" . $timestamp . "'), ('America/Port-au-Prince', '" . $timestamp . "'), ('America/Port_of_Spain', '" . $timestamp . "'), ('America/Porto_Acre', '" . $timestamp . "'), ('America/Porto_Velho', '" . $timestamp . "'), ('America/Puerto_Rico', '" . $timestamp . "'), ('America/Rainy_River', '" . $timestamp . "'), ('America/Rankin_Inlet', '" . $timestamp . "'), ('America/Recife', '" . $timestamp . "'), ('America/Regina', '" . $timestamp . "'), ('America/Resolute', '" . $timestamp . "'), ('America/Rio_Branco', '" . $timestamp . "'), ('America/Rosario', '" . $timestamp . "'), ('America/Santa_Isabel', '" . $timestamp . "'), ('America/Santarem', '" . $timestamp . "'), ('America/Santiago', '" . $timestamp . "'), ('America/Santo_Domingo', '" . $timestamp . "'), ('America/Sao_Paulo', '" . $timestamp . "'), ('America/Scoresbysund', '" . $timestamp . "'), ('America/Shiprock', '" . $timestamp . "'), ('America/Sitka', '" . $timestamp . "'), ('America/St_Barthelemy', '" . $timestamp . "'), ('America/St_Johns', '" . $timestamp . "'), ('America/St_Kitts', '" . $timestamp . "'), ('America/St_Lucia', '" . $timestamp . "'), ('America/St_Thomas', '" . $timestamp . "'), ('America/St_Vincent', '" . $timestamp . "'), ('America/Swift_Current', '" . $timestamp . "'), ('America/Tegucigalpa', '" . $timestamp . "'), ('America/Thule', '" . $timestamp . "'), ('America/Thunder_Bay', '" . $timestamp . "'), ('America/Tijuana', '" . $timestamp . "'), ('America/Toronto', '" . $timestamp . "'), ('America/Tortola', '" . $timestamp . "'), ('America/Vancouver', '" . $timestamp . "'), ('America/Virgin', '" . $timestamp . "'), ('America/Whitehorse', '" . $timestamp . "'), ('America/Winnipeg', '" . $timestamp . "'), ('America/Yakutat', '" . $timestamp . "'), ('America/Yellowknife', '" . $timestamp . "'), ('Antarctica/Casey', '" . $timestamp . "'), ('Antarctica/Davis', '" . $timestamp . "'), ('Antarctica/DumontDUrville', '" . $timestamp . "'), ('Antarctica/Macquarie', '" . $timestamp . "'), ('Antarctica/Mawson', '" . $timestamp . "'), ('Antarctica/McMurdo', '" . $timestamp . "'), ('Antarctica/Palmer', '" . $timestamp . "'), ('Antarctica/Rothera', '" . $timestamp . "'), ('Antarctica/South_Pole', '" . $timestamp . "'), ('Antarctica/Syowa', '" . $timestamp . "'), ('Antarctica/Vostok', '" . $timestamp . "'), ('Arctic/Longyearbyen', '" . $timestamp . "'), ('Asia/Aden', '" . $timestamp . "'), ('Asia/Almaty', '" . $timestamp . "'), ('Asia/Amman', '" . $timestamp . "'), ('Asia/Anadyr', '" . $timestamp . "'), ('Asia/Aqtau', '" . $timestamp . "'), ('Asia/Aqtobe', '" . $timestamp . "'), ('Asia/Ashgabat', '" . $timestamp . "'), ('Asia/Ashkhabad', '" . $timestamp . "'), ('Asia/Baghdad', '" . $timestamp . "'), ('Asia/Bahrain', '" . $timestamp . "'), ('Asia/Baku', '" . $timestamp . "'), ('Asia/Bangkok', '" . $timestamp . "'), ('Asia/Beirut', '" . $timestamp . "'), ('Asia/Bishkek', '" . $timestamp . "'), ('Asia/Brunei', '" . $timestamp . "'), ('Asia/Calcutta', '" . $timestamp . "'), ('Asia/Choibalsan', '" . $timestamp . "'), ('Asia/Chongqing', '" . $timestamp . "'), ('Asia/Chungking', '" . $timestamp . "'), ('Asia/Colombo', '" . $timestamp . "'), ('Asia/Dacca', '" . $timestamp . "'), ('Asia/Damascus', '" . $timestamp . "'), ('Asia/Dhaka', '" . $timestamp . "'), ('Asia/Dili', '" . $timestamp . "'), ('Asia/Dubai', '" . $timestamp . "'), ('Asia/Dushanbe', '" . $timestamp . "'), ('Asia/Gaza', '" . $timestamp . "'), ('Asia/Harbin', '" . $timestamp . "'), ('Asia/Hebron', '" . $timestamp . "'), ('Asia/Ho_Chi_Minh', '" . $timestamp . "'), ('Asia/Hong_Kong', '" . $timestamp . "'), ('Asia/Hovd', '" . $timestamp . "'), ('Asia/Irkutsk', '" . $timestamp . "'), ('Asia/Istanbul', '" . $timestamp . "'), ('Asia/Jakarta', '" . $timestamp . "'), ('Asia/Jayapura', '" . $timestamp . "'), ('Asia/Jerusalem', '" . $timestamp . "'), ('Asia/Kabul', '" . $timestamp . "'), ('Asia/Kamchatka', '" . $timestamp . "'), ('Asia/Karachi', '" . $timestamp . "'), ('Asia/Kashgar', '" . $timestamp . "'), ('Asia/Kathmandu', '" . $timestamp . "'), ('Asia/Katmandu', '" . $timestamp . "'), ('Asia/Khandyga', '" . $timestamp . "'), ('Asia/Kolkata', '" . $timestamp . "'), ('Asia/Krasnoyarsk', '" . $timestamp . "'), ('Asia/Kuala_Lumpur', '" . $timestamp . "'), ('Asia/Kuching', '" . $timestamp . "'), ('Asia/Kuwait', '" . $timestamp . "'), ('Asia/Macao', '" . $timestamp . "'), ('Asia/Macau', '" . $timestamp . "'), ('Asia/Magadan', '" . $timestamp . "'), ('Asia/Makassar', '" . $timestamp . "'), ('Asia/Manila', '" . $timestamp . "'), ('Asia/Muscat', '" . $timestamp . "'), ('Asia/Nicosia', '" . $timestamp . "'), ('Asia/Novokuznetsk', '" . $timestamp . "'), ('Asia/Novosibirsk', '" . $timestamp . "'), ('Asia/Omsk', '" . $timestamp . "'), ('Asia/Oral', '" . $timestamp . "'), ('Asia/Phnom_Penh', '" . $timestamp . "'), ('Asia/Pontianak', '" . $timestamp . "'), ('Asia/Pyongyang', '" . $timestamp . "'), ('Asia/Qatar', '" . $timestamp . "'), ('Asia/Qyzylorda', '" . $timestamp . "'), ('Asia/Rangoon', '" . $timestamp . "'), ('Asia/Riyadh', '" . $timestamp . "'), ('Asia/Saigon', '" . $timestamp . "'), ('Asia/Sakhalin', '" . $timestamp . "'), ('Asia/Samarkand', '" . $timestamp . "'), ('Asia/Seoul', '" . $timestamp . "'), ('Asia/Shanghai', '" . $timestamp . "'), ('Asia/Singapore', '" . $timestamp . "'), ('Asia/Taipei', '" . $timestamp . "'), ('Asia/Tashkent', '" . $timestamp . "'), ('Asia/Tbilisi', '" . $timestamp . "'), ('Asia/Tehran', '" . $timestamp . "'), ('Asia/Tel_Aviv', '" . $timestamp . "'), ('Asia/Thimbu', '" . $timestamp . "'), ('Asia/Thimphu', '" . $timestamp . "'), ('Asia/Tokyo', '" . $timestamp . "'), ('Asia/Ujung_Pandang', '" . $timestamp . "'), ('Asia/Ulaanbaatar', '" . $timestamp . "'), ('Asia/Ulan_Bator', '" . $timestamp . "'), ('Asia/Urumqi', '" . $timestamp . "'), ('Asia/Ust-Nera', '" . $timestamp . "'), ('Asia/Vientiane', '" . $timestamp . "'), ('Asia/Vladivostok', '" . $timestamp . "'), ('Asia/Yakutsk', '" . $timestamp . "'), ('Asia/Yekaterinburg', '" . $timestamp . "'), ('Asia/Yerevan', '" . $timestamp . "'), ('Atlantic/Azores', '" . $timestamp . "'), ('Atlantic/Bermuda', '" . $timestamp . "'), ('Atlantic/Canary', '" . $timestamp . "'), ('Atlantic/Cape_Verde', '" . $timestamp . "'), ('Atlantic/Faeroe', '" . $timestamp . "'), ('Atlantic/Faroe', '" . $timestamp . "'), ('Atlantic/Jan_Mayen', '" . $timestamp . "'), ('Atlantic/Madeira', '" . $timestamp . "'), ('Atlantic/Reykjavik', '" . $timestamp . "'), ('Atlantic/South_Georgia', '" . $timestamp . "'), ('Atlantic/St_Helena', '" . $timestamp . "'), ('Atlantic/Stanley', '" . $timestamp . "'), ('Australia/ACT', '" . $timestamp . "'), ('Australia/Adelaide', '" . $timestamp . "'), ('Australia/Brisbane', '" . $timestamp . "'), ('Australia/Broken_Hill', '" . $timestamp . "'), ('Australia/Canberra', '" . $timestamp . "'), ('Australia/Currie', '" . $timestamp . "'), ('Australia/Darwin', '" . $timestamp . "'), ('Australia/Eucla', '" . $timestamp . "'), ('Australia/Hobart', '" . $timestamp . "'), ('Australia/LHI', '" . $timestamp . "'), ('Australia/Lindeman', '" . $timestamp . "'), ('Australia/Lord_Howe', '" . $timestamp . "'), ('Australia/Melbourne', '" . $timestamp . "'), ('Australia/North', '" . $timestamp . "'), ('Australia/NSW', '" . $timestamp . "'), ('Australia/Perth', '" . $timestamp . "'), ('Australia/Queensland', '" . $timestamp . "'), ('Australia/South', '" . $timestamp . "'), ('Australia/Sydney', '" . $timestamp . "'), ('Australia/Tasmania', '" . $timestamp . "'), ('Australia/Victoria', '" . $timestamp . "'), ('Australia/West', '" . $timestamp . "'), ('Australia/Yancowinna', '" . $timestamp . "'), ('Brazil/Acre', '" . $timestamp . "'), ('Brazil/DeNoronha', '" . $timestamp . "'), ('Brazil/East', '" . $timestamp . "'), ('Brazil/West', '" . $timestamp . "'), ('Canada/Atlantic', '" . $timestamp . "'), ('Canada/Central', '" . $timestamp . "'), ('Canada/East-Saskatchewan', '" . $timestamp . "'), ('Canada/Eastern', '" . $timestamp . "'), ('Canada/Mountain', '" . $timestamp . "'), ('Canada/Newfoundland', '" . $timestamp . "'), ('Canada/Pacific', '" . $timestamp . "'), ('Canada/Saskatchewan', '" . $timestamp . "'), ('Canada/Yukon', '" . $timestamp . "'), ('Chile/Continental', '" . $timestamp . "'), ('Chile/EasterIsland', '" . $timestamp . "'), ('Cuba', '" . $timestamp . "'), ('Egypt', '" . $timestamp . "'), ('Eire', '" . $timestamp . "'), ('Europe/Amsterdam', '" . $timestamp . "'), ('Europe/Andorra', '" . $timestamp . "'), ('Europe/Athens', '" . $timestamp . "'), ('Europe/Belfast', '" . $timestamp . "'), ('Europe/Belgrade', '" . $timestamp . "'), ('Europe/Berlin', '" . $timestamp . "'), ('Europe/Bratislava', '" . $timestamp . "'), ('Europe/Brussels', '" . $timestamp . "'), ('Europe/Bucharest', '" . $timestamp . "'), ('Europe/Budapest', '" . $timestamp . "'), ('Europe/Busingen', '" . $timestamp . "'), ('Europe/Chisinau', '" . $timestamp . "'), ('Europe/Copenhagen', '" . $timestamp . "'), ('Europe/Dublin', '" . $timestamp . "'), ('Europe/Gibraltar', '" . $timestamp . "'), ('Europe/Guernsey', '" . $timestamp . "'), ('Europe/Helsinki', '" . $timestamp . "'), ('Europe/Isle_of_Man', '" . $timestamp . "'), ('Europe/Istanbul', '" . $timestamp . "'), ('Europe/Jersey', '" . $timestamp . "'), ('Europe/Kaliningrad', '" . $timestamp . "'), ('Europe/Kiev', '" . $timestamp . "'), ('Europe/Lisbon', '" . $timestamp . "'), ('Europe/Ljubljana', '" . $timestamp . "'), ('Europe/London', '" . $timestamp . "'), ('Europe/Luxembourg', '" . $timestamp . "'), ('Europe/Madrid', '" . $timestamp . "'), ('Europe/Malta', '" . $timestamp . "'), ('Europe/Mariehamn', '" . $timestamp . "'), ('Europe/Minsk', '" . $timestamp . "'), ('Europe/Monaco', '" . $timestamp . "'), ('Europe/Moscow', '" . $timestamp . "'), ('Europe/Nicosia', '" . $timestamp . "'), ('Europe/Oslo', '" . $timestamp . "'), ('Europe/Paris', '" . $timestamp . "'), ('Europe/Podgorica', '" . $timestamp . "'), ('Europe/Prague', '" . $timestamp . "'), ('Europe/Riga', '" . $timestamp . "'), ('Europe/Rome', '" . $timestamp . "'), ('Europe/Samara', '" . $timestamp . "'), ('Europe/San_Marino', '" . $timestamp . "'), ('Europe/Sarajevo', '" . $timestamp . "'), ('Europe/Simferopol', '" . $timestamp . "'), ('Europe/Skopje', '" . $timestamp . "'), ('Europe/Sofia', '" . $timestamp . "'), ('Europe/Stockholm', '" . $timestamp . "'), ('Europe/Tallinn', '" . $timestamp . "'), ('Europe/Tirane', '" . $timestamp . "'), ('Europe/Tiraspol', '" . $timestamp . "'), ('Europe/Uzhgorod', '" . $timestamp . "'), ('Europe/Vaduz', '" . $timestamp . "'), ('Europe/Vatican', '" . $timestamp . "'), ('Europe/Vienna', '" . $timestamp . "'), ('Europe/Vilnius', '" . $timestamp . "'), ('Europe/Volgograd', '" . $timestamp . "'), ('Europe/Warsaw', '" . $timestamp . "'), ('Europe/Zagreb', '" . $timestamp . "'), ('Europe/Zaporozhye', '" . $timestamp . "'), ('Europe/Zurich', '" . $timestamp . "'), ('Greenwich', '" . $timestamp . "'), ('Hongkong', '" . $timestamp . "'), ('Iceland', '" . $timestamp . "'), ('Indian/Antananarivo', '" . $timestamp . "'), ('Indian/Chagos', '" . $timestamp . "'), ('Indian/Christmas', '" . $timestamp . "'), ('Indian/Cocos', '" . $timestamp . "'), ('Indian/Comoro', '" . $timestamp . "'), ('Indian/Kerguelen', '" . $timestamp . "'), ('Indian/Mahe', '" . $timestamp . "'), ('Indian/Maldives', '" . $timestamp . "'), ('Indian/Mauritius', '" . $timestamp . "'), ('Indian/Mayotte', '" . $timestamp . "'), ('Indian/Reunion', '" . $timestamp . "'), ('Iran', '" . $timestamp . "'), ('Israel', '" . $timestamp . "'), ('Jamaica', '" . $timestamp . "'), ('Japan', '" . $timestamp . "'), ('Kwajalein', '" . $timestamp . "'), ('Libya', '" . $timestamp . "'), ('Mexico/BajaNorte', '" . $timestamp . "'), ('Mexico/BajaSur', '" . $timestamp . "'), ('Mexico/General', '" . $timestamp . "'), ('Pacific/Apia', '" . $timestamp . "'), ('Pacific/Auckland', '" . $timestamp . "'), ('Pacific/Chatham', '" . $timestamp . "'), ('Pacific/Chuuk', '" . $timestamp . "'), ('Pacific/Easter', '" . $timestamp . "'), ('Pacific/Efate', '" . $timestamp . "'), ('Pacific/Enderbury', '" . $timestamp . "'), ('Pacific/Fakaofo', '" . $timestamp . "'), ('Pacific/Fiji', '" . $timestamp . "'), ('Pacific/Funafuti', '" . $timestamp . "'), ('Pacific/Galapagos', '" . $timestamp . "'), ('Pacific/Gambier', '" . $timestamp . "'), ('Pacific/Guadalcanal', '" . $timestamp . "'), ('Pacific/Guam', '" . $timestamp . "'), ('Pacific/Honolulu', '" . $timestamp . "'), ('Pacific/Johnston', '" . $timestamp . "'), ('Pacific/Kiritimati', '" . $timestamp . "'), ('Pacific/Kosrae', '" . $timestamp . "'), ('Pacific/Kwajalein', '" . $timestamp . "'), ('Pacific/Majuro', '" . $timestamp . "'), ('Pacific/Marquesas', '" . $timestamp . "'), ('Pacific/Midway', '" . $timestamp . "'), ('Pacific/Nauru', '" . $timestamp . "'), ('Pacific/Niue', '" . $timestamp . "'), ('Pacific/Norfolk', '" . $timestamp . "'), ('Pacific/Noumea', '" . $timestamp . "'), ('Pacific/Pago_Pago', '" . $timestamp . "'), ('Pacific/Palau', '" . $timestamp . "'), ('Pacific/Pitcairn', '" . $timestamp . "'), ('Pacific/Pohnpei', '" . $timestamp . "'), ('Pacific/Ponape', '" . $timestamp . "'), ('Pacific/Port_Moresby', '" . $timestamp . "'), ('Pacific/Rarotonga', '" . $timestamp . "'), ('Pacific/Saipan', '" . $timestamp . "'), ('Pacific/Samoa', '" . $timestamp . "'), ('Pacific/Tahiti', '" . $timestamp . "'), ('Pacific/Tarawa', '" . $timestamp . "'), ('Pacific/Tongatapu', '" . $timestamp . "'), ('Pacific/Truk', '" . $timestamp . "'), ('Pacific/Wake', '" . $timestamp . "'), ('Pacific/Wallis', '" . $timestamp . "'), ('Pacific/Yap', '" . $timestamp . "'), ('Poland', '" . $timestamp . "'), ('Portugal', '" . $timestamp . "'), ('Singapore', '" . $timestamp . "'), ('Turkey', '" . $timestamp . "'), ('US/Alaska', '" . $timestamp . "'), ('US/Aleutian', '" . $timestamp . "'), ('US/Arizona', '" . $timestamp . "'), ('US/Central', '" . $timestamp . "'), ('US/East-Indiana', '" . $timestamp . "'), ('US/Eastern', '" . $timestamp . "'), ('US/Hawaii', '" . $timestamp . "'), ('US/Indiana-Starke', '" . $timestamp . "'), ('US/Michigan', '" . $timestamp . "'), ('US/Mountain', '" . $timestamp . "'), ('US/Pacific', '" . $timestamp . "'), ('US/Pacific-New', '" . $timestamp . "'), ('US/Samoa', '" . $timestamp . "'), ('Zulu', '" . $timestamp . "')");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0023',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0023';

}

// upgrade database from 2.0023 to 2.0024
if ($current_db_version === '2.0023') {

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0024',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0024';

}

// upgrade database from 2.0024 to 2.0025
if ($current_db_version === '2.0024') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `notes` LONGTEXT NOT NULL,
            `default_host` INT(1) NOT NULL DEFAULT '0',
            `active` INT(1) NOT NULL DEFAULT '1',
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO `hosting`
        (`name`, `default_host`, `insert_time`)
        VALUES
        ('[no hosting]', 1, '" . $timestamp . "')");

    $pdo->query("
        ALTER TABLE `domains`
        ADD `hosting_id` INT(10) NOT NULL DEFAULT '1' AFTER `ip_id`");

    $temp_hosting_id = $pdo->query("
            SELECT id
            FROM hosting
            WHERE name = '[no hosting]'")->fetchColumn();

    $pdo->query("
        UPDATE domains
        SET hosting_id = '" . $temp_hosting_id . "',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `owner_id` `owner_id` INT(5) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `registrar_id` `registrar_id` INT(5) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `account_id` `account_id` INT(5) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `dns_id` `dns_id` INT(5) NOT NULL DEFAULT '1'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0025',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0025';

}

// upgrade database from 2.0025 to 2.0026
if ($current_db_version === '2.0025') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_domain_host` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_dns`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0026',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0026';

}

// upgrade database from 2.0026 to 2.0027
if ($current_db_version === '2.0026') {

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        ADD `password` VARCHAR(100) NOT NULL AFTER `username`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0027',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0027';

}

// upgrade database from 2.0027 to 2.0028
if ($current_db_version === '2.0027') {

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        ADD `password` VARCHAR(100) NOT NULL AFTER `username`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0028',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0028';

}

// upgrade database from 2.0028 to 2.0029
if ($current_db_version === '2.0028') {

    $pdo->query("
        ALTER TABLE `dns`
        ADD `ip1` VARCHAR(255) NOT NULL AFTER `dns10`,
        ADD `ip2` VARCHAR(255) NOT NULL AFTER `ip1`,
        ADD `ip3` VARCHAR(255) NOT NULL AFTER `ip2`,
        ADD `ip4` VARCHAR(255) NOT NULL AFTER `ip3`,
        ADD `ip5` VARCHAR(255) NOT NULL AFTER `ip4`,
        ADD `ip6` VARCHAR(255) NOT NULL AFTER `ip5`,
        ADD `ip7` VARCHAR(255) NOT NULL AFTER `ip6`,
        ADD `ip8` VARCHAR(255) NOT NULL AFTER `ip7`,
        ADD `ip9` VARCHAR(255) NOT NULL AFTER `ip8`,
        ADD `ip10` VARCHAR(255) NOT NULL AFTER `ip9`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `expiration_email_days` INT(3) NOT NULL DEFAULT '60' AFTER `timezone`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0029',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0029';

}

// upgrade database from 2.0029 to 2.003
if ($current_db_version === '2.0029') {

    $pdo->query("
        ALTER TABLE `domains`
        ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $result = $pdo->query("
        SELECT id, `status`, status_notes, notes
        FROM domains")->fetchAll();

    foreach ($result as $row) {

        if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

            $full_status = "";
            $full_status_notes = "";
            $new_notes = "";

            if ($row->status != "") {

                $full_status .= "--------------------\r\n";
                $full_status .= "OLD STATUS - INSERTED " . $timestamp . "\r\n";
                $full_status .= "The Status field was removed because it was redundant.\r\n";
                $full_status .= "--------------------\r\n";
                $full_status .= $row->status . "\r\n";
                $full_status .= "--------------------";

            } else {

                $full_status = "";

            }

            if ($row->status_notes != "") {

                $full_status_notes .= "--------------------\r\n";
                $full_status_notes .= "OLD STATUS NOTES - INSERTED " . $timestamp . "\r\n";
                $full_status_notes .= "The Status Notes field was removed because it was redundant.\r\n";
                $full_status_notes .= "--------------------\r\n";
                $full_status_notes .= $row->status_notes . "\r\n";
                $full_status_notes .= "--------------------";

            } else {

                $full_status_notes = "";

            }

            if ($row->notes != "") {

                if ($full_status != "" && $full_status_notes != "") {

                    $new_notes = $full_status . "\r\n\r\n" . $full_status_notes . "\r\n\r\n" . $row->notes;

                } elseif ($full_status != "" && $full_status_notes == "") {

                    $new_notes = $full_status . "\r\n\r\n" . $row->notes;

                } elseif ($full_status == "" && $full_status_notes != "") {

                    $new_notes = $full_status_notes . "\r\n\r\n" . $row->notes;

                } elseif ($full_status == "" && $full_status_notes == "") {

                    $new_notes = $row->notes;

                }

            } elseif ($row->notes == "") {

                if ($full_status != "" && $full_status_notes != "") {

                    $new_notes = $full_status . "\r\n\r\n" . $full_status_notes;

                } elseif ($full_status != "" && $full_status_notes == "") {

                    $new_notes = $full_status;

                } elseif ($full_status == "" && $full_status_notes != "") {

                    $new_notes = $full_status_notes;

                }

            }

            $stmt = $pdo->prepare("
                UPDATE domains
                SET notes = :new_notes,
                    notes_fixed_temp = '1',
                    update_time = :timestamp
                WHERE id = :domain_id");
            $stmt->bindValue('new_notes', trim($new_notes), PDO::PARAM_LOB);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('domain_id', $row->id, PDO::PARAM_INT);
            $stmt->execute();

        } else {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET notes_fixed_temp = '1',
                    update_time = :timestamp
                WHERE id = :domain_id");
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('domain_id', $row->id, PDO::PARAM_INT);
            $stmt->execute();

        }

    }

    $result = $pdo->query("
        SELECT id
        FROM domains
        WHERE notes_fixed_temp = '0'
        LIMIT 1")->fetchColumn();

    if ($result) {

        echo "DATABASE UPDATE v2.003 FAILED: PLEASE CONTACT YOUR " . strtoupper(SOFTWARE_TITLE) . " ADMINISTRATOR IMMEDIATELY";
        exit;

    } else {

        $pdo->query("
            ALTER TABLE `domains`
            DROP `status`,
            DROP `status_notes`,
            DROP `notes_fixed_temp`");

    }

    $pdo->query("
        UPDATE settings
        SET db_version = '2.003',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.003';

}

// upgrade database from 2.003 to 2.0031
if ($current_db_version === '2.003') {

    $pdo->query("
        ALTER TABLE `categories`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `dns`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `hosting`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `owners`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `registrars`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `segments`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        DROP `active`");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        DROP `active`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0031',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0031';

}

// upgrade database from 2.0031 to 2.0032
if ($current_db_version === '2.0031') {

    $pdo->query("
        ALTER TABLE `fees`
        ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`");

    $pdo->query("
        UPDATE fees
        SET transfer_fee = initial_fee,
            update_time = '" . $timestamp . "'");

    // This section was made redundant by DB update v2.0033
    // (redundant code was here)

    $current_db_version = '2.0032';

}

// upgrade database from 2.0032 to 2.0033
if ($current_db_version === '2.0032') {

    $pdo->query("
        ALTER TABLE `ssl_fees`
        DROP `transfer_fee`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0033',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0033';

}

// upgrade database from 2.0033 to 2.0034
if ($current_db_version === '2.0033') {

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `owner_id` `owner_id` INT(10) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `account_id` `account_id` INT(10) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `dns_id` `dns_id` INT(10) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `fees`
        CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `owner_id` `owner_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `owner_id` `owner_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `owner_id` `owner_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `account_id` `account_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `type_id` `type_id` INT(10) NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0034',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0034';

}

// upgrade database from 2.0034 to 2.0035
if ($current_db_version === '2.0034') {

    $pdo->query("
        ALTER TABLE categories CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE currencies CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE dns CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE domains CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE fees CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE hosting CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ip_addresses CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE owners CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE registrars CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE registrar_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE segments CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE segment_data CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE settings CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_certs CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_cert_types CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_fees CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_providers CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE timezones CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE users CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE user_settings CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE categories CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE currencies CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE dns CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE domains CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE hosting CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ip_addresses CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE owners CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE registrars CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE registrar_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE segments CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE segment_data CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_certs CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_cert_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE ssl_providers CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE timezones CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE user_settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0035',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0035';

}

// upgrade database from 2.0035 to 2.0036
if ($current_db_version === '2.0035') {

    $pdo->query("
        DROP TABLE `currency_data`");

    $pdo->query("
        ALTER TABLE `currencies`
        ADD `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `conversion`,
        ADD `symbol_order` INT(1) NOT NULL DEFAULT '0' AFTER `symbol`,
        ADD `symbol_space` INT(1) NOT NULL DEFAULT '0' AFTER `symbol_order`,
        ADD `newly_inserted` INT(1) NOT NULL DEFAULT '1' AFTER `symbol_space`");

    $pdo->query("
        UPDATE currencies
        SET newly_inserted = '0',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`");

    $pdo->query("
        UPDATE settings
        SET default_currency = 'USD',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        UPDATE user_settings
        SET default_currency = 'USD',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        INSERT INTO currencies
        (name, currency, symbol, insert_time)
        VALUES
        ('Albania Lek', 'ALL', 'Lek', '" . $timestamp . "'),
        ('Afghanistan Afghani', 'AFN', '؋', '" . $timestamp . "'),
        ('Argentina Peso', 'ARS', '$', '" . $timestamp . "'),
        ('Aruba Guilder', 'AWG', 'ƒ', '" . $timestamp . "'),
        ('Australia Dollar', 'AUD', '$', '" . $timestamp . "'),
        ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $timestamp . "'),
        ('Bahamas Dollar', 'BSD', '$', '" . $timestamp . "'),
        ('Barbados Dollar', 'BBD', '$', '" . $timestamp . "'),
        ('Belarus Ruble', 'BYR', 'p.', '" . $timestamp . "'),
        ('Belize Dollar', 'BZD', 'BZ$', '" . $timestamp . "'),
        ('Bermuda Dollar', 'BMD', '$', '" . $timestamp . "'),
        ('Bolivia Boliviano', 'BOB', '\$b', '" . $timestamp . "'),
        ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $timestamp . "'),
        ('Botswana Pula', 'BWP', 'P', '" . $timestamp . "'),
        ('Bulgaria Lev', 'BGN', 'лв', '" . $timestamp . "'),
        ('Brazil Real', 'BRL', 'R$', '" . $timestamp . "'),
        ('Brunei Darussalam Dollar', 'BND', '$', '" . $timestamp . "'),
        ('Cambodia Riel', 'KHR', '៛', '" . $timestamp . "'),
        ('Canada Dollar', 'CAD', '$', '" . $timestamp . "'),
        ('Cayman Islands Dollar', 'KYD', '$', '" . $timestamp . "'),
        ('Chile Peso', 'CLP', '$', '" . $timestamp . "'),
        ('China Yuan Renminbi', 'CNY', '¥', '" . $timestamp . "'),
        ('Colombia Peso', 'COP', '$', '" . $timestamp . "'),
        ('Costa Rica Colon', 'CRC', '₡', '" . $timestamp . "'),
        ('Croatia Kuna', 'HRK', 'kn', '" . $timestamp . "'),
        ('Cuba Peso', 'CUP', '₱', '" . $timestamp . "'),
        ('Czech Republic Koruna', 'CZK', 'Kč', '" . $timestamp . "'),
        ('Denmark Krone', 'DKK', 'kr', '" . $timestamp . "'),
        ('Dominican Republic Peso', 'DOP', 'RD$', '" . $timestamp . "'),
        ('East Caribbean Dollar', 'XCD', '$', '" . $timestamp . "'),
        ('Egypt Pound', 'EGP', '£', '" . $timestamp . "'),
        ('El Salvador Colon', 'SVC', '$', '" . $timestamp . "'),
        ('Estonia Kroon', 'EEK', 'kr', '" . $timestamp . "'),
        ('Euro Member Countries', 'EUR', '€', '" . $timestamp . "'),
        ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $timestamp . "'),
        ('Fiji Dollar', 'FJD', '$', '" . $timestamp . "'),
        ('Ghana Cedis', 'GHC', '¢', '" . $timestamp . "'),
        ('Gibraltar Pound', 'GIP', '£', '" . $timestamp . "'),
        ('Guatemala Quetzal', 'GTQ', 'Q', '" . $timestamp . "'),
        ('Guernsey Pound', 'GGP', '£', '" . $timestamp . "'),
        ('Guyana Dollar', 'GYD', '$', '" . $timestamp . "'),
        ('Honduras Lempira', 'HNL', 'L', '" . $timestamp . "'),
        ('Hong Kong Dollar', 'HKD', '$', '" . $timestamp . "'),
        ('Hungary Forint', 'HUF', 'Ft', '" . $timestamp . "'),
        ('Iceland Krona', 'ISK', 'kr', '" . $timestamp . "'),
        ('India Rupee', 'INR', 'Rs', '" . $timestamp . "'),
        ('Indonesia Rupiah', 'IDR', 'Rp', '" . $timestamp . "'),
        ('Iran Rial', 'IRR', '﷼', '" . $timestamp . "'),
        ('Isle of Man Pound', 'IMP', '£', '" . $timestamp . "'),
        ('Israel Shekel', 'ILS', '₪', '" . $timestamp . "'),
        ('Jamaica Dollar', 'JMD', 'J$', '" . $timestamp . "'),
        ('Japan Yen', 'JPY', '¥', '" . $timestamp . "'),
        ('Jersey Pound', 'JEP', '£', '" . $timestamp . "'),
        ('Kazakhstan Tenge', 'KZT', 'лв', '" . $timestamp . "'),
        ('Korea (North) Won', 'KPW', '₩', '" . $timestamp . "'),
        ('Korea (South) Won', 'KRW', '₩', '" . $timestamp . "'),
        ('Kyrgyzstan Som', 'KGS', 'лв', '" . $timestamp . "'),
        ('Laos Kip', 'LAK', '₭', '" . $timestamp . "'),
        ('Latvia Lat', 'LVL', 'Ls', '" . $timestamp . "'),
        ('Lebanon Pound', 'LBP', '£', '" . $timestamp . "'),
        ('Liberia Dollar', 'LRD', '$', '" . $timestamp . "'),
        ('Lithuania Litas', 'LTL', 'Lt', '" . $timestamp . "'),
        ('Macedonia Denar', 'MKD', 'ден', '" . $timestamp . "'),
        ('Malaysia Ringgit', 'RM', 'RM', '" . $timestamp . "'),
        ('Mauritius Rupee', 'MUR', '₨', '" . $timestamp . "'),
        ('Mexico Peso', 'MXN', '$', '" . $timestamp . "'),
        ('Mongolia Tughrik', 'MNT', '₮', '" . $timestamp . "'),
        ('Mozambique Metical', 'MZN', 'MT', '" . $timestamp . "'),
        ('Namibia Dollar', 'NAD', '$', '" . $timestamp . "'),
        ('Nepal Rupee', 'NPR', '₨', '" . $timestamp . "'),
        ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $timestamp . "'),
        ('New Zealand Dollar', 'NZD', '$', '" . $timestamp . "'),
        ('Nicaragua Cordoba', 'NIO', 'C$', '" . $timestamp . "'),
        ('Nigeria Naira', 'NGN', '₦', '" . $timestamp . "'),
        ('Norway Krone', 'NOK', 'kr', '" . $timestamp . "'),
        ('Oman Rial', 'OMR', '﷼', '" . $timestamp . "'),
        ('Pakistan Rupee', 'PKR', '₨', '" . $timestamp . "'),
        ('Panama Balboa', 'PAB', 'B/.', '" . $timestamp . "'),
        ('Paraguay Guarani', 'PYG', 'Gs', '" . $timestamp . "'),
        ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $timestamp . "'),
        ('Philippines Peso', 'PHP', '₱', '" . $timestamp . "'),
        ('Poland Zloty', 'PLN', 'zł', '" . $timestamp . "'),
        ('Qatar Riyal', 'QAR', '﷼', '" . $timestamp . "'),
        ('Romania New Leu', 'RON', 'lei', '" . $timestamp . "'),
        ('Russia Ruble', 'RUB', 'руб', '" . $timestamp . "'),
        ('Saint Helena Pound', 'SHP', '£', '" . $timestamp . "'),
        ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $timestamp . "'),
        ('Serbia Dinar', 'RSD', 'Дин.', '" . $timestamp . "'),
        ('Seychelles Rupee', 'SCR', '₨', '" . $timestamp . "'),
        ('Singapore Dollar', 'SGD', '$', '" . $timestamp . "'),
        ('Solomon Islands Dollar', 'SBD', '$', '" . $timestamp . "'),
        ('Somalia Shilling', 'SOS', 'S', '" . $timestamp . "'),
        ('South Africa Rand', 'ZAR', 'R', '" . $timestamp . "'),
        ('Sri Lanka Rupee', 'LKR', '₨', '" . $timestamp . "'),
        ('Sweden Krona', 'SEK', 'kr', '" . $timestamp . "'),
        ('Switzerland Franc', 'CHF', 'CHF', '" . $timestamp . "'),
        ('Suriname Dollar', 'SRD', '$', '" . $timestamp . "'),
        ('Syria Pound', 'SYP', '£', '" . $timestamp . "'),
        ('Taiwan New Dollar', 'TWD', 'NT$', '" . $timestamp . "'),
        ('Thailand Baht', 'THB', '฿', '" . $timestamp . "'),
        ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $timestamp . "'),
        ('Turkey Lira', 'TRY', '₤', '" . $timestamp . "'),
        ('Tuvalu Dollar', 'TVD', '$', '" . $timestamp . "'),
        ('Ukraine Hryvna', 'UAH', '₴', '" . $timestamp . "'),
        ('United Kingdom Pound', 'GBP', '£', '" . $timestamp . "'),
        ('United States Dollar', 'USD', '$', '" . $timestamp . "'),
        ('Uruguay Peso', 'UYU', '\$U', '" . $timestamp . "'),
        ('Uzbekistan Som', 'UZS', 'лв', '" . $timestamp . "'),
        ('Venezuela Bolivar', 'VEF', 'Bs', '" . $timestamp . "'),
        ('Viet Nam Dong', 'VND', '₫', '" . $timestamp . "'),
        ('Yemen Rial', 'YER', '﷼', '" . $timestamp . "'),
        ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $timestamp . "'),
        ('Emirati Dirham', 'AED', 'د.إ', '" . $timestamp . "'),
        ('Malaysian Ringgit', 'MYR', 'RM', '" . $timestamp . "'),
        ('Kuwaiti Dinar', 'KWD', 'ك', '" . $timestamp . "'),
        ('Moroccan Dirham', 'MAD', 'م.', '" . $timestamp . "'),
        ('Iraqi Dinar', 'IQD', 'د.ع', '" . $timestamp . "'),
        ('Bangladeshi Taka', 'BDT', 'Tk', '" . $timestamp . "'),
        ('Bahraini Dinar', 'BHD', 'BD', '" . $timestamp . "'),
        ('Kenyan Shilling', 'KES', 'KSh', '" . $timestamp . "'),
        ('CFA Franc', 'XOF', 'CFA', '" . $timestamp . "'),
        ('Jordanian Dinar', 'JOD', 'JD', '" . $timestamp . "'),
        ('Tunisian Dinar', 'TND', 'د.ت', '" . $timestamp . "'),
        ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $timestamp . "'),
        ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $timestamp . "'),
        ('Algerian Dinar', 'DZD', 'دج', '" . $timestamp . "'),
        ('CFP Franc', 'XPF', 'F', '" . $timestamp . "'),
        ('Ugandan Shilling', 'UGX', 'USh', '" . $timestamp . "'),
        ('Tanzanian Shilling', 'TZS', 'TZS', '" . $timestamp . "'),
        ('Ethiopian Birr', 'ETB', 'Br', '" . $timestamp . "'),
        ('Georgian Lari', 'GEL', 'GEL', '" . $timestamp . "'),
        ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $timestamp . "'),
        ('Burmese Kyat', 'MMK', 'K', '" . $timestamp . "'),
        ('Libyan Dinar', 'LYD', 'LD', '" . $timestamp . "'),
        ('Zambian Kwacha', 'ZMK', 'ZK', '" . $timestamp . "'),
        ('Zambian Kwacha', 'ZMW', 'ZK', '" . $timestamp . "'),
        ('Macau Pataca', 'MOP', 'MOP$', '" . $timestamp . "'),
        ('Armenian Dram', 'AMD', 'AMD', '" . $timestamp . "'),
        ('Angolan Kwanza', 'AOA', 'Kz', '" . $timestamp . "'),
        ('Papua New Guinean Kina', 'PGK', 'K', '" . $timestamp . "'),
        ('Malagasy Ariary', 'MGA', 'Ar', '" . $timestamp . "'),
        ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $timestamp . "'),
        ('Sudanese Pound', 'SDG', 'SDG', '" . $timestamp . "'),
        ('Malawian Kwacha', 'MWK', 'MK', '" . $timestamp . "'),
        ('Rwandan Franc', 'RWF', 'FRw', '" . $timestamp . "'),
        ('Gambian Dalasi', 'GMD', 'D', '" . $timestamp . "'),
        ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $timestamp . "'),
        ('Congolese Franc', 'CDF', 'FC', '" . $timestamp . "'),
        ('Djiboutian Franc', 'DJF', 'Fdj', '" . $timestamp . "'),
        ('Haitian Gourde', 'HTG', 'G', '" . $timestamp . "'),
        ('Samoan Tala', 'WST', '$', '" . $timestamp . "'),
        ('Guinean Franc', 'GNF', 'FG', '" . $timestamp . "'),
        ('Cape Verdean Escudo', 'CVE', '$', '" . $timestamp . "'),
        ('Tongan Pa\'anga', 'TOP', 'T$', '" . $timestamp . "'),
        ('Moldovan Leu', 'MDL', 'MDL', '" . $timestamp . "'),
        ('Sierra Leonean Leone', 'SLL', 'Le', '" . $timestamp . "'),
        ('Burundian Franc', 'BIF', 'FBu', '" . $timestamp . "'),
        ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $timestamp . "'),
        ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $timestamp . "'),
        ('Swazi Lilangeni', 'SZL', 'SZL', '" . $timestamp . "'),
        ('Tajikistani Somoni', 'TJS', 'TJS', '" . $timestamp . "'),
        ('Turkmenistani Manat', 'TMT', 'm', '" . $timestamp . "'),
        ('Basotho Loti', 'LSL', 'LSL', '" . $timestamp . "'),
        ('Comoran Franc', 'KMF', 'CF', '" . $timestamp . "'),
        ('Sao Tomean Dobra', 'STD', 'STD', '" . $timestamp . "'),
        ('Seborgan Luigino', 'SPL', 'SPL', '" . $timestamp . "')");

    $result = $pdo->query("
        SELECT id, currency
        FROM currencies
        WHERE newly_inserted = '0'")->fetchAll();

    if ($result) {

        $stmt = $pdo->prepare("
            SELECT id, symbol
            FROM currencies
            WHERE newly_inserted = '1'
              AND currency = :currency");
        $stmt->bindParam('currency', $bind_currency, PDO::PARAM_STR);

        foreach ($result as $row) {

            $bind_currency = $row->currency;
            $stmt->execute();
            $result2 = $stmt->fetchAll();

            if ($result2) {

                $stmt2 = $pdo->prepare("
                    UPDATE currencies
                    SET symbol = :symbol
                    WHERE id = :id");
                $stmt2->bindParam('symbol', $bind_symbol, PDO::PARAM_STR);
                $stmt2->bindParam('id', $bind_id_1, PDO::PARAM_INT);

                $stmt3 = $pdo->prepare("
                    DELETE FROM currencies
                    WHERE id = :id");
                $stmt3->bindParam('id', $bind_id_2, PDO::PARAM_INT);

                foreach ($result2 as $row2) {

                    $bind_symbol = $row2->symbol;
                    $bind_id_1 = $row->id;
                    $stmt2->execute();

                    $bind_id_2 = $row2->id;
                    $stmt3->execute();

                }

            }

        }

    }

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `newly_inserted`");

    $pdo->query("
        UPDATE settings
            SET db_version = '2.0036',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0036';

}

// upgrade database from 2.0036 to 2.0037
if ($current_db_version === '2.0036') {

    $temp_currency = $pdo->query("
        SELECT currency
        FROM currencies
        WHERE default_currency = '1'")->fetchColumn();

    $pdo->query("
        UPDATE settings
        SET default_currency = '" . $temp_currency . "'");

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `default_currency`");

    $pdo->query("
        ALTER TABLE `user_settings`
        DROP `default_currency`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0037',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0037';

}

// upgrade database from 2.0037 to 2.0038
if ($current_db_version === '2.0037') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER user_id");

    $temp_default_currency = $pdo->query("
        SELECT default_currency
        FROM settings")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_currency = '" . $temp_default_currency . "'");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `currency_conversions` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `currency_id` INT(10) NOT NULL,
            `user_id` INT(10) NOT NULL,
            `conversion` FLOAT NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $result = $pdo->query("
        SELECT id
        FROM users")->fetchAll();

    foreach ($result as $row) {

        $result2 = $pdo->query("
            SELECT id, conversion
            FROM currencies
            WHERE conversion != '0'")->fetchAll();

        foreach ($result2 as $row2) {

            $stmt = $pdo->prepare("
                INSERT INTO currency_conversions
                (currency_id, user_id, conversion, insert_time, update_time)
                VALUES
                (:currency_id, :user_id, :conversion, :timestamp_insert, :timestamp_update)");
            $stmt->bindValue('currency_id', $row2->id, PDO::PARAM_INT);
            $stmt->bindValue('user_id', $row->id, PDO::PARAM_INT);
            $stmt->bindValue('conversion', strval($row2->conversion), PDO::PARAM_STR);
            $stmt->bindValue('timestamp_insert', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('timestamp_update', $timestamp, PDO::PARAM_STR);
            $stmt->execute();

        }

    }

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `conversion`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0038',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0038';

}
//@formatter:on

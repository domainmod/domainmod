<?php
/**
 * /_includes/updates/2.0022-2.0037.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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

    $sql = "CREATE TABLE IF NOT EXISTS `timezones` (
                `id` INT(5) NOT NULL AUTO_INCREMENT,
                `timezone` VARCHAR(50) NOT NULL,
                `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `timezones`
            (`timezone`, `insert_time`)
            VALUES
            ('Africa/Abidjan', '" . $time->stamp() . "'), ('Africa/Accra', '" . $time->stamp() . "'), ('Africa/Addis_Ababa', '" . $time->stamp() . "'), ('Africa/Algiers', '" . $time->stamp() . "'), ('Africa/Asmara', '" . $time->stamp() . "'), ('Africa/Asmera', '" . $time->stamp() . "'), ('Africa/Bamako', '" . $time->stamp() . "'), ('Africa/Bangui', '" . $time->stamp() . "'), ('Africa/Banjul', '" . $time->stamp() . "'), ('Africa/Bissau', '" . $time->stamp() . "'), ('Africa/Blantyre', '" . $time->stamp() . "'), ('Africa/Brazzaville', '" . $time->stamp() . "'), ('Africa/Bujumbura', '" . $time->stamp() . "'), ('Africa/Cairo', '" . $time->stamp() . "'), ('Africa/Casablanca', '" . $time->stamp() . "'), ('Africa/Ceuta', '" . $time->stamp() . "'), ('Africa/Conakry', '" . $time->stamp() . "'), ('Africa/Dakar', '" . $time->stamp() . "'), ('Africa/Dar_es_Salaam', '" . $time->stamp() . "'), ('Africa/Djibouti', '" . $time->stamp() . "'), ('Africa/Douala', '" . $time->stamp() . "'), ('Africa/El_Aaiun', '" . $time->stamp() . "'), ('Africa/Freetown', '" . $time->stamp() . "'), ('Africa/Gaborone', '" . $time->stamp() . "'), ('Africa/Harare', '" . $time->stamp() . "'), ('Africa/Johannesburg', '" . $time->stamp() . "'), ('Africa/Juba', '" . $time->stamp() . "'), ('Africa/Kampala', '" . $time->stamp() . "'), ('Africa/Khartoum', '" . $time->stamp() . "'), ('Africa/Kigali', '" . $time->stamp() . "'), ('Africa/Kinshasa', '" . $time->stamp() . "'), ('Africa/Lagos', '" . $time->stamp() . "'), ('Africa/Libreville', '" . $time->stamp() . "'), ('Africa/Lome', '" . $time->stamp() . "'), ('Africa/Luanda', '" . $time->stamp() . "'), ('Africa/Lubumbashi', '" . $time->stamp() . "'), ('Africa/Lusaka', '" . $time->stamp() . "'), ('Africa/Malabo', '" . $time->stamp() . "'), ('Africa/Maputo', '" . $time->stamp() . "'), ('Africa/Maseru', '" . $time->stamp() . "'), ('Africa/Mbabane', '" . $time->stamp() . "'), ('Africa/Mogadishu', '" . $time->stamp() . "'), ('Africa/Monrovia', '" . $time->stamp() . "'), ('Africa/Nairobi', '" . $time->stamp() . "'), ('Africa/Ndjamena', '" . $time->stamp() . "'), ('Africa/Niamey', '" . $time->stamp() . "'), ('Africa/Nouakchott', '" . $time->stamp() . "'), ('Africa/Ouagadougou', '" . $time->stamp() . "'), ('Africa/Porto-Novo', '" . $time->stamp() . "'), ('Africa/Sao_Tome', '" . $time->stamp() . "'), ('Africa/Timbuktu', '" . $time->stamp() . "'), ('Africa/Tripoli', '" . $time->stamp() . "'), ('Africa/Tunis', '" . $time->stamp() . "'), ('Africa/Windhoek', '" . $time->stamp() . "'), ('America/Adak', '" . $time->stamp() . "'), ('America/Anchorage', '" . $time->stamp() . "'), ('America/Anguilla', '" . $time->stamp() . "'), ('America/Antigua', '" . $time->stamp() . "'), ('America/Araguaina', '" . $time->stamp() . "'), ('America/Argentina/Buenos_Aires', '" . $time->stamp() . "'), ('America/Argentina/Catamarca', '" . $time->stamp() . "'), ('America/Argentina/ComodRivadavia', '" . $time->stamp() . "'), ('America/Argentina/Cordoba', '" . $time->stamp() . "'), ('America/Argentina/Jujuy', '" . $time->stamp() . "'), ('America/Argentina/La_Rioja', '" . $time->stamp() . "'), ('America/Argentina/Mendoza', '" . $time->stamp() . "'), ('America/Argentina/Rio_Gallegos', '" . $time->stamp() . "'), ('America/Argentina/Salta', '" . $time->stamp() . "'), ('America/Argentina/San_Juan', '" . $time->stamp() . "'), ('America/Argentina/San_Luis', '" . $time->stamp() . "'), ('America/Argentina/Tucuman', '" . $time->stamp() . "'), ('America/Argentina/Ushuaia', '" . $time->stamp() . "'), ('America/Aruba', '" . $time->stamp() . "'), ('America/Asuncion', '" . $time->stamp() . "'), ('America/Atikokan', '" . $time->stamp() . "'), ('America/Atka', '" . $time->stamp() . "'), ('America/Bahia', '" . $time->stamp() . "'), ('America/Bahia_Banderas', '" . $time->stamp() . "'), ('America/Barbados', '" . $time->stamp() . "'), ('America/Belem', '" . $time->stamp() . "'), ('America/Belize', '" . $time->stamp() . "'), ('America/Blanc-Sablon', '" . $time->stamp() . "'), ('America/Boa_Vista', '" . $time->stamp() . "'), ('America/Bogota', '" . $time->stamp() . "'), ('America/Boise', '" . $time->stamp() . "'), ('America/Buenos_Aires', '" . $time->stamp() . "'), ('America/Cambridge_Bay', '" . $time->stamp() . "'), ('America/Campo_Grande', '" . $time->stamp() . "'), ('America/Cancun', '" . $time->stamp() . "'), ('America/Caracas', '" . $time->stamp() . "'), ('America/Catamarca', '" . $time->stamp() . "'), ('America/Cayenne', '" . $time->stamp() . "'), ('America/Cayman', '" . $time->stamp() . "'), ('America/Chicago', '" . $time->stamp() . "'), ('America/Chihuahua', '" . $time->stamp() . "'), ('America/Coral_Harbour', '" . $time->stamp() . "'), ('America/Cordoba', '" . $time->stamp() . "'), ('America/Costa_Rica', '" . $time->stamp() . "'), ('America/Creston', '" . $time->stamp() . "'), ('America/Cuiaba', '" . $time->stamp() . "'), ('America/Curacao', '" . $time->stamp() . "'), ('America/Danmarkshavn', '" . $time->stamp() . "'), ('America/Dawson', '" . $time->stamp() . "'), ('America/Dawson_Creek', '" . $time->stamp() . "'), ('America/Denver', '" . $time->stamp() . "'), ('America/Detroit', '" . $time->stamp() . "'), ('America/Dominica', '" . $time->stamp() . "'), ('America/Edmonton', '" . $time->stamp() . "'), ('America/Eirunepe', '" . $time->stamp() . "'), ('America/El_Salvador', '" . $time->stamp() . "'), ('America/Ensenada', '" . $time->stamp() . "'), ('America/Fort_Wayne', '" . $time->stamp() . "'), ('America/Fortaleza', '" . $time->stamp() . "'), ('America/Glace_Bay', '" . $time->stamp() . "'), ('America/Godthab', '" . $time->stamp() . "'), ('America/Goose_Bay', '" . $time->stamp() . "'), ('America/Grand_Turk', '" . $time->stamp() . "'), ('America/Grenada', '" . $time->stamp() . "'), ('America/Guadeloupe', '" . $time->stamp() . "'), ('America/Guatemala', '" . $time->stamp() . "'), ('America/Guayaquil', '" . $time->stamp() . "'), ('America/Guyana', '" . $time->stamp() . "'), ('America/Halifax', '" . $time->stamp() . "'), ('America/Havana', '" . $time->stamp() . "'), ('America/Hermosillo', '" . $time->stamp() . "'), ('America/Indiana/Indianapolis', '" . $time->stamp() . "'), ('America/Indiana/Knox', '" . $time->stamp() . "'), ('America/Indiana/Marengo', '" . $time->stamp() . "'), ('America/Indiana/Petersburg', '" . $time->stamp() . "'), ('America/Indiana/Tell_City', '" . $time->stamp() . "'), ('America/Indiana/Vevay', '" . $time->stamp() . "'), ('America/Indiana/Vincennes', '" . $time->stamp() . "'), ('America/Indiana/Winamac', '" . $time->stamp() . "'), ('America/Indianapolis', '" . $time->stamp() . "'), ('America/Inuvik', '" . $time->stamp() . "'), ('America/Iqaluit', '" . $time->stamp() . "'), ('America/Jamaica', '" . $time->stamp() . "'), ('America/Jujuy', '" . $time->stamp() . "'), ('America/Juneau', '" . $time->stamp() . "'), ('America/Kentucky/Louisville', '" . $time->stamp() . "'), ('America/Kentucky/Monticello', '" . $time->stamp() . "'), ('America/Knox_IN', '" . $time->stamp() . "'), ('America/Kralendijk', '" . $time->stamp() . "'), ('America/La_Paz', '" . $time->stamp() . "'), ('America/Lima', '" . $time->stamp() . "'), ('America/Los_Angeles', '" . $time->stamp() . "'), ('America/Louisville', '" . $time->stamp() . "'), ('America/Lower_Princes', '" . $time->stamp() . "'), ('America/Maceio', '" . $time->stamp() . "'), ('America/Managua', '" . $time->stamp() . "'), ('America/Manaus', '" . $time->stamp() . "'), ('America/Marigot', '" . $time->stamp() . "'), ('America/Martinique', '" . $time->stamp() . "'), ('America/Matamoros', '" . $time->stamp() . "'), ('America/Mazatlan', '" . $time->stamp() . "'), ('America/Mendoza', '" . $time->stamp() . "'), ('America/Menominee', '" . $time->stamp() . "'), ('America/Merida', '" . $time->stamp() . "'), ('America/Metlakatla', '" . $time->stamp() . "'), ('America/Mexico_City', '" . $time->stamp() . "'), ('America/Miquelon', '" . $time->stamp() . "'), ('America/Moncton', '" . $time->stamp() . "'), ('America/Monterrey', '" . $time->stamp() . "'), ('America/Montevideo', '" . $time->stamp() . "'), ('America/Montreal', '" . $time->stamp() . "'), ('America/Montserrat', '" . $time->stamp() . "'), ('America/Nassau', '" . $time->stamp() . "'), ('America/New_York', '" . $time->stamp() . "'), ('America/Nipigon', '" . $time->stamp() . "'), ('America/Nome', '" . $time->stamp() . "'), ('America/Noronha', '" . $time->stamp() . "'), ('America/North_Dakota/Beulah', '" . $time->stamp() . "'), ('America/North_Dakota/Center', '" . $time->stamp() . "'), ('America/North_Dakota/New_Salem', '" . $time->stamp() . "'), ('America/Ojinaga', '" . $time->stamp() . "'), ('America/Panama', '" . $time->stamp() . "'), ('America/Pangnirtung', '" . $time->stamp() . "'), ('America/Paramaribo', '" . $time->stamp() . "'), ('America/Phoenix', '" . $time->stamp() . "'), ('America/Port-au-Prince', '" . $time->stamp() . "'), ('America/Port_of_Spain', '" . $time->stamp() . "'), ('America/Porto_Acre', '" . $time->stamp() . "'), ('America/Porto_Velho', '" . $time->stamp() . "'), ('America/Puerto_Rico', '" . $time->stamp() . "'), ('America/Rainy_River', '" . $time->stamp() . "'), ('America/Rankin_Inlet', '" . $time->stamp() . "'), ('America/Recife', '" . $time->stamp() . "'), ('America/Regina', '" . $time->stamp() . "'), ('America/Resolute', '" . $time->stamp() . "'), ('America/Rio_Branco', '" . $time->stamp() . "'), ('America/Rosario', '" . $time->stamp() . "'), ('America/Santa_Isabel', '" . $time->stamp() . "'), ('America/Santarem', '" . $time->stamp() . "'), ('America/Santiago', '" . $time->stamp() . "'), ('America/Santo_Domingo', '" . $time->stamp() . "'), ('America/Sao_Paulo', '" . $time->stamp() . "'), ('America/Scoresbysund', '" . $time->stamp() . "'), ('America/Shiprock', '" . $time->stamp() . "'), ('America/Sitka', '" . $time->stamp() . "'), ('America/St_Barthelemy', '" . $time->stamp() . "'), ('America/St_Johns', '" . $time->stamp() . "'), ('America/St_Kitts', '" . $time->stamp() . "'), ('America/St_Lucia', '" . $time->stamp() . "'), ('America/St_Thomas', '" . $time->stamp() . "'), ('America/St_Vincent', '" . $time->stamp() . "'), ('America/Swift_Current', '" . $time->stamp() . "'), ('America/Tegucigalpa', '" . $time->stamp() . "'), ('America/Thule', '" . $time->stamp() . "'), ('America/Thunder_Bay', '" . $time->stamp() . "'), ('America/Tijuana', '" . $time->stamp() . "'), ('America/Toronto', '" . $time->stamp() . "'), ('America/Tortola', '" . $time->stamp() . "'), ('America/Vancouver', '" . $time->stamp() . "'), ('America/Virgin', '" . $time->stamp() . "'), ('America/Whitehorse', '" . $time->stamp() . "'), ('America/Winnipeg', '" . $time->stamp() . "'), ('America/Yakutat', '" . $time->stamp() . "'), ('America/Yellowknife', '" . $time->stamp() . "'), ('Antarctica/Casey', '" . $time->stamp() . "'), ('Antarctica/Davis', '" . $time->stamp() . "'), ('Antarctica/DumontDUrville', '" . $time->stamp() . "'), ('Antarctica/Macquarie', '" . $time->stamp() . "'), ('Antarctica/Mawson', '" . $time->stamp() . "'), ('Antarctica/McMurdo', '" . $time->stamp() . "'), ('Antarctica/Palmer', '" . $time->stamp() . "'), ('Antarctica/Rothera', '" . $time->stamp() . "'), ('Antarctica/South_Pole', '" . $time->stamp() . "'), ('Antarctica/Syowa', '" . $time->stamp() . "'), ('Antarctica/Vostok', '" . $time->stamp() . "'), ('Arctic/Longyearbyen', '" . $time->stamp() . "'), ('Asia/Aden', '" . $time->stamp() . "'), ('Asia/Almaty', '" . $time->stamp() . "'), ('Asia/Amman', '" . $time->stamp() . "'), ('Asia/Anadyr', '" . $time->stamp() . "'), ('Asia/Aqtau', '" . $time->stamp() . "'), ('Asia/Aqtobe', '" . $time->stamp() . "'), ('Asia/Ashgabat', '" . $time->stamp() . "'), ('Asia/Ashkhabad', '" . $time->stamp() . "'), ('Asia/Baghdad', '" . $time->stamp() . "'), ('Asia/Bahrain', '" . $time->stamp() . "'), ('Asia/Baku', '" . $time->stamp() . "'), ('Asia/Bangkok', '" . $time->stamp() . "'), ('Asia/Beirut', '" . $time->stamp() . "'), ('Asia/Bishkek', '" . $time->stamp() . "'), ('Asia/Brunei', '" . $time->stamp() . "'), ('Asia/Calcutta', '" . $time->stamp() . "'), ('Asia/Choibalsan', '" . $time->stamp() . "'), ('Asia/Chongqing', '" . $time->stamp() . "'), ('Asia/Chungking', '" . $time->stamp() . "'), ('Asia/Colombo', '" . $time->stamp() . "'), ('Asia/Dacca', '" . $time->stamp() . "'), ('Asia/Damascus', '" . $time->stamp() . "'), ('Asia/Dhaka', '" . $time->stamp() . "'), ('Asia/Dili', '" . $time->stamp() . "'), ('Asia/Dubai', '" . $time->stamp() . "'), ('Asia/Dushanbe', '" . $time->stamp() . "'), ('Asia/Gaza', '" . $time->stamp() . "'), ('Asia/Harbin', '" . $time->stamp() . "'), ('Asia/Hebron', '" . $time->stamp() . "'), ('Asia/Ho_Chi_Minh', '" . $time->stamp() . "'), ('Asia/Hong_Kong', '" . $time->stamp() . "'), ('Asia/Hovd', '" . $time->stamp() . "'), ('Asia/Irkutsk', '" . $time->stamp() . "'), ('Asia/Istanbul', '" . $time->stamp() . "'), ('Asia/Jakarta', '" . $time->stamp() . "'), ('Asia/Jayapura', '" . $time->stamp() . "'), ('Asia/Jerusalem', '" . $time->stamp() . "'), ('Asia/Kabul', '" . $time->stamp() . "'), ('Asia/Kamchatka', '" . $time->stamp() . "'), ('Asia/Karachi', '" . $time->stamp() . "'), ('Asia/Kashgar', '" . $time->stamp() . "'), ('Asia/Kathmandu', '" . $time->stamp() . "'), ('Asia/Katmandu', '" . $time->stamp() . "'), ('Asia/Khandyga', '" . $time->stamp() . "'), ('Asia/Kolkata', '" . $time->stamp() . "'), ('Asia/Krasnoyarsk', '" . $time->stamp() . "'), ('Asia/Kuala_Lumpur', '" . $time->stamp() . "'), ('Asia/Kuching', '" . $time->stamp() . "'), ('Asia/Kuwait', '" . $time->stamp() . "'), ('Asia/Macao', '" . $time->stamp() . "'), ('Asia/Macau', '" . $time->stamp() . "'), ('Asia/Magadan', '" . $time->stamp() . "'), ('Asia/Makassar', '" . $time->stamp() . "'), ('Asia/Manila', '" . $time->stamp() . "'), ('Asia/Muscat', '" . $time->stamp() . "'), ('Asia/Nicosia', '" . $time->stamp() . "'), ('Asia/Novokuznetsk', '" . $time->stamp() . "'), ('Asia/Novosibirsk', '" . $time->stamp() . "'), ('Asia/Omsk', '" . $time->stamp() . "'), ('Asia/Oral', '" . $time->stamp() . "'), ('Asia/Phnom_Penh', '" . $time->stamp() . "'), ('Asia/Pontianak', '" . $time->stamp() . "'), ('Asia/Pyongyang', '" . $time->stamp() . "'), ('Asia/Qatar', '" . $time->stamp() . "'), ('Asia/Qyzylorda', '" . $time->stamp() . "'), ('Asia/Rangoon', '" . $time->stamp() . "'), ('Asia/Riyadh', '" . $time->stamp() . "'), ('Asia/Saigon', '" . $time->stamp() . "'), ('Asia/Sakhalin', '" . $time->stamp() . "'), ('Asia/Samarkand', '" . $time->stamp() . "'), ('Asia/Seoul', '" . $time->stamp() . "'), ('Asia/Shanghai', '" . $time->stamp() . "'), ('Asia/Singapore', '" . $time->stamp() . "'), ('Asia/Taipei', '" . $time->stamp() . "'), ('Asia/Tashkent', '" . $time->stamp() . "'), ('Asia/Tbilisi', '" . $time->stamp() . "'), ('Asia/Tehran', '" . $time->stamp() . "'), ('Asia/Tel_Aviv', '" . $time->stamp() . "'), ('Asia/Thimbu', '" . $time->stamp() . "'), ('Asia/Thimphu', '" . $time->stamp() . "'), ('Asia/Tokyo', '" . $time->stamp() . "'), ('Asia/Ujung_Pandang', '" . $time->stamp() . "'), ('Asia/Ulaanbaatar', '" . $time->stamp() . "'), ('Asia/Ulan_Bator', '" . $time->stamp() . "'), ('Asia/Urumqi', '" . $time->stamp() . "'), ('Asia/Ust-Nera', '" . $time->stamp() . "'), ('Asia/Vientiane', '" . $time->stamp() . "'), ('Asia/Vladivostok', '" . $time->stamp() . "'), ('Asia/Yakutsk', '" . $time->stamp() . "'), ('Asia/Yekaterinburg', '" . $time->stamp() . "'), ('Asia/Yerevan', '" . $time->stamp() . "'), ('Atlantic/Azores', '" . $time->stamp() . "'), ('Atlantic/Bermuda', '" . $time->stamp() . "'), ('Atlantic/Canary', '" . $time->stamp() . "'), ('Atlantic/Cape_Verde', '" . $time->stamp() . "'), ('Atlantic/Faeroe', '" . $time->stamp() . "'), ('Atlantic/Faroe', '" . $time->stamp() . "'), ('Atlantic/Jan_Mayen', '" . $time->stamp() . "'), ('Atlantic/Madeira', '" . $time->stamp() . "'), ('Atlantic/Reykjavik', '" . $time->stamp() . "'), ('Atlantic/South_Georgia', '" . $time->stamp() . "'), ('Atlantic/St_Helena', '" . $time->stamp() . "'), ('Atlantic/Stanley', '" . $time->stamp() . "'), ('Australia/ACT', '" . $time->stamp() . "'), ('Australia/Adelaide', '" . $time->stamp() . "'), ('Australia/Brisbane', '" . $time->stamp() . "'), ('Australia/Broken_Hill', '" . $time->stamp() . "'), ('Australia/Canberra', '" . $time->stamp() . "'), ('Australia/Currie', '" . $time->stamp() . "'), ('Australia/Darwin', '" . $time->stamp() . "'), ('Australia/Eucla', '" . $time->stamp() . "'), ('Australia/Hobart', '" . $time->stamp() . "'), ('Australia/LHI', '" . $time->stamp() . "'), ('Australia/Lindeman', '" . $time->stamp() . "'), ('Australia/Lord_Howe', '" . $time->stamp() . "'), ('Australia/Melbourne', '" . $time->stamp() . "'), ('Australia/North', '" . $time->stamp() . "'), ('Australia/NSW', '" . $time->stamp() . "'), ('Australia/Perth', '" . $time->stamp() . "'), ('Australia/Queensland', '" . $time->stamp() . "'), ('Australia/South', '" . $time->stamp() . "'), ('Australia/Sydney', '" . $time->stamp() . "'), ('Australia/Tasmania', '" . $time->stamp() . "'), ('Australia/Victoria', '" . $time->stamp() . "'), ('Australia/West', '" . $time->stamp() . "'), ('Australia/Yancowinna', '" . $time->stamp() . "'), ('Brazil/Acre', '" . $time->stamp() . "'), ('Brazil/DeNoronha', '" . $time->stamp() . "'), ('Brazil/East', '" . $time->stamp() . "'), ('Brazil/West', '" . $time->stamp() . "'), ('Canada/Atlantic', '" . $time->stamp() . "'), ('Canada/Central', '" . $time->stamp() . "'), ('Canada/East-Saskatchewan', '" . $time->stamp() . "'), ('Canada/Eastern', '" . $time->stamp() . "'), ('Canada/Mountain', '" . $time->stamp() . "'), ('Canada/Newfoundland', '" . $time->stamp() . "'), ('Canada/Pacific', '" . $time->stamp() . "'), ('Canada/Saskatchewan', '" . $time->stamp() . "'), ('Canada/Yukon', '" . $time->stamp() . "'), ('Chile/Continental', '" . $time->stamp() . "'), ('Chile/EasterIsland', '" . $time->stamp() . "'), ('Cuba', '" . $time->stamp() . "'), ('Egypt', '" . $time->stamp() . "'), ('Eire', '" . $time->stamp() . "'), ('Europe/Amsterdam', '" . $time->stamp() . "'), ('Europe/Andorra', '" . $time->stamp() . "'), ('Europe/Athens', '" . $time->stamp() . "'), ('Europe/Belfast', '" . $time->stamp() . "'), ('Europe/Belgrade', '" . $time->stamp() . "'), ('Europe/Berlin', '" . $time->stamp() . "'), ('Europe/Bratislava', '" . $time->stamp() . "'), ('Europe/Brussels', '" . $time->stamp() . "'), ('Europe/Bucharest', '" . $time->stamp() . "'), ('Europe/Budapest', '" . $time->stamp() . "'), ('Europe/Busingen', '" . $time->stamp() . "'), ('Europe/Chisinau', '" . $time->stamp() . "'), ('Europe/Copenhagen', '" . $time->stamp() . "'), ('Europe/Dublin', '" . $time->stamp() . "'), ('Europe/Gibraltar', '" . $time->stamp() . "'), ('Europe/Guernsey', '" . $time->stamp() . "'), ('Europe/Helsinki', '" . $time->stamp() . "'), ('Europe/Isle_of_Man', '" . $time->stamp() . "'), ('Europe/Istanbul', '" . $time->stamp() . "'), ('Europe/Jersey', '" . $time->stamp() . "'), ('Europe/Kaliningrad', '" . $time->stamp() . "'), ('Europe/Kiev', '" . $time->stamp() . "'), ('Europe/Lisbon', '" . $time->stamp() . "'), ('Europe/Ljubljana', '" . $time->stamp() . "'), ('Europe/London', '" . $time->stamp() . "'), ('Europe/Luxembourg', '" . $time->stamp() . "'), ('Europe/Madrid', '" . $time->stamp() . "'), ('Europe/Malta', '" . $time->stamp() . "'), ('Europe/Mariehamn', '" . $time->stamp() . "'), ('Europe/Minsk', '" . $time->stamp() . "'), ('Europe/Monaco', '" . $time->stamp() . "'), ('Europe/Moscow', '" . $time->stamp() . "'), ('Europe/Nicosia', '" . $time->stamp() . "'), ('Europe/Oslo', '" . $time->stamp() . "'), ('Europe/Paris', '" . $time->stamp() . "'), ('Europe/Podgorica', '" . $time->stamp() . "'), ('Europe/Prague', '" . $time->stamp() . "'), ('Europe/Riga', '" . $time->stamp() . "'), ('Europe/Rome', '" . $time->stamp() . "'), ('Europe/Samara', '" . $time->stamp() . "'), ('Europe/San_Marino', '" . $time->stamp() . "'), ('Europe/Sarajevo', '" . $time->stamp() . "'), ('Europe/Simferopol', '" . $time->stamp() . "'), ('Europe/Skopje', '" . $time->stamp() . "'), ('Europe/Sofia', '" . $time->stamp() . "'), ('Europe/Stockholm', '" . $time->stamp() . "'), ('Europe/Tallinn', '" . $time->stamp() . "'), ('Europe/Tirane', '" . $time->stamp() . "'), ('Europe/Tiraspol', '" . $time->stamp() . "'), ('Europe/Uzhgorod', '" . $time->stamp() . "'), ('Europe/Vaduz', '" . $time->stamp() . "'), ('Europe/Vatican', '" . $time->stamp() . "'), ('Europe/Vienna', '" . $time->stamp() . "'), ('Europe/Vilnius', '" . $time->stamp() . "'), ('Europe/Volgograd', '" . $time->stamp() . "'), ('Europe/Warsaw', '" . $time->stamp() . "'), ('Europe/Zagreb', '" . $time->stamp() . "'), ('Europe/Zaporozhye', '" . $time->stamp() . "'), ('Europe/Zurich', '" . $time->stamp() . "'), ('Greenwich', '" . $time->stamp() . "'), ('Hongkong', '" . $time->stamp() . "'), ('Iceland', '" . $time->stamp() . "'), ('Indian/Antananarivo', '" . $time->stamp() . "'), ('Indian/Chagos', '" . $time->stamp() . "'), ('Indian/Christmas', '" . $time->stamp() . "'), ('Indian/Cocos', '" . $time->stamp() . "'), ('Indian/Comoro', '" . $time->stamp() . "'), ('Indian/Kerguelen', '" . $time->stamp() . "'), ('Indian/Mahe', '" . $time->stamp() . "'), ('Indian/Maldives', '" . $time->stamp() . "'), ('Indian/Mauritius', '" . $time->stamp() . "'), ('Indian/Mayotte', '" . $time->stamp() . "'), ('Indian/Reunion', '" . $time->stamp() . "'), ('Iran', '" . $time->stamp() . "'), ('Israel', '" . $time->stamp() . "'), ('Jamaica', '" . $time->stamp() . "'), ('Japan', '" . $time->stamp() . "'), ('Kwajalein', '" . $time->stamp() . "'), ('Libya', '" . $time->stamp() . "'), ('Mexico/BajaNorte', '" . $time->stamp() . "'), ('Mexico/BajaSur', '" . $time->stamp() . "'), ('Mexico/General', '" . $time->stamp() . "'), ('Pacific/Apia', '" . $time->stamp() . "'), ('Pacific/Auckland', '" . $time->stamp() . "'), ('Pacific/Chatham', '" . $time->stamp() . "'), ('Pacific/Chuuk', '" . $time->stamp() . "'), ('Pacific/Easter', '" . $time->stamp() . "'), ('Pacific/Efate', '" . $time->stamp() . "'), ('Pacific/Enderbury', '" . $time->stamp() . "'), ('Pacific/Fakaofo', '" . $time->stamp() . "'), ('Pacific/Fiji', '" . $time->stamp() . "'), ('Pacific/Funafuti', '" . $time->stamp() . "'), ('Pacific/Galapagos', '" . $time->stamp() . "'), ('Pacific/Gambier', '" . $time->stamp() . "'), ('Pacific/Guadalcanal', '" . $time->stamp() . "'), ('Pacific/Guam', '" . $time->stamp() . "'), ('Pacific/Honolulu', '" . $time->stamp() . "'), ('Pacific/Johnston', '" . $time->stamp() . "'), ('Pacific/Kiritimati', '" . $time->stamp() . "'), ('Pacific/Kosrae', '" . $time->stamp() . "'), ('Pacific/Kwajalein', '" . $time->stamp() . "'), ('Pacific/Majuro', '" . $time->stamp() . "'), ('Pacific/Marquesas', '" . $time->stamp() . "'), ('Pacific/Midway', '" . $time->stamp() . "'), ('Pacific/Nauru', '" . $time->stamp() . "'), ('Pacific/Niue', '" . $time->stamp() . "'), ('Pacific/Norfolk', '" . $time->stamp() . "'), ('Pacific/Noumea', '" . $time->stamp() . "'), ('Pacific/Pago_Pago', '" . $time->stamp() . "'), ('Pacific/Palau', '" . $time->stamp() . "'), ('Pacific/Pitcairn', '" . $time->stamp() . "'), ('Pacific/Pohnpei', '" . $time->stamp() . "'), ('Pacific/Ponape', '" . $time->stamp() . "'), ('Pacific/Port_Moresby', '" . $time->stamp() . "'), ('Pacific/Rarotonga', '" . $time->stamp() . "'), ('Pacific/Saipan', '" . $time->stamp() . "'), ('Pacific/Samoa', '" . $time->stamp() . "'), ('Pacific/Tahiti', '" . $time->stamp() . "'), ('Pacific/Tarawa', '" . $time->stamp() . "'), ('Pacific/Tongatapu', '" . $time->stamp() . "'), ('Pacific/Truk', '" . $time->stamp() . "'), ('Pacific/Wake', '" . $time->stamp() . "'), ('Pacific/Wallis', '" . $time->stamp() . "'), ('Pacific/Yap', '" . $time->stamp() . "'), ('Poland', '" . $time->stamp() . "'), ('Portugal', '" . $time->stamp() . "'), ('Singapore', '" . $time->stamp() . "'), ('Turkey', '" . $time->stamp() . "'), ('US/Alaska', '" . $time->stamp() . "'), ('US/Aleutian', '" . $time->stamp() . "'), ('US/Arizona', '" . $time->stamp() . "'), ('US/Central', '" . $time->stamp() . "'), ('US/East-Indiana', '" . $time->stamp() . "'), ('US/Eastern', '" . $time->stamp() . "'), ('US/Hawaii', '" . $time->stamp() . "'), ('US/Indiana-Starke', '" . $time->stamp() . "'), ('US/Michigan', '" . $time->stamp() . "'), ('US/Mountain', '" . $time->stamp() . "'), ('US/Pacific', '" . $time->stamp() . "'), ('US/Pacific-New', '" . $time->stamp() . "'), ('US/Samoa', '" . $time->stamp() . "'), ('Zulu', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0023',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0023';

}

// upgrade database from 2.0023 to 2.0024
if ($current_db_version === '2.0023') {

    $sql = "ALTER TABLE `settings`
            CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0024',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0024';

}

// upgrade database from 2.0024 to 2.0025
if ($current_db_version === '2.0024') {

    $sql = "CREATE TABLE IF NOT EXISTS `hosting` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `notes` LONGTEXT NOT NULL,
                `default_host` INT(1) NOT NULL DEFAULT '0',
                `active` INT(1) NOT NULL DEFAULT '1',
                `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
                `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `hosting`
            (`name`, `default_host`, `insert_time`)
            VALUES
            ('[no hosting]', 1, '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            ADD `hosting_id` INT(10) NOT NULL DEFAULT '1' AFTER `ip_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM hosting
            WHERE name = '[no hosting]'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {
        $temp_hosting_id = $row->id;
    }

    $sql = "UPDATE domains
            SET hosting_id = '" . $temp_hosting_id . "',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            CHANGE `owner_id` `owner_id` INT(5) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            CHANGE `registrar_id` `registrar_id` INT(5) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            CHANGE `account_id` `account_id` INT(5) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            CHANGE `dns_id` `dns_id` INT(5) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0025',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0025';

}

// upgrade database from 2.0025 to 2.0026
if ($current_db_version === '2.0025') {

    $sql = "ALTER TABLE `user_settings`
            ADD `display_domain_host` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_dns`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0026',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0026';

}

// upgrade database from 2.0026 to 2.0027
if ($current_db_version === '2.0026') {

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0027',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0027';

}

// upgrade database from 2.0027 to 2.0028
if ($current_db_version === '2.0027') {

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0028',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0028';

}

// upgrade database from 2.0028 to 2.0029
if ($current_db_version === '2.0028') {

    $sql = "ALTER TABLE `dns`
            ADD `ip1` VARCHAR(255) NOT NULL AFTER `dns10`,
            ADD `ip2` VARCHAR(255) NOT NULL AFTER `ip1`,
            ADD `ip3` VARCHAR(255) NOT NULL AFTER `ip2`,
            ADD `ip4` VARCHAR(255) NOT NULL AFTER `ip3`,
            ADD `ip5` VARCHAR(255) NOT NULL AFTER `ip4`,
            ADD `ip6` VARCHAR(255) NOT NULL AFTER `ip5`,
            ADD `ip7` VARCHAR(255) NOT NULL AFTER `ip6`,
            ADD `ip8` VARCHAR(255) NOT NULL AFTER `ip7`,
            ADD `ip9` VARCHAR(255) NOT NULL AFTER `ip8`,
            ADD `ip10` VARCHAR(255) NOT NULL AFTER `ip9`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            ADD `expiration_email_days` INT(3) NOT NULL DEFAULT '60' AFTER `timezone`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0029',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0029';

}

// upgrade database from 2.0029 to 2.003
if ($current_db_version === '2.0029') {

    $sql = "ALTER TABLE `domains`
            ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id, `status`, status_notes, notes
            FROM domains";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    while ($row = mysqli_fetch_object($result)) {

        if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

            $full_status = "";
            $full_status_notes = "";
            $new_notes = "";

            if ($row->status != "") {

                $full_status .= "--------------------\r\n";
                $full_status .= "OLD STATUS - INSERTED " . $time->stamp() . "\r\n";
                $full_status .= "The Status field was removed because it was redundant.\r\n";
                $full_status .= "--------------------\r\n";
                $full_status .= $row->status . "\r\n";
                $full_status .= "--------------------";

            } else {

                $full_status = "";

            }

            if ($row->status_notes != "") {

                $full_status_notes .= "--------------------\r\n";
                $full_status_notes .= "OLD STATUS NOTES - INSERTED " . $time->stamp() . "\r\n";
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

            $sql_update = "UPDATE domains
                           SET notes = '" . trim(mysqli_real_escape_string($connection, $new_notes)) . "',
                                  notes_fixed_temp = '1',
                               update_time = '" . $time->stamp() . "'
                           WHERE id = '" . $row->id . "'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

        } else {

            $sql_update = "UPDATE domains
                           SET notes_fixed_temp = '1',
                               update_time = '" . $time->stamp() . "'
                           WHERE id = '" . $row->id . "'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
        }

    }

    $sql = "SELECT *
            FROM domains
            WHERE notes_fixed_temp = '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        echo "DATABASE UPDATE v2.003 FAILED: PLEASE CONTACT YOUR " . strtoupper($software_title) . " ADMINISTRATOR IMMEDIATELY";
        exit;

    } else {

        $sql = "ALTER TABLE `domains`
                DROP `status`,
                DROP `status_notes`,
                DROP `notes_fixed_temp`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    }

    $sql = "UPDATE settings
            SET db_version = '2.003',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.003';

}

// upgrade database from 2.003 to 2.0031
if ($current_db_version === '2.003') {

    $sql = "ALTER TABLE `categories`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `currencies`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `dns`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `hosting`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ip_addresses`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `owners`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `registrars`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `registrar_accounts`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `segments`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_accounts`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_cert_types`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_providers`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_providers`
            DROP `active`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0031',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0031';

}

// upgrade database from 2.0031 to 2.0032
if ($current_db_version === '2.0031') {

    $sql = "ALTER TABLE `fees`
            ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE fees
            SET transfer_fee = initial_fee,
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql);

    // This section was made redundant by DB update v2.0033
    // (redundant code was here)

    $current_db_version = '2.0032';

}

// upgrade database from 2.0032 to 2.0033
if ($current_db_version === '2.0032') {

    $sql = "ALTER TABLE `ssl_fees`
            DROP `transfer_fee`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0033',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0033';

}

// upgrade database from 2.0033 to 2.0034
if ($current_db_version === '2.0033') {

    $sql = "ALTER TABLE `domains`
            CHANGE `owner_id` `owner_id` INT(10) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `domains`
            CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `domains`
            CHANGE `account_id` `account_id` INT(10) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `domains`
            CHANGE `dns_id` `dns_id` INT(10) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `fees`
            CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `registrar_accounts`
            CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_accounts`
            CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `account_id` `account_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_fees`
            CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_fees`
            CHANGE `type_id` `type_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0034',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0034';

}

// upgrade database from 2.0034 to 2.0035
if ($current_db_version === '2.0034') {

    $sql = "ALTER DATABASE " . $dbname . "
            CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE categories CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE currencies CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE dns CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE domains CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE hosting CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ip_addresses CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE owners CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE registrars CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE registrar_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE segments CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE segment_data CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_certs CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_cert_types CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_providers CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE timezones CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE users CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE user_settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE categories CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE currencies CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE dns CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE domains CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE hosting CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ip_addresses CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE owners CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE registrars CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE registrar_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE segments CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE segment_data CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_certs CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_cert_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE ssl_providers CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE timezones CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE user_settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0035',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0035';

}

// upgrade database from 2.0035 to 2.0036
if ($current_db_version === '2.0035') {

    $sql = "DROP TABLE `currency_data`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER DATABASE " . $dbname . "
            CHARACTER SET utf8
            DEFAULT CHARACTER SET utf8
            COLLATE utf8_unicode_ci
            DEFAULT COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `currencies`
            ADD `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `conversion`,
            ADD `symbol_order` INT(1) NOT NULL DEFAULT '0' AFTER `symbol`,
            ADD `symbol_space` INT(1) NOT NULL DEFAULT '0' AFTER `symbol_order`,
            ADD `newly_inserted` INT(1) NOT NULL DEFAULT '1' AFTER `symbol_space`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE currencies
            SET newly_inserted = '0',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
            ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `user_settings`
            ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET default_currency = 'USD',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE user_settings
            SET default_currency = 'USD',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "INSERT INTO currencies
            (name, currency, symbol, insert_time)
            VALUES
            ('Albania Lek', 'ALL', 'Lek', '" . $time->stamp() . "'),
            ('Afghanistan Afghani', 'AFN', '؋', '" . $time->stamp() . "'),
            ('Argentina Peso', 'ARS', '$', '" . $time->stamp() . "'),
            ('Aruba Guilder', 'AWG', 'ƒ', '" . $time->stamp() . "'),
            ('Australia Dollar', 'AUD', '$', '" . $time->stamp() . "'),
            ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $time->stamp() . "'),
            ('Bahamas Dollar', 'BSD', '$', '" . $time->stamp() . "'),
            ('Barbados Dollar', 'BBD', '$', '" . $time->stamp() . "'),
            ('Belarus Ruble', 'BYR', 'p.', '" . $time->stamp() . "'),
            ('Belize Dollar', 'BZD', 'BZ$', '" . $time->stamp() . "'),
            ('Bermuda Dollar', 'BMD', '$', '" . $time->stamp() . "'),
            ('Bolivia Boliviano', 'BOB', '\$b', '" . $time->stamp() . "'),
            ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $time->stamp() . "'),
            ('Botswana Pula', 'BWP', 'P', '" . $time->stamp() . "'),
            ('Bulgaria Lev', 'BGN', 'лв', '" . $time->stamp() . "'),
            ('Brazil Real', 'BRL', 'R$', '" . $time->stamp() . "'),
            ('Brunei Darussalam Dollar', 'BND', '$', '" . $time->stamp() . "'),
            ('Cambodia Riel', 'KHR', '៛', '" . $time->stamp() . "'),
            ('Canada Dollar', 'CAD', '$', '" . $time->stamp() . "'),
            ('Cayman Islands Dollar', 'KYD', '$', '" . $time->stamp() . "'),
            ('Chile Peso', 'CLP', '$', '" . $time->stamp() . "'),
            ('China Yuan Renminbi', 'CNY', '¥', '" . $time->stamp() . "'),
            ('Colombia Peso', 'COP', '$', '" . $time->stamp() . "'),
            ('Costa Rica Colon', 'CRC', '₡', '" . $time->stamp() . "'),
            ('Croatia Kuna', 'HRK', 'kn', '" . $time->stamp() . "'),
            ('Cuba Peso', 'CUP', '₱', '" . $time->stamp() . "'),
            ('Czech Republic Koruna', 'CZK', 'Kč', '" . $time->stamp() . "'),
            ('Denmark Krone', 'DKK', 'kr', '" . $time->stamp() . "'),
            ('Dominican Republic Peso', 'DOP', 'RD$', '" . $time->stamp() . "'),
            ('East Caribbean Dollar', 'XCD', '$', '" . $time->stamp() . "'),
            ('Egypt Pound', 'EGP', '£', '" . $time->stamp() . "'),
            ('El Salvador Colon', 'SVC', '$', '" . $time->stamp() . "'),
            ('Estonia Kroon', 'EEK', 'kr', '" . $time->stamp() . "'),
            ('Euro Member Countries', 'EUR', '€', '" . $time->stamp() . "'),
            ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $time->stamp() . "'),
            ('Fiji Dollar', 'FJD', '$', '" . $time->stamp() . "'),
            ('Ghana Cedis', 'GHC', '¢', '" . $time->stamp() . "'),
            ('Gibraltar Pound', 'GIP', '£', '" . $time->stamp() . "'),
            ('Guatemala Quetzal', 'GTQ', 'Q', '" . $time->stamp() . "'),
            ('Guernsey Pound', 'GGP', '£', '" . $time->stamp() . "'),
            ('Guyana Dollar', 'GYD', '$', '" . $time->stamp() . "'),
            ('Honduras Lempira', 'HNL', 'L', '" . $time->stamp() . "'),
            ('Hong Kong Dollar', 'HKD', '$', '" . $time->stamp() . "'),
            ('Hungary Forint', 'HUF', 'Ft', '" . $time->stamp() . "'),
            ('Iceland Krona', 'ISK', 'kr', '" . $time->stamp() . "'),
            ('India Rupee', 'INR', 'Rs', '" . $time->stamp() . "'),
            ('Indonesia Rupiah', 'IDR', 'Rp', '" . $time->stamp() . "'),
            ('Iran Rial', 'IRR', '﷼', '" . $time->stamp() . "'),
            ('Isle of Man Pound', 'IMP', '£', '" . $time->stamp() . "'),
            ('Israel Shekel', 'ILS', '₪', '" . $time->stamp() . "'),
            ('Jamaica Dollar', 'JMD', 'J$', '" . $time->stamp() . "'),
            ('Japan Yen', 'JPY', '¥', '" . $time->stamp() . "'),
            ('Jersey Pound', 'JEP', '£', '" . $time->stamp() . "'),
            ('Kazakhstan Tenge', 'KZT', 'лв', '" . $time->stamp() . "'),
            ('Korea (North) Won', 'KPW', '₩', '" . $time->stamp() . "'),
            ('Korea (South) Won', 'KRW', '₩', '" . $time->stamp() . "'),
            ('Kyrgyzstan Som', 'KGS', 'лв', '" . $time->stamp() . "'),
            ('Laos Kip', 'LAK', '₭', '" . $time->stamp() . "'),
            ('Latvia Lat', 'LVL', 'Ls', '" . $time->stamp() . "'),
            ('Lebanon Pound', 'LBP', '£', '" . $time->stamp() . "'),
            ('Liberia Dollar', 'LRD', '$', '" . $time->stamp() . "'),
            ('Lithuania Litas', 'LTL', 'Lt', '" . $time->stamp() . "'),
            ('Macedonia Denar', 'MKD', 'ден', '" . $time->stamp() . "'),
            ('Malaysia Ringgit', 'RM', 'RM', '" . $time->stamp() . "'),
            ('Mauritius Rupee', 'MUR', '₨', '" . $time->stamp() . "'),
            ('Mexico Peso', 'MXN', '$', '" . $time->stamp() . "'),
            ('Mongolia Tughrik', 'MNT', '₮', '" . $time->stamp() . "'),
            ('Mozambique Metical', 'MZN', 'MT', '" . $time->stamp() . "'),
            ('Namibia Dollar', 'NAD', '$', '" . $time->stamp() . "'),
            ('Nepal Rupee', 'NPR', '₨', '" . $time->stamp() . "'),
            ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $time->stamp() . "'),
            ('New Zealand Dollar', 'NZD', '$', '" . $time->stamp() . "'),
            ('Nicaragua Cordoba', 'NIO', 'C$', '" . $time->stamp() . "'),
            ('Nigeria Naira', 'NGN', '₦', '" . $time->stamp() . "'),
            ('Norway Krone', 'NOK', 'kr', '" . $time->stamp() . "'),
            ('Oman Rial', 'OMR', '﷼', '" . $time->stamp() . "'),
            ('Pakistan Rupee', 'PKR', '₨', '" . $time->stamp() . "'),
            ('Panama Balboa', 'PAB', 'B/.', '" . $time->stamp() . "'),
            ('Paraguay Guarani', 'PYG', 'Gs', '" . $time->stamp() . "'),
            ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $time->stamp() . "'),
            ('Philippines Peso', 'PHP', '₱', '" . $time->stamp() . "'),
            ('Poland Zloty', 'PLN', 'zł', '" . $time->stamp() . "'),
            ('Qatar Riyal', 'QAR', '﷼', '" . $time->stamp() . "'),
            ('Romania New Leu', 'RON', 'lei', '" . $time->stamp() . "'),
            ('Russia Ruble', 'RUB', 'руб', '" . $time->stamp() . "'),
            ('Saint Helena Pound', 'SHP', '£', '" . $time->stamp() . "'),
            ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $time->stamp() . "'),
            ('Serbia Dinar', 'RSD', 'Дин.', '" . $time->stamp() . "'),
            ('Seychelles Rupee', 'SCR', '₨', '" . $time->stamp() . "'),
            ('Singapore Dollar', 'SGD', '$', '" . $time->stamp() . "'),
            ('Solomon Islands Dollar', 'SBD', '$', '" . $time->stamp() . "'),
            ('Somalia Shilling', 'SOS', 'S', '" . $time->stamp() . "'),
            ('South Africa Rand', 'ZAR', 'R', '" . $time->stamp() . "'),
            ('Sri Lanka Rupee', 'LKR', '₨', '" . $time->stamp() . "'),
            ('Sweden Krona', 'SEK', 'kr', '" . $time->stamp() . "'),
            ('Switzerland Franc', 'CHF', 'CHF', '" . $time->stamp() . "'),
            ('Suriname Dollar', 'SRD', '$', '" . $time->stamp() . "'),
            ('Syria Pound', 'SYP', '£', '" . $time->stamp() . "'),
            ('Taiwan New Dollar', 'TWD', 'NT$', '" . $time->stamp() . "'),
            ('Thailand Baht', 'THB', '฿', '" . $time->stamp() . "'),
            ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $time->stamp() . "'),
            ('Turkey Lira', 'TRY', '₤', '" . $time->stamp() . "'),
            ('Tuvalu Dollar', 'TVD', '$', '" . $time->stamp() . "'),
            ('Ukraine Hryvna', 'UAH', '₴', '" . $time->stamp() . "'),
            ('United Kingdom Pound', 'GBP', '£', '" . $time->stamp() . "'),
            ('United States Dollar', 'USD', '$', '" . $time->stamp() . "'),
            ('Uruguay Peso', 'UYU', '\$U', '" . $time->stamp() . "'),
            ('Uzbekistan Som', 'UZS', 'лв', '" . $time->stamp() . "'),
            ('Venezuela Bolivar', 'VEF', 'Bs', '" . $time->stamp() . "'),
            ('Viet Nam Dong', 'VND', '₫', '" . $time->stamp() . "'),
            ('Yemen Rial', 'YER', '﷼', '" . $time->stamp() . "'),
            ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $time->stamp() . "'),
            ('Emirati Dirham', 'AED', 'د.إ', '" . $time->stamp() . "'),
            ('Malaysian Ringgit', 'MYR', 'RM', '" . $time->stamp() . "'),
            ('Kuwaiti Dinar', 'KWD', 'ك', '" . $time->stamp() . "'),
            ('Moroccan Dirham', 'MAD', 'م.', '" . $time->stamp() . "'),
            ('Iraqi Dinar', 'IQD', 'د.ع', '" . $time->stamp() . "'),
            ('Bangladeshi Taka', 'BDT', 'Tk', '" . $time->stamp() . "'),
            ('Bahraini Dinar', 'BHD', 'BD', '" . $time->stamp() . "'),
            ('Kenyan Shilling', 'KES', 'KSh', '" . $time->stamp() . "'),
            ('CFA Franc', 'XOF', 'CFA', '" . $time->stamp() . "'),
            ('Jordanian Dinar', 'JOD', 'JD', '" . $time->stamp() . "'),
            ('Tunisian Dinar', 'TND', 'د.ت', '" . $time->stamp() . "'),
            ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $time->stamp() . "'),
            ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $time->stamp() . "'),
            ('Algerian Dinar', 'DZD', 'دج', '" . $time->stamp() . "'),
            ('CFP Franc', 'XPF', 'F', '" . $time->stamp() . "'),
            ('Ugandan Shilling', 'UGX', 'USh', '" . $time->stamp() . "'),
            ('Tanzanian Shilling', 'TZS', 'TZS', '" . $time->stamp() . "'),
            ('Ethiopian Birr', 'ETB', 'Br', '" . $time->stamp() . "'),
            ('Georgian Lari', 'GEL', 'GEL', '" . $time->stamp() . "'),
            ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $time->stamp() . "'),
            ('Burmese Kyat', 'MMK', 'K', '" . $time->stamp() . "'),
            ('Libyan Dinar', 'LYD', 'LD', '" . $time->stamp() . "'),
            ('Zambian Kwacha', 'ZMK', 'ZK', '" . $time->stamp() . "'),
            ('Zambian Kwacha', 'ZMW', 'ZK', '" . $time->stamp() . "'),
            ('Macau Pataca', 'MOP', 'MOP$', '" . $time->stamp() . "'),
            ('Armenian Dram', 'AMD', 'AMD', '" . $time->stamp() . "'),
            ('Angolan Kwanza', 'AOA', 'Kz', '" . $time->stamp() . "'),
            ('Papua New Guinean Kina', 'PGK', 'K', '" . $time->stamp() . "'),
            ('Malagasy Ariary', 'MGA', 'Ar', '" . $time->stamp() . "'),
            ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $time->stamp() . "'),
            ('Sudanese Pound', 'SDG', 'SDG', '" . $time->stamp() . "'),
            ('Malawian Kwacha', 'MWK', 'MK', '" . $time->stamp() . "'),
            ('Rwandan Franc', 'RWF', 'FRw', '" . $time->stamp() . "'),
            ('Gambian Dalasi', 'GMD', 'D', '" . $time->stamp() . "'),
            ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $time->stamp() . "'),
            ('Congolese Franc', 'CDF', 'FC', '" . $time->stamp() . "'),
            ('Djiboutian Franc', 'DJF', 'Fdj', '" . $time->stamp() . "'),
            ('Haitian Gourde', 'HTG', 'G', '" . $time->stamp() . "'),
            ('Samoan Tala', 'WST', '$', '" . $time->stamp() . "'),
            ('Guinean Franc', 'GNF', 'FG', '" . $time->stamp() . "'),
            ('Cape Verdean Escudo', 'CVE', '$', '" . $time->stamp() . "'),
            ('Tongan Pa\'anga', 'TOP', 'T$', '" . $time->stamp() . "'),
            ('Moldovan Leu', 'MDL', 'MDL', '" . $time->stamp() . "'),
            ('Sierra Leonean Leone', 'SLL', 'Le', '" . $time->stamp() . "'),
            ('Burundian Franc', 'BIF', 'FBu', '" . $time->stamp() . "'),
            ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $time->stamp() . "'),
            ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $time->stamp() . "'),
            ('Swazi Lilangeni', 'SZL', 'SZL', '" . $time->stamp() . "'),
            ('Tajikistani Somoni', 'TJS', 'TJS', '" . $time->stamp() . "'),
            ('Turkmenistani Manat', 'TMT', 'm', '" . $time->stamp() . "'),
            ('Basotho Loti', 'LSL', 'LSL', '" . $time->stamp() . "'),
            ('Comoran Franc', 'KMF', 'CF', '" . $time->stamp() . "'),
            ('Sao Tomean Dobra', 'STD', 'STD', '" . $time->stamp() . "'),
            ('Seborgan Luigino', 'SPL', 'SPL', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id, currency
            FROM currencies
            WHERE newly_inserted = '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    while ($row = mysqli_fetch_object($result)) {

        $sql_find_new = "SELECT id, symbol
                         FROM currencies
                         WHERE newly_inserted = '1'
                           AND currency = '" . $row->currency . "'";
        $result_find_new = mysqli_query($connection, $sql_find_new);
        $total_results = mysqli_num_rows($result_find_new);

        while ($row_find_new = mysqli_fetch_object($result_find_new)) {

            if ($total_results > 0) {

                $sql_update_old = "UPDATE currencies
                                   SET symbol = '" . $row_find_new->symbol . "'
                                   WHERE id = '" . $row->id . "'";
                $result_update_old = mysqli_query($connection, $sql_update_old);

                $sql_delete_new = "DELETE FROM currencies
                                   WHERE id = '" . $row_find_new->id . "'";
                $result_delete_new = mysqli_query($connection, $sql_delete_new);

            }

        }

    }

    $sql = "ALTER TABLE `currencies`
            DROP `newly_inserted`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0036',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0036';

}

// upgrade database from 2.0036 to 2.0037
if ($current_db_version === '2.0036') {

    $sql = "SELECT currency
            FROM currencies
            WHERE default_currency = '1'";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {
        $temp_currency = $row->currency;
    }

    $sql = "UPDATE settings
            SET default_currency = '" . $temp_currency . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `currencies`
            DROP `default_currency`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `user_settings`
            DROP `default_currency`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0037',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0037';

}

// upgrade database from 2.0037 to 2.0038
if ($current_db_version === '2.0037') {

    $sql = "ALTER TABLE `user_settings`
            ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER user_id";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT default_currency
            FROM settings";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {
        $temp_default_currency = $row->default_currency;
    }

    $sql = "UPDATE user_settings
            SET default_currency = '" . $temp_default_currency . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `currency_id` INT(10) NOT NULL,
                `user_id` INT(10) NOT NULL,
                `conversion` FLOAT NOT NULL,
                `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
                `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
            FROM users";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_conversion = "SELECT id, conversion
                           FROM currencies
                           WHERE conversion != '0'";
        $result_conversion = mysqli_query($connection, $sql_conversion);

        while ($row_conversion = mysqli_fetch_object($result_conversion)) {

            $sql_insert = "INSERT INTO currency_conversions
                           (currency_id, user_id, conversion, insert_time, update_time)
                           VALUES
                           ('" . $row_conversion->id . "', '" . $row->id . "', '" . $row_conversion->conversion . "', '" . $time->stamp() . "', '" . $time->stamp() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

    }

    $sql = "ALTER TABLE `currencies`
            DROP `conversion`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0038',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0038';

}

//@formatter:on

<?php
/**
 * /_includes/updates/2.0022-2.0037.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
<?php
// upgrade database from 2.0022 to 2.0023
if ($current_db_version === '2.0022') {

    $sql = "CREATE TABLE IF NOT EXISTS `timezones` (
                `id` INT(5) NOT NULL AUTO_INCREMENT,
                `timezone` VARCHAR(50) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `timezones`
                (`timezone`, `insert_time`) VALUES
                ('Africa/Abidjan', '" . $time->time() . "'), ('Africa/Accra', '" . $time->time() . "'), ('Africa/Addis_Ababa', '" . $time->time() . "'), ('Africa/Algiers', '" . $time->time() . "'), ('Africa/Asmara', '" . $time->time() . "'), ('Africa/Asmera', '" . $time->time() . "'), ('Africa/Bamako', '" . $time->time() . "'), ('Africa/Bangui', '" . $time->time() . "'), ('Africa/Banjul', '" . $time->time() . "'), ('Africa/Bissau', '" . $time->time() . "'), ('Africa/Blantyre', '" . $time->time() . "'), ('Africa/Brazzaville', '" . $time->time() . "'), ('Africa/Bujumbura', '" . $time->time() . "'), ('Africa/Cairo', '" . $time->time() . "'), ('Africa/Casablanca', '" . $time->time() . "'), ('Africa/Ceuta', '" . $time->time() . "'), ('Africa/Conakry', '" . $time->time() . "'), ('Africa/Dakar', '" . $time->time() . "'), ('Africa/Dar_es_Salaam', '" . $time->time() . "'), ('Africa/Djibouti', '" . $time->time() . "'), ('Africa/Douala', '" . $time->time() . "'), ('Africa/El_Aaiun', '" . $time->time() . "'), ('Africa/Freetown', '" . $time->time() . "'), ('Africa/Gaborone', '" . $time->time() . "'), ('Africa/Harare', '" . $time->time() . "'), ('Africa/Johannesburg', '" . $time->time() . "'), ('Africa/Juba', '" . $time->time() . "'), ('Africa/Kampala', '" . $time->time() . "'), ('Africa/Khartoum', '" . $time->time() . "'), ('Africa/Kigali', '" . $time->time() . "'), ('Africa/Kinshasa', '" . $time->time() . "'), ('Africa/Lagos', '" . $time->time() . "'), ('Africa/Libreville', '" . $time->time() . "'), ('Africa/Lome', '" . $time->time() . "'), ('Africa/Luanda', '" . $time->time() . "'), ('Africa/Lubumbashi', '" . $time->time() . "'), ('Africa/Lusaka', '" . $time->time() . "'), ('Africa/Malabo', '" . $time->time() . "'), ('Africa/Maputo', '" . $time->time() . "'), ('Africa/Maseru', '" . $time->time() . "'), ('Africa/Mbabane', '" . $time->time() . "'), ('Africa/Mogadishu', '" . $time->time() . "'), ('Africa/Monrovia', '" . $time->time() . "'), ('Africa/Nairobi', '" . $time->time() . "'), ('Africa/Ndjamena', '" . $time->time() . "'), ('Africa/Niamey', '" . $time->time() . "'), ('Africa/Nouakchott', '" . $time->time() . "'), ('Africa/Ouagadougou', '" . $time->time() . "'), ('Africa/Porto-Novo', '" . $time->time() . "'), ('Africa/Sao_Tome', '" . $time->time() . "'), ('Africa/Timbuktu', '" . $time->time() . "'), ('Africa/Tripoli', '" . $time->time() . "'), ('Africa/Tunis', '" . $time->time() . "'), ('Africa/Windhoek', '" . $time->time() . "'), ('America/Adak', '" . $time->time() . "'), ('America/Anchorage', '" . $time->time() . "'), ('America/Anguilla', '" . $time->time() . "'), ('America/Antigua', '" . $time->time() . "'), ('America/Araguaina', '" . $time->time() . "'), ('America/Argentina/Buenos_Aires', '" . $time->time() . "'), ('America/Argentina/Catamarca', '" . $time->time() . "'), ('America/Argentina/ComodRivadavia', '" . $time->time() . "'), ('America/Argentina/Cordoba', '" . $time->time() . "'), ('America/Argentina/Jujuy', '" . $time->time() . "'), ('America/Argentina/La_Rioja', '" . $time->time() . "'), ('America/Argentina/Mendoza', '" . $time->time() . "'), ('America/Argentina/Rio_Gallegos', '" . $time->time() . "'), ('America/Argentina/Salta', '" . $time->time() . "'), ('America/Argentina/San_Juan', '" . $time->time() . "'), ('America/Argentina/San_Luis', '" . $time->time() . "'), ('America/Argentina/Tucuman', '" . $time->time() . "'), ('America/Argentina/Ushuaia', '" . $time->time() . "'), ('America/Aruba', '" . $time->time() . "'), ('America/Asuncion', '" . $time->time() . "'), ('America/Atikokan', '" . $time->time() . "'), ('America/Atka', '" . $time->time() . "'), ('America/Bahia', '" . $time->time() . "'), ('America/Bahia_Banderas', '" . $time->time() . "'), ('America/Barbados', '" . $time->time() . "'), ('America/Belem', '" . $time->time() . "'), ('America/Belize', '" . $time->time() . "'), ('America/Blanc-Sablon', '" . $time->time() . "'), ('America/Boa_Vista', '" . $time->time() . "'), ('America/Bogota', '" . $time->time() . "'), ('America/Boise', '" . $time->time() . "'), ('America/Buenos_Aires', '" . $time->time() . "'), ('America/Cambridge_Bay', '" . $time->time() . "'), ('America/Campo_Grande', '" . $time->time() . "'), ('America/Cancun', '" . $time->time() . "'), ('America/Caracas', '" . $time->time() . "'), ('America/Catamarca', '" . $time->time() . "'), ('America/Cayenne', '" . $time->time() . "'), ('America/Cayman', '" . $time->time() . "'), ('America/Chicago', '" . $time->time() . "'), ('America/Chihuahua', '" . $time->time() . "'), ('America/Coral_Harbour', '" . $time->time() . "'), ('America/Cordoba', '" . $time->time() . "'), ('America/Costa_Rica', '" . $time->time() . "'), ('America/Creston', '" . $time->time() . "'), ('America/Cuiaba', '" . $time->time() . "'), ('America/Curacao', '" . $time->time() . "'), ('America/Danmarkshavn', '" . $time->time() . "'), ('America/Dawson', '" . $time->time() . "'), ('America/Dawson_Creek', '" . $time->time() . "'), ('America/Denver', '" . $time->time() . "'), ('America/Detroit', '" . $time->time() . "'), ('America/Dominica', '" . $time->time() . "'), ('America/Edmonton', '" . $time->time() . "'), ('America/Eirunepe', '" . $time->time() . "'), ('America/El_Salvador', '" . $time->time() . "'), ('America/Ensenada', '" . $time->time() . "'), ('America/Fort_Wayne', '" . $time->time() . "'), ('America/Fortaleza', '" . $time->time() . "'), ('America/Glace_Bay', '" . $time->time() . "'), ('America/Godthab', '" . $time->time() . "'), ('America/Goose_Bay', '" . $time->time() . "'), ('America/Grand_Turk', '" . $time->time() . "'), ('America/Grenada', '" . $time->time() . "'), ('America/Guadeloupe', '" . $time->time() . "'), ('America/Guatemala', '" . $time->time() . "'), ('America/Guayaquil', '" . $time->time() . "'), ('America/Guyana', '" . $time->time() . "'), ('America/Halifax', '" . $time->time() . "'), ('America/Havana', '" . $time->time() . "'), ('America/Hermosillo', '" . $time->time() . "'), ('America/Indiana/Indianapolis', '" . $time->time() . "'), ('America/Indiana/Knox', '" . $time->time() . "'), ('America/Indiana/Marengo', '" . $time->time() . "'), ('America/Indiana/Petersburg', '" . $time->time() . "'), ('America/Indiana/Tell_City', '" . $time->time() . "'), ('America/Indiana/Vevay', '" . $time->time() . "'), ('America/Indiana/Vincennes', '" . $time->time() . "'), ('America/Indiana/Winamac', '" . $time->time() . "'), ('America/Indianapolis', '" . $time->time() . "'), ('America/Inuvik', '" . $time->time() . "'), ('America/Iqaluit', '" . $time->time() . "'), ('America/Jamaica', '" . $time->time() . "'), ('America/Jujuy', '" . $time->time() . "'), ('America/Juneau', '" . $time->time() . "'), ('America/Kentucky/Louisville', '" . $time->time() . "'), ('America/Kentucky/Monticello', '" . $time->time() . "'), ('America/Knox_IN', '" . $time->time() . "'), ('America/Kralendijk', '" . $time->time() . "'), ('America/La_Paz', '" . $time->time() . "'), ('America/Lima', '" . $time->time() . "'), ('America/Los_Angeles', '" . $time->time() . "'), ('America/Louisville', '" . $time->time() . "'), ('America/Lower_Princes', '" . $time->time() . "'), ('America/Maceio', '" . $time->time() . "'), ('America/Managua', '" . $time->time() . "'), ('America/Manaus', '" . $time->time() . "'), ('America/Marigot', '" . $time->time() . "'), ('America/Martinique', '" . $time->time() . "'), ('America/Matamoros', '" . $time->time() . "'), ('America/Mazatlan', '" . $time->time() . "'), ('America/Mendoza', '" . $time->time() . "'), ('America/Menominee', '" . $time->time() . "'), ('America/Merida', '" . $time->time() . "'), ('America/Metlakatla', '" . $time->time() . "'), ('America/Mexico_City', '" . $time->time() . "'), ('America/Miquelon', '" . $time->time() . "'), ('America/Moncton', '" . $time->time() . "'), ('America/Monterrey', '" . $time->time() . "'), ('America/Montevideo', '" . $time->time() . "'), ('America/Montreal', '" . $time->time() . "'), ('America/Montserrat', '" . $time->time() . "'), ('America/Nassau', '" . $time->time() . "'), ('America/New_York', '" . $time->time() . "'), ('America/Nipigon', '" . $time->time() . "'), ('America/Nome', '" . $time->time() . "'), ('America/Noronha', '" . $time->time() . "'), ('America/North_Dakota/Beulah', '" . $time->time() . "'), ('America/North_Dakota/Center', '" . $time->time() . "'), ('America/North_Dakota/New_Salem', '" . $time->time() . "'), ('America/Ojinaga', '" . $time->time() . "'), ('America/Panama', '" . $time->time() . "'), ('America/Pangnirtung', '" . $time->time() . "'), ('America/Paramaribo', '" . $time->time() . "'), ('America/Phoenix', '" . $time->time() . "'), ('America/Port-au-Prince', '" . $time->time() . "'), ('America/Port_of_Spain', '" . $time->time() . "'), ('America/Porto_Acre', '" . $time->time() . "'), ('America/Porto_Velho', '" . $time->time() . "'), ('America/Puerto_Rico', '" . $time->time() . "'), ('America/Rainy_River', '" . $time->time() . "'), ('America/Rankin_Inlet', '" . $time->time() . "'), ('America/Recife', '" . $time->time() . "'), ('America/Regina', '" . $time->time() . "'), ('America/Resolute', '" . $time->time() . "'), ('America/Rio_Branco', '" . $time->time() . "'), ('America/Rosario', '" . $time->time() . "'), ('America/Santa_Isabel', '" . $time->time() . "'), ('America/Santarem', '" . $time->time() . "'), ('America/Santiago', '" . $time->time() . "'), ('America/Santo_Domingo', '" . $time->time() . "'), ('America/Sao_Paulo', '" . $time->time() . "'), ('America/Scoresbysund', '" . $time->time() . "'), ('America/Shiprock', '" . $time->time() . "'), ('America/Sitka', '" . $time->time() . "'), ('America/St_Barthelemy', '" . $time->time() . "'), ('America/St_Johns', '" . $time->time() . "'), ('America/St_Kitts', '" . $time->time() . "'), ('America/St_Lucia', '" . $time->time() . "'), ('America/St_Thomas', '" . $time->time() . "'), ('America/St_Vincent', '" . $time->time() . "'), ('America/Swift_Current', '" . $time->time() . "'), ('America/Tegucigalpa', '" . $time->time() . "'), ('America/Thule', '" . $time->time() . "'), ('America/Thunder_Bay', '" . $time->time() . "'), ('America/Tijuana', '" . $time->time() . "'), ('America/Toronto', '" . $time->time() . "'), ('America/Tortola', '" . $time->time() . "'), ('America/Vancouver', '" . $time->time() . "'), ('America/Virgin', '" . $time->time() . "'), ('America/Whitehorse', '" . $time->time() . "'), ('America/Winnipeg', '" . $time->time() . "'), ('America/Yakutat', '" . $time->time() . "'), ('America/Yellowknife', '" . $time->time() . "'), ('Antarctica/Casey', '" . $time->time() . "'), ('Antarctica/Davis', '" . $time->time() . "'), ('Antarctica/DumontDUrville', '" . $time->time() . "'), ('Antarctica/Macquarie', '" . $time->time() . "'), ('Antarctica/Mawson', '" . $time->time() . "'), ('Antarctica/McMurdo', '" . $time->time() . "'), ('Antarctica/Palmer', '" . $time->time() . "'), ('Antarctica/Rothera', '" . $time->time() . "'), ('Antarctica/South_Pole', '" . $time->time() . "'), ('Antarctica/Syowa', '" . $time->time() . "'), ('Antarctica/Vostok', '" . $time->time() . "'), ('Arctic/Longyearbyen', '" . $time->time() . "'), ('Asia/Aden', '" . $time->time() . "'), ('Asia/Almaty', '" . $time->time() . "'), ('Asia/Amman', '" . $time->time() . "'), ('Asia/Anadyr', '" . $time->time() . "'), ('Asia/Aqtau', '" . $time->time() . "'), ('Asia/Aqtobe', '" . $time->time() . "'), ('Asia/Ashgabat', '" . $time->time() . "'), ('Asia/Ashkhabad', '" . $time->time() . "'), ('Asia/Baghdad', '" . $time->time() . "'), ('Asia/Bahrain', '" . $time->time() . "'), ('Asia/Baku', '" . $time->time() . "'), ('Asia/Bangkok', '" . $time->time() . "'), ('Asia/Beirut', '" . $time->time() . "'), ('Asia/Bishkek', '" . $time->time() . "'), ('Asia/Brunei', '" . $time->time() . "'), ('Asia/Calcutta', '" . $time->time() . "'), ('Asia/Choibalsan', '" . $time->time() . "'), ('Asia/Chongqing', '" . $time->time() . "'), ('Asia/Chungking', '" . $time->time() . "'), ('Asia/Colombo', '" . $time->time() . "'), ('Asia/Dacca', '" . $time->time() . "'), ('Asia/Damascus', '" . $time->time() . "'), ('Asia/Dhaka', '" . $time->time() . "'), ('Asia/Dili', '" . $time->time() . "'), ('Asia/Dubai', '" . $time->time() . "'), ('Asia/Dushanbe', '" . $time->time() . "'), ('Asia/Gaza', '" . $time->time() . "'), ('Asia/Harbin', '" . $time->time() . "'), ('Asia/Hebron', '" . $time->time() . "'), ('Asia/Ho_Chi_Minh', '" . $time->time() . "'), ('Asia/Hong_Kong', '" . $time->time() . "'), ('Asia/Hovd', '" . $time->time() . "'), ('Asia/Irkutsk', '" . $time->time() . "'), ('Asia/Istanbul', '" . $time->time() . "'), ('Asia/Jakarta', '" . $time->time() . "'), ('Asia/Jayapura', '" . $time->time() . "'), ('Asia/Jerusalem', '" . $time->time() . "'), ('Asia/Kabul', '" . $time->time() . "'), ('Asia/Kamchatka', '" . $time->time() . "'), ('Asia/Karachi', '" . $time->time() . "'), ('Asia/Kashgar', '" . $time->time() . "'), ('Asia/Kathmandu', '" . $time->time() . "'), ('Asia/Katmandu', '" . $time->time() . "'), ('Asia/Khandyga', '" . $time->time() . "'), ('Asia/Kolkata', '" . $time->time() . "'), ('Asia/Krasnoyarsk', '" . $time->time() . "'), ('Asia/Kuala_Lumpur', '" . $time->time() . "'), ('Asia/Kuching', '" . $time->time() . "'), ('Asia/Kuwait', '" . $time->time() . "'), ('Asia/Macao', '" . $time->time() . "'), ('Asia/Macau', '" . $time->time() . "'), ('Asia/Magadan', '" . $time->time() . "'), ('Asia/Makassar', '" . $time->time() . "'), ('Asia/Manila', '" . $time->time() . "'), ('Asia/Muscat', '" . $time->time() . "'), ('Asia/Nicosia', '" . $time->time() . "'), ('Asia/Novokuznetsk', '" . $time->time() . "'), ('Asia/Novosibirsk', '" . $time->time() . "'), ('Asia/Omsk', '" . $time->time() . "'), ('Asia/Oral', '" . $time->time() . "'), ('Asia/Phnom_Penh', '" . $time->time() . "'), ('Asia/Pontianak', '" . $time->time() . "'), ('Asia/Pyongyang', '" . $time->time() . "'), ('Asia/Qatar', '" . $time->time() . "'), ('Asia/Qyzylorda', '" . $time->time() . "'), ('Asia/Rangoon', '" . $time->time() . "'), ('Asia/Riyadh', '" . $time->time() . "'), ('Asia/Saigon', '" . $time->time() . "'), ('Asia/Sakhalin', '" . $time->time() . "'), ('Asia/Samarkand', '" . $time->time() . "'), ('Asia/Seoul', '" . $time->time() . "'), ('Asia/Shanghai', '" . $time->time() . "'), ('Asia/Singapore', '" . $time->time() . "'), ('Asia/Taipei', '" . $time->time() . "'), ('Asia/Tashkent', '" . $time->time() . "'), ('Asia/Tbilisi', '" . $time->time() . "'), ('Asia/Tehran', '" . $time->time() . "'), ('Asia/Tel_Aviv', '" . $time->time() . "'), ('Asia/Thimbu', '" . $time->time() . "'), ('Asia/Thimphu', '" . $time->time() . "'), ('Asia/Tokyo', '" . $time->time() . "'), ('Asia/Ujung_Pandang', '" . $time->time() . "'), ('Asia/Ulaanbaatar', '" . $time->time() . "'), ('Asia/Ulan_Bator', '" . $time->time() . "'), ('Asia/Urumqi', '" . $time->time() . "'), ('Asia/Ust-Nera', '" . $time->time() . "'), ('Asia/Vientiane', '" . $time->time() . "'), ('Asia/Vladivostok', '" . $time->time() . "'), ('Asia/Yakutsk', '" . $time->time() . "'), ('Asia/Yekaterinburg', '" . $time->time() . "'), ('Asia/Yerevan', '" . $time->time() . "'), ('Atlantic/Azores', '" . $time->time() . "'), ('Atlantic/Bermuda', '" . $time->time() . "'), ('Atlantic/Canary', '" . $time->time() . "'), ('Atlantic/Cape_Verde', '" . $time->time() . "'), ('Atlantic/Faeroe', '" . $time->time() . "'), ('Atlantic/Faroe', '" . $time->time() . "'), ('Atlantic/Jan_Mayen', '" . $time->time() . "'), ('Atlantic/Madeira', '" . $time->time() . "'), ('Atlantic/Reykjavik', '" . $time->time() . "'), ('Atlantic/South_Georgia', '" . $time->time() . "'), ('Atlantic/St_Helena', '" . $time->time() . "'), ('Atlantic/Stanley', '" . $time->time() . "'), ('Australia/ACT', '" . $time->time() . "'), ('Australia/Adelaide', '" . $time->time() . "'), ('Australia/Brisbane', '" . $time->time() . "'), ('Australia/Broken_Hill', '" . $time->time() . "'), ('Australia/Canberra', '" . $time->time() . "'), ('Australia/Currie', '" . $time->time() . "'), ('Australia/Darwin', '" . $time->time() . "'), ('Australia/Eucla', '" . $time->time() . "'), ('Australia/Hobart', '" . $time->time() . "'), ('Australia/LHI', '" . $time->time() . "'), ('Australia/Lindeman', '" . $time->time() . "'), ('Australia/Lord_Howe', '" . $time->time() . "'), ('Australia/Melbourne', '" . $time->time() . "'), ('Australia/North', '" . $time->time() . "'), ('Australia/NSW', '" . $time->time() . "'), ('Australia/Perth', '" . $time->time() . "'), ('Australia/Queensland', '" . $time->time() . "'), ('Australia/South', '" . $time->time() . "'), ('Australia/Sydney', '" . $time->time() . "'), ('Australia/Tasmania', '" . $time->time() . "'), ('Australia/Victoria', '" . $time->time() . "'), ('Australia/West', '" . $time->time() . "'), ('Australia/Yancowinna', '" . $time->time() . "'), ('Brazil/Acre', '" . $time->time() . "'), ('Brazil/DeNoronha', '" . $time->time() . "'), ('Brazil/East', '" . $time->time() . "'), ('Brazil/West', '" . $time->time() . "'), ('Canada/Atlantic', '" . $time->time() . "'), ('Canada/Central', '" . $time->time() . "'), ('Canada/East-Saskatchewan', '" . $time->time() . "'), ('Canada/Eastern', '" . $time->time() . "'), ('Canada/Mountain', '" . $time->time() . "'), ('Canada/Newfoundland', '" . $time->time() . "'), ('Canada/Pacific', '" . $time->time() . "'), ('Canada/Saskatchewan', '" . $time->time() . "'), ('Canada/Yukon', '" . $time->time() . "'), ('Chile/Continental', '" . $time->time() . "'), ('Chile/EasterIsland', '" . $time->time() . "'), ('Cuba', '" . $time->time() . "'), ('Egypt', '" . $time->time() . "'), ('Eire', '" . $time->time() . "'), ('Europe/Amsterdam', '" . $time->time() . "'), ('Europe/Andorra', '" . $time->time() . "'), ('Europe/Athens', '" . $time->time() . "'), ('Europe/Belfast', '" . $time->time() . "'), ('Europe/Belgrade', '" . $time->time() . "'), ('Europe/Berlin', '" . $time->time() . "'), ('Europe/Bratislava', '" . $time->time() . "'), ('Europe/Brussels', '" . $time->time() . "'), ('Europe/Bucharest', '" . $time->time() . "'), ('Europe/Budapest', '" . $time->time() . "'), ('Europe/Busingen', '" . $time->time() . "'), ('Europe/Chisinau', '" . $time->time() . "'), ('Europe/Copenhagen', '" . $time->time() . "'), ('Europe/Dublin', '" . $time->time() . "'), ('Europe/Gibraltar', '" . $time->time() . "'), ('Europe/Guernsey', '" . $time->time() . "'), ('Europe/Helsinki', '" . $time->time() . "'), ('Europe/Isle_of_Man', '" . $time->time() . "'), ('Europe/Istanbul', '" . $time->time() . "'), ('Europe/Jersey', '" . $time->time() . "'), ('Europe/Kaliningrad', '" . $time->time() . "'), ('Europe/Kiev', '" . $time->time() . "'), ('Europe/Lisbon', '" . $time->time() . "'), ('Europe/Ljubljana', '" . $time->time() . "'), ('Europe/London', '" . $time->time() . "'), ('Europe/Luxembourg', '" . $time->time() . "'), ('Europe/Madrid', '" . $time->time() . "'), ('Europe/Malta', '" . $time->time() . "'), ('Europe/Mariehamn', '" . $time->time() . "'), ('Europe/Minsk', '" . $time->time() . "'), ('Europe/Monaco', '" . $time->time() . "'), ('Europe/Moscow', '" . $time->time() . "'), ('Europe/Nicosia', '" . $time->time() . "'), ('Europe/Oslo', '" . $time->time() . "'), ('Europe/Paris', '" . $time->time() . "'), ('Europe/Podgorica', '" . $time->time() . "'), ('Europe/Prague', '" . $time->time() . "'), ('Europe/Riga', '" . $time->time() . "'), ('Europe/Rome', '" . $time->time() . "'), ('Europe/Samara', '" . $time->time() . "'), ('Europe/San_Marino', '" . $time->time() . "'), ('Europe/Sarajevo', '" . $time->time() . "'), ('Europe/Simferopol', '" . $time->time() . "'), ('Europe/Skopje', '" . $time->time() . "'), ('Europe/Sofia', '" . $time->time() . "'), ('Europe/Stockholm', '" . $time->time() . "'), ('Europe/Tallinn', '" . $time->time() . "'), ('Europe/Tirane', '" . $time->time() . "'), ('Europe/Tiraspol', '" . $time->time() . "'), ('Europe/Uzhgorod', '" . $time->time() . "'), ('Europe/Vaduz', '" . $time->time() . "'), ('Europe/Vatican', '" . $time->time() . "'), ('Europe/Vienna', '" . $time->time() . "'), ('Europe/Vilnius', '" . $time->time() . "'), ('Europe/Volgograd', '" . $time->time() . "'), ('Europe/Warsaw', '" . $time->time() . "'), ('Europe/Zagreb', '" . $time->time() . "'), ('Europe/Zaporozhye', '" . $time->time() . "'), ('Europe/Zurich', '" . $time->time() . "'), ('Greenwich', '" . $time->time() . "'), ('Hongkong', '" . $time->time() . "'), ('Iceland', '" . $time->time() . "'), ('Indian/Antananarivo', '" . $time->time() . "'), ('Indian/Chagos', '" . $time->time() . "'), ('Indian/Christmas', '" . $time->time() . "'), ('Indian/Cocos', '" . $time->time() . "'), ('Indian/Comoro', '" . $time->time() . "'), ('Indian/Kerguelen', '" . $time->time() . "'), ('Indian/Mahe', '" . $time->time() . "'), ('Indian/Maldives', '" . $time->time() . "'), ('Indian/Mauritius', '" . $time->time() . "'), ('Indian/Mayotte', '" . $time->time() . "'), ('Indian/Reunion', '" . $time->time() . "'), ('Iran', '" . $time->time() . "'), ('Israel', '" . $time->time() . "'), ('Jamaica', '" . $time->time() . "'), ('Japan', '" . $time->time() . "'), ('Kwajalein', '" . $time->time() . "'), ('Libya', '" . $time->time() . "'), ('Mexico/BajaNorte', '" . $time->time() . "'), ('Mexico/BajaSur', '" . $time->time() . "'), ('Mexico/General', '" . $time->time() . "'), ('Pacific/Apia', '" . $time->time() . "'), ('Pacific/Auckland', '" . $time->time() . "'), ('Pacific/Chatham', '" . $time->time() . "'), ('Pacific/Chuuk', '" . $time->time() . "'), ('Pacific/Easter', '" . $time->time() . "'), ('Pacific/Efate', '" . $time->time() . "'), ('Pacific/Enderbury', '" . $time->time() . "'), ('Pacific/Fakaofo', '" . $time->time() . "'), ('Pacific/Fiji', '" . $time->time() . "'), ('Pacific/Funafuti', '" . $time->time() . "'), ('Pacific/Galapagos', '" . $time->time() . "'), ('Pacific/Gambier', '" . $time->time() . "'), ('Pacific/Guadalcanal', '" . $time->time() . "'), ('Pacific/Guam', '" . $time->time() . "'), ('Pacific/Honolulu', '" . $time->time() . "'), ('Pacific/Johnston', '" . $time->time() . "'), ('Pacific/Kiritimati', '" . $time->time() . "'), ('Pacific/Kosrae', '" . $time->time() . "'), ('Pacific/Kwajalein', '" . $time->time() . "'), ('Pacific/Majuro', '" . $time->time() . "'), ('Pacific/Marquesas', '" . $time->time() . "'), ('Pacific/Midway', '" . $time->time() . "'), ('Pacific/Nauru', '" . $time->time() . "'), ('Pacific/Niue', '" . $time->time() . "'), ('Pacific/Norfolk', '" . $time->time() . "'), ('Pacific/Noumea', '" . $time->time() . "'), ('Pacific/Pago_Pago', '" . $time->time() . "'), ('Pacific/Palau', '" . $time->time() . "'), ('Pacific/Pitcairn', '" . $time->time() . "'), ('Pacific/Pohnpei', '" . $time->time() . "'), ('Pacific/Ponape', '" . $time->time() . "'), ('Pacific/Port_Moresby', '" . $time->time() . "'), ('Pacific/Rarotonga', '" . $time->time() . "'), ('Pacific/Saipan', '" . $time->time() . "'), ('Pacific/Samoa', '" . $time->time() . "'), ('Pacific/Tahiti', '" . $time->time() . "'), ('Pacific/Tarawa', '" . $time->time() . "'), ('Pacific/Tongatapu', '" . $time->time() . "'), ('Pacific/Truk', '" . $time->time() . "'), ('Pacific/Wake', '" . $time->time() . "'), ('Pacific/Wallis', '" . $time->time() . "'), ('Pacific/Yap', '" . $time->time() . "'), ('Poland', '" . $time->time() . "'), ('Portugal', '" . $time->time() . "'), ('Singapore', '" . $time->time() . "'), ('Turkey', '" . $time->time() . "'), ('US/Alaska', '" . $time->time() . "'), ('US/Aleutian', '" . $time->time() . "'), ('US/Arizona', '" . $time->time() . "'), ('US/Central', '" . $time->time() . "'), ('US/East-Indiana', '" . $time->time() . "'), ('US/Eastern', '" . $time->time() . "'), ('US/Hawaii', '" . $time->time() . "'), ('US/Indiana-Starke', '" . $time->time() . "'), ('US/Michigan', '" . $time->time() . "'), ('US/Mountain', '" . $time->time() . "'), ('US/Pacific', '" . $time->time() . "'), ('US/Pacific-New', '" . $time->time() . "'), ('US/Samoa', '" . $time->time() . "'), ('Zulu', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0023',
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `hosting`
                    (`name`, `default_host`, `insert_time`) VALUES
                    ('[no hosting]', 1, '" . $time->time() . "');";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0029';

}

// upgrade database from 2.0029 to 2.003
if ($current_db_version === '2.0029') {

    $sql = "ALTER TABLE `domains`
                    ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id, status, status_notes, notes
                FROM domains";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    while ($row = mysqli_fetch_object($result)) {

        if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

            $full_status = "";
            $full_status_notes = "";
            $new_notes = "";

            if ($row->status != "") {

                $full_status .= "--------------------\r\n";
                $full_status .= "OLD STATUS - INSERTED " . $time->time() . "\r\n";
                $full_status .= "The Status field was removed because it was redundant.\r\n";
                $full_status .= "--------------------\r\n";
                $full_status .= $row->status . "\r\n";
                $full_status .= "--------------------";

            } else {

                $full_status = "";

            }

            if ($row->status_notes != "") {

                $full_status_notes .= "--------------------\r\n";
                $full_status_notes .= "OLD STATUS NOTES - INSERTED " . $time->time() . "\r\n";
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
                                   update_time = '" . $time->time() . "'
                               WHERE id = '" . $row->id . "'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

        } else {

            $sql_update = "UPDATE domains
                               SET notes_fixed_temp = '1',
                                   update_time = '" . $time->time() . "'
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql);

    // This section was made redundant by DB update v2.0033
    /*
    $sql = "ALTER TABLE `ssl_fees`
            ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
    $result = mysqli_query($connection, $sql);
    */

    $sql = "UPDATE settings
                SET db_version = '2.0032',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0032';

}

// upgrade database from 2.0032 to 2.0033
if ($current_db_version === '2.0032') {

    $sql = "ALTER TABLE `ssl_fees`
                DROP `transfer_fee`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET db_version = '2.0033',
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
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
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET default_currency = '" . $_SESSION['s_default_currency'] . "',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE user_settings
                SET default_currency = '" . $_SESSION['s_default_currency'] . "',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "INSERT INTO currencies
                (name, currency, symbol, insert_time) VALUES
                ('Albania Lek', 'ALL', 'Lek', '" . $time->time() . "'),
                ('Afghanistan Afghani', 'AFN', '؋', '" . $time->time() . "'),
                ('Argentina Peso', 'ARS', '$', '" . $time->time() . "'),
                ('Aruba Guilder', 'AWG', 'ƒ', '" . $time->time() . "'),
                ('Australia Dollar', 'AUD', '$', '" . $time->time() . "'),
                ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $time->time() . "'),
                ('Bahamas Dollar', 'BSD', '$', '" . $time->time() . "'),
                ('Barbados Dollar', 'BBD', '$', '" . $time->time() . "'),
                ('Belarus Ruble', 'BYR', 'p.', '" . $time->time() . "'),
                ('Belize Dollar', 'BZD', 'BZ$', '" . $time->time() . "'),
                ('Bermuda Dollar', 'BMD', '$', '" . $time->time() . "'),
                ('Bolivia Boliviano', 'BOB', '\$b', '" . $time->time() . "'),
                ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $time->time() . "'),
                ('Botswana Pula', 'BWP', 'P', '" . $time->time() . "'),
                ('Bulgaria Lev', 'BGN', 'лв', '" . $time->time() . "'),
                ('Brazil Real', 'BRL', 'R$', '" . $time->time() . "'),
                ('Brunei Darussalam Dollar', 'BND', '$', '" . $time->time() . "'),
                ('Cambodia Riel', 'KHR', '៛', '" . $time->time() . "'),
                ('Canada Dollar', 'CAD', '$', '" . $time->time() . "'),
                ('Cayman Islands Dollar', 'KYD', '$', '" . $time->time() . "'),
                ('Chile Peso', 'CLP', '$', '" . $time->time() . "'),
                ('China Yuan Renminbi', 'CNY', '¥', '" . $time->time() . "'),
                ('Colombia Peso', 'COP', '$', '" . $time->time() . "'),
                ('Costa Rica Colon', 'CRC', '₡', '" . $time->time() . "'),
                ('Croatia Kuna', 'HRK', 'kn', '" . $time->time() . "'),
                ('Cuba Peso', 'CUP', '₱', '" . $time->time() . "'),
                ('Czech Republic Koruna', 'CZK', 'Kč', '" . $time->time() . "'),
                ('Denmark Krone', 'DKK', 'kr', '" . $time->time() . "'),
                ('Dominican Republic Peso', 'DOP', 'RD$', '" . $time->time() . "'),
                ('East Caribbean Dollar', 'XCD', '$', '" . $time->time() . "'),
                ('Egypt Pound', 'EGP', '£', '" . $time->time() . "'),
                ('El Salvador Colon', 'SVC', '$', '" . $time->time() . "'),
                ('Estonia Kroon', 'EEK', 'kr', '" . $time->time() . "'),
                ('Euro Member Countries', 'EUR', '€', '" . $time->time() . "'),
                ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $time->time() . "'),
                ('Fiji Dollar', 'FJD', '$', '" . $time->time() . "'),
                ('Ghana Cedis', 'GHC', '¢', '" . $time->time() . "'),
                ('Gibraltar Pound', 'GIP', '£', '" . $time->time() . "'),
                ('Guatemala Quetzal', 'GTQ', 'Q', '" . $time->time() . "'),
                ('Guernsey Pound', 'GGP', '£', '" . $time->time() . "'),
                ('Guyana Dollar', 'GYD', '$', '" . $time->time() . "'),
                ('Honduras Lempira', 'HNL', 'L', '" . $time->time() . "'),
                ('Hong Kong Dollar', 'HKD', '$', '" . $time->time() . "'),
                ('Hungary Forint', 'HUF', 'Ft', '" . $time->time() . "'),
                ('Iceland Krona', 'ISK', 'kr', '" . $time->time() . "'),
                ('India Rupee', 'INR', 'Rs', '" . $time->time() . "'),
                ('Indonesia Rupiah', 'IDR', 'Rp', '" . $time->time() . "'),
                ('Iran Rial', 'IRR', '﷼', '" . $time->time() . "'),
                ('Isle of Man Pound', 'IMP', '£', '" . $time->time() . "'),
                ('Israel Shekel', 'ILS', '₪', '" . $time->time() . "'),
                ('Jamaica Dollar', 'JMD', 'J$', '" . $time->time() . "'),
                ('Japan Yen', 'JPY', '¥', '" . $time->time() . "'),
                ('Jersey Pound', 'JEP', '£', '" . $time->time() . "'),
                ('Kazakhstan Tenge', 'KZT', 'лв', '" . $time->time() . "'),
                ('Korea (North) Won', 'KPW', '₩', '" . $time->time() . "'),
                ('Korea (South) Won', 'KRW', '₩', '" . $time->time() . "'),
                ('Kyrgyzstan Som', 'KGS', 'лв', '" . $time->time() . "'),
                ('Laos Kip', 'LAK', '₭', '" . $time->time() . "'),
                ('Latvia Lat', 'LVL', 'Ls', '" . $time->time() . "'),
                ('Lebanon Pound', 'LBP', '£', '" . $time->time() . "'),
                ('Liberia Dollar', 'LRD', '$', '" . $time->time() . "'),
                ('Lithuania Litas', 'LTL', 'Lt', '" . $time->time() . "'),
                ('Macedonia Denar', 'MKD', 'ден', '" . $time->time() . "'),
                ('Malaysia Ringgit', 'RM', 'RM', '" . $time->time() . "'),
                ('Mauritius Rupee', 'MUR', '₨', '" . $time->time() . "'),
                ('Mexico Peso', 'MXN', '$', '" . $time->time() . "'),
                ('Mongolia Tughrik', 'MNT', '₮', '" . $time->time() . "'),
                ('Mozambique Metical', 'MZN', 'MT', '" . $time->time() . "'),
                ('Namibia Dollar', 'NAD', '$', '" . $time->time() . "'),
                ('Nepal Rupee', 'NPR', '₨', '" . $time->time() . "'),
                ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $time->time() . "'),
                ('New Zealand Dollar', 'NZD', '$', '" . $time->time() . "'),
                ('Nicaragua Cordoba', 'NIO', 'C$', '" . $time->time() . "'),
                ('Nigeria Naira', 'NGN', '₦', '" . $time->time() . "'),
                ('Norway Krone', 'NOK', 'kr', '" . $time->time() . "'),
                ('Oman Rial', 'OMR', '﷼', '" . $time->time() . "'),
                ('Pakistan Rupee', 'PKR', '₨', '" . $time->time() . "'),
                ('Panama Balboa', 'PAB', 'B/.', '" . $time->time() . "'),
                ('Paraguay Guarani', 'PYG', 'Gs', '" . $time->time() . "'),
                ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $time->time() . "'),
                ('Philippines Peso', 'PHP', '₱', '" . $time->time() . "'),
                ('Poland Zloty', 'PLN', 'zł', '" . $time->time() . "'),
                ('Qatar Riyal', 'QAR', '﷼', '" . $time->time() . "'),
                ('Romania New Leu', 'RON', 'lei', '" . $time->time() . "'),
                ('Russia Ruble', 'RUB', 'руб', '" . $time->time() . "'),
                ('Saint Helena Pound', 'SHP', '£', '" . $time->time() . "'),
                ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $time->time() . "'),
                ('Serbia Dinar', 'RSD', 'Дин.', '" . $time->time() . "'),
                ('Seychelles Rupee', 'SCR', '₨', '" . $time->time() . "'),
                ('Singapore Dollar', 'SGD', '$', '" . $time->time() . "'),
                ('Solomon Islands Dollar', 'SBD', '$', '" . $time->time() . "'),
                ('Somalia Shilling', 'SOS', 'S', '" . $time->time() . "'),
                ('South Africa Rand', 'ZAR', 'R', '" . $time->time() . "'),
                ('Sri Lanka Rupee', 'LKR', '₨', '" . $time->time() . "'),
                ('Sweden Krona', 'SEK', 'kr', '" . $time->time() . "'),
                ('Switzerland Franc', 'CHF', 'CHF', '" . $time->time() . "'),
                ('Suriname Dollar', 'SRD', '$', '" . $time->time() . "'),
                ('Syria Pound', 'SYP', '£', '" . $time->time() . "'),
                ('Taiwan New Dollar', 'TWD', 'NT$', '" . $time->time() . "'),
                ('Thailand Baht', 'THB', '฿', '" . $time->time() . "'),
                ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $time->time() . "'),
                ('Turkey Lira', 'TRY', '₤', '" . $time->time() . "'),
                ('Tuvalu Dollar', 'TVD', '$', '" . $time->time() . "'),
                ('Ukraine Hryvna', 'UAH', '₴', '" . $time->time() . "'),
                ('United Kingdom Pound', 'GBP', '£', '" . $time->time() . "'),
                ('United States Dollar', 'USD', '$', '" . $time->time() . "'),
                ('Uruguay Peso', 'UYU', '\$U', '" . $time->time() . "'),
                ('Uzbekistan Som', 'UZS', 'лв', '" . $time->time() . "'),
                ('Venezuela Bolivar', 'VEF', 'Bs', '" . $time->time() . "'),
                ('Viet Nam Dong', 'VND', '₫', '" . $time->time() . "'),
                ('Yemen Rial', 'YER', '﷼', '" . $time->time() . "'),
                ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $time->time() . "'),
                ('Emirati Dirham', 'AED', 'د.إ', '" . $time->time() . "'),
                ('Malaysian Ringgit', 'MYR', 'RM', '" . $time->time() . "'),
                ('Kuwaiti Dinar', 'KWD', 'ك', '" . $time->time() . "'),
                ('Moroccan Dirham', 'MAD', 'م.', '" . $time->time() . "'),
                ('Iraqi Dinar', 'IQD', 'د.ع', '" . $time->time() . "'),
                ('Bangladeshi Taka', 'BDT', 'Tk', '" . $time->time() . "'),
                ('Bahraini Dinar', 'BHD', 'BD', '" . $time->time() . "'),
                ('Kenyan Shilling', 'KES', 'KSh', '" . $time->time() . "'),
                ('CFA Franc', 'XOF', 'CFA', '" . $time->time() . "'),
                ('Jordanian Dinar', 'JOD', 'JD', '" . $time->time() . "'),
                ('Tunisian Dinar', 'TND', 'د.ت', '" . $time->time() . "'),
                ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $time->time() . "'),
                ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $time->time() . "'),
                ('Algerian Dinar', 'DZD', 'دج', '" . $time->time() . "'),
                ('CFP Franc', 'XPF', 'F', '" . $time->time() . "'),
                ('Ugandan Shilling', 'UGX', 'USh', '" . $time->time() . "'),
                ('Tanzanian Shilling', 'TZS', 'TZS', '" . $time->time() . "'),
                ('Ethiopian Birr', 'ETB', 'Br', '" . $time->time() . "'),
                ('Georgian Lari', 'GEL', 'GEL', '" . $time->time() . "'),
                ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $time->time() . "'),
                ('Burmese Kyat', 'MMK', 'K', '" . $time->time() . "'),
                ('Libyan Dinar', 'LYD', 'LD', '" . $time->time() . "'),
                ('Zambian Kwacha', 'ZMK', 'ZK', '" . $time->time() . "'),
                ('Zambian Kwacha', 'ZMW', 'ZK', '" . $time->time() . "'),
                ('Macau Pataca', 'MOP', 'MOP$', '" . $time->time() . "'),
                ('Armenian Dram', 'AMD', 'AMD', '" . $time->time() . "'),
                ('Angolan Kwanza', 'AOA', 'Kz', '" . $time->time() . "'),
                ('Papua New Guinean Kina', 'PGK', 'K', '" . $time->time() . "'),
                ('Malagasy Ariary', 'MGA', 'Ar', '" . $time->time() . "'),
                ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $time->time() . "'),
                ('Sudanese Pound', 'SDG', 'SDG', '" . $time->time() . "'),
                ('Malawian Kwacha', 'MWK', 'MK', '" . $time->time() . "'),
                ('Rwandan Franc', 'RWF', 'FRw', '" . $time->time() . "'),
                ('Gambian Dalasi', 'GMD', 'D', '" . $time->time() . "'),
                ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $time->time() . "'),
                ('Congolese Franc', 'CDF', 'FC', '" . $time->time() . "'),
                ('Djiboutian Franc', 'DJF', 'Fdj', '" . $time->time() . "'),
                ('Haitian Gourde', 'HTG', 'G', '" . $time->time() . "'),
                ('Samoan Tala', 'WST', '$', '" . $time->time() . "'),
                ('Guinean Franc', 'GNF', 'FG', '" . $time->time() . "'),
                ('Cape Verdean Escudo', 'CVE', '$', '" . $time->time() . "'),
                ('Tongan Pa\'anga', 'TOP', 'T$', '" . $time->time() . "'),
                ('Moldovan Leu', 'MDL', 'MDL', '" . $time->time() . "'),
                ('Sierra Leonean Leone', 'SLL', 'Le', '" . $time->time() . "'),
                ('Burundian Franc', 'BIF', 'FBu', '" . $time->time() . "'),
                ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $time->time() . "'),
                ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $time->time() . "'),
                ('Swazi Lilangeni', 'SZL', 'SZL', '" . $time->time() . "'),
                ('Tajikistani Somoni', 'TJS', 'TJS', '" . $time->time() . "'),
                ('Turkmenistani Manat', 'TMT', 'm', '" . $time->time() . "'),
                ('Basotho Loti', 'LSL', 'LSL', '" . $time->time() . "'),
                ('Comoran Franc', 'KMF', 'CF', '" . $time->time() . "'),
                ('Sao Tomean Dobra', 'STD', 'STD', '" . $time->time() . "'),
                ('Seborgan Luigino', 'SPL', 'SPL', '" . $time->time() . "')";
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
                    update_time = '" . $time->time() . "'";
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

    $_SESSION['s_default_currency'] = $temp_currency;

    $sql = "SELECT name, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = '" . $_SESSION['s_default_currency'] . "'";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {
        $_SESSION['s_default_currency_name'] = $row->name;
        $_SESSION['s_default_currency_symbol'] = $row->symbol;
        $_SESSION['s_default_currency_symbol_order'] = $row->symbol_order;
        $_SESSION['s_default_currency_symbol_space'] = $row->symbol_space;
    }

    $sql = "ALTER TABLE `currencies`
                DROP `default_currency`;";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `user_settings`
                DROP `default_currency`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET db_version = '2.0037',
                    update_time = '" . $time->time() . "'";
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
        $_SESSION['s_default_currency'] = $row->default_currency;
    }

    $sql = "SELECT name, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = '" . $_SESSION['s_default_currency'] . "'";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $_SESSION['s_default_currency_name'] = $row->name;
        $_SESSION['s_default_currency_symbol'] = $row->symbol;
        $_SESSION['s_default_currency_symbol_order'] = $row->symbol_order;
        $_SESSION['s_default_currency_symbol_space'] = $row->symbol_space;

    }

    $sql = "UPDATE user_settings
                SET default_currency = '" . $temp_default_currency . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `currency_id` INT(10) NOT NULL,
                `user_id` INT(10) NOT NULL,
                `conversion` FLOAT NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
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
                               (currency_id, user_id, conversion, insert_time, update_time) VALUES
                               ('" . $row_conversion->id . "', '" . $row->id . "', '" . $row_conversion->conversion . "', '" . $time->time() . "', '" . $time->time() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

    }

    $sql = "ALTER TABLE `currencies`
                DROP `conversion`;";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET db_version = '2.0038',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0038';

}

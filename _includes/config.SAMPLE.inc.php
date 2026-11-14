<?php
/**
 * /_includes/config.SAMPLE.inc.php
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
?>
<?php
// Path Settings
$web_root = '/dm'; // don't include trailing slash (if you're installing in the root just leave this blank)

// Database Settings
$dbhostname = 'localhost';
$dbname = 'db_name';
$dbusername = 'db_username';
$dbpassword = 'dbPassword123';

// Encrypted Database Connections (optional)
// By default DomainMOD connects as before, and only uses an encrypted connection if the database server requires
// one. That encrypted connection doesn't check the server's certificate, so it doesn't protect you from an attacker
// on the network. If your database is on a different server, set $dbsslmode to 'verify' to always encrypt the
// connection and check the server's certificate. 'off' never encrypts the connection.
// $dbsslmode = 'auto'; // auto, verify, or off
// $dbsslca = '/path/to/ca.pem'; // for 'verify': the CA that signed the server's certificate (default: the system's trusted CAs)

// Task Scheduler Key (optional)
// By default cron.php runs whenever its URL is opened, which is how many installations schedule it. If you set a key,
// cron.php only runs from the command line (php cron.php) or from a URL that includes the key, such as
// https://example.com/cron.php?key=YourKeyHere. Use a long random value, and update your scheduled job to match.
// $cron_key = '';

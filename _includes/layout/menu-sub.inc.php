<?php
// /_includes/layout/menu-sub.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<br><br>
<?php 
if ($software_section == "domains") {

	if ($_SESSION['need_registrar'] != "1" && $_SESSION['need_registrar_account'] != "1") { ?>
    	&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/domain.php">Add A New Domain</a><?php 
	} 
	
} elseif ($software_section == "ssl-providers") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/ssl-provider.php">Add A New SSL Provider</a><?php 

} elseif ($software_section == "ssl-accounts") {

	if ($_SESSION['need_ssl_provider'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/ssl-account.php">Add A New SSL Account</a><?php 
	} 
	
} elseif ($software_section == "ssl-certs") {
	
	if ($_SESSION['need_ssl_provider'] != "1" && $_SESSION['need_ssl_account'] != "1" && $_SESSION['need_domain'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/ssl-cert.php">Add A New SSL Certificate</a><?php 
	}

} elseif ($software_section == "ssl-types") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/ssl-type.php">Add A New SSL Type</a><?php 

} elseif ($software_section == "categories") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/category.php">Add A New Category</a><?php 
		
} elseif ($software_section == "dns") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/dns.php">Add A New DNS Profile</a><?php 
		
} elseif ($software_section == "ip-addresses") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/ip-address.php">Add A New IP Address</a><?php 

} elseif ($software_section == "hosting") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/host.php">Add A New Web Host</a><?php 

} elseif ($software_section == "registrars") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/registrar.php">Add A New Registrar</a><?php 

} elseif ($software_section == "accounts") {
	
	if ($_SESSION['need_registrar'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/account.php">Add A New Registrar Account</a><?php 
	}
	
} elseif ($software_section == "owners") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/assets/add/owner.php">Add A New Owner</a><?php 
		
} elseif ($software_section == "segments") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/segment.php">Add A New Segment</a><?php 

} elseif ($software_section == "system") { ?>

<?php
} elseif ($software_section == "reporting") { ?>

<?php
} elseif ($software_section == "bulkactions") { ?>

<?php
} elseif ($software_section == "help") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/help/">Main</a>  /  <a href="<?php if ($web_root != "/") echo $web_root; ?>/help/getting-started/">Getting Started</a><?php

} ?>
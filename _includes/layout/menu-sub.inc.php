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
    	&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/domain.php">Add A New Domain</a><?php 
	} 
	
} elseif ($software_section == "ssl-providers") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/ssl-provider.php">Add A New SSL Provider</a><?php 

} elseif ($software_section == "ssl-accounts") {

	if ($_SESSION['need_ssl_provider'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/ssl-account.php">Add A New SSL Account</a><?php 
	} 
	
} elseif ($software_section == "ssl-certs") {
	
	if ($_SESSION['need_ssl_provider'] != "1" && $_SESSION['need_ssl_account'] != "1" && $_SESSION['need_domain'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/ssl-cert.php">Add A New SSL Certificate</a><?php 
	}

} elseif ($software_section == "ssl-types") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/ssl-type.php">Add A New SSL Type</a><?php 

} elseif ($software_section == "categories") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/category.php">Add A New Category</a><?php 
		
} elseif ($software_section == "dns") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/dns.php">Add A New DNS Profile</a><?php 
		
} elseif ($software_section == "ip-addresses") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/ip-address.php">Add A New IP Address</a><?php 

} elseif ($software_section == "hosting") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/host.php">Add A New Web Host</a><?php 

} elseif ($software_section == "registrars") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/registrar.php">Add A New Registrar</a><?php 

} elseif ($software_section == "accounts") {
	
	if ($_SESSION['need_registrar'] != "1") { ?>
		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/account.php">Add A New Registrar Account</a><?php 
	}
	
} elseif ($software_section == "owners") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/owner.php">Add A New Owner</a><?php 
		
} elseif ($software_section == "segments") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/segment.php">Add A New Segment</a><?php 

} elseif ($software_section == "currencies") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/add/currency.php">Add A New Currency</a>
		&nbsp;&nbsp;/&nbsp;&nbsp;<a href="<?php if ($web_root != "/") echo $web_root; ?>/system/update-conversion-rates.php">Update Conversion Rates</a><?php 
		
} elseif ($software_section == "system") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/system/">Control Panel</a><?php 

} elseif ($software_section == "reporting") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/reporting/">Reporting</a><?php 

} elseif ($software_section == "bulkactions") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/system/bulk-actions.php">Reset Bulk Action</a><?php 

} elseif ($software_section == "help") { ?>

		&raquo; <a href="<?php if ($web_root != "/") echo $web_root; ?>/help/">Main</a>  /  <a href="<?php if ($web_root != "/") echo $web_root; ?>/help/getting-started/">Getting Started</a><?php

} ?>
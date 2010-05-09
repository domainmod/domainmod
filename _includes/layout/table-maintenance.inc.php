<?php
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
<?php
session_start();
?>

<?php if ($_SESSION['session_missing_domain_fees'] == 1 || $_SESSION['session_missing_ssl_fees'] == 1) { ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" bordercolor="#000000">
	<tr>
    	<td bordercolor="#000000">

            <table width="100%" cellspacing="0" cellpadding="0">
            	<tr>
                	<td class="cell-maintenance-table">
                    <BR>
                    <strong>Maintenance Warning! (clear up the issues to make this table disappear)</strong><BR><BR>
					<?php if ($_SESSION['session_missing_domain_fees'] == 1) { ?>
                    <LI>Some of your Registrars/TLDs are missing domain fees. <a href="<?=$web_root?>/missing-domain-fees.php">Click here to fix this</a>.</LI>
                    <?php } ?>
					<?php if ($_SESSION['session_missing_ssl_fees'] == 1) { ?>
                    <LI>Some of your SSL Certificates are missing fees. <a href="<?=$web_root?>/missing-ssl-fees.php">Click here to fix this</a>.</LI>
                    <?php } ?>
					<BR>
                    </td>
				</tr>
			</table>

		</td>
	</tr>
</table>
<BR><BR><BR>
<?php } ?>
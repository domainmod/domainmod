<?php
/**
 * /_includes/layout/footer.inc.php
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
		</div>
	</div>

    <div class="footer-container">
	    <?php echo $software_title; ?> is open source software released under the <a target="_blank" class="invisiblelink" href="http://www.gnu.org/licenses/">GNU/GPL License</a><br>
        To obtain your own copy of the <?php echo $software_title; ?> software <a target="_blank" class="invisiblelink" href="<?php echo $code_home_url; ?>">click here</a><BR>
        Created by <a target="_blank" class="invisiblelink" href="http://chetcuti.com">Greg Chetcuti</a><BR><BR>
        <a target="_blank" href="http://www.gnu.org/licenses/"><img border="0" width="88" height="31" src="<?php echo $web_root; ?>/images/gpl_logo.png" /></a>
    </div>

</div>
<?php
$full_filename = $full_server_path . "/_includes/layout/footer.DEMO.inc.php";

if (file_exists($full_filename)) {

    include($full_server_path . "/_includes/layout/footer.DEMO.inc.php");

}

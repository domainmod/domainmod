<?php
/**
 * /_includes/layout/header-bare.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2021 Greg Chetcuti <greg@chetcuti.com>
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
<BR><BR>
<div style="text-align: <?php echo $page_align; ?>;">
    <BR><a href="<?php echo $web_root . "/dashboard/\"><img border=\"0\" src=\"" . $web_root . "/images/logo-lg.png\"></a><BR><BR>";

$full_filename = DIR_INC . '/layout/header.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/header.DEMO.inc.php';

}

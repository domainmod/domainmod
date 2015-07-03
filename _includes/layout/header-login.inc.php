<?php
/**
 * /_includes/layout/header-login.inc.php
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
    <a name="top"></a>
    <div class="main-container-login">

    <div class="header-container">
        <div class="header-center">
            <?php echo "<img border=\"0\" src=\"" . $web_root . "/images/logo.png\">"; ?>
        </div>
    </div>

    <div class="main-outer-login">
    <div class="main-inner">
    <BR><?php
include(DIR_INC . "layout/table-maintenance.inc.php");
?>
<?php
if ($_SESSION['result_message'] != "") {

    echo $system->showResultMessage($_SESSION['result_message']);
    unset($_SESSION['result_message']);

}

<?php
/**
 * /_includes/layout/breadcrumbs.inc.php
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
<ol class="breadcrumb float-sm-right">
    <?php if ($software_section == 'dashboard') { ?>
        <li class="breadcrumb-item active">Dashboard</li>
    <?php } else { ?>
        <li class="breadcrumb-item"><a href="<?php echo $web_root; ?>/">Dashboard</a></li>
    <?php } ?>
    <?php require_once DIR_INC . '/breadcrumbs/' . $slug . '.inc.php'; ?>

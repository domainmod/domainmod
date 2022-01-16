<?php
/**
 * /_includes/layout/reporting-block-sub.inc.php
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
<?php if ($new_start_date != '' && $new_end_date != '') { ?>

    <strong><?php echo _('Date Range'); ?>:</strong> <?php echo $new_start_date; ?> - <?php echo $new_end_date; ?><BR>

<?php } else { ?>

    <strong><?php echo _('Date Range'); ?>:</strong> <?php echo strtoupper(_('All')); ?><BR>

<?php } ?>

<strong><?php echo _('Total Cost'); ?>:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['s_default_currency']; ?><BR>

<?php if ($report_section == 'domains') { ?>

    <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo $number_of_domains_total; ?><BR>

<?php } elseif ($report_section == 'ssl') { ?>

    <strong><?php echo _('Number of SSL Certs'); ?>:</strong> <?php echo $number_of_certs_total; ?><BR>

<?php }

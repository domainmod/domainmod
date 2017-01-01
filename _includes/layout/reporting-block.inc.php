<?php
/**
 * /_includes/layout/reporting-block.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
<?php echo $form->showFormTop(''); ?>

<a href="<?php echo $report_filename; ?>?all=1"><?php echo $layout->showButton('button', 'View Full Report'); ?></a> <BR><BR><strong>or Expiring Between</strong><BR><BR><?php

if ($new_start_date == "") {
    $new_start_date = $time->toUserTimezone($time->timeBasic(), 'Y-m-d');
}
if ($new_end_date == "") {
    $new_end_date = $time->toUserTimezone($time->timeBasic(), 'Y-m-d');
} ?>

<input type="text" name="daterange" size="26" value="<?php echo $new_start_date; ?> - <?php echo $new_end_date; ?>" />

&nbsp;&nbsp;<?php echo $form->showSubmitButton('Generate Report', '', ''); ?><BR>

<?php
if ($total_rows > 0 && $total_rows != '') { //@formatter:off ?>

    <BR><a href="<?php echo $report_filename; ?>?export_data=1&all=<?php echo $all; ?>&daterange=<?php echo $daterange;
    ?>"><?php echo $layout->showButton('button', 'Export'); ?></a>

<?php } //@formatter:on ?>
<?php
echo $form->showFormBottom('');

<?php
/**
 * /_includes/layout/reporting-block.inc.php
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
//@formatter:off ?>

    <a href="<?php echo $report_filename; ?>"><?php echo $layout->showButton('button', _('View Full Report')); ?></a>&nbsp;&nbsp;<?php

    if ($total_rows > 0 && $total_rows != '') { ?>
        <a href="<?php echo $report_filename; ?>?export_data=1&daterange=<?php echo $daterange; ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><?php
    } ?>
    <BR><BR>

    <?php echo $form->showFormTop(''); ?>

    <label for="daterange" class="form-label fw-bold"><?php echo _('Filter By Date Range'); ?></label>
    <div class="domainmod-date-filter d-flex flex-wrap align-items-center gap-2 mb-4">
        <input type="text" name="daterange" id="daterange" class="form-control w-auto" size="23" value="<?php echo $daterange; ?>" />
        <?php echo $form->showSubmitButton(_('Filter Report'), '', ''); ?>
    </div>

<?php //@formatter:on
echo $form->showFormBottom('');

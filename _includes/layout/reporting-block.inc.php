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
//@formatter:off
    // The page's actions, drawn in the page header (see Layout::renderPageActions())
    $layout->addPageAction(_('View Full Report'), $report_filename, 'bi-arrow-counterclockwise');
    if ($total_rows > 0 && $total_rows != '') {
        $layout->addPageAction(_('Export Report'), $report_filename . '?export_data=1&daterange=' . urlencode(html_entity_decode($daterange, ENT_QUOTES, 'UTF-8')), 'bi-download');
    }

    // Whether the report covers a date range (a whole, valid one), or every date. The summary below uses it too.
    $report_has_range = $new_start_date != '' && $new_end_date != ''; ?>

    <?php echo $form->showFormTop(''); ?>

    <?php // The filter's label, field, and button on one row, with a way back to every date once a range is set ?>
    <div class="domainmod-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
        <label for="daterange" class="form-label fw-bold mb-0"><?php echo _('Filter By Date Range'); ?></label>
        <input type="text" name="daterange" id="daterange" class="form-control w-auto" size="23" dir="ltr" value="<?php echo $daterange; ?>" />
        <?php echo $form->showSubmitButton(_('Filter Report'), '', ''); ?>
        <?php if ($report_has_range) echo $layout->linkButton($report_filename, _('Clear')); ?>
    </div>

<?php //@formatter:on
echo $form->showFormBottom('');

// The dates the report covers, under the filter. A range that failed its check shows only its error.
if ($submission_failed != '1') {

    echo $layout->factList(array(array(
        'label' => _('Date Range'),
        'value' => $report_has_range ? $new_start_date . ' - ' . $new_end_date : _('All dates'),
        'ltr' => $report_has_range
    )));

}

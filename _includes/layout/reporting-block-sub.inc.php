<?php
/**
 * /_includes/layout/reporting-block-sub.inc.php
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
?>
<?php
/*
 * The report's summary, under the date filter: one card with the number of domains or SSL certificates as its number,
 * the total cost as its detail line, and a View link to the table below. The total is money, so it never goes in the
 * card's number, which Dashboard::displayPanel() rounds to a whole number. A report with nothing in it gets an empty
 * state instead, and a date range that failed its check shows only its error.
 */
if ($submission_failed != '1') {

    $report_url = '/reporting/' . $report_section . '/' . $report_filename;
    if ($report_has_range) $report_url .= '?daterange=' . urlencode($new_start_date . ' - ' . $new_end_date);

    if ($total_rows > 0) {

        // The amount and its currency code stay in order on right-to-left pages (isolated with Unicode marks, since
        // the card's detail line is plain text)
        $report_total = "\u{2066}" . $grand_total . ' ' . $_SESSION['s_default_currency'] . "\u{2069}";

        echo '<div class="row mb-4">';
        if ($report_section == 'domains') {
            echo $dashboard->displayPanel(_('Domains'), (int) $number_of_domains_total, 'danger', 'bi-globe2', $report_url . '#' . $slug, $breadcrumb, $report_total);
        } else {
            echo $dashboard->displayPanel(_('SSL Certificates'), (int) $number_of_certs_total, 'danger', 'bi-shield-lock', $report_url . '#' . $slug, $breadcrumb, $report_total);
        }
        echo '</div>';

    } elseif ($report_has_range) {

        $report_empty_text = $report_section == 'domains'
            ? _('No live domains with a fee expire between these dates.')
            : _('No live SSL certificates with a fee expire between these dates.');
        echo $layout->emptyState('bi-calendar-x', _('No costs in this date range'), $report_empty_text, '', '',
            '/reporting/' . $report_section . '/' . $report_filename, _('Clear the date range'));

    } else {

        $report_empty_text = $report_section == 'domains'
            ? _('Live domains are counted here once they have a fee.')
            : _('Live SSL certificates are counted here once they have a fee.');
        echo $layout->emptyState('bi-cash-coin', _('No costs to report yet'), $report_empty_text);

    }

}

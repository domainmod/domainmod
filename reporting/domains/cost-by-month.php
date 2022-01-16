<?php
/**
 * /reporting/domains/cost-by-month.php
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-domain-cost-by-month.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];
$daterange = $sanitize->text($_REQUEST['daterange']);

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The end date proceeds the start date') . '<BR>';
        $submission_failed = '1';

    }

}

$range_string = $reporting->getRangeString('d.expiry_date', $new_start_date, $new_end_date);

$result = $pdo->query("
    SELECT d.id, YEAR(d.expiry_date) AS year, MONTH(d.expiry_date) AS month
    FROM domains AS d, fees AS f, currencies AS c
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND d.active NOT IN ('0', '10')" .
    $range_string . "
    GROUP BY year, month
    ORDER BY year, month")->fetchAll();

$total_rows = count($result);

$result_grand_total = $pdo->query("
    SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
      AND d.active NOT IN ('0', '10')" .
      $range_string)->fetchAll();

foreach ($result_grand_total as $row_grand_total) {

    $grand_total = $row_grand_total->grand_total;
    $number_of_domains_total = $row_grand_total->number_of_domains_total;

}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($submission_failed != '1' && $total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($daterange == '') {

            $export_file = $export->openFile(_('domain_cost_by_month_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(
                _('domain_cost_by_month_report'),
                $new_start_date . '--' . $new_end_date
            );

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($daterange == '') {

            $row_contents = array(_('Date Range') . ':', strtoupper(_('All')));

        } else {

            $row_contents = array(_('Date Range') . ':', $daterange);

        }
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Total Cost') . ':',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Number of Domains') . ':',
            $number_of_domains_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Year'),
            _('Month'),
            _('Cost'),
            _('By Year')
        );
        $export->writeRow($export_file, $row_contents);

        $new_year = '';
        $last_year = '';
        $new_month = '';
        $last_month = '';

        if ($result) {

            foreach ($result as $row) {

                $new_year = $row->year;
                $new_month = $row->month;

                $result_monthly_cost = $pdo->query("
                    SELECT SUM(d.total_cost * cc.conversion) AS monthly_cost
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      AND d.active NOT IN ('0', '10')
                      AND YEAR(d.expiry_date) = '" . $row->year . "' 
                      AND MONTH(d.expiry_date) = '" . $row->month . "'" .
                      $range_string)->fetchAll();

                foreach ($result_monthly_cost as $row_monthly_cost) {

                    $monthly_cost = $row_monthly_cost->monthly_cost;

                }

                $monthly_cost = $currency->format($monthly_cost, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                if ($row->month == '1') { $display_month = _('January');
                } elseif ($row->month == '2') { $display_month = _('February');
                } elseif ($row->month == '3') { $display_month = _('March');
                } elseif ($row->month == '4') { $display_month = _('April');
                } elseif ($row->month == '5') { $display_month = _('May');
                } elseif ($row->month == '6') { $display_month = _('June');
                } elseif ($row->month == '7') { $display_month = _('July');
                } elseif ($row->month == '8') { $display_month = _('August');
                } elseif ($row->month == '9') { $display_month = _('September');
                } elseif ($row->month == '10') { $display_month = _('October');
                } elseif ($row->month == '11') { $display_month = _('November');
                } elseif ($row->month == '12') { $display_month = _('December');
                }

                $result_yearly_cost = $pdo->query("
                    SELECT SUM(d.total_cost * cc.conversion) AS yearly_cost
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      AND d.active NOT IN ('0', '10')
                      AND YEAR(d.expiry_date) = '" . $row->year . "'" .
                      $range_string)->fetchAll();

                foreach ($result_yearly_cost as $row_yearly_cost) {

                    $yearly_cost = $row_yearly_cost->yearly_cost;

                }

                $yearly_cost = $currency->format($yearly_cost, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row_contents = array(
                    $row->year,
                    $display_month,
                    $monthly_cost,
                    $yearly_cost
                );
                $export->writeRow($export_file, $row_contents);

                $last_year = $row->year;
                $last_month = $row->month;

            }

        }
        $export->closeFile($export_file);

    }

} else {

    $total_rows = '0';

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php require_once DIR_INC . '/layout/reporting-block.inc.php'; ?>
<?php
if ($submission_failed != '1' && $total_rows > 0) { ?>

    <?php require_once DIR_INC . '/layout/reporting-block-sub.inc.php'; ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Year'); ?></th>
            <th><?php echo _('Month'); ?></th>
            <th><?php echo _('Cost'); ?></th>
            <th><?php echo _('By Year'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $new_year = '';
        $last_year = '';
        $new_month = '';
        $last_month = '';

        foreach ($result as $row) {

            $new_year = $row->year;
            $new_month = $row->month;

            $result_monthly_cost = $pdo->query("
                SELECT SUM(d.total_cost * cc.conversion) AS monthly_cost
                FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                WHERE d.fee_id = f.id
                  AND f.currency_id = c.id
                  AND c.id = cc.currency_id
                  AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                  AND d.active NOT IN ('0', '10')
                  AND YEAR(d.expiry_date) = '" . $row->year . "'
                  AND MONTH(d.expiry_date) = '" . $row->month . "'" .
                  $range_string)->fetchAll();

            foreach ($result_monthly_cost as $row_monthly_cost) {

                $monthly_cost = $row_monthly_cost->monthly_cost;

            }

            $monthly_cost = $currency->format($monthly_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            if ($row->month == '1') { $display_month = _('January');
            } elseif ($row->month == '2') { $display_month = _('February');
            } elseif ($row->month == '3') { $display_month = _('March');
            } elseif ($row->month == '4') { $display_month = _('April');
            } elseif ($row->month == '5') { $display_month = _('May');
            } elseif ($row->month == '6') { $display_month = _('June');
            } elseif ($row->month == '7') { $display_month = _('July');
            } elseif ($row->month == '8') { $display_month = _('August');
            } elseif ($row->month == '9') { $display_month = _('September');
            } elseif ($row->month == '10') { $display_month = _('October');
            } elseif ($row->month == '11') { $display_month = _('November');
            } elseif ($row->month == '12') { $display_month = _('December');
            }

            if ($new_year > $last_year || $new_year == '') {

                $result_yearly_cost = $pdo->query("
                    SELECT SUM(d.total_cost * cc.conversion) AS yearly_cost
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      AND d.active NOT IN ('0', '10')
                      AND YEAR(d.expiry_date) = '" . $row->year . "'" .
                      $range_string)->fetchAll();

                foreach ($result_yearly_cost as $row_yearly_cost) {

                    $yearly_cost = $row_yearly_cost->yearly_cost;

                }

                $yearly_cost = $currency->format($yearly_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']); ?>

                <tr>
                    <td></td>
                    <td><?php echo $row->year; ?></td>
                    <td><?php echo $display_month; ?></td>
                    <td><?php echo $monthly_cost; ?></td>
                    <td><?php echo $yearly_cost; ?></td>
                </tr><?php

                $last_year = $row->year;
                $last_month = $row->month;

            } else { ?>

                <tr>
                    <td></td>
                    <td>&nbsp;</td>
                    <td><?php echo $display_month; ?></td>
                    <td><?php echo $monthly_cost; ?></td>
                    <td>&nbsp;</td>
                </tr><?php

                $last_year = $row->year;
                $last_month = $row->month;

            }

        } ?>

        </tbody>
    </table><?php

} else {

    echo _('No results.') . '<BR>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
</body>
</html>

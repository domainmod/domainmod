<?php
/**
 * /assets/ssl-provider-fees.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$assets = new DomainMOD\Assets();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-ssl-provider-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$sslpid = (int) ($_GET['sslpid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);

$ssl_provider_name = $assets->getSslProvider($sslpid);

$stmt = $pdo->prepare("
    SELECT f.id, f.initial_fee, f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, sslct.type, c.currency, c.symbol, c.symbol_order, c.symbol_space
    FROM ssl_fees AS f, ssl_cert_types AS sslct, currencies AS c
    WHERE f.currency_id = c.id
      AND f.type_id = sslct.id
      AND f.ssl_provider_id = :sslpid
    ORDER BY sslct.type ASC");
$stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_provider_fee_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('SSL Provider'),
        _('Type'),
        _('Initial Fee'),
        _('Renewal Fee'),
        _('Misc Fee'),
        _('Currency'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $row_contents = array(
                $ssl_provider_name,
                $row->type,
                $row->initial_fee,
                $row->renewal_fee,
                $row->misc_fee,
                $row->currency,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo _('Below is a list of all the fees associated with'); ?> <a href="edit/ssl-provider.php?sslpid=<?php echo urlencode($sslpid); ?>"><?php echo $ssl_provider_name; ?></a>.<BR><BR>
<a href="add/ssl-provider-fee.php?sslpid=<?php echo urlencode($sslpid); ?>"><?php echo $layout->showButton('button', _('Add Fee')); ?></a>
<a href="ssl-provider-fees.php?sslpid=<?php echo urlencode($sslpid); ?>&export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

$stmt2 = $pdo->prepare("
    SELECT sslct.id, sslct.type
     FROM ssl_certs AS sslc, ssl_cert_types AS sslct
     WHERE sslc.type_id = sslct.id
       AND sslc.ssl_provider_id = :sslpid
       AND sslc.fee_id = '0'
     GROUP BY sslct.type
     ORDER BY sslct.type ASC");
$stmt2->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
$stmt2->execute();
$result2 = $stmt2->fetchAll();

if ($result2) { ?>

    <h4><?php echo _('Missing SSL Type Fees'); ?></h4><?php

    $temp_all_missing_fees = '';
    $count = 0;

    foreach ($result2 as $row2) {

        $temp_all_missing_fees = $temp_all_missing_fees .= "<a href=\"add/ssl-provider-fee.php?sslpid=" . $sslpid . "&type_id=" . $row2->id . "\">" . $row2->type . "</a>, ";
        $count++;

    }

    $all_missing_fees = substr($temp_all_missing_fees, 0, -2); ?>
    <strong><?php echo $all_missing_fees; ?></strong><BR>
    <?php
    if ($count == 1) {
        echo sprintf(_('You have SSL certificates with %s that use this SSL Type, however there are no fees associated with it yet. You should add this fee as soon as possible.'), $ssl_provider_name) . '<BR><BR><BR>';
    } else {
        echo sprintf(_('You have SSL certificates with %s that use these SSL Types, however there are no fees associated with them yet. You should add these fees as soon as possible.'), $ssl_provider_name) . '<BR><BR><BR>';
    }

}

if (!$result) { ?>

    <BR><?php echo _("You don't currently have any fees associated with this SSL provider."); ?> <a href="add/ssl-provider-fee.php?sslpid=<?php echo urlencode($sslpid); ?>"><?php echo _('Click here to add one'); ?></a>.<?php

} else { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Type'); ?></th>
            <th><?php echo _('Initial Fee'); ?></th>
            <th><?php echo _('Renewal Fee'); ?></th>
            <th><?php echo _('Misc Fee'); ?></th>
            <th><?php echo _('Currency'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                <a href="edit/ssl-provider-fee.php?sslpid=<?php echo urlencode($sslpid); ?>&fee_id=<?php echo urlencode($row->id); ?>"><?php echo $row->type; ?></a>
            </td>
            <td><?php
                if ($row->initial_fee > 0) {

                    echo $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td>
                <?php
                if ($row->renewal_fee > 0) {

                    echo $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td>
                <?php
                if ($row->misc_fee > 0) {

                    echo $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td>
                <?php echo $row->currency; ?>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}
require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>

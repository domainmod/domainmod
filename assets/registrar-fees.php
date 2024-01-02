<?php
/**
 * /assets/registrar-fees.php
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
require_once DIR_INC . '/settings/assets-registrar-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$rid = (int) ($_GET['rid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);

$registrar_name = $assets->getRegistrar($rid);

$stmt = $pdo->prepare("
    SELECT f.id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
    FROM fees AS f, currencies AS c
    WHERE f.currency_id = c.id
      AND f.registrar_id = :rid
    ORDER BY f.tld ASC");
$stmt->bindValue('rid', $rid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('registrar_fee_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Registrar'),
        _('TLD'),
        _('Initial Fee'),
        _('Renewal Fee'),
        _('Transfer Fee'),
        _('Privacy Fee'),
        _('Misc Fee'),
        _('Currency'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $row_contents = array(
                $registrar_name,
                $row->tld,
                $row->initial_fee,
                $row->renewal_fee,
                $row->transfer_fee,
                $row->privacy_fee,
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
<?php echo _('Below is a list of all the fees associated with'); ?> <a href="edit/registrar.php?rid=<?php echo urlencode($rid); ?>"><?php echo $registrar_name; ?></a>.<BR><BR>
<a href="add/registrar-fee.php?rid=<?php echo urlencode($rid); ?>"><?php echo $layout->showButton('button', _('Add Fee')); ?></a>
<a href="registrar-fees.php?rid=<?php echo urlencode($rid); ?>&export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

$stmt2 = $pdo->prepare("
    SELECT tld
    FROM domains
    WHERE registrar_id = :rid
      AND fee_id = '0'
    GROUP BY tld
    ORDER BY tld ASC");
$stmt2->bindValue('rid', $rid, PDO::PARAM_INT);
$stmt2->execute();
$result2 = $stmt2->fetchAll();

if ($result2) { ?>

    <h4><?php echo _('Missing TLD Fees'); ?></h4><?php

    $temp_all_missing_fees = '';
    $count = 0;

    foreach ($result2 as $row2) {

        $temp_all_missing_fees .= "<a href=\"add/registrar-fee.php?rid=" . htmlentities($rid) . "&tld=" . $row2->tld . "\">." . $row2->tld . "</a>, ";
        $count++;

    }

    $all_missing_fees = substr($temp_all_missing_fees, 0, -2); ?>
    <strong><?php echo $all_missing_fees; ?></strong><BR>
    <?php if ($count == 1) { ?>
        <?php echo sprintf(_('You have domains with %s that use this TLDs, however there are no fees associated with it yet. You should add this fee as soon as possible.'), $registrar_name); ?><BR><BR><BR>
    <?php } else { ?>
       <?php echo sprintf(_('You have domains with %s that use these TLDs, however there are no fees associated with them yet. You should add these fees as soon as possible.'), $registrar_name); ?><BR><BR><BR>
    <?php }

}

if (!$result) { ?>

    <BR><?php echo _("You don't currently have any fees associated with this domain registrar."); ?> <a href="add/registrar-fee.php?rid=<?php echo urlencode($rid); ?>"><?php echo _('Click here to add one'); ?></a>.<?php

} else { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('TLD'); ?></th>
            <th><?php echo _('Initial'); ?></th>
            <th><?php echo _('Renewal'); ?></th>
            <th><?php echo _('Transfer'); ?></th>
            <th><?php echo _('Privacy'); ?></th>
            <th><?php echo _('Misc'); ?></th>
            <th><?php echo _('Currency'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                .<a href="edit/registrar-fee.php?rid=<?php echo urlencode($rid); ?>&fee_id=<?php echo urlencode($row->id); ?>"><?php echo $row->tld; ?></a>
            </td>
            <td><?php
                if ($row->initial_fee > 0) {

                    echo $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td><?php
                if ($row->renewal_fee > 0) {

                    echo $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                } ?>
            </td>
            <td><?php
                if ($row->transfer_fee > 0) {

                    echo $currency->format($row->transfer_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                } ?>
            </td>
            <td><?php
                if ($row->privacy_fee > 0) {

                    echo $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                } ?>
            </td>
            <td><?php
                if ($row->misc_fee > 0) {

                    echo $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                } ?>
            </td>
            <td><?php echo $row->currency; ?>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}
require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>

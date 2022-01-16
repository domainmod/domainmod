<?php
/**
 * /assets/registrar-fees-missing.php
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
<?php
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-registrar-fees-missing.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$result = $pdo->query("
    SELECT r.id AS registrar_id, r.name AS registrar_name
    FROM registrars r, domains d
    WHERE r.id = d.registrar_id
      AND d.fee_id = '0'
    GROUP BY r.name
    ORDER BY r.name ASC")->fetchAll();
?>
<?php echo _('The following Registrars/TLDs are missing Domain fees. In order to ensure your domain reporting is accurate please update these fees as soon as possible.'); ?><BR>
<table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th width="20px"></th>
        <th><?php echo _('Registrar'); ?></th>
        <th><?php echo _('Missing TLD Fees'); ?></th>
    </tr>
    </thead>
    <tbody><?php

    foreach ($result as $row) { ?>

        <tr>
        <td></td>
        <td>
            <?php echo $row->registrar_name; ?>
        </td>
        <td><?php

            $result_missing_tlds = $pdo->query("
                SELECT tld
                FROM domains
                WHERE registrar_id = '" . $row->registrar_id . "'
                  AND fee_id = '0'
                GROUP BY tld
                ORDER BY tld ASC")->fetchAll();

            $full_tld_list = "";

            foreach ($result_missing_tlds as $row_missing_tlds) {

                $full_tld_list .= '<a href=\'' . $web_root . '/assets/add/registrar-fee.php?rid=' . $row->registrar_id . '&tld=' . $row_missing_tlds->tld . '\'>' . $row_missing_tlds->tld . "</a>, ";

            }

            $full_tld_list_formatted = substr($full_tld_list, 0, -2); ?>
            <a href="registrar-fees.php?rid=<?php echo $row->registrar_id; ?>"><?php echo $full_tld_list_formatted; ?></a>
        </td>
        </tr><?php

    } ?>

    </tbody>
</table>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>

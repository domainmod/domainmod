<?php
/**
 * /assets/registrar-fees-missing.php
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
<?php
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';

require_once DIR_ROOT . '/classes/Autoloader.php';
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-registrar-fees-missing.inc.php';
require_once DIR_INC . '/database.inc.php';

$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$sql = "SELECT r.id AS registrar_id, r.name AS registrar_name
        FROM registrars r, domains d
        WHERE r.id = d.registrar_id
          AND d.fee_id = '0'
        GROUP BY r.name
        ORDER BY r.name ASC";
$result = mysqli_query($dbcon, $sql);
?>
The following Registrars/TLDs are missing Domain fees. In order to ensure your domain reporting is accurate please
update these fees as soon as possible.<BR>

<table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th width="20px"></th>
        <th>Registrar</th>
        <th>Missing TLD Fees</th>
    </tr>
    </thead>
    <tbody><?php

    while ($row = mysqli_fetch_object($result)) { ?>

        <tr>
        <td></td>
        <td>
            <?php echo $row->registrar_name; ?>
        </td>
        <td><?php

            $sql_missing_tlds = "SELECT tld
                                 FROM domains
                                 WHERE registrar_id = '" . $row->registrar_id . "'
                                   AND fee_id = '0'
                                 GROUP BY tld
                                 ORDER BY tld ASC";
            $result_missing_tlds = mysqli_query($dbcon, $sql_missing_tlds);

            $full_tld_list = "";

            while ($row_missing_tlds = mysqli_fetch_object($result_missing_tlds)) {

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

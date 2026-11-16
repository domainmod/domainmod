<?php
/**
 * /assets/registrar-fees-missing.php
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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$result = $pdo->query("
    SELECT r.id AS registrar_id, r.name AS registrar_name
    FROM registrars r, domains d
    WHERE r.id = d.registrar_id
      AND d.fee_id = '0'
    GROUP BY r.name
    ORDER BY r.name ASC")->fetchAll();

// When nothing is missing, that's good news rather than an empty table
if (!$result) {

    echo $layout->emptyState('bi-check-circle', _('No missing fees'), _('Every domain has a fee, so its costs can be worked out.'));

} else { ?>
<p class="domainmod-intro"><?php echo _('The following Registrars/TLDs are missing Domain fees. In order to ensure your domain reporting is accurate please update these fees as soon as possible.'); ?></p>
<table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th></th>
        <th><?php echo _('Registrar'); ?></th>
        <th><?php echo _('Missing TLD Fees'); ?></th>
    </tr>
    </thead>
    <tbody><?php

    $stmt = $pdo->prepare("
        SELECT tld
        FROM domains
        WHERE registrar_id = :registrar_id
          AND fee_id = '0'
        GROUP BY tld
        ORDER BY tld ASC");
    $bind_registrar_id = 0;
    $stmt->bindParam('registrar_id', $bind_registrar_id, PDO::PARAM_INT);

    foreach ($result as $row) {

        $bind_registrar_id = $row->registrar_id;
        $stmt->execute();
        $result_missing_tlds = $stmt->fetchAll();

        // Each missing TLD links to Add Registrar Fee for it, with its dot and kept left to right on right-to-left pages
        $missing_tld_links = array();

        foreach ($result_missing_tlds as $row_missing_tlds) {

            $missing_tld_links[] = '<bdi dir="ltr"><a href="' . $web_root . '/assets/add/registrar-fee.php?rid=' .
                urlencode($row->registrar_id) . '&amp;tld=' . urlencode($row_missing_tlds->tld) . '">.' .
                $row_missing_tlds->tld . '</a></bdi>';

        } ?>

        <tr>
        <td></td>
        <td>
            <a href="registrar-fees.php?rid=<?php echo urlencode($row->registrar_id); ?>"><?php echo $row->registrar_name; ?></a>
        </td>
        <td>
            <?php echo implode(', ', $missing_tld_links); ?>
        </td>
        </tr><?php

    } ?>

    </tbody>
</table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>

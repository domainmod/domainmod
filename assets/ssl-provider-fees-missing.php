<?php
/**
 * /assets/ssl-provider-fees-missing.php
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
require_once DIR_INC . '/settings/assets-ssl-provider-fees-missing.inc.php';

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
    SELECT sp.id AS ssl_provider_id, sp.name AS ssl_provider_name
    FROM ssl_providers sp, ssl_certs sc
    WHERE sp.id = sc.ssl_provider_id
      AND sc.fee_id = '0'
    GROUP BY sp.name
    ORDER BY sp.name ASC")->fetchAll();

// When nothing is missing, that's good news rather than an empty table
if (!$result) {

    echo $layout->emptyState('bi-check-circle', _('No missing fees'), _('Every SSL certificate has a fee, so its costs can be worked out.'));

} else { ?>
<p class="domainmod-intro"><?php echo _('The following SSL Certificates are missing fees. In order to ensure your SSL reporting is accurate please update these fees as soon as possible.'); ?></p>
<table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th></th>
        <th><?php echo _('Provider'); ?></th>
        <th><?php echo _('Missing Fees'); ?></th>
    </tr>
    </thead>
    <tbody><?php

    $stmt = $pdo->prepare("
        SELECT sslct.id, sslct.type
        FROM ssl_certs AS sslc, ssl_cert_types AS sslct
        WHERE sslc.type_id = sslct.id
          AND sslc.ssl_provider_id = :ssl_provider_id
          AND sslc.fee_id = '0'
        GROUP BY sslct.type
        ORDER BY sslct.type ASC");
    $bind_ssl_provider_id = 0;
    $stmt->bindParam('ssl_provider_id', $bind_ssl_provider_id, PDO::PARAM_INT);

    foreach ($result as $row) {

        $bind_ssl_provider_id = $row->ssl_provider_id;
        $stmt->execute();
        $result_missing_types = $stmt->fetchAll();

        // Each missing SSL type links to Add SSL Provider Fee for it
        $missing_type_links = array();

        foreach ($result_missing_types as $row_missing_types) {

            $missing_type_links[] = '<a href="' . $web_root . '/assets/add/ssl-provider-fee.php?sslpid=' .
                urlencode($row->ssl_provider_id) . '&amp;type_id=' . urlencode($row_missing_types->id) . '">' .
                $row_missing_types->type . '</a>';

        } ?>

        <tr>
        <td></td>
        <td>
            <a href="ssl-provider-fees.php?sslpid=<?php echo urlencode($row->ssl_provider_id); ?>"><?php echo $row->ssl_provider_name; ?></a>
        </td>
        <td>
            <?php echo implode(', ', $missing_type_links); ?>
        </td>
        </tr><?php

    } ?>

    </tbody>
</table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>

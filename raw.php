<?php
/**
 * /raw.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
// Rates for every currency in use, or the saved list query below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

$_SESSION['s_raw_list_type'] = $_SESSION['s_raw_list_type'] ?? '';
if ($_SESSION['s_raw_list_type'] == 'domains') {

    $page_title = _('Domains (Raw List)');
    $software_section = "domains";

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    $page_title = _('SSL Certificates (Raw List)');
    $software_section = "ssl-certs";

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('raw'); ?>
<?php
// The saved query has a new name, so a query saved by an older version (whose date range wasn't checked) is never run.
unset($_SESSION['s_raw_list_query']);
/*
 * The Domains and SSL Certificates lists' links carry the table's search box when it holds a search (dt_search, with
 * dt_layout and dt_list, added by js/server-list.js), so the list matches the rows the table shows. It's only run on
 * the saved list the link was made for, with the same columns, never on one another tab has saved since.
 */
$table_search = array('sql' => '', 'params' => array(), 'stale' => false);
$list_query = null;
$export_order_sql = '';
if ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    $list_changed = _('The SSL Certificates list has changed since you searched it. Please reload the list and search again.');

} else {

    $list_changed = _('The Domains list has changed since you searched it. Please reload the list and search again.');

}

if (($_GET['dt_search'] ?? '') !== '') {

    if (isset($_SESSION['s_raw_list_unordered_sql'])
        && ($_GET['dt_list'] ?? '') === DomainMOD\ListQuery::savedListKey($_SESSION['s_raw_list_unordered_sql'])) {

        if ($_SESSION['s_raw_list_type'] == 'domains') {

            $list_query = new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'] ?? 0,
                (new DomainMOD\CustomField())->getCustomFieldsSql('domain_fields', 'dfd'));
            $list_settings = DomainMOD\DomainListColumns::sessionCopy($_SESSION);
            $export_order_sql = DomainMOD\DomainList::EXPORT_ORDER_SQL;

        } elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

            $list_query = new DomainMOD\SslList($pdo, $_SESSION['s_user_id'] ?? 0,
                (new DomainMOD\CustomField())->getCustomFieldsSql('ssl_cert_fields', 'sslfd'));
            $list_settings = DomainMOD\SslListColumns::sessionCopy($_SESSION);
            $export_order_sql = DomainMOD\SslList::EXPORT_ORDER_SQL;

        }

    }

    if ($list_query !== null) {

        $table_search = $list_query->tableSearchSql($_GET['dt_search'], $_GET['dt_layout'] ?? '', $list_settings);

    } else {

        $table_search['stale'] = true;

    }

}

if ($table_search['stale']) {

    echo htmlspecialchars($list_changed, ENT_QUOTES, 'UTF-8') . "<BR>";
    $result = array();

} elseif ($table_search['sql'] !== '') {

    $stmt = $pdo->prepare($_SESSION['s_raw_list_unordered_sql'] . $table_search['sql'] . $export_order_sql);
    $stmt->execute($table_search['params']);
    $result = $stmt->fetchAll();

} else {

    $result = isset($_SESSION['s_raw_list_sql']) ? $pdo->query($_SESSION['s_raw_list_sql'])->fetchAll() : array();

}

if ($_SESSION['s_raw_list_type'] == 'domains') {

    foreach ($result as $row) {

        echo $row->domain . "<BR>";

    }

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    foreach ($result as $row) {

        echo $row->name . "<BR>";

    }

}
?>
</body>
</html>

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
<main>
<?php
// The list is the page's main content, so screen readers can go straight to it. Only the page itself scrolls (the
// keyboard scrolls it already), so the list needs no tabindex of its own.
// The saved query has a new name, so a query saved by an older version (whose date range wasn't checked) is never run.
unset($_SESSION['s_raw_list_query']);
/*
 * The Domains and SSL Certificates lists' links carry the table's search box when it holds a search (dt_search, with
 * dt_layout and dt_list, added by js/server-list.js), so the list matches the rows the table shows. It's only run on
 * the saved list the link was made for, with the same columns, never on one another tab has saved since.
 */
$table_search = array('sql' => '', 'params' => array(), 'stale' => false);
$list_query = null;
$list_settings = array();
// The custom fields are only read for a table search on the saved list its link was made for, which can match them
// (the names alone never need them)
$raw_list_search = ($_GET['dt_search'] ?? '') !== '';
$raw_list_search_saved = $raw_list_search && isset($_SESSION['s_raw_list_unordered_sql'])
    && ($_GET['dt_list'] ?? '') === DomainMOD\ListQuery::savedListKey($_SESSION['s_raw_list_unordered_sql']);
if ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    $list_changed = _('The SSL Certificates list has changed since you searched it. Please reload the list and search again.');
    $list_reopen = _('The SSL Certificates list has changed since you opened it. Please reload the list and open Raw List again.');
    $list_query = new DomainMOD\SslList($pdo, $_SESSION['s_user_id'] ?? 0,
        $raw_list_search_saved ? (new DomainMOD\CustomField())->getCustomFieldsSql('ssl_cert_fields', 'sslfd') : '');
    $list_settings = DomainMOD\SslListColumns::sessionCopy($_SESSION);

} else {

    $list_changed = _('The Domains list has changed since you searched it. Please reload the list and search again.');
    $list_reopen = _('The Domains list has changed since you opened it. Please reload the list and open Raw List again.');
    if ($_SESSION['s_raw_list_type'] == 'domains') {

        $list_query = new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'] ?? 0,
            $raw_list_search_saved ? (new DomainMOD\CustomField())->getCustomFieldsSql('domain_fields', 'dfd') : '');
        $list_settings = DomainMOD\DomainListColumns::sessionCopy($_SESSION);

    }

}

if ($raw_list_search) {

    if ($list_query !== null && $raw_list_search_saved) {

        $table_search = $list_query->tableSearchSql($_GET['dt_search'], $_GET['dt_layout'] ?? '', $list_settings);

    } else {

        $table_search['stale'] = true;

    }

}

// What's needed from the session is copied first, so the session can be closed before a long list is read, and the
// user's other tabs aren't held up
$raw_list_saved = isset($_SESSION['s_raw_list_sql']);
$raw_list_from_sql = $_SESSION['s_raw_list_from_sql'] ?? null;
session_write_close();

if ($table_search['stale']) {

    echo htmlspecialchars($list_changed, ENT_QUOTES, 'UTF-8') . "<BR>";

} elseif ($list_query !== null && $raw_list_saved && $raw_list_from_sql === null) {

    // Saved by a version before the list was read in chunks, so it can't be read only by its names
    echo htmlspecialchars($list_reopen, ENT_QUOTES, 'UTF-8') . "<BR>";

} elseif ($list_query !== null && $raw_list_from_sql !== null) {

    // Every output buffer that can be ended is ended, so a host that buffers output can't hold the whole list
    DomainMOD\Export::endOutputBuffers();

    // The names only, in the list's export order, read in chunks inside one read-only snapshot (see
    // ListQuery::exportKeyBatches()), so a long list can't skip or repeat a row between chunks, or use up PHP's memory
    $in_snapshot = false;
    $list_stopped = _('The list stopped early because of an error. Please reload the page to try again.');

    // An error PHP can't catch (such as its time limit) part way through still ends the list with the message
    $list_streaming = true;
    register_shutdown_function(function () use (&$list_streaming, $list_stopped) {
        if ($list_streaming && DomainMOD\Export::fatalError(error_get_last())) {
            echo htmlspecialchars($list_stopped, ENT_QUOTES, 'UTF-8') . "<BR>";
        }
    });

    try {

        $list_query->beginSnapshot();
        $in_snapshot = true;

        foreach ($list_query->exportKeyBatches($raw_list_from_sql . $table_search['sql'], $table_search['params'], null,
            false) as $keys) {

            foreach ($keys as $key) {

                echo $key['ks_name'] . "<BR>";

            }

            DomainMOD\Export::flushOutput();
            DomainMOD\Export::resetTimeLimit();

        }

        $list_query->endSnapshot(true);
        $in_snapshot = false;

        $list_streaming = false;

    } catch (Throwable $e) {

        $list_streaming = false;

        if ($in_snapshot) {

            try {
                $list_query->endSnapshot(false);
            } catch (Throwable $ignored) {
            }

        }

        echo htmlspecialchars($list_stopped, ENT_QUOTES, 'UTF-8') . "<BR>";

        try {
            (new DomainMOD\Log('/raw.php'))->critical('The Raw List stopped early', array('Error' => $e->getMessage()));
        } catch (Throwable $ignored) {
        }

    }

}
?>
</main>
</body>
</html>

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

/*
 * The Domains and SSL Certificates lists' Raw List links name the list they were made for: its saved query and bound
 * values (dt_list, see ListQuery::savedListKey()), its type (dt_type), and its custom field filters' key (dt_cfk, see
 * CustomFieldFilter::filterKey()). With a search in the table, they also carry the search box (dt_search) and the
 * column layout (dt_layout), added by js/server-list.js, so the list matches the rows the table shows. A link is only
 * run on the saved list it was made for, with the same custom fields, never on one another tab has saved since, and its
 * page title and messages follow its own type. A plain link with none of them (an old bookmark) runs the saved list
 * unchecked, as before.
 */
$raw_list_link = isset($_GET['dt_list']) && is_string($_GET['dt_list']);
$raw_list_type = $_SESSION['s_raw_list_type'];
if ($raw_list_link && in_array($_GET['dt_type'] ?? null, array('domains', 'ssl-certs'), true)) {
    $raw_list_type = $_GET['dt_type'];
}

if ($raw_list_type == 'domains') {

    $page_title = _('Domains (Raw List)');
    $software_section = "domains";

} elseif ($raw_list_type == 'ssl-certs') {

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

if ($raw_list_type == 'ssl-certs') {

    $list_changed = _('The SSL Certificates list has changed since you searched it. Please reload the list and search again.');
    $list_reopen = _('The SSL Certificates list has changed since you opened it. Please reload the list and open Raw List again.');
    $list_class = 'DomainMOD\SslList';
    $list_fields_table = 'ssl_cert_fields';
    $list_alias = 'sslfd';
    $list_settings = DomainMOD\SslListColumns::sessionCopy($_SESSION);

} else {

    $list_changed = _('The Domains list has changed since you searched it. Please reload the list and search again.');
    $list_reopen = _('The Domains list has changed since you opened it. Please reload the list and open Raw List again.');
    $list_class = $raw_list_type == 'domains' ? 'DomainMOD\DomainList' : null;
    $list_fields_table = 'domain_fields';
    $list_alias = 'dfd';
    $list_settings = DomainMOD\DomainListColumns::sessionCopy($_SESSION);

}

// What's needed from the session is copied first, so the session can be closed before a long list is read, and the
// user's other tabs aren't held up
$raw_list_search = ($_GET['dt_search'] ?? '') !== '';
$raw_list_saved = isset($_SESSION['s_raw_list_sql']);
$raw_list_from_sql = $_SESSION['s_raw_list_from_sql'] ?? null;
$raw_list_params = is_array($_SESSION['s_raw_list_params'] ?? null) ? $_SESSION['s_raw_list_params'] : array();
$raw_list_filter_keys = is_array($_SESSION['s_raw_list_filter_keys'] ?? null) ? $_SESSION['s_raw_list_filter_keys'] : array();
$raw_list_saved_type = $_SESSION['s_raw_list_type'];
session_write_close();

// The message the list shows instead, when its link no longer matches the saved list: "searched" with a table search,
// "opened" without one
$raw_list_mismatch = $raw_list_search ? $list_changed : $list_reopen;
$raw_list_message = '';
$raw_list_status = DomainMOD\ListQuery::rawLinkStatus($_GET, $raw_list_type, $raw_list_saved_type, $raw_list_from_sql,
    $raw_list_params);
if ($raw_list_status === 'mismatch') {

    $raw_list_message = $raw_list_mismatch;

} elseif ($raw_list_status === 'unchecked' && $raw_list_search) {

    $raw_list_message = $list_changed;

}

if ($raw_list_message !== '') {

    echo htmlspecialchars($raw_list_message, ENT_QUOTES, 'UTF-8') . "<BR>";

} elseif ($list_class !== null && $raw_list_saved && $raw_list_from_sql === null) {

    // Saved by a version before the list was read in chunks, so it can't be read only by its names
    echo htmlspecialchars($list_reopen, ENT_QUOTES, 'UTF-8') . "<BR>";

} elseif ($list_class !== null && $raw_list_from_sql !== null) {

    // Every output buffer that can be ended is ended, so a host that buffers output can't hold the whole list
    DomainMOD\Export::endOutputBuffers();

    // The names only, in the list's export order, read in chunks inside one read-only snapshot (see
    // ListQuery::exportKeyBatches()), so a long list can't skip or repeat a row between chunks, or use up PHP's memory
    $in_snapshot = false;
    $printed = false;
    $list_query = null;
    $list_stopped = _('The list stopped early because of an error. Please reload the page to try again.');

    // An error PHP can't catch (such as its time limit) part way through still ends the list with the message
    $list_streaming = true;
    register_shutdown_function(function () use (&$list_streaming, $list_stopped) {
        if ($list_streaming && DomainMOD\Export::fatalError(error_get_last())) {
            echo htmlspecialchars($list_stopped, ENT_QUOTES, 'UTF-8') . "<BR>";
        }
    });

    try {

        $list_query = new $list_class($pdo, $_SESSION['s_user_id'] ?? 0, array());
        $list_query->beginSnapshot();
        $in_snapshot = true;

        /*
         * The custom fields as the snapshot has them, read before anything is printed: the custom field filters' key
         * must still match the link's (a filtered field deleted and added again under the same name keeps its column,
         * so nothing else would notice), and the table search's columns and its layout check come from the same read.
         * Inside the snapshot they can't change again.
         */
        $table_search = array('sql' => '', 'params' => array(), 'stale' => false);
        $list_ok = true;
        if ($raw_list_link) {

            $list_fields = DomainMOD\CustomField::readFilterFields($pdo, $list_fields_table);
            $list_query = new $list_class($pdo, $_SESSION['s_user_id'] ?? 0, $list_fields);

            if (!hash_equals((new DomainMOD\CustomFieldFilter($list_fields, $list_alias))->filterKey($raw_list_filter_keys),
                $_GET['dt_cfk'])) {

                $list_ok = false;

            } elseif ($raw_list_search) {

                $table_search = $list_query->tableSearchSql($_GET['dt_search'], $_GET['dt_layout'] ?? '', $list_settings);
                if ($table_search['stale']) $list_ok = false;

            }

        }

        if ($list_ok) {

            foreach ($list_query->exportKeyBatches($raw_list_from_sql . $table_search['sql'],
                $raw_list_params + $table_search['params'], null, false) as $keys) {

                foreach ($keys as $key) {

                    echo $key['ks_name'] . "<BR>";
                    $printed = true;

                }

                DomainMOD\Export::flushOutput();
                DomainMOD\Export::resetTimeLimit();

            }

        } else {

            echo htmlspecialchars($raw_list_mismatch, ENT_QUOTES, 'UTF-8') . "<BR>";

        }

        $list_query->endSnapshot(true);
        $in_snapshot = false;

        $list_streaming = false;

    } catch (Throwable $e) {

        $list_streaming = false;

        if ($in_snapshot && $list_query !== null) {

            try {
                $list_query->endSnapshot(false);
            } catch (Throwable $ignored) {
            }

        }

        // A custom field filter's column that's gone (or a table whose definition changed after the snapshot began)
        // before any name was printed means the list has changed, not that it broke
        if (!$printed && DomainMOD\CustomFieldFilter::fieldsChangedError($e)) {

            echo htmlspecialchars($raw_list_mismatch, ENT_QUOTES, 'UTF-8') . "<BR>";

        } else {

            echo htmlspecialchars($list_stopped, ENT_QUOTES, 'UTF-8') . "<BR>";

            try {
                (new DomainMOD\Log('/raw.php'))->critical('The Raw List stopped early', array('Error' => $e->getMessage()));
            } catch (Throwable $ignored) {
            }

        }

    }

}
?>
</main>
</body>
</html>

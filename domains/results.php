<?php
/**
 * /domains/results.php
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
$assets = new DomainMOD\Assets();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

$segid = (int) ($_GET['segid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);
$type = $_GET['type'] ?? '';

// Only the three lists this page shows (a link with any other type has nothing to list).
if (!in_array($type, array('inactive', 'filtered', 'missing'), true)) {

    header("Location: ../segments/");
    exit;

}

require_once DIR_INC . '/settings/domains-results.inc.php';

// A segment that's been deleted (or a changed link) has no results, so it goes back to the segments with a message
$stmt = $pdo->prepare("
    SELECT `name`
    FROM segments
    WHERE id = :segid");
$stmt->bindValue('segid', $segid, PDO::PARAM_INT);
$stmt->execute();
$segment_name = $stmt->fetchColumn();
$stmt->closeCursor();

if ($segment_name === false) {

    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That segment no longer exists.') . '<BR>';
    header("Location: ../segments/");
    exit;

}

// The name is stored escaped, so it's ready to draw (the breadcrumb and the introduction)
$segment_name_html = $segment_name;

if ($export_data === 1) {

    // A streamed export (see Export::begin()), read and written in chunks, so PHP holds only a chunk of the rows at once.
    // An export that fails before its file starts sends the user back to this page.
    $export = new DomainMOD\Export();
    $export->begin('results.php?' . http_build_query(array('type' => $type, 'segid' => $segid)));

    try {

        // Everything from here to the last row reads one snapshot of the database, so the count and the rows always
        // agree, however the data changes in the meantime (a Domains list starts it, since the snapshot is the list
        // reader's, but none of its rows are read)
        $export->startSnapshot(new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'], array()));

        $total_rows = DomainMOD\Segment::resultsCount($pdo, $type, $_SESSION['s_user_id'], $segid);

        if ($type == "inactive") {

            $base_filename = _('segment_results_inactive');

        } elseif ($type == "filtered") {

            $base_filename = _('segment_results_filtered');

        } elseif ($type == "missing") {

            $base_filename = _('segment_results_missing');

        }

        $export_file = $export->openStream($base_filename, strtotime($time->stamp()));

        if ($type == "inactive" || $type == "filtered") {

            if ($type == "inactive") {

                $row_contents = array(DomainMOD\Format::toUpper(_('Inactive Domains')));

            } elseif ($type == "filtered") {

                $row_contents = array(DomainMOD\Format::toUpper(_('Filtered Domains')));

            }

            $export->writeRow($export_file, $row_contents);

            $export->writeBlankRow($export_file);

            $row_contents = array(
                _('Domain Status'),
                _('Expiry Date'),
                _('Initial Fee'),
                _('Renewal Fee'),
                _('Domain'),
                _('TLD'),
                _('Renewal Status'),
                _('WHOIS Status'),
                _('Registrar'),
                _('Username'),
                _('DNS Profile'),
                _('IP Address Name'),
                _('IP Address'),
                _('IP Address rDNS'),
                _('Web Host'),
                _('Category'),
                _('Category Stakeholder'),
                _('Owner'),
                _('Function'),
                _('Notes'),
                _('Inserted'),
                _('Updated')
            );
            $export->writeRow($export_file, $row_contents);

        } elseif ($type == "missing") {

            $row_contents = array(DomainMOD\Format::toUpper(_('Missing Domains')));
            $export->writeRow($export_file, $row_contents);

        }

        $export->chunkWritten();

        foreach (DomainMOD\Segment::resultsChunks($pdo, $type, $_SESSION['s_user_id'], $segid) as $result) {

            if ($type == "inactive" || $type == "filtered") {

                foreach ($result as $row) {

                    $temp_initial_fee = $row->initial_fee * $row->conversion;
                    $temp_renewal_fee = $row->renewal_fee * $row->conversion;

                    if ($row->active == "0") {
                        $domain_status = DomainMOD\Format::toUpper(_('Expired'));
                    } elseif ($row->active == "1") {
                        $domain_status = DomainMOD\Format::toUpper(_('Active'));
                    } elseif ($row->active == "2") {
                        $domain_status = DomainMOD\Format::toUpper(_('Pending (Transfer)'));
                    } elseif ($row->active == "3") {
                        $domain_status = DomainMOD\Format::toUpper(_('Pending (Renewal)'));
                    } elseif ($row->active == "4") {
                        $domain_status = DomainMOD\Format::toUpper(_('Pending (Other)'));
                    } elseif ($row->active == "5") {
                        $domain_status = DomainMOD\Format::toUpper(_('Pending (Registration)'));
                    } elseif ($row->active == "10") {
                        $domain_status = DomainMOD\Format::toUpper(_('Sold'));
                    } else {
                        $domain_status = _('ERROR -- PROBLEM WITH CODE IN RESULTS.PHP');
                    }

                    if ($row->autorenew == "1") {

                        $autorenew_status = _('Auto-Renewal');

                    } elseif ($row->autorenew == "0") {

                        $autorenew_status = _('Manual Renewal');

                    }

                    if ($row->privacy == "1") {

                        $privacy_status = _('Private');

                    } elseif ($row->privacy == "0") {

                        $privacy_status = _('Public');

                    }

                    $export_initial_fee = $currency->format($temp_initial_fee,
                        $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                        $_SESSION['s_default_currency_symbol_space']);

                    $export_renewal_fee = $currency->format($temp_renewal_fee,
                        $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                        $_SESSION['s_default_currency_symbol_space']);

                    $row_contents = array(
                        $domain_status,
                        $row->expiry_date,
                        $export_initial_fee,
                        $export_renewal_fee,
                        $row->domain,
                        '.' . $row->tld,
                        $autorenew_status,
                        $privacy_status,
                        $row->registrar_name,
                        $row->username,
                        $row->dns_profile,
                        $row->name,
                        $row->ip,
                        $row->rdns,
                        $row->wh_name,
                        $row->category_name,
                        $row->category_stakeholder,
                        $row->owner_name,
                        $row->function,
                        $row->notes,
                        $time->toUserTimezone($row->insert_time),
                        $time->toUserTimezone($row->update_time)
                    );
                    $export->writeDataRow($export_file, $row_contents);

                }

            } elseif ($type == "missing") {

                foreach ($result as $missing_domain) {

                    $row_contents = array($missing_domain);
                    $export->writeDataRow($export_file, $row_contents);

                }

            }

            $export->chunkWritten();

        }

        $export->finish($total_rows);

    } catch (Throwable $e) {

        $export->fail($e);

    }

    // finish() and fail() both end the request
    exit;

}

if ($type == "inactive" || $type == "filtered") {

    $stmt = $pdo->prepare(DomainMOD\Segment::resultsSql($type));
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

} elseif ($type == "missing") {

    $stmt = $pdo->prepare("
        SELECT domain
        FROM segment_data
        WHERE segment_id = :segid
          AND missing = '1'
        ORDER BY domain");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

}
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
if (!$result) {

    // TRANSLATORS: Shown when a segment's list of inactive, filtered, or missing domains is empty. %s is the segment's name.
    echo $layout->emptyState('bi-funnel', _('No domains to show'), sprintf(_('None of the domains in the segment %s are in this list right now.'), '<strong>' . $segment_name_html . '</strong>'));

} else {

    // The page's actions, drawn in the page header (see Layout::renderPageActions())
    $layout->addPageAction(_('Export Results'), 'results.php?type=' . $type . '&segid=' . $segid . '&export_data=1', 'bi-download');

    // The status names, as the Domains list shows them
    $status_names = array('0' => _('Expired'), '1' => _('Active'), '2' => _('Pending (Transfer)'),
        '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'), '5' => _('Pending (Registration)'), '10' => _('Sold')); ?>

    <p class="domainmod-intro"><?php
    if ($type == "inactive") {
        echo sprintf(_('The below domains are in the segment %s, and they are stored in your %s database, but they are currently marked as inactive.'), '<strong>' . $segment_name_html . '</strong>', SOFTWARE_TITLE);
    } elseif ($type == "filtered") {
        echo sprintf(_('The below domains are in the segment %s, and they are stored in your %s database, but they were filtered out based on your search criteria.'), '<strong>' . $segment_name_html . '</strong>', SOFTWARE_TITLE);
    } elseif ($type == "missing") {
        echo sprintf(_('The below domains are in the segment %s, and they are not in your %s database.'), '<strong>' . $segment_name_html . '</strong>', SOFTWARE_TITLE);
    } ?>
    </p>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Domain'); ?></th><?php
            if ($type != "missing") { ?>
            <th><?php echo _('Status'); ?></th>
            <th><?php echo _('Expiry Date'); ?></th>
            <th><?php echo _('Registrar'); ?></th><?php
            } ?>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
                <td></td><?php
                if ($type == "missing") { ?>
                <td><?php echo $layout->ltr($row->domain); ?></td><?php
                } else { ?>
                <td><a href="edit.php?did=<?php echo (int) $row->id; ?>"><?php echo $layout->ltr($row->domain); ?></a></td>
                <td><?php echo htmlspecialchars($status_names[(string) $row->active] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $layout->ltr($row->expiry_date); ?></td>
                <td><?php echo $row->registrar_name; ?></td><?php
                } ?>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>

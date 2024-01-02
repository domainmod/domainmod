<?php
/**
 * /domains/results.php
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
$pdo = $deeb->cnxx;

$segid = (int) ($_GET['segid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);
$type = $_GET['type'] ?? '';

if ($type == "inactive") {
    $page_title = _('Segments') . ' - ' . _('Inactive Domains');
} elseif ($type == "filtered") {
    $page_title = _('Segments') . ' - ' . _('Filtered Domains');
} elseif ($type == "missing") {
    $page_title = _('Segments') . ' - ' . _('Missing Domains');
}

$software_section = "segments";

if ($type == "inactive") {

    $stmt = $pdo->prepare("
        SELECT d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
        FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
        WHERE d.account_id = ra.id
          AND ra.registrar_id = r.id
          AND ra.owner_id = o.id
          AND d.registrar_id = f.registrar_id
          AND d.tld = f.tld
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND d.cat_id = cat.id
          AND d.dns_id = dns.id
          AND d.ip_id = ip.id
          AND d.hosting_id = h.id
          AND cc.user_id = :user_id
          AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = :segid AND inactive = '1' ORDER BY domain)
        ORDER BY d.domain ASC");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

} elseif ($type == "filtered") {

    $stmt = $pdo->prepare("
        SELECT d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
        FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
        WHERE d.account_id = ra.id
          AND ra.registrar_id = r.id
          AND ra.owner_id = o.id
          AND d.registrar_id = f.registrar_id
          AND d.tld = f.tld
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND d.cat_id = cat.id
          AND d.dns_id = dns.id
          AND d.ip_id = ip.id
          AND d.hosting_id = h.id
          AND cc.user_id = :user_id
          AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = :segid AND filtered = '1' ORDER BY domain)
        ORDER BY d.domain ASC");
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

if ($export_data === 1) {

    if ($type == "inactive") {

        $base_filename = _('segment_results_inactive');

    } elseif ($type == "filtered") {

        $base_filename = _('segment_results_filtered');

    } elseif ($type == "missing") {

        $base_filename = _('segment_results_missing');

    }

    $export = new DomainMOD\Export();
    $export_file = $export->openFile($base_filename, strtotime($time->stamp()));

    if ($type == "inactive" || $type == "filtered") {

        if ($type == "inactive") {

            $row_contents = array(strtoupper(_('Inactive Domains')));

        } elseif ($type == "filtered") {

            $row_contents = array(strtoupper(_('Filtered Domains')));

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

        $row_contents = array(strtoupper(_('Missing Domains')));
        $export->writeRow($export_file, $row_contents);

    }

    if ($type == "inactive" || $type == "filtered") {

        foreach ($result as $row) {

            $temp_initial_fee = $row->initial_fee * $row->conversion;
            $total_initial_fee_export = $total_initial_fee_export + $temp_initial_fee;

            $temp_renewal_fee = $row->renewal_fee * $row->conversion;
            $total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

            if ($row->active == "0") {
                $domain_status = strtoupper(_('Expired'));
            } elseif ($row->active == "1") {
                $domain_status = strtoupper(_('Active'));
            } elseif ($row->active == "2") {
                $domain_status = strtoupper(_('Pending (Transfer)'));
            } elseif ($row->active == "3") {
                $domain_status = strtoupper(_('Pending (Renewal)'));
            } elseif ($row->active == "4") {
                $domain_status = strtoupper(_('Pending (Other)'));
            } elseif ($row->active == "5") {
                $domain_status = strtoupper(_('Pending (Registration)'));
            } elseif ($row->active == "10") {
                $domain_status = strtoupper(_('Sold'));
            } else {
                $domain_status = _('ERROR -- PROBLEM WITH CODE IN RESULTS.PHP');
            }

            if ($row->autorenew == "1") {

                $autorenew_status = _('Auto Renewal');

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
            $export->writeRow($export_file, $row_contents);

        }

    } elseif ($type == "missing") {

        foreach ($result as $row) {

            $row_contents = array($row->domain);
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
<?php
$page_align = 'left';
require_once DIR_INC . '/layout/header-bare.inc.php'; ?>
<?php
$segment = new DomainMOD\Segment();
$segment_name = $segment->getName($segid);

if ($type == "inactive") {
    echo sprintf(_('The below domains are in the segment %s, and they are stored in your %s database, but they are currently marked as inactive.'), '<strong>' . $segment_name . '</strong>', SOFTWARE_TITLE) . '<BR><BR>';
} elseif ($type == "filtered") {
    echo sprintf(_('The below domains are in the segment %s, and they are stored in your %s database, but they were filtered out based on your search criteria.'), '<strong>' . $segment_name . '</strong>', SOFTWARE_TITLE) . '<BR><BR>';
} elseif ($type == "missing") {
    echo sprintf(_('The below domains are in the segment %s, and they are not in your %s database.'), '<strong>' . $segment_name . '</strong>', SOFTWARE_TITLE) . '<BR><BR>';
}
?>
<?php
if ($type == "inactive") {
    echo "[<a href=\"results.php?type=inactive&segid=" . urlencode($segid) . "&export_data=1\">" . strtoupper(_('Export Results')) . "</a>]<BR><BR>";
} elseif ($type == "filtered") {
    echo "[<a href=\"results.php?type=filtered&segid=" . urlencode($segid) . "&export_data=1\">" . strtoupper(_('Export Results')) . "</a>]<BR><BR>";
} elseif ($type == "missing") {
    echo "[<a href=\"results.php?type=missing&segid=" . urlencode($segid) . "&export_data=1\">" . strtoupper(_('Export Results')) . "</a>]<BR><BR>";
}

foreach ($result as $row) {

    echo $row->domain . "<BR>";

}
?>
<?php require_once DIR_INC . '/layout/footer-bare.inc.php'; ?>
</body>
</html>

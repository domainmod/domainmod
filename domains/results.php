<?php
/**
 * /domains/results.php
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
require_once('../_includes/start-session.inc.php');
require_once('../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$currency = new DomainMOD\Currency();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$segid = $_GET['segid'];
$export_data = $_GET['export_data'];
$type = $_GET['type'];

if ($type == "inactive") {
    $page_title = "Segments - Inactive Domains";
} elseif ($type == "filtered") {
    $page_title = "Segments - Filtered Domains";
} elseif ($type == "missing") {
    $page_title = "Segments - Missing Domains";
}

$software_section = "segments";

if ($type == "inactive") {

    $query = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
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
                AND cc.user_id = ?
                AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = ? AND inactive = '1' ORDER BY domain)
              ORDER BY d.domain asc";
    $params1 = 'ii';
    $params2 = array(&$_SESSION['s_user_id'], &$segid);
    $binding = array(&$id, &$domain, &$tld, &$expiry_date, &$function, &$notes, &$autorenew, &$privacy, &$active, &$insert_time, &$update_time, &$username, &$registrar_name, &$owner_name, &$initial_fee, &$renewal_fee, &$conversion, &$category_name, &$category_stakeholder, &$dns_profile, &$name, &$ip, &$rdns, &$wh_name);

} elseif ($type == "filtered") {

    $query = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
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
                AND cc.user_id = ?
                AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = ? AND filtered = '1' ORDER BY domain)
              ORDER BY d.domain asc";
    $params1 = 'ii';
    $params2 = array(&$_SESSION['s_user_id'], &$segid);
    $binding = array(&$id, &$domain, &$tld, &$expiry_date, &$function, &$notes, &$autorenew, &$privacy, &$active, &$insert_time, &$update_time, &$username, &$registrar_name, &$owner_name, &$initial_fee, &$renewal_fee, &$conversion, &$category_name, &$category_stakeholder, &$dns_profile, &$name, &$ip, &$rdns, &$wh_name);

} elseif ($type == "missing") {

    $query = "SELECT domain
              FROM segment_data
              WHERE segment_id = ?
                AND missing = '1'
              ORDER BY domain";
    $params1 = 'i';
    $params2 = array(&$segid);
    $binding = array(&$domain);

}
$qd = $system->dynamicQuery($conn, $query, $params1, $params2, $binding);

if ($export_data == "1") {

    if ($type == "inactive") {

        $base_filename = "segment_results_inactive";

    } elseif ($type == "filtered") {

        $base_filename = "segment_results_filtered";

    } elseif ($type == "missing") {

        $base_filename = "segment_results_missing";

    }

    $export = new DomainMOD\Export();
    $export_file = $export->openFile("$base_filename", strtotime($time->stamp()));

    if ($type == "inactive" || $type == "filtered") {

        if ($type == "inactive") {

            $row_contents = array('INACTIVE DOMAINS');

        } elseif ($type == "filtered") {

            $row_contents = array('FILTERED DOMAINS');

        }

        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Domain Status',
            'Expiry Date',
            'Initial Fee',
            'Renewal Fee',
            'Domain',
            'TLD',
            'Renewal Status',
            'WHOIS Status',
            'Registrar',
            'Username',
            'DNS Profile',
            'IP Address Name',
            'IP Address',
            'IP Address rDNS',
            'Web Host',
            'Category',
            'Category Stakeholder',
            'Owner',
            'Function',
            'Notes',
            'Inserted',
            'Updated'
        );
        $export->writeRow($export_file, $row_contents);

    } elseif ($type == "missing") {

        $row_contents = array('MISSING DOMAINS');
        $export->writeRow($export_file, $row_contents);

    }

    if ($type == "inactive" || $type == "filtered") {

        while ($qd->fetch()) {

            $temp_initial_fee = $initial_fee * $conversion;
            $total_initial_fee_export = $total_initial_fee_export + $temp_initial_fee;

            $temp_renewal_fee = $renewal_fee * $conversion;
            $total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

            if ($active == "0") {
                $domain_status = "EXPIRED";
            } elseif ($active == "1") {
                $domain_status = "ACTIVE";
            } elseif ($active == "2") {
                $domain_status = "PENDING (TRANSFER)";
            } elseif ($active == "3") {
                $domain_status = "PENDING (RENEWAL)";
            } elseif ($active == "4") {
                $domain_status = "PENDING (OTHER)";
            } elseif ($active == "5") {
                $domain_status = "PENDING (REGISTRATION)";
            } elseif ($active == "10") {
                $domain_status = "SOLD";
            } else {
                $domain_status = "ERROR -- PROBLEM WITH CODE IN RESULTS.PHP";
            }

            if ($autorenew == "1") {

                $autorenew_status = "Auto Renewal";

            } elseif ($autorenew == "0") {

                $autorenew_status = "Manual Renewal";

            }

            if ($privacy == "1") {

                $privacy_status = "Private";

            } elseif ($privacy == "0") {

                $privacy_status = "Public";

            }

            $export_initial_fee = $currency->format($temp_initial_fee,
                $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                $_SESSION['s_default_currency_symbol_space']);

            $export_renewal_fee = $currency->format($temp_renewal_fee,
                $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                $_SESSION['s_default_currency_symbol_space']);

            $row_contents = array(
                $domain_status,
                $expiry_date,
                $export_initial_fee,
                $export_renewal_fee,
                $domain,
                '.' . $tld,
                $autorenew_status,
                $privacy_status,
                $registrar_name,
                $username,
                $dns_profile,
                $name,
                $ip,
                $rdns,
                $wh_name,
                $category_name,
                $category_stakeholder,
                $owner_name,
                $function,
                $notes,
                $time->toUserTimezone($insert_time),
                $time->toUserTimezone($update_time)
            );
            $export->writeRow($export_file, $row_contents);

        }

    } elseif ($type == "missing") {

        while ($qd->fetch()) {

            $row_contents = array($domain);
            $export->writeRow($export_file, $row_contents);

        }

    }

    $qd->close;

    $export->closeFile($export_file);

}
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php
$page_align = 'left';
require_once(DIR_INC . 'layout/header-bare.inc.php'); ?>
<?php
$query = "SELECT `name`
          FROM segments
          WHERE id = ?";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $segid);
    $q->execute();
    $q->store_result();
    $q->bind_result($name);

    while ($q->fetch()) {

        $segment_name = $name;

    }

    $q->close();

} else $error->outputSqlError($conn, "ERROR");

if ($type == "inactive") {
    echo "The below domains are in the segment <strong>" . $segment_name . "</strong>, and they are stored in your  " . $software_title . " database, but they are currently marked as inactive.<BR><BR>";
} elseif ($type == "filtered") {
    echo "The below domains are in the segment <strong>" . $segment_name . "</strong>, and they are stored in your  " . $software_title . " database, but they were filtered out based on your search criteria.<BR><BR>";
} elseif ($type == "missing") {
    echo "The below domains are in the segment <strong>" . $segment_name . "</strong>, but they are not in your " . $software_title . " database.<BR><BR>";
}
?>
<?php
if ($type == "inactive") {
    echo "[<a href=\"results.php?type=inactive&segid=" . urlencode($segid) . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "filtered") {
    echo "[<a href=\"results.php?type=filtered&segid=" . urlencode($segid) . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "missing") {
    echo "[<a href=\"results.php?type=missing&segid=" . urlencode($segid) . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
}
?>
<?php
while ($qd->fetch()) {
    echo $domain . "<BR>";
}
$qd->close;
?>
<?php require_once(DIR_INC . 'layout/footer-bare.inc.php'); ?>
</body>
</html>

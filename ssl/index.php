<?php
/**
 * /ssl/index.php
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
<?php //@formatter:off
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$currency = new DomainMOD\Currency();
$customField = new DomainMOD\CustomField();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-main.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

/*
 * The keyword search as an SQL condition on the domain column. The term is quoted by PDO rather than pasted into the
 * SQL, and a term that's also a domain or label in another spelling (a pasted xn--mnchen-3ya.de) matches its stored
 * form too. Domains are stored in lowercase, so the term is lowercased; the two values are each worked out from what
 * was typed, so casing rules can't send both to the wrong spelling.
 */
$domain_search_condition = function ($column, $term) use ($pdo) {
    $term = htmlspecialchars_decode((string) $term, ENT_QUOTES);
    $lower = mb_strtolower($term, 'UTF-8');
    $conditions = array($column . ' LIKE ' . $pdo->quote('%' . $lower . '%'));
    $idn = DomainMOD\Domain::normalize($term);
    if ($idn !== '' && $idn !== $lower) $conditions[] = $column . ' LIKE ' . $pdo->quote('%' . $idn . '%');
    return '(' . implode(' OR ', $conditions) . ')';
};

$export_data = (int) ($_GET['export_data'] ?? 0);
$oid = (int) ($_REQUEST['oid'] ?? 0);
$did = (int) ($_REQUEST['did'] ?? 0);
$sslpid = (int) ($_REQUEST['sslpid'] ?? 0);
$sslpaid = (int) ($_REQUEST['sslpaid'] ?? 0);
$ssltid = (int) ($_REQUEST['ssltid'] ?? 0);
$sslipid = (int) ($_REQUEST['sslipid'] ?? 0);
$sslpcid = (int) ($_REQUEST['sslpcid'] ?? 0);
$is_active = $_REQUEST['is_active'] ?? '';
$search_for = isset($_REQUEST['search_for']) ? $sanitize->text($_REQUEST['search_for']) : '';
$from_dropdown = (int) ($_REQUEST['from_dropdown'] ?? 0);
$expand = (int) ($_REQUEST['expand'] ?? 0);
$daterange = isset($_REQUEST['daterange']) ? $sanitize->text($_REQUEST['daterange']) : '';

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $from_dropdown = 0;

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The date range is invalid') . '<BR>';

    }

}

if ($export_data !== 1) {

    if ($from_dropdown !== 1) {

        if ($search_for != "") {

            $_SESSION['s_search_for_ssl'] = $search_for;

        } else {

            $_SESSION['s_search_for_ssl'] = "";

        }

        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $_SESSION['s_start_date'] = $new_start_date;
            $_SESSION['s_end_date'] = $new_end_date;

        } else {

            $_SESSION['s_start_date'] = 'YYYY-MM-DD';
            $_SESSION['s_end_date'] = 'YYYY-MM-DD';

        }

    }

}

if ($_SESSION['s_start_date'] == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if ($_SESSION['s_end_date'] == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';

// A range saved by an older version may not have been checked, so anything that isn't a real date range is dropped.
if (!$date->checkDateFormat($_SESSION['s_start_date']) || !$date->checkDateFormat($_SESSION['s_end_date'])) {

    $_SESSION['s_start_date'] = 'YYYY-MM-DD';
    $_SESSION['s_end_date'] = 'YYYY-MM-DD';

}

// The keyword and date range the list uses. An export reads them from its own request when its link carries them
// (FilterToolbar::exportUrl() always does), without touching the session, so filters another tab saved there can't end
// up in the file. An older export link without them falls back to the session, as before.
$list_search_for = $_SESSION['s_search_for_ssl'];
$list_start_date = $_SESSION['s_start_date'];
$list_end_date = $_SESSION['s_end_date'];

if ($export_data === 1) {

    if (array_key_exists('search_for', $_GET)) $list_search_for = $search_for;

    if (array_key_exists('daterange', $_GET)) {

        // The same test the list uses when it saves a range, so the export matches the list exactly
        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $list_start_date = $new_start_date;
            $list_end_date = $new_end_date;

        } else {

            $list_start_date = 'YYYY-MM-DD';
            $list_end_date = 'YYYY-MM-DD';

        }

    }

}

// Anything else (including translated values in links from older versions) falls back to the default
$valid_is_active = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'LIVE', 'ALL');
if (!is_string($is_active) || !in_array($is_active, $valid_is_active, true)) $is_active = 'LIVE';

if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' ";
} elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' ";
} elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' ";
} elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' ";
} elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' ";
} elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' ";
} elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' ";
} elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' ";
} elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' ";
} elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' ";
} elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' ";
} elseif ($is_active == 'LIVE') { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
} elseif ($is_active == 'ALL') { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
}

if ($oid !== 0) {
    $oid_string = " AND o.id = '$oid' ";
} else {
    $oid_string = "";
}
if ($did !== 0) {
    $did_string = " AND d.id = '$did' ";
} else {
    $did_string = "";
}
if ($sslpid !== 0) {
    $sslpid_string = " AND sslp.id = '$sslpid' ";
} else {
    $sslpid_string = "";
}
if ($sslpaid !== 0) {
    $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
} else {
    $sslpaid_string = "";
}
if ($ssltid !== 0) {
    $ssltid_string = " AND sslc.type_id = '$ssltid' ";
} else {
    $ssltid_string = "";
}
if ($sslipid !== 0) {
    $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
} else {
    $sslipid_string = "";
}
if ($sslpcid !== 0) {
    $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
} else {
    $sslpcid_string = "";
}
if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {
    $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($list_start_date) . " AND sslc.expiry_date <= " . $pdo->quote($list_end_date) . ")";
} else {
    $range_string = "";
}
// certificate names are stored HTML-encoded (as the search term is), so they're compared as they are; only the domain
// side of the search is decoded and normalised
if ($list_search_for != "") {
    $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $list_search_for . '%') . ")";
} else {
    $search_string = "";
}

$sslfd_columns = $customField->getCustomFieldsSql('ssl_cert_fields', 'sslfd');

$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.total_cost, sslc.notes, sslc.active, sslc.creation_type_id, sslc.created_by, sslc.insert_time, sslc.update_time, sslpa.id AS sslpa_id, sslpa.username, sslp.id AS sslp_id, sslp.name AS ssl_provider_name, o.id AS o_id, o.name AS owner_name, f.id AS f_id, f.initial_fee, f.renewal_fee, f.misc_fee, cc.conversion, d.domain, sslcf.id as type_id, sslcf.type, ip.id AS ip_id, ip.name as ip_name, ip.ip, ip.rdns, cat.id AS cat_id, cat.name AS cat_name" . $sslfd_columns . "
        FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f,
             currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip,
             categories AS cat, ssl_cert_field_data AS sslfd
        WHERE sslc.account_id = sslpa.id
          AND sslpa.ssl_provider_id = sslp.id
          AND sslpa.owner_id = o.id
          AND sslc.fee_id = f.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND sslc.domain_id = d.id
          AND sslc.type_id = sslcf.id
          AND sslc.ip_id = ip.id
          AND sslc.cat_id = cat.id
          AND sslc.id = sslfd.ssl_id
          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
          $is_active_string
          $oid_string
          $did_string
          $sslpid_string
          $sslpaid_string
          $ssltid_string
          $sslipid_string
          $sslpcid_string
          $range_string
          $search_string";
$_SESSION['s_raw_list_type'] = 'ssl-certs';
$_SESSION['s_raw_list_sql'] = $sql;

// The list's filters as they are now, kept for the empty state's counts, since the filter menus below reuse these names
$list_filter_sql = $is_active_string . $oid_string . $did_string . $sslpid_string . $sslpaid_string . $ssltid_string
    . $sslipid_string . $sslpcid_string . $range_string . $search_string;
$list_has_range = $range_string != '';
$list_has_search = $search_string != '';

// This query is identical to the main query, except that it only does a count
$total_rows = $pdo->query("
    SELECT count(*)
    FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat, ssl_cert_field_data AS sslfd
    WHERE sslc.account_id = sslpa.id
      AND sslpa.ssl_provider_id = sslp.id
      AND sslpa.owner_id = o.id
      AND sslc.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND sslc.domain_id = d.id
      AND sslc.type_id = sslcf.id
      AND sslc.ip_id = ip.id
      AND sslc.cat_id = cat.id
      AND sslc.id = sslfd.ssl_id
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
      $is_active_string
      $oid_string
      $did_string
      $sslpid_string
      $sslpaid_string
      $ssltid_string
      $sslipid_string
      $sslpcid_string
      $range_string
      $search_string")->fetchColumn();

$grand_total = $pdo->query("
    SELECT SUM(sslc.total_cost * cc.conversion) AS grand_total
    FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
    WHERE sslc.account_id = sslpa.id
      AND sslpa.ssl_provider_id = sslp.id
      AND sslpa.owner_id = o.id
      AND sslc.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND sslc.domain_id = d.id
      AND sslc.type_id = sslcf.id
      AND sslc.ip_id = ip.id
      AND sslc.cat_id = cat.id
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
      $is_active_string
      $oid_string
      $did_string
      $sslpid_string
      $sslpaid_string
      $ssltid_string
      $sslipid_string
      $sslpcid_string
      $range_string
      $search_string")->fetchColumn();

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($export_data == 1) {

    $result = $pdo->query($sql)->fetchAll();

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_results'), strtotime($time->stamp()));

    $row_contents = array(_('SSL Certificate Search Results Export'));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Number of SSL Certs') . ':',
        $total_rows
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        _('Total Cost') . ':',
        $grand_total,
        $_SESSION['s_default_currency']
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        '[' . _('Search Filters') . ']'
    );
    $export->writeRow($export_file, $row_contents);

    if ($list_search_for != "") {

        $row_contents = array(
            _('Keyword Search') . ':',
            $list_search_for
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($did > 0) {

        $domain = new DomainMOD\Domain();
        $temp_domain = $domain->getDomain($did);

        $row_contents = array(
            _('Associated Domain') . ':',
            $temp_domain
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($sslpid > 0) {

        $temp_name = $assets->getSslProvider($sslpid);

        $row_contents = array(
            _('SSL Provider') . ':',
            $temp_name
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($sslpaid > 0) {

        $stmt = $pdo->prepare("
            SELECT sslp.name AS ssl_provider_name, o.name AS owner_name, sslpa.username
            FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
            WHERE sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslpa.id = :sslpaid");
        $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
        $stmt->execute();
        // Its own variable, so the certificates in $result are still there for the rows below
        $account_heading = $stmt->fetch();
        $stmt->closeCursor();

        if ($account_heading) {

            $row_contents = array(
                _('SSL Provider Account') . ':',
                $account_heading->ssl_provider_name . " - " . $account_heading->owner_name . " - " . $account_heading->username
            );
            $export->writeRow($export_file, $row_contents);


        }

    }

    if ($ssltid > 0) {

        $temp_type = $assets->getSslType($ssltid);

        $row_contents = array(
            _('SSL Type') . ':',
            $temp_type
        );
        $export->writeRow($export_file, $row_contents);


    }

    if ($sslipid > 0) {

        list($temp_ip, $temp_ip_name) = $assets->getIpAndName($sslipid);

        $row_contents = array(
            _('SSL IP Address') . ':',
            $temp_ip_name . ' (' . $temp_ip . ')'
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($sslpcid > 0) {

        $temp_category = $assets->getCat($sslpcid);

        $row_contents = array(
            _('SSL Category') . ':',
            $temp_category
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($oid > 0) {

        $temp_owner = $assets->getOwner($oid);

        $row_contents = array(
            _('Owner') . ':',
            $temp_owner
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {

        $row_contents = array(
            _('Expiry Date Range') . ':',
            $list_start_date  . " to " . $list_end_date
        );
        $export->writeRow($export_file, $row_contents);

    }

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = _('SSL Status') . ':';

    if ($is_active == 'ALL') {

        $row_contents[$count++] = DomainMOD\Format::toUpper(_('All'));

    } elseif ($is_active == 'LIVE' || $is_active == "") {

        $row_contents[$count++] = DomainMOD\Format::toUpper(_('Live')) . ' (' . _('Active') . ' / ' . _('Pending') . ')';

    } elseif ($is_active == "0") {

        $row_contents[$count++] = _('Expired');

    } elseif ($is_active == "1") {

        $row_contents[$count++] = _('Active');

    } elseif ($is_active == "3") {

        $row_contents[$count++] = _('Pending (Renewal)');

    } elseif ($is_active == "4") {

        $row_contents[$count++] = _('Pending (Other)');

    } elseif ($is_active == "5") {

        $row_contents[$count++] = _('Pending (Registration)');

    }
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = _('SSL Cert Status');
    $row_contents[$count++] = _('Expiry Date');
    $row_contents[$count++] = _('Initial Fee');
    $row_contents[$count++] = _('Renewal Fee');
    $row_contents[$count++] = _('Misc Fee');
    $row_contents[$count++] = _('Total Yearly Cost');
    $row_contents[$count++] = _('Host') . ' / ' . _('Label');
    $row_contents[$count++] = _('Domain');
    $row_contents[$count++] = _('SSL Provider');
    $row_contents[$count++] = _('SSL Provider Account');
    $row_contents[$count++] = _('Username');
    $row_contents[$count++] = _('SSL Type');
    $row_contents[$count++] = _('IP Address Name');
    $row_contents[$count++] = _('IP Address');
    $row_contents[$count++] = _('IP Address rDNS');
    $row_contents[$count++] = _('Category');
    $row_contents[$count++] = _('Owner');
    $row_contents[$count++] = _('Notes');
    $row_contents[$count++] = _('Creation Type');
    $row_contents[$count++] = _('Created By');
    $row_contents[$count++] = _('Inserted');
    $row_contents[$count++] = _('Updated');
    $row_contents[$count++] = DomainMOD\Format::toUpper(_('Custom Fields'));

    $result_field = $pdo->query("
        SELECT `name`
        FROM ssl_cert_fields
        ORDER BY `name` ASC")->fetchAll();

    if ($result_field) {

        foreach ($result_field as $row_field) {

            $row_contents[$count++] = $row_field->name;

        }

    }

    $export->writeRow($export_file, $row_contents);

    foreach ($result as $row) {

        $temp_initial_fee = $row->initial_fee * $row->conversion;
        $temp_renewal_fee = $row->renewal_fee * $row->conversion;
        $temp_misc_fee = $row->misc_fee * $row->conversion;
        $temp_total_cost = $row->total_cost * $row->conversion;

        if ($row->active == "0") {
            $ssl_status = DomainMOD\Format::toUpper(_('Expired'));
        } elseif ($row->active == "1") {
            $ssl_status = DomainMOD\Format::toUpper(_('Active'));
        } elseif ($row->active == "3") {
            $ssl_status = DomainMOD\Format::toUpper(_('Pending (Renewal)'));
        } elseif ($row->active == "4") {
            $ssl_status = DomainMOD\Format::toUpper(_('Pending (Other)'));
        } elseif ($row->active == "5") {
            $ssl_status = DomainMOD\Format::toUpper(_('Pending (Registration)'));
        } else {
            $ssl_status = _('ERROR -- PROBLEM WITH CODE IN SSL/INDEX.PHP');
        }

        $export_initial_fee = $currency->format($temp_initial_fee,
            $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
            $_SESSION['s_default_currency_symbol_space']);

        $export_renewal_fee = $currency->format($temp_renewal_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_misc_fee = $currency->format($temp_misc_fee,
            $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
            $_SESSION['s_default_currency_symbol_space']);

        $export_total_cost = $currency->format($temp_total_cost, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        unset($row_contents);
        $count = 0;

        $creation_type = $system->getCreationType($row->creation_type_id);

        if ($row->created_by == '0') {
            $created_by = _('Unknown');
        } else {
            $user = new DomainMOD\User();
            $created_by = $user->getFullName($row->created_by);
        }

        $row_contents[$count++] = $ssl_status;
        $row_contents[$count++] = $row->expiry_date;
        $row_contents[$count++] = $export_initial_fee;
        $row_contents[$count++] = $export_renewal_fee;
        $row_contents[$count++] = $export_misc_fee;
        $row_contents[$count++] = $export_total_cost;
        $row_contents[$count++] = $row->name;
        $row_contents[$count++] = $row->domain;
        $row_contents[$count++] = $row->ssl_provider_name;
        $row_contents[$count++] = $row->ssl_provider_name . ', ' . $row->owner_name . ' (' . $row->username . ')';
        $row_contents[$count++] = $row->username;
        $row_contents[$count++] = $row->type;
        $row_contents[$count++] = $row->ip_name;
        $row_contents[$count++] = $row->ip;
        $row_contents[$count++] = $row->rdns;
        $row_contents[$count++] = $row->cat_name;
        $row_contents[$count++] = $row->owner_name;
        $row_contents[$count++] = $row->notes;
        $row_contents[$count++] = $creation_type;
        $row_contents[$count++] = $created_by;
        $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
        $row_contents[$count++] = $time->toUserTimezone($row->update_time);
        $row_contents[$count++] = '';

        $sslfd_columns_array = $customField->getCustomFields('ssl_cert_fields');

        if ($sslfd_columns_array != "") {

            foreach ($sslfd_columns_array as $column) {

                $row_contents[$count++] = $row->{$column};

            }

        }

        $export->writeRow($export_file, $row_contents);

    }

    $export->closeFile($export_file);

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
// Double check to make sure there are still no SSL certs in the system
if ($_SESSION['s_has_ssl_cert'] == '0') {

    $queryB = new DomainMOD\QueryBuild();
    $sql_asset_check = $queryB->singleAsset('ssl_certs');
    $_SESSION['s_has_ssl_cert'] = $system->checkForRows($sql_asset_check);

}

// Before anything exists, the list walks a new user through the order things are added in: an SSL provider, then an
// account at it, then a domain, then certificates, one step at a time (Layout::emptyState())
if ($_SESSION['s_has_ssl_provider'] != '1') {

    echo $layout->emptyState('bi-shield-check', _('Add an SSL provider first'), _('SSL certificates belong to an SSL provider account, so start by adding the company you buy them from.'), '/assets/add/ssl-provider.php', _('Add SSL Provider'));

}

if ($_SESSION['s_has_ssl_account'] != '1' && $_SESSION['s_has_ssl_provider'] == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add an SSL provider account next'), _('Add the account you hold at that SSL provider.'), '/assets/add/ssl-provider-account.php', _('Add SSL Account'));

}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {

    // Adding a domain may need a registrar and an account first, so this goes to the Domains list, which guides that
    echo $layout->emptyState('bi-globe2', _('Add a domain first'), _('Each SSL certificate belongs to a domain, so add the domain it covers.'), '/domains/', _('Go to Domains'), '', '', 2, false);

}

if ($_SESSION['s_has_ssl_cert'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_domain'] == '1') {

    echo $layout->emptyState('bi-shield-lock', _('No SSL certificates yet'), _('Add the SSL certificates you want to keep track of.'), '/ssl/add.php', _('Add SSL Cert'));
    $ready_for_ssl_certs = 1;

} elseif ($_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {

    $ready_for_ssl_certs = 1;

}

$result = $pdo->query($sql)->fetchAll();

// The status names, as the status menu shows them, so the menu and the status chip always match. Any other stored value
// shows as its number in both.
$status_names = array('0' => _('Expired'), '1' => _('Active'), '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'),
    '5' => _('Pending (Registration)'),
    'LIVE' => _('Live SSL Certificates') . ' (' . _('Active') . ' / ' . _('Pending') . ')',
    'ALL' => DomainMOD\Format::toUpper(_('All')));

/*
 * The filter toolbar's state (DomainMOD\FilterToolbar, shared with the Domains list): every active filter with its
 * plain-text name for its chip. Each id filter's name is read with one small query, only when that filter is on, and an
 * id that no longer exists still gets a chip.
 */
$filter_toolbar = new DomainMOD\FilterToolbar('ssl-filters', $web_root . '/ssl/index.php', $web_root . '/ssl/index.php');
$filter_name = function ($sql, $id) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name === false ? DomainMOD\FilterToolbar::notFound() : DomainMOD\FilterToolbar::decode($name);
};
if ($did !== 0) $filter_toolbar->addFilter('did', $did, 0, $filter_name("SELECT domain FROM domains WHERE id = :id", $did), _('Domain'));
if ($sslpid !== 0) $filter_toolbar->addFilter('sslpid', $sslpid, 0, $filter_name("SELECT `name` FROM ssl_providers WHERE id = :id", $sslpid), _('SSL Provider'));
if ($sslpaid !== 0) $filter_toolbar->addFilter('sslpaid', $sslpaid, 0, $filter_name("
    SELECT CONCAT(sslp.name, ', ', o.name, ' (', sslpa.username, ')')
    FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
    WHERE sslpa.ssl_provider_id = sslp.id
      AND sslpa.owner_id = o.id
      AND sslpa.id = :id", $sslpaid), _('SSL Provider Account'));
if ($ssltid !== 0) $filter_toolbar->addFilter('ssltid', $ssltid, 0, $filter_name("SELECT type FROM ssl_cert_types WHERE id = :id", $ssltid), _('SSL Type'));
if ($sslipid !== 0) $filter_toolbar->addFilter('sslipid', $sslipid, 0, $filter_name("SELECT CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id = :id", $sslipid), _('IP Address'));
if ($sslpcid !== 0) $filter_toolbar->addFilter('sslpcid', $sslpcid, 0, $filter_name("SELECT `name` FROM categories WHERE id = :id", $sslpcid), _('Category'));
if ($oid !== 0) $filter_toolbar->addFilter('oid', $oid, 0, $filter_name("SELECT `name` FROM owners WHERE id = :id", $oid), _('Owner'));
$filter_toolbar->addFilter('is_active', $is_active, 'LIVE', $status_names[$is_active] ?? $is_active, _('Status'));
if ($list_search_for != '') {
    $filter_keyword = DomainMOD\FilterToolbar::decode($list_search_for);
    $filter_toolbar->addFilter('search_for', $filter_keyword, '', '"' . $filter_keyword . '"', _('Keyword Search'));
}
if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {
    $filter_toolbar->addFilter('daterange', $list_start_date . ' - ' . $list_end_date, '',
        sprintf(_('Expiring: %1$s to %2$s'), $list_start_date, $list_end_date));
}

if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') {

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);

    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);

    echo $form->showFormTop(''); ?>

    <div><?php
            // DOMAIN
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_domain = $pdo->query("
                SELECT d.id, d.domain
                FROM domains AS d, ssl_certs AS sslc
                WHERE d.id = sslc.domain_id
                  AND d.active not in ('0', '10')
                  $is_active_string
                  $oid_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY d.domain
                ORDER BY d.domain asc")->fetchAll();

            echo $form->showDropdownTop('did', _('Domain'), '', '', '');
            echo $form->showDropdownOption('', _('Domain') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_domain as $row_domain) {

                echo $form->showDropdownOption($row_domain->id, $row_domain->domain, $did);

            }
            echo $filter_toolbar->keepOption('did', array_column($result_domain, 'id'));
            echo $form->showDropdownBottom('');


            // SSL PROVIDER
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_ssl_provider = $pdo->query("
                SELECT sslp.id, sslp.name
                FROM ssl_providers AS sslp, ssl_certs AS sslc, domains AS d
                WHERE sslp.id = sslc.ssl_provider_id
                  AND sslc.domain_id = d.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY sslp.name
                ORDER BY sslp.name asc")->fetchAll();

            echo $form->showDropdownTop('sslpid', _('SSL Provider'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Provider') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_ssl_provider as $row_ssl_provider) {

                echo $form->showDropdownOption($row_ssl_provider->id, $row_ssl_provider->name, $sslpid);

            }
            echo $filter_toolbar->keepOption('sslpid', array_column($result_ssl_provider, 'id'));
            echo $form->showDropdownBottom('');


            // SSL PROVIDER ACCOUNT
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_account = $pdo->query("
                SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
                FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_certs AS sslc, domains AS d
                WHERE sslpa.ssl_provider_id = sslp.id
                  AND sslpa.owner_id = o.id
                  AND sslpa.id = sslc.account_id
                  AND sslc.domain_id = d.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY sslp.name, o.name, sslpa.username
                ORDER BY sslp.name asc, o.name asc, sslpa.username asc")->fetchAll();

            echo $form->showDropdownTop('sslpaid', _('SSL Provider Account'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Provider Account') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_account as $row_account) {

                echo $form->showDropdownOption($row_account->sslpa_id, $row_account->sslp_name . ', ' . $row_account->owner_name . ' (' . $row_account->username . ')', $sslpaid);

            }
            echo $filter_toolbar->keepOption('sslpaid', array_column($result_account, 'sslpa_id'));
            echo $form->showDropdownBottom('');


            // TYPE
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_type = $pdo->query("
                SELECT sslc.type_id, sslcf.type
                FROM ssl_certs AS sslc, domains AS d, ssl_cert_types AS sslcf
                WHERE sslc.domain_id = d.id
                  AND sslc.type_id = sslcf.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY sslcf.type
                ORDER BY sslcf.type asc")->fetchAll();

            echo $form->showDropdownTop('ssltid', _('SSL Type'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Type') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_type as $row_type) {

                echo $form->showDropdownOption($row_type->type_id, $row_type->type, $ssltid);

            }
            echo $filter_toolbar->keepOption('ssltid', array_column($result_type, 'type_id'));
            echo $form->showDropdownBottom(''); ?>

            <?php echo $form->showInputText('search_for', _('Keyword Search'), '', DomainMOD\FilterToolbar::decode($_SESSION['s_search_for_ssl']), '100', '', '', '', ''); ?><?php

            // IP ADDRESS
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_ip = $pdo->query("
                SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
                FROM ssl_certs AS sslc, domains AS d, ip_addresses AS ip
                WHERE sslc.domain_id = d.id
                  AND sslc.ip_id = ip.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY ip.name, ip.ip
                ORDER BY ip.name, ip.ip")->fetchAll();

            echo $form->showDropdownTop('sslipid', _('IP Address'), '', '', '');
            echo $form->showDropdownOption('', _('IP Address') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_ip as $row_ip) {

                echo $form->showDropdownOption($row_ip->ip_id, $row_ip->ip_name . ' (' . $row_ip->ip . ')', $sslipid);

            }
            echo $filter_toolbar->keepOption('sslipid', array_column($result_ip, 'ip_id'));
            echo $form->showDropdownBottom('');


            // CATEGORY
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_cat = $pdo->query("
                SELECT c.id AS cat_id, c.name AS cat_name
                FROM ssl_certs AS sslc, domains AS d, categories AS c
                WHERE sslc.domain_id = d.id
                  AND sslc.cat_id = c.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $range_string
                  $search_string
               GROUP BY c.name
               ORDER BY c.name")->fetchAll();

            echo $form->showDropdownTop('sslpcid', _('Category'), '', '', '');
            echo $form->showDropdownOption('', _('Category') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_cat as $row_cat) {

                echo $form->showDropdownOption($row_cat->cat_id, $row_cat->cat_name, $sslpcid);

            }
            echo $filter_toolbar->keepOption('sslpcid', array_column($result_cat, 'cat_id'));
            echo $form->showDropdownBottom('');


            // OWNER
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_owner = $pdo->query("
                SELECT o.id, o.name
                FROM owners AS o, ssl_certs AS sslc, domains AS d
                WHERE o.id = sslc.owner_id
                  AND o.id = d.owner_id
                  $is_active_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY o.name
                ORDER BY o.name asc")->fetchAll();

            echo $form->showDropdownTop('oid', _('Owner'), '', '', '');
            echo $form->showDropdownOption('', _('Owner') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_owner as $row_owner) {

                echo $form->showDropdownOption($row_owner->id, $row_owner->name, $oid);

            }
            echo $filter_toolbar->keepOption('oid', array_column($result_owner, 'id'));
            echo $form->showDropdownBottom('');


            // STATUS
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . ")";
            } else {
                $search_string = "";
            }

            $result_active = $pdo->query("
                SELECT sslc.active, count(*) AS total_count
                FROM ssl_certs AS sslc
                WHERE sslc.id != '0'
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                GROUP BY sslc.active
                ORDER BY sslc.active asc")->fetchAll();

            echo $form->showDropdownTop('is_active', _('Status'), '', '', '');
            echo $form->showDropdownOption('LIVE', _('Live SSL Certificates') . ' (' . _('Active') . ' / ' . _('Pending') . ')', 'LIVE');
            foreach ($result_active as $row_active) {

                $display_text = $row_active->active;

                if (isset($status_names[(string) $row_active->active])) $display_text = $status_names[(string) $row_active->active];

                /* TODO: This needs to be fixed, but it's going to be a very big refactoring job, and this is the best temporary band-aid solution. */
                /*
                 * The problem is that the showDropdownOptionJump method uses the == comparison operator instead of ===,
                 * so 0 technically equals "LIVE", and Expired gets selected in the dropdown menu by default, since it
                 * overrides the actual "LIVE" option.
                 *
                 * It would be easy to just change the == operator to ===, however this may break other instances of
                 * the method, so to fix this properly it's going to take some time.
                 *
                 * This issue also exists on the main Domains page.
                 *
                 * START
                 */
                if ($row_active->active === 0) $row_active->active = '0';
                /*
                 * END
                 */

                echo $form->showDropdownOption($row_active->active, $display_text, $is_active);

            }
            echo $form->showDropdownOption('ALL', DomainMOD\Format::toUpper(_('All')), $is_active);
            echo $filter_toolbar->keepOption('is_active', array_merge(array('LIVE', 'ALL'), array_map('strval', array_column($result_active, 'active'))));
            echo $form->showDropdownBottom('');

            echo $form->showInputText('daterange', _('Expiring Between'), '', $daterange, '23', '', '', '', ''); ?>

            <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                <?php echo $form->showSubmitButton(_('Apply Filters'), '', ''); ?>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/ssl/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Reset Filters'); ?></a>
            </div><?php

            echo $form->showFormBottom(''); ?>
    </div><?php

    echo $filter_toolbar->panelBottom($filter_expanded);

}

if ($result) {

    /*
     * The page's actions, drawn in the page header (see Layout::renderPageActions()). Export's link carries the filters
     * the list actually used, from the filter toolbar's state (FilterToolbar::exportUrl()), since the filter form posts
     * them and they'd otherwise never reach the export. Each active filter is written out (so an inactive-only list
     * keeps is_active=0), and the keyword and date range always are, empty when off.
     */
    $layout->setPageAddAction(_('Add SSL Cert'), 'add.php');
    $layout->addPageAction(_('Raw List'), $web_root . '/raw.php', 'bi-list-ul', array('target' => '_blank'));
    $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl(), 'bi-download'); ?>

    <strong><?php echo _('Number of SSL Certs'); ?>:</strong> <?php echo number_format(count($result)); ?><BR>
    <strong><?php echo _('Total Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?><BR><BR><?php

    if ($total_rows) { ?>

        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th class="all">
                    <?php echo _('Name'); ?>
                </th>
                <?php if ($_SESSION['s_display_ssl_expiry_date'] == "1") { ?>
                <th>
                    <?php echo _('Expiry'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_fee'] == "1") { ?>
                <th>
                    <?php echo _('Fee'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_domain'] == "1") { ?>
                <th>
                    <?php echo _('Domain'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_provider'] == "1") { ?>
                <th>
                    <?php echo _('Provider'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_account'] == "1") { ?>
                <th>
                    <?php echo _('Account'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_type'] == "1") { ?>
                <th>
                    <?php echo _('Type'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_ip'] == "1") { ?>
                <th>
                    <?php echo _('IP Address'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_category'] == "1") { ?>
                <th>
                    <?php echo _('Category'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_owner'] == "1") { ?>
                <th>
                    <?php echo _('Owner'); ?>
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_csf_data']) {

                    foreach ($_SESSION['s_csf_data'] as $field) {

                        if ($field['value'] == 1 && $field['type_id'] != '3') { // Don't show column for Text Areas ?>

                            <th>

                                <?php echo $field['name']; ?>

                            </th><?php

                        }

                    }

                } ?>
            </tr>
            </thead>
            <tbody>
            <?php $today = $time->timeBasic(); // for marking past expiry dates below ?>
            <?php foreach ($result as $row) { ?>
            <tr>
                <td></td>
                <td>
                    <?php if ($row->active == "0") {
                        echo $layout->statusBadge('x', _('Expired'), 'secondary', 'bi-x-circle') . "&nbsp;";
                    } elseif ($row->active == "3") {
                        echo $layout->statusBadge(_('Pending Renewal Characters'), _('Pending (Renewal)'), 'warning', 'bi-arrow-repeat') . "&nbsp;";
                    } elseif ($row->active == "4") {
                        echo $layout->statusBadge(_('Pending Other Characters'), _('Pending (Other)'), 'warning', 'bi-three-dots') . "&nbsp;";
                    } elseif ($row->active == "5") {
                        echo $layout->statusBadge(_('Pending Registration Characters'), _('Pending (Registration)'), 'warning', 'bi-plus-circle') . "&nbsp;";
                    }
                    ?><a href="edit.php?sslcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
                </td>
                <?php if ($_SESSION['s_display_ssl_expiry_date'] == "1") { ?>
                <td>
                    <a href="edit.php?sslcid=<?php echo $row->id; ?>"<?php if ($row->active != '0' && $row->expiry_date < $today) echo ' class="domainmod-css-expired" data-bs-toggle="tooltip" data-bs-title="' . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . $row->expiry_date . ', ' . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '"'; ?>><?php echo $row->expiry_date; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_fee'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider-fee.php?sslpid=<?php echo $row->sslp_id; ?>&fee_id=<?php echo $row->f_id; ?>">
                            <?php
                            $converted_total_cost = $row->total_cost * $row->conversion;
                            $temp_output_amount = $currency->format($converted_total_cost,
                                $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                                $_SESSION['s_default_currency_symbol_space']);
                            echo htmlentities($temp_output_amount, ENT_QUOTES, 'UTF-8');
                            ?>
                        </a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_domain'] == "1") { ?>
                <td>
                    <a href="../domains/edit.php?did=<?php echo $row->domain_id; ?>"><?php echo $row->domain; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_provider'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider.php?sslpid=<?php echo $row->sslp_id; ?>"><?php echo $row->ssl_provider_name; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_account'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider.php?sslpid=<?php echo $row->sslp_id; ?>"><?php echo $row->ssl_provider_name; ?></a>, <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a> (<a href="../assets/edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpa_id; ?>"><?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_type'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-type.php?ssltid=<?php echo $row->type_id; ?>"><?php echo $row->type; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_ip'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ip-address.php?ipid=<?php echo $row->ip_id; ?>"><?php
                        echo $row->ip_name; ?> (<?php echo $row->ip; ?>)</a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_category'] == "1") { ?>
                <td>
                    <a href="../assets/edit/category.php?pcid=<?php echo $row->cat_id; ?>"><?php echo $row->cat_name; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_owner'] == "1") { ?>
                <td>
                    <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                </td>
                <?php } ?>

                <?php if ($_SESSION['s_csf_data']) {

                    foreach ($_SESSION['s_csf_data'] as $field) {

                        if ($field['value'] == 1 && $field['type_id'] != '3') { // Don't show data for Text Areas ?>

                        <td><?php

                            if ($field['type_id'] == 1) { // Check Box

                                echo ($row->{$field['field']} == 1 ? 'Yes' : 'No');

                            } elseif ($field['type_id'] == 2) { // Text

                                echo $layout->customFieldText($row->{$field['field']});

                            } elseif ($field['type_id'] == 4) { // Date

                                if ($row->{$field['field']} == '1970-01-01') {

                                    echo '';

                                } else {

                                    echo $layout->customFieldText($row->{$field['field']});

                                }

                            } elseif ($field['type_id'] == 5) { // Time Stamp

                                if ($row->{$field['field']} == '1970-01-01 00:00:00') {

                                    echo '';

                                } else {

                                    echo $layout->customFieldText($row->{$field['field']});

                                }

                            } elseif ($field['type_id'] == 6) { // URL

                                if ($row->{$field['field']} != '') {

                                    echo $layout->customFieldLink($row->{$field['field']});

                                }

                            } ?>

                        </td><?php

                        }

                    }

                } ?>

            </tr>
            <?php } ?>
            </tbody>
        </table><BR><?php

    }

} else {

    $ready_for_ssl_certs = $ready_for_ssl_certs ?? 0;

    // With no certificates at all, the "No SSL certificates yet" empty state above already offers the Add button
    if ($ready_for_ssl_certs === 1 && $_SESSION['s_has_ssl_cert'] == '1') {

        $layout->setPageAddAction(_('Add SSL Cert'), 'add.php');

    }

    if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') {

        // Certificates exist but none are listed. Which empty state shows depends on why (see the empty states plan).
        $ssl_filters_active = $oid !== 0 || $did !== 0 || $sslpid !== 0 || $sslpaid !== 0 || $ssltid !== 0 || $sslipid !== 0
            || $sslpcid !== 0 || $list_has_range || $list_has_search || $is_active != 'LIVE';

        // The list only shows certificates that have a fee (it joins the fees to work out costs), so these counts
        // leave the fees out, to tell "nothing matches" apart from "hidden until they have a fee"
        $ssl_without_fee_join = "
            FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, domains AS d,
                 ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat, ssl_cert_field_data AS sslfd
            WHERE sslc.account_id = sslpa.id
              AND sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslc.domain_id = d.id
              AND sslc.type_id = sslcf.id
              AND sslc.ip_id = ip.id
              AND sslc.cat_id = cat.id
              AND sslc.id = sslfd.ssl_id";

        if (!$ssl_filters_active) {

            $live_certs = (int) $pdo->query("SELECT count(*) FROM ssl_certs WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')")->fetchColumn();

            if ($live_certs === 0) {

                echo $layout->emptyState('bi-archive', _('No live SSL certificates'), _('Your SSL certificates are all expired or inactive.'), '', '', '/ssl/index.php?is_active=ALL', _('Show all SSL certificates'));

            } else {

                echo $layout->emptyState('bi-cash-coin', _('Some SSL certificates are hidden until they have a fee'), _("SSL certificates without a fee can't be costed, so they don't appear in the list yet."), '', '', '/assets/ssl-provider-fees-missing.php', _('Add the missing fees'));

            }

        } else {

            $filtered_certs = (int) $pdo->query("SELECT count(*)" . $ssl_without_fee_join . $list_filter_sql)->fetchColumn();

            if ($filtered_certs > 0) {

                echo $layout->emptyState('bi-cash-coin', _('Some SSL certificates are hidden until they have a fee'), _("SSL certificates without a fee can't be costed, so they don't appear in the list yet."), '', '', '/assets/ssl-provider-fees-missing.php', _('Add the missing fees'));

            } else {

                echo $layout->emptyState('bi-search', _('No SSL certificates match your filters'), _('Try changing or clearing the filters.'), '', '', '/ssl/index.php', _('Clear filters'));

            }

        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
</body>
</html>

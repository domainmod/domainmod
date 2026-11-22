<?php
/**
 * /queue/index.php
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
$time = new DomainMOD\Time();
$assets = new DomainMOD\Assets();
$queue = new DomainMOD\DomainQueue();
$ssl_queue = new DomainMOD\SslQueue();
$fee_status = new DomainMOD\FeeStatus();
$user = new DomainMOD\User();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$csrf = new DomainMOD\Csrf();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
$queue_rdap_available = $queue->getRdapApiRegistrarId() > 0;
$queue_fix_available = $queue->getEditedCreationTypeId() > 0;
$ssl_queue_available = $ssl_queue->isAvailable();
$read_only = ($_SESSION['s_read_only'] ?? '') == '1';

$list_id = (int) ($_GET['list_id'] ?? 0);
$dell = (int) ($_GET['dell'] ?? 0);
$really_dell = (int) ($_GET['really_dell'] ?? 0);

$domain_id = (int) ($_GET['domain_id'] ?? 0);
$deld = (int) ($_GET['deld'] ?? 0);
$really_deld = (int) ($_GET['really_deld'] ?? 0);

$really_clear = (int) ($_GET['really_clear'] ?? 0);
$s = isset($_GET['s']) ? $sanitize->text($_GET['s']) : '';
$export_data = (int) ($_GET['export_data'] ?? 0);

$result_lists = $pdo->query("
    SELECT dql.id, dql.api_registrar_id, dql.domain_count, dql.owner_id, dql.registrar_id, dql.account_id,
        dql.processing, dql.ready_to_import, dql.finished, dql.copied_to_history, dql.created_by,
        dql.insert_time, r.name AS registrar_name, ra.username AS username, o.name AS owner, ar.name AS api_registrar_name
    FROM domain_queue_list AS dql, registrars AS r, registrar_accounts AS ra, owners AS o, api_registrars AS ar
    WHERE dql.registrar_id = r.id
      AND dql.account_id = ra.id
      AND dql.owner_id = o.id
      AND dql.api_registrar_id = ar.id
    ORDER BY dql.insert_time DESC")->fetchAll();

$result_domains = $pdo->query("
    SELECT dq.id, dq.api_registrar_id, dq.domain_id, dq.owner_id, dq.registrar_id, dq.account_id, dq.domain,
        dq.tld, dq.expiry_date, dq.cat_id, dq.dns_id, dq.ip_id, dq.autorenew, dq.privacy,
        dq.processing, dq.ready_to_import, dq.finished, dq.already_in_domains, dq.already_in_queue, dq.invalid_domain,
        dq.copied_to_history, dq.message, dq.created_by, dq.insert_time, r.name AS registrar_name,
        ra.username AS username, o.name AS owner, ar.name AS api_registrar_name
    FROM domain_queue AS dq
    JOIN api_registrars AS ar ON dq.api_registrar_id = ar.id
    LEFT JOIN registrars AS r ON dq.registrar_id = r.id
    LEFT JOIN registrar_accounts AS ra ON dq.account_id = ra.id
    LEFT JOIN owners AS o ON dq.owner_id = o.id
    ORDER BY dq.already_in_domains ASC, dq.already_in_queue ASC, dq.insert_time DESC, dq.domain ASC")->fetchAll();

if ($export_data === 1) {

    if ($s == 'lists') {

        // list queue
        $export = new DomainMOD\Export();
        $export_file = $export->openFile(_('domain_list_queue'), strtotime($time->stamp()));

        $row_contents = array(_('Domain List Queue'));
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Status'),
            _('Registrar (API)'),
            _('Registrar (Name)'),
            _('Account Owner'),
            _('Account Username'),
            _('Domain Count'),
            _('Ready To Import'),
            _('Copied To History'),
            _('Added By'),
            _('Inserted')
        );
        $export->writeRow($export_file, $row_contents);

        if ($result_lists) {

            foreach ($result_lists as $row_lists) {

                if ($row_lists->finished == '1') {

                    $export_processing = _('Domains Added To Queue');

                } else {

                    if ($row_lists->processing == '1') {

                        $export_processing = _('Processing');

                    } else {

                        $export_processing = _('Pending');

                    }

                }

                $row_contents = array(
                    $export_processing,
                    $row_lists->api_registrar_name,
                    $row_lists->registrar_name,
                    $row_lists->owner,
                    $row_lists->username,
                    $row_lists->domain_count,
                    $row_lists->ready_to_import,
                    $row_lists->copied_to_history,
                    $user->getFullName($row_lists->created_by),
                    $time->toUserTimezone($row_lists->insert_time)
                );
                $export->writeRow($export_file, $row_contents);

            }

        }

        $export->closeFile($export_file);

    } elseif ($s == 'domains') {

        // domain queue
        $export = new DomainMOD\Export();
        $export_file = $export->openFile(_('domain_queue'), strtotime($time->stamp()));

        $row_contents = array(_('Domain Queue'));
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Status'),
            _('Domain'),
            _('Registrar (API)'),
            _('Registrar (Name)'),
            _('Account Owner'),
            _('Account Username'),
            _('TLD'),
            _('Expiry Date'),
            _('DNS Profile'),
            _('IP Address (Name)'),
            _('IP Address (IP)'),
            _('Category'),
            _('Auto-Renew'),
            _('Privacy'),
            _('Ready To Import'),
            _('Already in Domains'),
            _('Already in Queue'),
            _('Invalid Domain'),
            _('Copied To History'),
            _('Added By'),
            _('Inserted'),
            _('Domain ID')
        );
        $export->writeRow($export_file, $row_contents);

        if ($result_domains) {

            foreach ($result_domains as $row_domains) {

                $already_exists = '';
                if ($row_domains->finished == '1' && $row_domains->processing != '1') {

                    if ($row_domains->already_in_domains == '1') {

                        $export_processing = sprintf(_('Already in %s'), SOFTWARE_TITLE);
                        $already_exists = '1';

                    } elseif ($row_domains->already_in_queue == '1') {

                        $export_processing = _('Already in Domain Queue');
                        $already_exists = '1';

                    } elseif ($row_domains->invalid_domain === 1) {

                        $export_processing = _('Invalid Domain');
                        if ($row_domains->message != '') $export_processing .= ': ' . $queue->getRdapMessage($row_domains->message);

                    } elseif ($row_domains->message == 'edited') {

                        $export_processing = _('Imported (Edited Manually)');

                    } else {

                        $export_processing = _('Successfully Imported');

                    }

                } else {

                    if ($row_domains->processing == '1') {

                        $export_processing = _('Processing');

                    } else {

                        $export_processing = _('Pending');
                        if ($row_domains->message != '') $export_processing .= ': ' . $queue->getRdapMessage($row_domains->message);

                    }

                }

                // RDAP rows find their registrar and account when they're processed
                $rdap_unresolved = ($row_domains->finished == '1') ? _('Not resolved') : _('To be found during lookup');
                $export_registrar = $row_domains->registrar_name ?? $rdap_unresolved;

                if ($row_domains->expiry_date == '1970-01-01') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_expiry_date = '-';

                    } else {

                        $export_expiry_date = _("Pending");

                    }

                } else {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_expiry_date = '-';

                    } else {

                        $export_expiry_date = $row_domains->expiry_date;

                    }

                }

                if ($row_domains->dns_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_dns = '-';

                    } else {

                        $export_dns = _('Pending');

                    }

                } else {

                    $export_dns = $assets->getDnsName($row_domains->dns_id);

                }

                if ($row_domains->ip_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_ip_name = '-';
                        $export_ip_address = '-';

                    } else {

                        $export_ip_name = _('Pending');
                        $export_ip_address = _('Pending');

                    }

                } else {

                    list($export_ip_address, $export_ip_name) = $assets->getIpAndName($row_domains->ip_id);

                }

                if ($row_domains->cat_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_category = '-';

                    } else {

                        $export_category = _('Pending');

                    }

                } else {

                    $export_category = $assets->getCat($row_domains->cat_id);

                }

                if ($row_domains->autorenew == '1') {

                    $export_autorenew = _('Yes');

                } else {

                    if ($row_domains->finished == '1') {

                        if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                            $export_autorenew = '-';

                        } else {

                            $export_autorenew = _('No');

                        }

                    } else {

                        $export_autorenew = _('Pending');

                    }

                }

                if ($row_domains->privacy == '1') {

                    $export_privacy = _('Yes');

                } else {

                    if ($row_domains->finished == '1') {

                        if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                            $export_privacy = '-';

                        } else {

                            $export_privacy = _('No');

                        }

                    } else {

                        $export_privacy = _('Pending');

                    }

                }

                $account_export = $row_domains->account_id ? $assets->getUsername($row_domains->account_id) : $rdap_unresolved;

                if ($row_domains->created_by == '0') {

                    $full_name_export = '[' . DomainMOD\Format::toLower(_('Unknown')) . ']';

                } else {

                    $full_name_export = $user->getFullName($row_domains->created_by);

                }

                $row_contents = array(
                    $export_processing,
                    $row_domains->domain,
                    $row_domains->api_registrar_name,
                    $export_registrar,
                    $row_domains->owner,
                    $account_export,
                    $row_domains->tld,
                    $export_expiry_date,
                    $export_dns,
                    $export_ip_name,
                    $export_ip_address,
                    $export_category,
                    $export_autorenew,
                    $export_privacy,
                    $row_domains->ready_to_import,
                    $row_domains->already_in_domains,
                    $row_domains->already_in_queue,
                    $row_domains->invalid_domain,
                    $row_domains->copied_to_history,
                    $full_name_export,
                    $time->toUserTimezone($row_domains->insert_time),
                    $row_domains->domain_id
                );
                $export->writeRow($export_file, $row_contents);

            }

        }

        $export->closeFile($export_file);

    }

}

if ($really_clear === 1) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $queue->clearFinished();

    $_SESSION['s_message_success'] .= _('Completed items cleared from the queue') . '<BR>';

    header("Location: index.php");
    exit;

}

/*
 * A delete's second step: the question, then Yes, Delete (which carries the CSRF token) and a plain Cancel link back to
 * the queue in the alert's own colour, side by side, wrapping on narrow screens
 */
$delete_confirmation = function ($question, $url) use ($layout) {
    return $question . '<div class="d-flex flex-wrap align-items-center gap-3 mt-2">'
        . $layout->linkButton($url, _('Yes, Delete'), 'primary') . '<a class="alert-link" href="index.php">' . _('Cancel') . '</a></div>';
};

if ($dell === 1 && $list_id !== 0) {

    $_SESSION['s_message_danger'] .= $delete_confirmation(_('Are you sure you want to delete this Domain List from the Queue?'),
        'index.php?really_dell=1&list_id=' . $list_id . '&' . $csrf->query());

}

if ($really_dell === 1 && $list_id !== 0) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $stmt = $pdo->prepare("
        DELETE FROM domain_queue_list
        WHERE id = :list_id");
    $stmt->bindValue('list_id', $list_id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_message_success'] .= _('Domain List deleted from Queue') . '<BR>';

    header("Location: index.php");
    exit;

}

if ($deld === 1 && $domain_id !== 0) {

    $_SESSION['s_message_danger'] .= $delete_confirmation(_('Are you sure you want to delete this Domain from the Queue?'),
        'index.php?really_deld=1&domain_id=' . $domain_id . '&' . $csrf->query());

}

if ($really_deld === 1 && $domain_id !== 0) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $stmt = $pdo->prepare("
        DELETE FROM domain_queue
        WHERE id = :domain_id");
    $stmt->bindValue('domain_id', $domain_id, PDO::PARAM_INT);
    $stmt->execute();

    $queue->checkDomainQueue();

    $_SESSION['s_message_success'] .= _('Domain deleted from Queue') . '<BR>';

    header("Location: index.php");
    exit;

}
?>
<?php
/*
 * The menu shows the Import Queue only while either queue has rows, so its flags are worked out again before the header
 * draws the menu: rows are cleared from this page, and imports happen in cron, where no session is updated. The Domain
 * Queue's go by the rows the page lists.
 */
if ($result_lists) {
    $_SESSION['s_domains_in_list_queue'] = '1';
} else {
    unset($_SESSION['s_domains_in_list_queue']);
}
if ($result_domains) {
    $_SESSION['s_domains_in_queue'] = '1';
} else {
    unset($_SESSION['s_domains_in_queue']);
}

$ssl_rows = array();
$ssl_queue_processing = false;

if ($ssl_queue_available) {

    // Checked before the rows are read, so a run that ends in between leaves the notice up (it then reloads the page)
    // rather than showing a finished row as Processing with no notice
    $ssl_queue_processing = $ssl_queue->isProcessing();

    // The other indicators the SSL Queue's imports change are worked out again each time too
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows((new DomainMOD\QueryBuild())->missingFees('ssl_certs'));
    $system->checkExistingAssets();
    $ssl_queue->checkSslQueue();

    $ssl_rows = $ssl_queue->statusRows();

    // A row a run claimed after the check above keeps the notice up too, so it can't look stuck as Processing
    foreach ($ssl_rows as $row) {
        if (DomainMOD\SslQueue::freshClaim($row, time())) $ssl_queue_processing = true;
    }

}

list($domain_waiting_count, $ssl_waiting_count) = $ssl_queue->waitingCounts();

// A part's rows still waiting, after its heading: a badge, which screen readers hear as "(3 waiting)"
$waiting_badge = function ($count) {

    if ($count <= 0) return '';
    // TRANSLATORS: Read out by screen readers after the Domain Queue's or the SSL Queue's heading on the Import Queue page. %s is a number.
    $waiting = sprintf(ngettext('%s waiting', '%s waiting', $count), number_format($count));

    return ' <span class="badge text-bg-secondary ms-1" aria-hidden="true">' . number_format($count) . '</span><span class="visually-hidden"> ('
        . $waiting . ')</span>';

};
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
/*
 * The Import Queue's two parts on one page, each in its own card: the Domain Queue, then the SSL Queue once the database
 * update has added it. Each card's actions are drawn in its header (see Layout::renderCardActions()), so it's clear
 * which part they act on. More Info covers both, so it stays in the page header.
 *
 * The Domain Queue's actions. Read-only users can't add to or clear the queue, so Layout leaves those buttons out for
 * them (the pages turn them away too).
 */
$layout->setPageAddAction($queue_rdap_available ? _('Add Domains Using an API') : _('Add Domains To Queue'), $web_root . '/queue/add/');
if ($queue_rdap_available) $layout->addPageAddMenuItem(_('Add Domains Without an API'), $web_root . '/queue/add/rdap.php', 'bi-list-check');
// Clearing asks first (the confirmation names the queue, as both cards have one), then goes straight to the clear with
// its CSRF token
if ($result_domains) {
    $layout->addPageAction(_('Clear Completed'), 'index.php?really_clear=1&' . $csrf->query(), 'bi-check2-all',
        array('confirm' => array('title' => _('Domain Queue'), 'button' => _('Clear Completed'),
            'message' => _('Are you sure you want to clear completed items from the queue?') . ' '
                . _('Before clearing the queue you should review the results to make sure that everything is correct.'))));
}
if ($result_lists) $layout->addPageAction(_('Export Lists'), 'index.php?s=lists&export_data=1', 'bi-download');
if ($result_domains) $layout->addPageAction(_('Export Domains'), 'index.php?s=domains&export_data=1', 'bi-download'); ?>
<div class="card card-outline card-danger mb-5">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h2 class="card-title me-auto"><?php echo _('Domain Queue') . $waiting_badge($domain_waiting_count); ?></h2>
        <?php echo $layout->renderCardActions(_('Domain Queue actions')); ?>
    </div>
    <div class="card-body">
<?php
$queue->checkProcessingLists();
$queue->checkProcessingDomains();

$_SESSION['s_list_queue_processing'] = $_SESSION['s_list_queue_processing'] ?? 0;
$_SESSION['s_domain_queue_processing'] = $_SESSION['s_domain_queue_processing'] ?? 0;

if ($_SESSION['s_list_queue_processing'] == '1' || $_SESSION['s_domain_queue_processing'] == '1') { ?>

    <?php echo $queue->processingNotice(); ?><BR><BR><?php

} ?>
<p class="domainmod-intro"><?php
if ($queue_rdap_available) {

    echo sprintf(_("The Domain Queue imports your domains and their details automatically. If %s supports your registrar's API, the queue uses it, and if it doesn't, you can paste a list of your domains and have their details looked up using RDAP or WHOIS instead."), SOFTWARE_TITLE) . ' ';

} else {

    echo sprintf(_("The Domain Queue relies on your domain registrar's API to import your domains, so they must have an API and support for it must be built into %s."), SOFTWARE_TITLE) . ' ';

} ?>

<?php echo sprintf(_('See the %sDomain Queue information page%s for more details.'), '<a href="intro.php">', '</a>'); ?></p>
<?php
if ($result_lists) { ?>

    <h3 class="h4"><?php echo _('Domain List Queue'); ?></h3>
    <table id="<?php echo $slug; ?>-lists" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Status'); ?></th>
            <th><?php echo _('Registrar Account'); ?></th>
            <th><?php echo _('Domain Count'); ?></th>
            <th><?php echo _('Added By'); ?></th>
            <th><?php echo _('Added'); ?></th>
            <th><?php echo _('Delete'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result_lists as $row_lists) { ?>

            <tr>
            <td></td>
            <td>
                <?php
                if ($row_lists->finished == '1') {

                    echo _('Domains Added To Queue');

                } else {

                    if ($row_lists->processing == '1') {

                        echo _('Processing');

                    } else {

                        echo _('Pending');

                    }

                } ?>
            </td>
            <td>
                <?php echo $row_lists->registrar_name; ?>, <?php echo $row_lists->owner; ?>
                (<?php echo $row_lists->username; ?>)
            </td>
            <td>
                <?php
                if ($row_lists->domain_count == '0') {

                    $to_display = _('Pending');

                } else {

                    $to_display = $row_lists->domain_count;

                }
                echo $to_display; ?>
            </td>
            <td><?php
                if ($row_lists->created_by == '0') {

                    $to_display = '[' . DomainMOD\Format::toLower(_('Unknown')) . ']';

                } else {

                    $to_display = $user->getFullName($row_lists->created_by);

                }
                echo $to_display; ?>
            </td>
            <td>
                <?php echo $time->toUserTimezone($row_lists->insert_time, 'Y-m-d H:i:s', '[' . DomainMOD\Format::toLower(_('Unknown')) . ']'); ?>
            </td>
            <td>
                <a class="btn btn-sm btn-outline-danger" href="index.php?dell=1&list_id=<?php echo $row_lists->id; ?>" title="<?php echo _('Delete'); ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden"><?php
                    // TRANSLATORS: Read out by screen readers for a Delete button in a list. %s is a domain, or a registrar account (its registrar, owner, and username).
                    echo sprintf(_('Delete %s'), $row_lists->registrar_name . ', ' . $row_lists->owner . ' (' . $row_lists->username . ')'); ?></span></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
}

if ($result_domains) {

    // Beside the Domain List Queue, the domains get their own heading too
    if ($result_lists) { ?>

        <h3 class="h4 mt-5"><?php echo _('Domains'); ?></h3><?php

    } ?>

    <table id="<?php echo $slug; ?>-domains" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Status'); ?></th>
            <th><?php echo _('Domain'); ?></th>
            <th><?php echo _('Registrar Account'); ?></th>
            <th><?php echo _('Expiry Date'); ?></th>
            <th><?php echo _('DNS'); ?></th>
            <th><?php echo _('IP'); ?></th>
            <th><?php echo _('Added By'); ?></th>
            <th><?php echo _('Added'); ?></th>
            <th><?php echo _('Delete'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result_domains as $row_domains) { ?>

            <tr>
            <td></td>
            <td>
                <?php
                $already_exists = '';
                if ($row_domains->finished == '1' && $row_domains->processing != '1') {

                    if ($row_domains->already_in_domains == '1') {

                        echo $layout->highlightText('red', sprintf(_('Already in %s'), SOFTWARE_TITLE));
                        $already_exists = '1';

                    } elseif ($row_domains->already_in_queue == '1') {

                        echo $layout->highlightText('red', _('Already in Domain Queue'));
                        $already_exists = '1';

                    } elseif ($row_domains->invalid_domain === 1) {

                        echo $layout->highlightText('red', _('Invalid Domain'));
                        if ($row_domains->message != '') echo '<BR>' . $queue->getRdapMessageHtml($row_domains->message);

                    } elseif ($row_domains->message == 'edited') {

                        echo $layout->highlightText('green', _('Imported (Edited Manually)'));

                    } else {

                        echo $layout->highlightText('green', _('Successfully Imported'));

                    }

                } else {

                    if ($row_domains->processing == '1') {

                        echo _('Processing');

                    } else {

                        echo _('Pending');
                        if ($row_domains->message != '') echo '<BR>' . $queue->getRdapMessageHtml($row_domains->message);

                    }

                }
                $rdap_unresolved = ($row_domains->finished == '1') ? _('Not resolved') : _('To be found during lookup'); ?>
            </td>
            <td>
                <?php echo $row_domains->domain; ?>
            </td>
            <td>
                <?php echo $row_domains->registrar_name ?? $rdap_unresolved; ?>, <?php echo $row_domains->owner; ?>
                (<?php echo $row_domains->username ?? $rdap_unresolved; ?>)
            </td>
            <td><?php
                if ($row_domains->expiry_date == '1970-01-01') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $to_display = '-';

                    } else {

                        $to_display = _('Pending');

                    }

                } else {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $to_display = '-';

                    } else {

                        $to_display = $row_domains->expiry_date;

                    }

                }
                echo $to_display; ?>
            </td>
            <td><?php
                if ($row_domains->dns_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $to_display = '-';

                    } else {

                        $to_display = _('Pending');

                    }

                } else {

                    $to_display = $assets->getDnsName($row_domains->dns_id);

                }
                echo $to_display; ?>
            </td>
            <td><?php
                if ($row_domains->ip_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $to_display = '-';

                    } else {

                        $to_display = _('Pending');

                    }

                } else {

                    $to_display = $assets->getIpName($row_domains->ip_id);

                }
                echo $to_display; ?>
            </td>
            <td><?php
                if ($row_domains->created_by == '0') {

                    $to_display = '[' . DomainMOD\Format::toLower(_('Unknown')) . ']';

                } else {

                    $to_display = $user->getFullName($row_domains->created_by);

                }
                echo $to_display; ?>
            </td>
            <td>
                <?php echo $time->toUserTimezone($row_domains->insert_time, 'Y-m-d H:i:s', '[' . DomainMOD\Format::toLower(_('Unknown')) . ']'); ?>
            </td>
            <td><?php
                if ($queue_fix_available && $row_domains->finished == '1' && $row_domains->invalid_domain === 1
                    && $row_domains->already_in_domains != '1' && $row_domains->already_in_queue != '1') { ?>
                    <a class="btn btn-sm btn-outline-secondary me-1" href="edit/?qid=<?php echo $row_domains->id; ?>" title="<?php echo _('Fix and Import'); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i><span class="visually-hidden"><?php echo _('Fix and Import'); ?></span></a><?php
                } ?>
                <a class="btn btn-sm btn-outline-danger" href="index.php?deld=1&domain_id=<?php echo $row_domains->id; ?>" title="<?php echo _('Delete'); ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden"><?php
                    // TRANSLATORS: Read out by screen readers for a Delete button in a list. %s is a domain, or a registrar account (its registrar, owner, and username).
                    echo sprintf(_('Delete %s'), $row_domains->domain); ?></span></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
}

// With both lists empty, the Domain Queue says so. Its actions are already in its header, so it repeats none
if (!$result_lists && !$result_domains) {

    echo $layout->emptyState('bi-inbox', _('The Domain Queue is empty'), sprintf(_('Domains you add automatically wait here while %s looks up their details.'), SOFTWARE_TITLE), '', '', '', '', 3);

} ?>
    </div>
</div>
<?php
if ($ssl_queue_available) {

    /*
     * The SSL Queue's actions, which lead to queue/ssl/index.php and come back here. Read-only users can't add, clear,
     * or retry, so they don't get those buttons (the handlers turn them away too).
     */
    list($completed_count, $total_count) = DomainMOD\SslQueue::progressCounts($ssl_rows);
    $failed_count = 0;
    foreach ($ssl_rows as $row) {
        // stale_choice rows ask for the hostname to be added again, so Retry Failed leaves them out
        if (DomainMOD\SslQueue::rowState($row) === 'failed' && $row->message !== 'stale_choice') $failed_count++;
    }

    $layout->setPageAddAction(_('Add SSL Certificates'), $web_root . '/queue/add/ssl.php');
    if ($completed_count > 0) {
        $layout->addPageAction(_('Clear Completed'), 'ssl/index.php?really_clear=1&' . $csrf->query(), 'bi-check2-all',
            array('confirm' => array('title' => _('SSL Queue'), 'button' => _('Clear Completed'),
                'message' => _('Are you sure you want to clear completed items from the queue?') . ' '
                    . _('Before clearing the queue you should review the results to make sure that everything is correct.'))));
    }
    if ($failed_count > 0 && !$read_only) $layout->addPageAction(_('Retry Failed'), 'ssl/index.php?really_retry=1&' . $csrf->query(), 'bi-arrow-repeat');
    if ($ssl_rows) $layout->addPageAction(_('Export'), 'ssl/index.php?export_data=1', 'bi-download'); ?>
    <div class="card card-outline card-danger mb-4">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h2 class="card-title me-auto"><?php echo _('SSL Queue') . $waiting_badge($ssl_waiting_count); ?></h2>
            <?php echo $layout->renderCardActions(_('SSL Queue actions')); ?>
        </div>
        <div class="card-body"><?php

    if ($ssl_queue_processing) { ?>

        <?php echo $ssl_queue->processingNotice(); ?><BR><BR><?php

    } ?>
    <p class="domainmod-intro"><?php
        // TRANSLATORS: Both %1$s are the app's name, DomainMOD.
        echo sprintf(_('The SSL Queue reads the certificate of each site you add and adds it to %1$s as an SSL certificate. New certificates are linked to their domain when that domain is in %1$s.'), SOFTWARE_TITLE); ?>

    <?php echo sprintf(_('See the %sImport Queue information page%s for more details.'), '<a href="intro.php">', '</a>'); ?></p>
    <?php
    if (!$ssl_rows) {

        echo $layout->emptyState('bi-inbox', _('The SSL Queue is empty'), sprintf(_('Hostnames you add wait here while %s reads their certificates.'), SOFTWARE_TITLE), '', '', '', '', 3);

    } else {

        // Imported certificates with no fee are hidden from the SSL Certificates list, so the page says how many
        $no_fee_count = 0;
        foreach ($ssl_rows as $row) {
            if (DomainMOD\SslQueue::rowState($row) === 'imported' && $row->ssl_id !== null && !$fee_status->hasFee($row)) $no_fee_count++;
        }

        if ($no_fee_count > 0) {

            echo $system->showMessageWarning(sprintf(ngettext("%s imported SSL certificate has no fee yet, so it's hidden from the SSL Certificates list until its fee is added.",
                "%s imported SSL certificates have no fee yet, so they're hidden from the SSL Certificates list until their fees are added.", $no_fee_count),
                number_format($no_fee_count)));

        } ?>

        <p><?php
            // TRANSLATORS: The SSL Queue's progress. %1$s and %2$s are numbers, such as "12 of 40 completed".
            echo sprintf(ngettext('%1$s of %2$s completed', '%1$s of %2$s completed', $total_count), number_format($completed_count), number_format($total_count)); ?></p>

        <table id="<?php echo $slug; ?>-hosts" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Status'); ?></th>
                <th><?php echo _('Host'); ?></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('Expiry Date'); ?></th>
                <th><?php echo _('Issuer'); ?></th>
                <th><?php echo _('Added By'); ?></th>
                <th><?php echo _('Date Added'); ?></th><?php
                if (!$read_only) { ?>
                    <th><?php echo _('Delete'); ?></th><?php
                } ?>
            </tr>
            </thead>
            <tbody><?php

            foreach ($ssl_rows as $row) {

                list($state, $label, $flag, $detail) = $ssl_queue->statusParts($row);
                $domain_shown = DomainMOD\SslQueue::domainShown($row);
                // Imported and Already in DomainMOD rows link to the certificate while it still exists
                $linked = ($state === 'imported' || $state === 'already_in_ssl') && $row->ssl_id !== null; ?>

                <tr>
                <td></td>
                <td><div class="domainmod-queue-status"><?php
                    if ($state === 'imported') {
                        echo $layout->highlightText('green', $label);
                    } elseif ($state === 'failed' || $state === 'already_in_queue' || $state === 'already_in_ssl') {
                        echo $layout->highlightText('red', $label);
                    } else {
                        echo $label;
                    }
                    if ($flag !== '') echo ' ' . $layout->highlightText('red', $flag);
                    if ($detail !== '') echo '<BR>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
                    if ($state === 'imported' && $row->ssl_id !== null && !$fee_status->hasFee($row)) {
                        echo '<BR>' . $fee_status->sslWarning($row, !$read_only);
                    } ?></div>
                </td>
                <td><div class="domainmod-queue-host"><?php
                    if ($linked) {
                        echo '<a href="' . htmlspecialchars($web_root . '/ssl/edit.php?sslcid=' . (int) $row->ssl_id, ENT_QUOTES, 'UTF-8') . '">' . $layout->ltr($row->host) . '</a>';
                    } else {
                        echo $layout->ltr($row->host);
                    } ?></div>
                </td>
                <td><?php echo ($domain_shown !== '') ? $layout->ltr($domain_shown) : '-'; ?></td>
                <td><?php echo ($row->expiry_date !== '1970-01-01') ? $row->expiry_date : '-'; ?></td>
                <td><?php echo ($row->issuer !== '') ? htmlspecialchars($unsanitize->text($row->issuer), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                <td><?php echo htmlspecialchars($unsanitize->text(DomainMOD\SslQueue::addedBy($row)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $time->toUserTimezone($row->insert_time, 'Y-m-d H:i:s', '[' . DomainMOD\Format::toLower(_('Unknown')) . ']'); ?></td><?php
                if (!$read_only) { ?>
                    <td>
                        <a class="btn btn-sm btn-outline-danger" href="ssl/index.php?del=1&id=<?php echo (int) $row->id; ?>" title="<?php echo _('Delete'); ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden"><?php
                            // TRANSLATORS: Read out by screen readers for a Delete button in the SSL Queue. %s is a hostname, such as example.com.
                            echo sprintf(_('Delete %s'), htmlspecialchars($row->host, ENT_QUOTES, 'UTF-8')); ?></span></a>
                    </td><?php
                } ?>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    } ?>
        </div>
    </div><?php

}

// More Info covers both parts, so it's the page's own action, in the page header
$layout->addPageAction(_('More Info'), 'intro.php', 'bi-info-circle'); ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>

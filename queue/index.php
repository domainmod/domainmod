<?php
/**
 * /queue/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
$user = new DomainMOD\User();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$list_id = (int) $_GET['list_id'];
$dell = (int) $_GET['dell'];
$really_dell = (int) $_GET['really_dell'];

$domain_id = (int) $_GET['domain_id'];
$deld = (int) $_GET['deld'];
$really_deld = (int) $_GET['really_deld'];

$clear = (int) $_GET['clear'];
$really_clear = (int) $_GET['really_clear'];
$s = $sanitize->text($_GET['s']);
$export_data = (int) $_GET['export_data'];

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
        dq.tld, dq.expiry_date, dq.cat_id, dq.dns_id, dq.ip_id, dq.hosting_id, dq.autorenew, dq.privacy,
        dq.processing, dq.ready_to_import, dq.finished, dq.already_in_domains, dq.already_in_queue, dq.invalid_domain,
        dq.copied_to_history, dq.created_by, dq.insert_time, r.name AS registrar_name,
        ra.username AS username, o.name AS owner, ar.name AS api_registrar_name
    FROM domain_queue AS dq, registrars AS r, registrar_accounts AS ra, owners AS o, api_registrars AS ar
    WHERE dq.registrar_id = r.id
      AND dq.account_id = ra.id
      AND dq.owner_id = o.id
      AND dq.api_registrar_id = ar.id
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
            _('Web Host'),
            _('Category'),
            _('Auto Renew'),
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

                    } else {

                        $export_processing = _('Successfully Imported');

                    }

                } else {

                    if ($row_domains->processing == '1') {

                        $export_processing = _('Processing');

                    } else {

                        $export_processing = _('Pending');

                    }

                }

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

                if ($row_domains->hosting_id == '0') {

                    if ($already_exists == '1' || $row_domains->invalid_domain === 1) {

                        $export_host = '-';

                    } else {

                        $export_host = _('Pending');

                    }

                } else {

                    $export_host = $assets->getHost($row_domains->hosting_id);

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

                $account_export = $assets->getUsername($row_domains->account_id);

                if ($row_domains->created_by == '0') {

                    $full_name_export = '[' . strtolower(_('Unknown')) . ']';

                } else {

                    $full_name_export = $user->getFullName($row_domains->created_by);

                }

                $row_contents = array(
                    $export_processing,
                    $row_domains->domain,
                    $row_domains->api_registrar_name,
                    $row_domains->registrar_name,
                    $row_domains->owner,
                    $account_export,
                    $row_domains->tld,
                    $export_expiry_date,
                    $export_dns,
                    $export_ip_name,
                    $export_ip_address,
                    $export_host,
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

if ($clear === 1) {

    $_SESSION['s_message_danger'] .= _('Are you sure you want to clear completed items from the queue?') . '<BR><BR>' .
        _('Before clearing the queue you should review the results to make sure that everything is correct.') . '<BR><BR>' .
        '<a href="index.php?really_clear=1">' . _('YES, REALLY CLEAR COMPLETED ITEMS FROM THE QUEUE') . '</a><BR>';

}

if ($really_clear === 1) {

    $queue->clearFinished();

    $_SESSION['s_message_success'] .= _('Completed items cleared from the queue') . '<BR>';

    header("Location: index.php");
    exit;

}

if ($dell === 1 && $list_id !== 0) {

    $_SESSION['s_message_danger'] .= _('Are you sure you want to delete this Domain List from the Queue?') . '<BR><BR>' .
        '<a href="index.php?really_dell=1&list_id=' . $list_id . '">' . _('YES, REALLY DELETE THIS DOMAIN LIST FROM THE QUEUE') . '</a><BR>';

}

if ($really_dell === 1 && $list_id !== 0) {

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

    $_SESSION['s_message_danger'] .= _('Are you sure you want to delete this Domain from the Queue?') . '<BR><BR>' .
        '<a href="index.php?really_deld=1&domain_id=' . $domain_id . '">' . _('YES, REALLY DELETE THIS DOMAIN FROM THE QUEUE') . '</a><BR>';

}

if ($really_deld === 1 && $domain_id !== 0) {

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
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$queue->checkProcessingLists();
$queue->checkProcessingDomains();
if ($_SESSION['s_list_queue_processing'] == '1' || $_SESSION['s_domain_queue_processing'] == '1') { ?>

    <button type="button" class="btn btn-default btn-lrg">
        <i class="fa fa-spin fa-refresh"></i>&nbsp;&nbsp;&nbsp;<?php echo _('The Queue Is Currently Processing'); ?>
    </button><BR><BR><?php

} ?>
<?php echo sprintf(_("The Domain Queue relies on your domain registrar's API to import your domains, so they must have an API and support for it must be built into %s."), SOFTWARE_TITLE); ?>

<?php echo sprintf(_("For more information please see the %sDomain Queue information page%s"), '<a href="intro.php">', '</a>'); ?><BR>
<BR>
<a href="<?php echo $web_root; ?>/queue/add/"><?php echo $layout->showButton('button', _('Add Domains To Queue')); ?></a>
<?php if ($_SESSION['s_domains_in_queue'] == '1') { ?>
<a href="index.php?clear=1"><?php echo $layout->showButton('button', _('Clear Completed')); ?></a>
<?php } ?>
<a href="intro.php"><?php echo $layout->showButton('button', _('More Info')); ?></a>
<?php if ($_SESSION['s_domains_in_list_queue'] == '1') { ?>
<a href="index.php?s=lists&export_data=1"><?php echo $layout->showButton('button', _('Export Lists')); ?></a>
<?php } ?>
<?php if ($_SESSION['s_domains_in_queue'] == '1') { ?>
<a href="index.php?s=domains&export_data=1"><?php echo $layout->showButton('button', _('Export Domains')); ?></a>
<?php } ?>
<BR><BR>
<?php
if (!$result_lists) {

    unset($_SESSION['s_domains_in_list_queue']);

} else { ?>

    <h3><?php echo _('Domain List Queue'); ?></h3>
    <table id="<?php echo $slug; ?>-lists" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
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

                    $to_display = '[' . strtolower(_('Unknown')) . ']';

                } else {

                    $to_display = $user->getFullName($row_lists->created_by);

                }
                echo $to_display; ?>
            </td>
            <td><?php

                if ($row_lists->insert_time != '1970-01-01 00:00:00') {

                    $to_display = $time->toUserTimezone($row_lists->insert_time);

                } else {

                    $to_display = '[' . strtolower(_('Unknown')) . ']';

                }
                echo $to_display; ?>
            </td>
            <td>
                <a href="index.php?dell=1&list_id=<?php echo $row_lists->id; ?>"><i class="fa fa-times" style="padding-top: 3px;"></i></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
}

if (!$result_domains) {

    unset($_SESSION['s_domains_in_queue']);

} else { ?>

    <h3><?php echo _('Domain Queue'); ?></h3>
    <table id="<?php echo $slug; ?>-domains" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
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

                    } else {

                        echo $layout->highlightText('green', _('Successfully Imported'));

                    }

                } else {

                    if ($row_domains->processing == '1') {

                        echo _('Processing');

                    } else {

                        echo _('Pending');

                    }

                } ?>
            </td>
            <td>
                <?php echo $row_domains->domain; ?>
            </td>
            <td>
                <?php echo $row_domains->registrar_name; ?>, <?php echo $row_domains->owner; ?>
                (<?php echo $row_domains->username; ?>)
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

                    $to_display = '[' . strtolower(_('Unknown')) . ']';

                } else {

                    $to_display = $user->getFullName($row_domains->created_by);

                }
                echo $to_display; ?>
            </td>
            <td><?php
                if ($row_domains->insert_time != '1970-01-01 00:00:00') {

                    $to_display = $time->toUserTimezone($row_domains->insert_time);

                } else {

                    $to_display = '[' . strtolower(_('Unknown')) . ']';

                }
                echo $to_display; ?>
            </td>
            <td>
                <a href="index.php?deld=1&domain_id=<?php echo $row_domains->id; ?>"><i class="fa fa-times" style="padding-top: 3px;"></i></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>

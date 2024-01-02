<?php
/**
 * /admin/dw/dw.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$dropdown_selection = isset($_REQUEST['dropdown_selection']) ? $sanitize->text($_REQUEST['dropdown_selection']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $dropdown_selection != "") {

    $action = explode('-', $dropdown_selection);

    if ($action[0] == "accounts") {

        if ($action[1] == 'ALL') {

            $_SESSION['s_dw_view_all'] = 1;

        } else {

            $stmt = $pdo->prepare("
                SELECT `name`, `host`
                FROM dw_servers
                WHERE id = :id");
            $stmt->bindValue('id', $action[1], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $_SESSION['s_dw_view_all'] = "";
                $_SESSION['s_dw_server_id'] = $action[1];
                $_SESSION['s_dw_server_name'] = $result->name;
                $_SESSION['s_dw_server_host'] = $result->host;

            }

        }

        header("Location: list-accounts.php");
        exit;

    } elseif ($action[0] == "zones") {

        if ($action[1] == 'ALL') {

            $_SESSION['s_dw_view_all'] = 1;

        } else {

            $stmt = $pdo->prepare("
                SELECT `name`, `host`
                FROM dw_servers
                WHERE id = :id");
            $stmt->bindValue('id', $action[1], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $_SESSION['s_dw_view_all'] = "";
                $_SESSION['s_dw_server_id'] = $action[1];
                $_SESSION['s_dw_server_name'] = $result->name;
                $_SESSION['s_dw_server_host'] = $result->host;

            }

        }

        header("Location: list-zones.php");
        exit;

    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $dropdown_selection == "") {

    $_SESSION['s_message_danger'] = _('Invalid selection, please try again');

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
$result = $pdo->query("
    SELECT id
    FROM dw_servers
    LIMIT 1")->fetchAll();

if (!$result) {

    $has_servers = 0;

} else {

    $has_servers = 1;

}
?>
<a href="servers.php"><?php echo $layout->showButton('button', _('Manage Servers')); ?></a>&nbsp;&nbsp;<?php
if ($has_servers == 1) { ?><a href="rebuild.php"><?php echo $layout->showButton('button', _('Rebuild DW')); ?></a><?php } ?>
<?php
$result = $pdo->query("
    SELECT count(*)
    FROM dw_accounts")->fetchColumn();

if (!$result) {

    $no_results_accounts = 1;

} else {

    $temp_total_accounts = $result;

}

$result = $pdo->query("
    SELECT count(*)
    FROM dw_dns_records")->fetchColumn();

if (!$result) {

    $no_results_dns_zones = 1;

} else {

    $temp_total_dns_zones = $result;

}

$result = $pdo->query("
    SELECT build_status_overall
    FROM dw_servers
    LIMIT 1")->fetchColumn();

if ($result) {

    $is_the_build_finished = $result;

}
$is_the_build_finished = $is_the_build_finished ?? 0;
if ($is_the_build_finished == 1) {

    if ($no_results_accounts !== 1 || $no_results_dns_zones !== 1) { ?>

        <BR><BR><h3><?php echo _('View Data'); ?></h3>
        <?php

        echo $form->showFormTop('');
        echo $form->showDropdownTop('dropdown_selection', '', '', '', '');

        if ($temp_total_accounts != 0) {

            echo $form->showDropdownOption('', strtoupper(_('Server Accounts')), 'null');
            echo $form->showDropdownOption('accounts-ALL', '&nbsp;&nbsp;' . _('View All'), 'null');

            $result = $pdo->query("
                SELECT id, `name`, dw_accounts
                FROM dw_servers
                ORDER BY name, `host`")->fetchAll();

            foreach ($result as $row) {

                echo $form->showDropdownOption('accounts-' . $row->id, '&nbsp;&nbsp;' . $row->name . ' (' . number_format($row->dw_accounts) . ' ' . _('Accounts') . ')', 'null');

            }

        }

        if ($temp_total_dns_zones != 0) {

            echo $form->showDropdownOption('', strtoupper(_('DNS Zones & Records')), 'null');
            echo $form->showDropdownOption('zones-ALL', '&nbsp;&nbsp;' . _('View All'), 'null');

            $result = $pdo->query("
                SELECT id, name, dw_dns_zones, dw_dns_records
                FROM dw_servers
                ORDER BY name, host")->fetchAll();

            foreach ($result as $row) {

                echo $form->showDropdownOption('zones-' . $row->id, '&nbsp;&nbsp;' . $row->name . ' (' . number_format($row->dw_dns_zones) . ' ' . _('Zones') . ', ' . number_format($row->dw_dns_records) . ' ' . _('Records') . ')', 'null');

            }

        }

        echo $form->showDropdownBottom('');
        echo $form->showSubmitButton(_('View Data'), '', '');
        echo $form->showFormBottom('');

    }

}

$stmt = $pdo->prepare("
    SELECT build_status_overall, build_start_time_overall, build_end_time_overall, build_time_overall,
        has_ever_been_built_overall
    FROM dw_servers
    ORDER BY build_end_time_overall DESC
    LIMIT 1");
$stmt->execute();
$result = $stmt->fetch();
$stmt->closeCursor();

$result_count = $pdo->query("
    SELECT count(*)
    FROM dw_servers")->fetchColumn();

if (!$result) {

    $no_results_build_info = 1;

}

$no_results_build_info = $no_results_build_info ?? 0;
if ($no_results_build_info !== 1) { ?>

    <BR><BR><h3><?php echo _('Build Information'); ?></h3>
    <table id="<?php echo $slug; ?>-build" class="<?php echo $datatable_class; ?>"><?php

    if ($result_count == 0) {

        echo "<BR>" . _("You don't currently have any servers setup in your Data Warehouse.") . " <a href=\"add-server.php\">" . _('Click here to add one') . "</a>.";

    } else {

        if ($result->build_start_time_overall != '1970-01-01 00:00:00' &&
            $result->build_end_time_overall != '1970-01-01 00:00:00') {

            $temp_build_status_overall = _('Successful');

        }

        if ($result->build_start_time_overall != '1970-01-01 00:00:00' &&
            $result->has_ever_been_built_overall == 0) {

            $temp_build_status_overall = _('Building') . '...';

        }

        if ($result->build_start_time_overall != '1970-01-01 00:00:00' &&
            $result->build_end_time_overall == '1970-01-01 00:00:00' &&
            $result->build_status_overall == 0) {

            $result2 = $pdo->query("
                SELECT id
                FROM dw_servers
                WHERE build_status = '0'
                LIMIT 1")->fetchColumn();

            if (!$result2) {

                $temp_build_status_overall = _('Cleanup') . '...';

            } else {

                $temp_build_status_overall = _('Building') . '...';

            }

            $is_building = 1;

        }

        if ($result->build_start_time_overall == '1970-01-01 00:00:00' &&
            $result->has_ever_been_built_overall == 0) {

            $temp_build_status_overall = _('Never Built');

        }

        if ($result->build_start_time_overall == '1970-01-01 00:00:00') {

            $temp_build_start_time_overall = "-";

        } else {

            $temp_build_start_time_overall = $time->toUserTimezone($result->build_start_time_overall, 'M jS @ g:i:sa');

        }

        if ($result->build_end_time_overall == '1970-01-01 00:00:00') {

            $temp_build_end_time_overall = "-";

        } else {

            $temp_build_end_time_overall = $time->toUserTimezone($result->build_end_time_overall, 'M jS @ g:i:sa');

        }

        if ($result->build_time_overall <= 0) {

            $temp_build_time_overall = "-";

        } elseif ($result->build_time_overall > 0 && $result->build_time_overall <= 60) {

            $temp_build_time_overall = number_format($result->build_time_overall) . "s";

        } else {

            $number_of_minutes = intval($result->build_time_overall / 60);
            $number_of_seconds = $result->build_time_overall - ($number_of_minutes * 60);

            $temp_build_time_overall = $number_of_minutes . "m " . $number_of_seconds . "s";

        } ?>

        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Server'); ?></th>
            <th><?php echo _('Build Start'); ?></th>
            <th><?php echo _('Build End'); ?></th>
            <th><?php echo _('Build Time'); ?></th>
            <th><?php echo _('Build Status'); ?></th>
        </tr>
        </thead>

        <tbody>
        <tr>
            <td></td>
            <td><em><?php echo _('Full Build'); ?></em></td>
            <td><?php echo $temp_build_start_time_overall ?? ''; ?></td>
            <td><?php echo $temp_build_end_time_overall ?? ''; ?></td>
            <td><?php echo $temp_build_time_overall ?? ''; ?></td>
            <td><?php echo $temp_build_status_overall ?? ''; ?></td>
        </tr><?php

    }

    $result = $pdo->query("
        SELECT `name`, `host`, build_status, build_start_time, build_end_time, build_time, has_ever_been_built
        FROM dw_servers
        ORDER BY name, host")->fetchAll();

    if (!$result) {

        echo "";

    } else {

        foreach ($result as $row) {

            //@formatter:off

            if ($row->build_start_time != '1970-01-01 00:00:00' && $row->build_end_time != '1970-01-01 00:00:00') {

                $temp_build_status = _('Successful');

            }

            if ($row->build_start_time != '1970-01-01 00:00:00' && $row->build_end_time == '1970-01-01 00:00:00' &&
                $row->build_status == 0) {

                $temp_build_status = _('Building') . '...';

            }

            if ($row->build_start_time == '1970-01-01 00:00:00' && $row->has_ever_been_built == 0) {

                if ($is_building == 1) {

                    $temp_build_status = _('Pending');

                } else {

                    $temp_build_status = _('Never Built');

                }

            }

            if ($row->build_start_time == '1970-01-01 00:00:00' && $row->has_ever_been_built == 1) {

                $temp_build_status = _('Pending');

            }

            if ($row->build_start_time != '1970-01-01 00:00:00' && $row->has_ever_been_built == 0) {

                $temp_build_status = _('Building') . '...';

            }

            if ($row->build_start_time == '1970-01-01 00:00:00') {

                $temp_build_start_time = "-";

            } else {

                $temp_build_start_time = $time->toUserTimezone($row->build_start_time, 'M jS @ g:i:sa');

            }

            if ($row->build_end_time == '1970-01-01 00:00:00') {

                $temp_build_end_time = "-";

            } else {

                $temp_build_end_time = $time->toUserTimezone($row->build_end_time, 'M jS @ g:i:sa');

            }

            if ($row->build_time <= 0) {

                $temp_build_time = "-";

            } elseif ($row->build_time > 0 && $row->build_time <= 60) {

                $temp_build_time = number_format($row->build_time) . "s";

            } else {

                $number_of_minutes = intval($row->build_time / 60);
                $number_of_seconds = $row->build_time - ($number_of_minutes * 60);
                $temp_build_time = $number_of_minutes . "m " . $number_of_seconds . "s";

            } ?>

            <tr>
                <td></td>
                <td>
                    <?php echo $row->name; ?>
                </td>
                <td>
                    <?php echo $temp_build_start_time; ?>
                </td>
                <td>
                    <?php echo $temp_build_end_time; ?>
                </td>
                <td>
                    <?php echo $temp_build_time; ?>
                </td>
                <td>
                    <?php echo $temp_build_status; ?>
                </td>
            </tr><?php

            //@formatter:off

        }

    } ?>

    </tbody>
    </table><?php

}

$result = $pdo->query("
    SELECT dw_accounts, dw_dns_zones, dw_dns_records
    FROM dw_server_totals")->fetchAll();

if ($result) {

    foreach ($result as $row) {

        $temp_dw_accounts = $row->dw_accounts;
        $temp_dw_dns_zones = $row->dw_dns_zones;
        $temp_dw_dns_records = $row->dw_dns_records;

    }

}

if ($result) {

    $dwstats = new DomainMOD\DwStats();
    $found_table = $dwstats->checkForServerTotalsTable();

    if (!$found_table) {

        $table_exists = 0;

    } else {

        $table_exists = 1;

    }

    $is_building = $is_building ?? 0;
    if ($is_building != 1 && $table_exists != 0 && $temp_dw_accounts != 0 && $temp_dw_dns_zones != 0 &&
        $temp_dw_dns_records != 0
    ) { ?>

        <BR><h3><?php echo _('Data Warehouse Totals'); ?></h3>

        <table id="<?php echo $slug; ?>-totals" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th><?php echo _('Server'); ?></th>
                <th><?php echo _('Accounts'); ?></th>
                <th><?php echo _('DNS Zones'); ?></th>
                <th><?php echo _('DNS Records'); ?></th>
            </tr>
            </thead>
            <tbody><?php

        $stmt2 = $pdo->prepare("
            SELECT dw_servers, dw_accounts, dw_dns_zones, dw_dns_records
            FROM dw_server_totals");
        $stmt2->execute();
        $result2 = $stmt2->fetch();
        $stmt2->closeCursor();

        if ($result2) {

            if ($result2->dw_servers > 1) { ?>

                <tr>
                <td></td>
                <td>
                    <em><?php echo _('All Servers'); ?></em>
                </td>
                <td>
                    <?php echo number_format($result2->dw_accounts); ?>
                </td>
                <td>
                    <?php echo number_format($result2->dw_dns_zones); ?>
                </td>
                <td>
                    <?php echo number_format($result2->dw_dns_records); ?>
                </td>
                </tr><?php

            }

        }

        $result2 = $pdo->query("
            SELECT `name`, dw_accounts, dw_dns_zones, dw_dns_records
            FROM dw_servers
            WHERE has_ever_been_built = '1'
            ORDER BY name")->fetchAll();

        foreach ($result2 as $row2) { ?>

            <tr>
                <td></td>
                <td>
                    <?php echo $row2->name; ?>
                </td>
                <td>
                    <?php echo number_format($row2->dw_accounts); ?>
                </td>
                <td>
                    <?php echo number_format($row2->dw_dns_zones); ?>
                </td>
                <td>
                    <?php echo number_format($row2->dw_dns_records); ?>
                </td>
            </tr><?php

        } ?>

            </tbody>
        </table><?php

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>

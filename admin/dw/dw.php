<?php
/**
 * /admin/dw/dw.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/dw-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$id = $_GET['id'];
$action = $_GET['action'];
$view_all = $_GET['view_all'];

if ($action != "") {

    if ($action == "dw_accounts") {

        if ($view_all == '1') {

            $_SESSION['s_dw_view_all'] = 1;

        } else {

            $query = "SELECT `name`, `host`
                      FROM dw_servers
                      WHERE id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('i', $id);
                $q->execute();
                $q->store_result();
                $q->bind_result($temp_name, $temp_host);

                while ($q->fetch()) {

                    $_SESSION['s_dw_view_all'] = "";
                    $_SESSION['s_dw_server_id'] = $id;
                    $_SESSION['s_dw_server_name'] = $temp_name;
                    $_SESSION['s_dw_server_host'] = $temp_host;

                }

                $q->close();

            } else $error->outputSqlError($conn, "ERROR");

        }

        header("Location: list-accounts.php");
        exit;

    } elseif ($action == "dw_dns_zones") {

        if ($view_all == '1') {

            $_SESSION['s_dw_view_all'] = 1;

        } else {

            $query = "SELECT `name`, `host`
                      FROM dw_servers
                      WHERE id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('i', $id);
                $q->execute();
                $q->store_result();
                $q->bind_result($temp_name, $temp_host);

                while ($q->fetch()) {

                    $_SESSION['s_dw_view_all'] = "";
                    $_SESSION['s_dw_server_id'] = $id;
                    $_SESSION['s_dw_server_name'] = $temp_name;
                    $_SESSION['s_dw_server_host'] = $temp_host;

                }

                $q->close();

            } else $error->outputSqlError($conn, "ERROR");

        }

        header("Location: list-zones.php");
        exit;

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
    <?php echo $layout->jumpMenu(); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>

<?php
$sql = "SELECT id
        FROM dw_servers
        LIMIT 1";
$result = mysqli_query($connection, $sql);

if ($result === false || mysqli_num_rows($result) <= 0) {

    // Query error or no results
    $no_results_servers = 1;

} else {

    if (mysqli_num_rows($result) == 0) {

        $has_servers = 0;

    } else {

        $has_servers = 1;

    }

}
?>
<a href="servers.php"><?php echo $layout->showButton('button', 'Manage Servers'); ?></a><?php
if ($has_servers == 1) { ?>&nbsp;&nbsp;&nbsp;<a href="rebuild.php"><?php echo $layout->showButton('button', 'Rebuild DW'); ?></a><?php } ?>
<?php
$sql_accounts = "SELECT id
                 FROM dw_accounts";
$result_accounts = mysqli_query($connection, $sql_accounts);

if ($result_accounts === false || mysqli_num_rows($result_accounts) <= 0) {

    // Query error or not results
    $no_results_accounts = 1;

} else {

    $temp_total_accounts = mysqli_num_rows($result_accounts);

}

$sql_dns_zones = "SELECT id
                  FROM dw_dns_records";
$result_dns_zones = mysqli_query($connection, $sql_dns_zones);

if ($result_dns_zones === false || mysqli_num_rows($result_dns_zones) <= 0) {

    // Query error or no results
    $no_results_dns_zones = 1;

} else {

    $temp_total_dns_zones = mysqli_num_rows($result_dns_zones);

}

$sql_build_finished = "SELECT build_status_overall
                       FROM dw_servers
                       LIMIT 1";
$result_build_finished = mysqli_query($connection, $sql_build_finished);

if ($result_build_finished === false || mysqli_num_rows($result_build_finished) <= 0) {

    // Query error or no results
    $no_results_build_finished = 1;

} else {

    while ($row_build_finished = mysqli_fetch_object($result_build_finished)) {

        $is_the_build_finished = $row_build_finished->build_status_overall;

    }

}

if ($is_the_build_finished == 1 && ($no_results_accounts !== 1 || $no_results_dns_zones !== 1)) { ?>

    <BR><BR><h3>View Data</h3>
    <?php

    echo $form->showFormTop('');

    if ($temp_total_accounts == 0) {

        echo "No Accounts exist<BR>";

    } else {

        echo $form->showDropdownTopJump('', '', '', '');

        $sql_dw_account = "SELECT id, `name`, dw_accounts
                           FROM dw_servers
                           ORDER BY name, `host`";
        $result_dw_account = mysqli_query($connection, $sql_dw_account);

        echo $form->showDropdownOptionJump($web_root . '/admin/dw/dw.php', '', 'Server Accounts', '');
        echo $form->showDropdownOptionJump('dw.php?action=dw_accounts&view_all=1', '', 'VIEW ALL', 'null');

        while ($row_dw_account = mysqli_fetch_object($result_dw_account)) {

            echo $form->showDropdownOptionJump('dw.php?action=dw_accounts&id=' . $row_dw_account->id, '', $row_dw_account->name . ' (' . number_format($row_dw_account->dw_accounts) . ' Accounts)', 'null');

        }

        echo $form->showDropdownBottom('');

    }

    if ($temp_total_dns_zones == 0) {

        echo "No DNS Zones exist<BR>";

    } else {

        echo $form->showDropdownTopJump('', '', '', '');

        $sql_dw_dns_records = "SELECT id, name, dw_dns_zones, dw_dns_records
                               FROM dw_servers
                               ORDER BY name, host";
        $result_dw_dns_records = mysqli_query($connection, $sql_dw_dns_records);

        echo $form->showDropdownOptionJump($web_root . '/admin/dw/dw.php', '', 'DNS Zones & Records', '');
        echo $form->showDropdownOptionJump('dw.php?action=dw_dns_zones&view_all=1', '', 'VIEW ALL', 'null');

        while ($row_dw_dns_records = mysqli_fetch_object($result_dw_dns_records)) {

            echo $form->showDropdownOptionJump('dw.php?action=dw_dns_zones&id=' . $row_dw_dns_records->id, '', $row_dw_dns_records->name . ' (' . number_format($row_dw_dns_records->dw_dns_zones) . ' Zones, ' . number_format($row_dw_dns_records->dw_dns_records) . ' Records)', 'null');

        }

        echo $form->showDropdownBottom('');

    }

}

$sql_build_info = "SELECT build_status_overall, build_start_time_overall, build_end_time_overall, build_time_overall,
                       has_ever_been_built_overall
                   FROM dw_servers
                   ORDER BY build_end_time_overall DESC
                   LIMIT 1";
$result_build_info = mysqli_query($connection, $sql_build_info);

if ($result_build_info === false || mysqli_num_rows($result_build_info) <= 0) {

    $no_results_build_info = 1;

} else {

    $temp_build_info = mysqli_num_rows($result_build_info);

}

if ($no_results_build_info !== 1) { ?>

    <BR><h3>Build Information</h3>
    <table id="<?php echo $slug; ?>-build" class="<?php echo $datatable_class; ?>"><?php

    if ($temp_build_info == 0) {

        echo "<BR>You don't currently have any servers setup in your Data Warehouse. <a href=\"add-server.php\">Click here to add one</a>.";

    } else {

        while ($row_build_info = mysqli_fetch_object($result_build_info)) {

            if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" &&
                $row_build_info->build_end_time_overall != "0000-00-00 00:00:00") {

                $temp_build_status_overall = "Successful";

            }

            if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" &&
                $row_build_info->has_ever_been_built_overall == 0) {

                $temp_build_status_overall = "Building...";

            }

            if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" &&
                $row_build_info->build_end_time_overall == "0000-00-00 00:00:00" &&
                $row_build_info->build_status_overall == 0) {

                $sql_check_builds = "SELECT id
                                     FROM dw_servers
                                     WHERE build_status = '0'";
                $result_check_builds = mysqli_query($connection, $sql_check_builds);

                if ($result_check_builds === false || mysqli_num_rows($result_check_builds) <= 0) {

                    $no_results_check_builds = 1;

                } else {

                    if (mysqli_num_rows($result_check_builds) == 0) {

                        $temp_build_status_overall
                            = "Cleanup...";

                    } else {

                        $temp_build_status_overall
                            = "Building...";

                    }

                }

                $is_building = 1;

            }

            if ($row_build_info->build_start_time_overall == "0000-00-00 00:00:00" &&
                $row_build_info->has_ever_been_built_overall == 0) {

                $temp_build_status_overall = "Never Built";

            }

            if ($row_build_info->build_start_time_overall == "0000-00-00 00:00:00") {

                $temp_build_start_time_overall = "-";

            } else {

                $temp_build_start_time_overall = $time->toUserTimezone($row_build_info->build_start_time_overall, 'M jS @ g:i:sa');

            }

            if ($row_build_info->build_end_time_overall == "0000-00-00 00:00:00") {

                $temp_build_end_time_overall = "-";

            } else {

                $temp_build_end_time_overall = $time->toUserTimezone($row_build_info->build_end_time_overall, 'M jS @ g:i:sa');

            }

            if ($row_build_info->build_time_overall <= 0) {

                $temp_build_time_overall = "-";

            } elseif ($row_build_info->build_time_overall > 0 && $row_build_info->build_time_overall <= 60) {

                $temp_build_time_overall = number_format($row_build_info->build_time_overall) . "s";

            } else {

                $number_of_minutes = intval($row_build_info->build_time_overall / 60);
                $number_of_seconds = $row_build_info->build_time_overall - ($number_of_minutes * 60);

                $temp_build_time_overall = $number_of_minutes . "m " . $number_of_seconds . "s";

            } ?>

            <thead>
            <tr>
                <th width="20px"></th>
                <th>Server</th>
                <th>Build Start</th>
                <th>Build End</th>
                <th>Build Time</th>
                <th>Build Status</th>
            </tr>
            </thead>

            <tbody>
            <tr>
                <td></td>
                <td><em>Full Build</em></td>
                <td><?php echo $temp_build_start_time_overall; ?></td>
                <td><?php echo $temp_build_end_time_overall; ?></td>
                <td><?php echo $temp_build_time_overall; ?></td>
                <td><?php echo $temp_build_status_overall; ?></td>
            </tr><?php

        }

    } ?>

    <?php
    $sql = "SELECT `name`, `host`, build_status, build_start_time, build_end_time, build_time, has_ever_been_built
            FROM dw_servers
            ORDER BY name, host";
    $result = mysqli_query($connection, $sql);

    if (mysqli_num_rows($result) == 0) {

        echo "";

    } else {

        while ($row = mysqli_fetch_object($result)) {

            //@formatter:off

            if ($row->build_start_time != "0000-00-00 00:00:00" && $row->build_end_time != "0000-00-00 00:00:00") {

                $temp_build_status = "Successful";

            }

            if ($row->build_start_time != "0000-00-00 00:00:00" && $row->build_end_time == "0000-00-00 00:00:00" &&
                $row->build_status == 0) {

                $temp_build_status = "Building...";

            }

            if ($row->build_start_time == "0000-00-00 00:00:00" && $row->has_ever_been_built == 0) {

                if ($is_building == 1) {

                    $temp_build_status = "Pending";

                } else {

                    $temp_build_status = "Never Built";

                }

            }

            if ($row->build_start_time == "0000-00-00 00:00:00" && $row->has_ever_been_built == 1) {

                $temp_build_status = "Pending";

            }

            if ($row->build_start_time != "0000-00-00 00:00:00" && $row->has_ever_been_built == 0) {

                $temp_build_status = "Building...";

            }

            if ($row->build_start_time == "0000-00-00 00:00:00") {

                $temp_build_start_time = "-";

            } else {

                $temp_build_start_time = $time->toUserTimezone($row->build_start_time, 'M jS @ g:i:sa');

            }

            if ($row->build_end_time == "0000-00-00 00:00:00") {

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

$sql_data_check = "SELECT dw_accounts, dw_dns_zones, dw_dns_records
                   FROM dw_server_totals";
$result_data_check = mysqli_query($connection, $sql_data_check);

if ($result_data_check === false || mysqli_num_rows($result_data_check) <= 0) {

    // Query error or no results

} else {

    while ($row_data_check = mysqli_fetch_object($result_data_check)) {

        $temp_dw_accounts = $row_data_check->dw_accounts;
        $temp_dw_dns_zones = $row_data_check->dw_dns_zones;
        $temp_dw_dns_records = $row_data_check->dw_dns_records;

    }

}

if (mysqli_num_rows($result) == 0) {

    // Placeholder

} else {

    if (mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . `dw_server_totals` . "'")) >= 1) {

        $table_exists = 1;

    } else {

        $table_exists = 0;

    }

    if ($is_building != 1 && $table_exists != 0 && $temp_dw_accounts != 0 && $temp_dw_dns_zones != 0 &&
        $temp_dw_dns_records != 0
    ) { ?>

        <h3>Data Warehouse Totals</h3>

        <table id="<?php echo $slug; ?>-totals" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th>Server</th>
                <th>Accounts</th>
                <th>DNS Zones</th>
                <th>DNS Records</th>
            </tr>
            </thead>
            <tbody><?php

        $sql = "SELECT dw_servers, dw_accounts, dw_dns_zones, dw_dns_records
                    FROM dw_server_totals";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            if ($row->dw_servers > 1) { ?>

                <tr>
                <td></td>
                <td>
                    <em>All Servers</em>
                </td>
                <td>
                    <?php echo number_format($row->dw_accounts); ?>
                </td>
                <td>
                    <?php echo number_format($row->dw_dns_zones); ?>
                </td>
                <td>
                    <?php echo number_format($row->dw_dns_records); ?>
                </td>
                </tr><?php

            }

        }

        $sql = "SELECT `name`, dw_accounts, dw_dns_zones, dw_dns_records
                FROM dw_servers
                WHERE has_ever_been_built = '1'
                ORDER BY name";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr>
            <td></td>
            <td>
                <?php echo $row->name; ?>
            </td>
            <td>
                <?php echo number_format($row->dw_accounts); ?>
            </td>
            <td>
                <?php echo number_format($row->dw_dns_zones); ?>
            </td>
            <td>
                <?php echo number_format($row->dw_dns_records); ?>
            </td>
            </tr><?php

        } ?>

            </tbody>
        </table><?php

    }

} ?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>

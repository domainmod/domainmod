<?php
/**
 * /_includes/layout/head-tags.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
<style type="text/css">
    body {
        background-color: #FFFFFF;
        font-family: Verdana, Arial, Helvetica, sans-serif;
        color: #000000;
        font-size: 8pt;
    }

    td {
        background-color: #FFFFFF;
        font-family: Verdana, Arial, Helvetica, sans-serif;
        color: #000000;
        font-size: 8pt;
    }

    table.main_table tr.main_table_row_active:hover td, tr.main_table_row_inactive:hover td {
        padding-top: 2px;
        padding-right: 26px;
        padding-bottom: 2px;
        padding-left: 0px;
        color: #000000;
        background-color: #D1D1D1;
    }

    table.main_table_no_right_padding tr.main_table_row_active_no_right_padding:hover td, tr.main_table_row_inactive_no_right_padding:hover td {
        padding-top: 2px;
        padding-right: 0px;
        padding-bottom: 2px;
        padding-left: 0px;
        color: #000000;
        background-color: #D1D1D1;
    }

    tr.main_table_row_heading_active {
        /*    line-height: 18px; */
    }

    td.main_table_cell_heading_active {
        vertical-align: bottom;
        padding-top: 12px;
        padding-right: 26px;
        padding-bottom: 3px;
        padding-left: 0px;
    }

    tr.main_table_row_active {
        /*    line-height: 18px; */
    }

    tr.main_table_row_active_no_right_padding {
        /*    line-height: 18px; */
    }

    tr.main_table_row_active_nohover {
        /*    line-height: 18px; */
    }

    td.main_table_cell_active {
        vertical-align: middle;
        padding-top: 2px;
        padding-right: 26px;
        padding-bottom: 2px;
        padding-left: 0px;
    }

    td.main_table_cell_active_top_aligned {
        vertical-align: top;
        padding-top: 2px;
        padding-right: 26px;
        padding-bottom: 2px;
        padding-left: 0px;
    }

    td.main_table_cell_active_top_aligned_no_right_padding {
        vertical-align: top;
        padding-top: 2px;
        padding-right: 0px;
        padding-bottom: 2px;
        padding-left: 0px;
    }

    tr.main_table_row_heading_inactive {
        /*    line-height: 18px; */
    }

    td.main_table_cell_heading_inactive {
        vertical-align: bottom;
        padding-top: 12px;
        padding-right: 26px;
        padding-bottom: 3px;
        padding-left: 0px;
    }

    tr.main_table_row_inactive {
        /*    line-height: 18px; */
    }

    tr.main_table_row_inactive_no_right_padding {
        /*    line-height: 18px; */
    }

    td.main_table_cell_inactive {
        padding-top: 2px;
        padding-right: 26px;
        padding-bottom: 2px;
        padding-left: 0px;
    }

    td.main_table_cell_heading_active_centered {
        vertical-align: bottom;
        text-align: center;
        padding-top: 0px;
        padding-right: 26px;
        padding-bottom: 3px;
        padding-left: 0px;
    }

    td.main_table_cell_active_centered {
        text-align: center;
        padding-top: 2px;
        padding-right: 26px;
        padding-bottom: 2px;
        padding-left: 0px;
    }

    table.dns_table {
        border-spacing: 0px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    td.dns_table_left {
        text-align: left;
        vertical-align: center;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        font-size: 8pt;
        padding-top: 0px;
        padding-right: 15px;
        padding-bottom: 14px;
        padding-left: 0px;
    }

    td.dns_table_right {
        text-align: left;
        vertical-align: center;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        font-size: 8pt;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 14px;
        padding-left: 0px;
    }

    div.update_box_header {
        text-align: right;
        padding-top: 14px;
        padding-right: 22px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    table.update-block-outer {
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    td.update-block-left {
        width: 90px;
        vertical-align: top;
        text-align: left;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    td.update-block-right {
        vertical-align: top;
        text-align: left;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.main-container {
        width: <?php echo $site_width; ?>;
        display: table;
        margin: 0 auto;
    }

    div.main-container-login {
        width: <?php echo $site_width_login; ?>;
        display: table;
        margin: 0 auto;
    }

    div.main-outer {
        width: 100%;
        display: table;
        margin: 0 auto;
        text-align: left;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        border-top: 1px #6699CC ridge;
        border-bottom: 1px #6699CC ridge;
        border-right: 1px #6699CC ridge;
        border-left: 1px #6699CC ridge;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.main-outer-login {
        width: 100%;
        display: table;
        margin: 0 auto;
        text-align: left;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        border-top: 1px #6699CC ridge;
        border-bottom: 1px #6699CC ridge;
        border-right: 1px #6699CC ridge;
        border-left: 1px #6699CC ridge;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.main-inner {
        text-align: left;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 12px;
        padding-right: 23px;
        padding-bottom: 23px;
        padding-left: 23px;
    }

    div.header-container {
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.header-left {
        width: 42%;
        float: left;
        text-align: left;
        vertical-align: bottom;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 7px;
        padding-right: 0px;
        padding-bottom: 5px;
        padding-left: 25px;
    }

    div.header-right {
        width: 52%;
        float: right;
        text-align: right;
        vertical-align: bottom;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 33px;
        padding-right: 8px;
        padding-bottom: 8px;
        padding-left: 0px;
    }

    div.header-center {
        text-align: center;
        vertical-align: bottom;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 33px;
        padding-right: 0px;
        padding-bottom: 8px;
        padding-left: 0px;
    }

    div.main-menu {
        float: left;
        text-align: left;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 15px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 24px;
    }

    div.maintenance_warning_outer {
        width: 100%;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 13px;
        padding-left: 0px;
    }

    div.maintenance_warning_inner {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-size: 8pt;
        font-weight: normal;
        text-align: left;
        vertical-align: center;
        border-top: 3px #CC0000 ridge;
        border-bottom: 3px #CC0000 ridge;
        border-right: 3px #CC0000 ridge;
        border-left: 3px #CC0000 ridge;
        color: #000000;
        background-color: #fafafa;
        padding-top: 23px;
        padding-right: 8px;
        padding-bottom: 23px;
        padding-left: 20px;
    }

    div.result_message_outer {
        width: 100%;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 13px;
        padding-left: 0px;
    }

    div.result_message_inner {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        vertical-align: center;
        border-top: 1px #6699CC ridge;
        border-bottom: 1px #6699CC ridge;
        border-right: 1px #6699CC ridge;
        border-left: 1px #6699CC ridge;
        color: #000000;
        background-color: #E0E0E0;
        padding-top: 21px;
        padding-right: 8px;
        padding-bottom: 21px;
        padding-left: 8px;
    }

    div.asset-management-block-outer {
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 65px;
        padding-left: 0px;
    }

    div.asset-management-block-left {
        float: left;
        width: 235px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.asset-management-block-center {
        float: left;
        width: 235px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.asset-management-block-right {
        float: left;
        width: 235px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.reporting-block-outer {
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 65px;
        padding-left: 0px;
    }

    div.reporting-block-left {
        float: left;
        width: 275px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.reporting-block-center {
        float: left;
        width: 275px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.reporting-block-right {
        float: left;
        width: 275px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.search-block-outer {
        float: left;
        border-top: 1px #6699CC ridge;
        border-bottom: 1px #6699CC ridge;
        border-right: 1px #6699CC ridge;
        border-left: 1px #6699CC ridge;
        padding-top: 20px;
        padding-right: 20px;
        padding-bottom: 20px;
        padding-left: 20px;
        background-color: #E0E0E0;
    }

    div.search-block-inner {
        text-align: left;
        vertical-align: center;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        font-size: 8pt;
        color: #404040;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.search-block-left {
        float: left;
        width: 575px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.search-block-right {
        float: left;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.export-outer {
        float: left;
        line-height: 30px;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 12px;
        padding-left: 0px;
    }

    div.export-inner {
        text-align: left;
        vertical-align: center;
        border-top: 1px #6699CC ridge;
        border-bottom: 1px #6699CC ridge;
        border-right: 1px #6699CC ridge;
        border-left: 1px #6699CC ridge;
        font-family: Verdana, sans-serif, Arial;
        font-weight: normal;
        font-size: 8pt;
        background-color: #E0E0E0;
        padding-top: 18px;
        padding-right: 22px;
        padding-bottom: 18px;
        padding-left: 22px;
    }

    div.login_form {
        text-align: center;
    }

    div.pagination_menu_block {
        text-align: left;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.pagination_menu_block_inner {
        text-align: left;
        padding-top: 0px;
        padding-right: 0px;
        padding-bottom: 0px;
        padding-left: 0px;
    }

    div.reset-password {
        text-align: center;
        border-top: 0px;
        border-bottom: 0px;
        border-right: 0px;
        border-left: 0px;
        padding-top: 10px;
        padding-right: 0px;
        padding-bottom: 10px;
        padding-left: 0px;
    }

    div.footer-container {
        text-align: right;
        vertical-align: right;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 10px;
        padding-right: 8px;
        padding-bottom: 10px;
        padding-left: 0px;
    }

    div.footer-container-center {
        text-align: center;
        vertical-align: center;
        font-family: Verdana, sans-serif, Arial;
        font-size: 8pt;
        font-weight: normal;
        color: #000000;
        border-top: 0px #6699CC ridge;
        border-bottom: 0px #6699CC ridge;
        border-right: 0px #6699CC ridge;
        border-left: 0px #6699CC ridge;
        padding-top: 10px;
        padding-right: 0px;
        padding-bottom: 10px;
        padding-left: 0px;
    }

    font.headline {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 10pt;
        color: #AA0000;
    }

    font.subheadline {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 9pt;
        /*    color: #BB0000; */
        color: #AA0000;
    }

    font.main_table_heading_extra {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 8pt;
        color: #000000;
    }

    font.main_table_heading {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 8pt;
        color: #000000;
    }

    font.highlight {
        color: #CC0000;
    }

    font.default_highlight {
        font-weight: bold;
        color: #CC0000;
    }

    font.default_highlight_secondary {
        font-weight: bold;
        color: #0040FF;
    }

    font.reseller_highlight {
        font-weight: bold;
        color: #0040FF;
    }

    a:link {
        font-weight: bold;
        color: #0066FF;
        text-decoration: none;
    }

    a:visited {
        font-weight: bold;
        color: #0066FF;
        text-decoration: none;
    }

    a:hover {
        font-weight: bold;
        color: #CC0000;
        text-decoration: none;
    }

    a:active {
        font-weight: bold;
        color: #0066FF;
        text-decoration: none;
    }

    a.nobold:link {
        font-weight: 100;
        color: #0066FF;
        text-decoration: none;
    }

    a.nobold:visited {
        font-weight: 100;
        color: #0066FF;
        text-decoration: none;
    }

    a.nobold:hover {
        font-weight: 100;
        color: #CC0000;
        text-decoration: none;
    }

    a.nobold:active {
        font-weight: 100;
        color: #0066FF;
        text-decoration: none;
    }

    a.invisiblelink:link {
        font-weight: 100;
        color: #000000;
        text-decoration: none;
    }

    a.invisiblelink:visited {
        font-weight: 100;
        color: #000000;
        text-decoration: none;
    }

    a.invisiblelink:hover {
        font-weight: 100;
        color: #000000;
        text-decoration: underline;
    }

    a.invisiblelink:active {
        font-weight: 100;
        color: #000000;
        text-decoration: none;
    }

    a.subtlelink:link {
        font-weight: 100;
        color: #333;
        text-decoration: none;
    }

    a.subtlelink:visited {
        font-weight: 100;
        color: #333;
        text-decoration: none;
    }

    a.subtlelink:hover {
        font-weight: 100;
        color: #333;
        text-decoration: underline;
    }

    a.subtlelink:active {
        font-weight: 100;
        color: #333;
        text-decoration: none;
    }
</style>
<link rel="icon" type="image/ico" href="<?php echo $web_root; ?>/images/favicon.ico"/>
<style type="text/css">
    html {
        overflow-y: scroll;
    }
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

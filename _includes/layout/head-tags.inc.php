<?php
// /_includes/layout/head-tags.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
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
td.footer-table-login {
	text-align: center;
	vertical-align: center; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 0px #6699CC ridge;
	border-bottom: 0px #6699CC ridge;
	border-right: 0px #6699CC ridge;
	border-left: 0px #6699CC ridge;
	padding-top: 0px;
	padding-right: 10px; 
	padding-bottom: 15px;
	padding-left: 10px;
}
table.main_table {
	border-spacing: 0px;
	padding-top: 0px;
	padding-right: 0px; 
	padding-bottom: 0px;
	padding-left: 0px;
}
tr.main_table_row_heading_active {
	line-height: 30px; 
}
td.main_table_cell_heading_active {
	padding-top: 0px;
	padding-right: 29px;
	padding-bottom: 0px;
	padding-left: 0px;
}
tr.main_table_row_active {
	line-height: 18px; 
}
td.main_table_cell_active {
	padding-top: 0px; 
	padding-right: 29px; 
	padding-bottom: 0px; 
	padding-left: 0px; 
}
tr.main_table_row_heading_inactive {
	line-height: 30px; 
}
td.main_table_cell_heading_inactive {
	padding-top: 15px;
	padding-right: 29px;
	padding-bottom: 0px;
	padding-left: 0px;
}
tr.main_table_row_inactive {
	line-height: 18px; 
}
td.main_table_cell_inactive {
	padding-top: 0px; 
	padding-right: 29px; 
	padding-bottom: 0px; 
	padding-left: 0px; 
}
td.main_table_cell_heading_active_centered {
	padding-top: 0px;
	padding-right: 29px;
	padding-bottom: 0px;
	padding-left: 0px;
	text-align: center;
}
td.main_table_cell_active_centered {
	padding-top: 0px; 
	padding-right: 29px; 
	padding-bottom: 0px; 
	padding-left: 0px; 
	text-align: center;
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
div.main-container {
	width: <?=$site_width?>;
	display: table;
	margin: 0 auto;
}
div.main-container-login {
	width: <?=$site_width_login?>;
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
	width: 50%;
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
	padding-top: 3px;
	padding-right: 0px; 
	padding-bottom: 5px;
	padding-left: 20px;
}
div.header-right {
	width: 40%;
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
	padding-right: 15px; 
	padding-bottom: 0px;
	padding-left: 15px;
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
	width: 490px; 
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
div.search_options_block {
	text-align: left;
	padding-top: 0px;
	padding-right: 0px; 
	padding-bottom: 0px;
	padding-left: 0px;
}
div.search_options_block_inner {
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
	color: #CC0000;
}
font.subheadline {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 9pt;
	color: #CC0000;
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
	font-weight:100;
	color: #0066FF;
	text-decoration: none;
}
a.nobold:visited {
	font-weight:100;
	color: #0066FF;
	text-decoration: none;
}
a.nobold:hover {
	font-weight:100;
	color: #CC0000;
	text-decoration: none;
}
a.nobold:active {
	font-weight:100;
	color: #0066FF;
	text-decoration: none;
}
a.invisiblelink:link {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
a.invisiblelink:visited {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
a.invisiblelink:hover {
	font-weight:100;
	color: #000000;
	text-decoration: underline;
}
a.invisiblelink:active {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
a.subtlelink:link {
	font-weight:100;
	color: #333;
	text-decoration: none;
}
a.subtlelink:visited {
	font-weight:100;
	color: #333;
	text-decoration: none;
}
a.subtlelink:hover {
	font-weight:100;
	color: #333;
	text-decoration: underline;
}
a.subtlelink:active {
	font-weight:100;
	color: #333;
	text-decoration: none;
}
</style>
<link rel="icon" type="image/ico" href="<?=$web_root?>/images/favicon.ico"/>
<style type="text/css">
html { overflow-y: scroll; }
</style>
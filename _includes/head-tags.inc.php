<?php
// /_includes/head-tags.inc.php
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
	font-size: 10pt;
}
td {
	background-color: #FFFFFF;
	font-family: Verdana, Arial, Helvetica, sans-serif;
	color: #000000;
	font-size: 10pt;
	font-size: .7em;
}
td.header-table-left {
	text-align: left;
	vertical-align: bottom; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 0px #6699CC ridge;
	border-bottom: 0px #6699CC ridge;
	border-right: 0px #6699CC ridge;
	border-left: 0px #6699CC ridge;
	padding-top: 3px;
	padding-bottom: 5px;
	padding-left: 20px;
	padding-right: 0px; 
}
td.header-table-right {
	text-align: right;
	vertical-align: bottom; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 0px #6699CC ridge;
	border-bottom: 0px #6699CC ridge;
	border-right: 0px #6699CC ridge;
	border-left: 0px #6699CC ridge;
	padding-top: 0px;
	padding-bottom: 10px;
	padding-left: 0px;
	padding-right: 8px; 
}
td.header-table-center {
	text-align: center;
	vertical-align: bottom; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 0px #6699CC ridge;
	border-bottom: 0px #6699CC ridge;
	border-right: 0px #6699CC ridge;
	border-left: 0px #6699CC ridge;
	padding-top: 0px;
	padding-bottom: 10px;
	padding-left: 0px;
	padding-right: 8px; 
}
td.main-table {
	text-align: left;
	vertical-align: center; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 1px #6699CC ridge;
	border-bottom: 1px #6699CC ridge;
	border-right: 1px #6699CC ridge;
	border-left: 1px #6699CC ridge;
	padding-top: 15px;
	padding-bottom: 15px;
	padding-left: 15px;
	padding-right: 15px; 
}
td.search-table {
	text-align: left;
	vertical-align: center; 
	border-top: 1px #6699CC ridge;
	border-bottom: 1px #6699CC ridge;
	border-right: 1px #6699CC ridge;
	border-left: 1px #6699CC ridge;
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	font-size: .7em;
	color: #404040;
	background-color: #fafafa;
	padding-top: 8px;
	padding-bottom: 8px;
	padding-left: 8px;
	padding-right: 8px; 
}
td.search-table-inside {
	vertical-align: top; 
	background-color: #fafafa;
}
td.cell-maintenance-table {
	text-align: left;
	vertical-align: center; 
	border-top: 1px #CC0000 ridge;
	border-bottom: 1px #CC0000 ridge;
	border-right: 1px #CC0000 ridge;
	border-left: 1px #CC0000 ridge;
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	font-size: .7em;
	color: #404040;
	background-color: #fafafa;
	padding-top: 10px;
	padding-bottom: 10px;
	padding-left: 20px;
	padding-right: 20px; 
}
td.footer-table {
	text-align: right;
	vertical-align: center; 
	font-family: Verdana, sans-serif, Arial;
	font-weight: normal;
	color: #404040;
	border-top: 0px #6699CC ridge;
	border-bottom: 0px #6699CC ridge;
	border-right: 0px #6699CC ridge;
	border-left: 0px #6699CC ridge;
	padding-top: 0px;
	padding-bottom: 15px;
	padding-left: 10px;
	padding-right: 10px; 
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
	padding-bottom: 15px;
	padding-left: 10px;
	padding-right: 10px; 
}
table.main_table {
	border-spacing: 0px;
	padding-top: 0px;
	padding-bottom: 0px;
	padding-left: 0px;
	padding-right: 0px; 
}
tr.main_table_row_heading_active {
	line-height: 30px; 
}
td.main_table_cell_heading_active {
	padding-right: 40px;
}
tr.main_table_row_active {
	line-height: 18px; 
}
td.main_table_cell_active {
	padding-right: 40px; 
}

tr.main_table_row_heading_inactive {
	line-height: 30px; 
}
td.main_table_cell_heading_inactive {
	padding-top: 15px;
	padding-right: 40px;
}
tr.main_table_row_inactive {
	line-height: 18px; 
}
td.main_table_cell_inactive {
	padding-right: 40px; 
}

td.main_table_cell_heading_active_centered {
	padding-right: 40px;
	text-align: center;
}
td.main_table_cell_active_centered {
	padding-right: 40px; 
	text-align: center;
}

div.result_message_outer {
	width: 100%;
	border-top: 0px;
	border-bottom: 0px;
	border-right: 0px;
	border-left: 0px;
	padding-top: 0px;
	padding-bottom: 13px;
	padding-left: 0px;
	padding-right: 0px; 
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
	color: #404040;
	background-color: #fafafa;
	padding-top: 21px;
	padding-bottom: 21px;
	padding-left: 8px;
	padding-right: 8px; 
}

div.login_form {
	text-align: center;
}

div.reset_password {
	text-align: center;
}

font.headline {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 15;
	color: #CC0000;
}
font.subheadline {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 13;
	color: #CC0000;
}
font.main_table_heading {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 12;
	color: #000000;
}
font.highlight {
	color: #CC0000;
}
font.default_highlight {
	color: #CC0000;
}
font.reseller_highlight {
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

a.subtlelink:link {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
a.subtlelink:visited {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
a.subtlelink:hover {
	font-weight:100;
	color: #000000;
	text-decoration: underline;
}
a.subtlelink:active {
	font-weight:100;
	color: #000000;
	text-decoration: none;
}
</style>
<link rel="icon" type="image/ico" href="<?=$web_root?>/images/favicon.ico"/>
<style type="text/css">
html { overflow-y: scroll; }
</style>
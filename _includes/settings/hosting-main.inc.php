<?php
/**
 * /_includes/settings/hosting-main.inc.php
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
<?php
$page_title = _('Hosting Services');
$breadcrumb = $page_title;
$software_section = "hosting";
$slug = "hosting-main";

// No dt-responsive: the list keeps every column and scrolls sideways in its own box (see js/server-list.js)
$datatable_class = 'table table-striped cell-border compact';

// The list uses server-side processing: hosting/index.php prints the first page, with the table's settings in its
// data-domainmod-list attribute, and js/server-list.js starts DataTables from them.
$datatable_options = 'domainmodServerList(\'#' . $slug . '\');';

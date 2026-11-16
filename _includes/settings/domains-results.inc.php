<?php
/**
 * /_includes/settings/domains-results.inc.php
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
// $type is already checked by domains/results.php (inactive, filtered, or missing)
if ($type == 'inactive') {
    $page_title = _('Inactive Domains');
} elseif ($type == 'filtered') {
    $page_title = _('Filtered Domains');
} else {
    $page_title = _('Missing Domains');
}
$breadcrumb = $page_title;
$software_section = "domains";
$slug = "domains-results";
$datatable_class = 'table table-striped dt-responsive cell-border compact';
$datatable_options = 'domainmodTable(\'#' . $slug . '\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": true,
                          "info": true,
                          "autoWidth": true,
                          "bAutoWidth": false,
                          "responsive": {
                               details: {
                                         type: "column"
                                        }
                                        },
                          "columnDefs": [ {
                                           className: "control",
                                           orderable: false,
                                           targets:   0
                                           } ],
                          "ordering": true,
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\'
                      });';

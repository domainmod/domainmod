<?php
/**
 * /_includes/settings/reporting-hosting-costs.inc.php
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
// $cost_grouping is the report's grouping (DomainMOD\CostReport::grouping()), set by the page before this file
$page_title = $cost_grouping['title'];
$breadcrumb = _('Hosting Costs');
$report_name = $cost_grouping['name'];
$software_section = "reporting";
$slug = "reporting-hosting-costs";
$report_section = 'hosting';
$report_filename = 'costs.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
if (isset($cost_grouping['page_length'])) {
    // The list opens at the user's number of domains per page, and the page-length menu lists that number once,
    // in order with the others
    $page_length = (int) ($_SESSION[$cost_grouping['page_length']] ?? 0);
    if ($page_length < 1) $page_length = 50;
    $length_menu = array_unique(array($page_length, 50, 75, 100));
    sort($length_menu);
    $datatable_paging = '"paging": true,
                          "lengthChange": true,
                          "pageLength": ' . $page_length . ',
                          "lengthMenu": [ ' . implode(', ', $length_menu) . ', -1 ],
                          "searching": true,
                          "info": true,';
} else {
    $datatable_paging = '"paging": false,
                          "lengthChange": true,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": false,
                          "info": false,';
}
$datatable_options = 'domainmodTable(\'#' . $slug . '\', {
                          ' . $datatable_paging . '
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
                          "ordering": ' . ($cost_grouping['ordering'] ? 'true' : 'false') . ',
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\'
                      });';

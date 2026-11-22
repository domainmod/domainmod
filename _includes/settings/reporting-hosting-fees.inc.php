<?php
/**
 * /_includes/settings/reporting-hosting-fees.inc.php
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
$page_title = _('Hosting Fees');
$breadcrumb = _('Hosting Fees');
$software_section = "reporting";
$slug = "reporting-hosting-fees";
$report_section = 'hosting';
$report_filename = 'provider-fees.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
$datatable_options = 'domainmodTable(\'#' . $slug . '\', {
                          "paging": false,
                          "lengthChange": false,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": false,
                          "info": false,
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
                                           }, {
                                           responsivePriority: 1,
                                           targets:   1
                                           }, {
                                           responsivePriority: 2,
                                           targets:   2
                                           } ],
                          "ordering": false,
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\'
                      });';

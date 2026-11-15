<?php
/**
 * /_includes/settings/reporting-domain-upcoming-renewals.inc.php
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
$page_title = _('Upcoming Renewals Report');
$breadcrumb = _('Upcoming Renewals');
$software_section = "reporting";
$slug = "reporting-domain-upcoming-renewals";
$report_section = 'domains';
$report_filename = 'upcoming-renewals.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
$datatable_options = 'domainmodTable(\'#' . $slug . '\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ [' . ($_SESSION['s_number_of_domains'] ?? '') . ', 50, 75, 100, -1], [' . ($_SESSION['s_number_of_domains'] ?? '') . ', 50, 75, 100, "All"] ],
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
                          "order": [[ 2, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\'
                      });';

<?php
/**
 * /_includes/settings/queue-main.inc.php
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
$page_title = _('Domain Queue');
$breadcrumb = $page_title;
$software_section = "queue";
$slug = "queue-main";
$datatable_class = 'table table-striped dt-responsive cell-border compact';
$datatable_options = 'domainmodTable(\'#' . $slug . '-lists\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ [5, 10, 25, 50, 75, 100, -1], [5, 10, 25, 50, 75, 100, "All"] ],
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
                          "order": [[ 5, "desc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Domain List Queue')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });
                      
                      domainmodTable(\'#' . $slug . '-domains\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ [25, 50, 75, 100, -1], [25, 50, 75, 100, "All"] ],
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
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Domain Queue')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });';

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
$page_title = _('Import Queue');
$breadcrumb = $page_title;
$software_section = "queue";
$slug = "queue-main";
// The three tables have the dense list style (see css/base.css): every column in a box that scrolls sideways, so
// they don't use Responsive, and DataTables puts just the table in a box of its own (js/datatables.js)
$datatable_class = 'table domainmod-dense-table';
$datatable_options = 'domainmodTable(\'#' . $slug . '-lists\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": true,
                          "info": true,
                          "autoWidth": true,
                          "bAutoWidth": false,
                          "columnDefs": [ {
                                           type: "string",
                                           targets:   ".domainmod-col-date"
                                           }, {
                                           className: "dt-center",
                                           targets:   ".domainmod-col-delete"
                                           } ],
                          "ordering": true,
                          "order": [[ 4, "desc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>r<"domainmod-list-scroll"t><"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Domain List Queue')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });
                      
                      domainmodTable(\'#' . $slug . '-domains\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": true,
                          "info": true,
                          "autoWidth": true,
                          "bAutoWidth": false,
                          "columnDefs": [ {
                                           type: "string",
                                           targets:   ".domainmod-col-date"
                                           }, {
                                           className: "dt-center",
                                           targets:   ".domainmod-col-delete"
                                           } ],
                          "ordering": true,
                          // The rows start in the order the page lists them: domains already in DomainMOD or the
                          // queue last, and newest first
                          "order": [],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>r<"domainmod-list-scroll"t><"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Domain Queue')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });

                      domainmodTable(\'#' . $slug . '-hosts\', {
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ 25, 50, 75, 100, -1 ],
                          "searching": true,
                          "info": true,
                          "autoWidth": true,
                          "bAutoWidth": false,
                          "columnDefs": [ {
                                           type: "string",
                                           targets:   ".domainmod-col-date"
                                           }, {
                                           className: "dt-center",
                                           targets:   ".domainmod-col-delete"
                                           } ],
                          "ordering": true,
                          "order": [[ 6, "desc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>r<"domainmod-list-scroll"t><"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('SSL Queue')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });';

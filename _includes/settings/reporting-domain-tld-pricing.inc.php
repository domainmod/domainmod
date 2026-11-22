<?php
/**
 * /_includes/settings/reporting-domain-tld-pricing.inc.php
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
$page_title = _('TLD Price Comparison Report');
$breadcrumb = $page_title;
$software_section = "reporting";
$slug = "reporting-domain-tld-pricing";
$report_section = 'domains';
$report_filename = 'tld-pricing.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
// The list opens at the user's number of domains per page, and the page-length menu lists that number once,
// in order with the others
$page_length = (int) ($_SESSION['s_number_of_domains'] ?? 0);
if ($page_length < 1) $page_length = 50;
$length_menu = array_unique(array($page_length, 50, 75, 100));
sort($length_menu);
// The overview and the savings, paged and sortable like the Domains list, each opening in the page's own order (most
// domains first, and largest saving first). The selected TLD's registrars are a short, plain table that the
// comparison swaps in place.
$datatable_options = 'domainmodTable(\'#' . $slug . '-overview\', {
                          "paging": true,
                          "lengthChange": true,
                          "pageLength": ' . $page_length . ',
                          "lengthMenu": [ ' . implode(', ', $length_menu) . ', -1 ],
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
                          "order": [[ 2, "desc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('TLDs')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });

                      domainmodTable(\'#' . $slug . '-savings\', {
                          "paging": true,
                          "lengthChange": true,
                          "pageLength": ' . $page_length . ',
                          "lengthMenu": [ ' . implode(', ', $length_menu) . ', -1 ],
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
                          "order": [[ 7, "desc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Possible Savings')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });';

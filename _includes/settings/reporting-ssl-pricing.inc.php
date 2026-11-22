<?php
/**
 * /_includes/settings/reporting-ssl-pricing.inc.php
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
$page_title = _('SSL Price Comparison Report');
$breadcrumb = $page_title;
$software_section = "reporting";
$slug = "reporting-ssl-pricing";
$report_section = 'ssl';
$report_filename = 'ssl-pricing.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
// The list opens at the user's number of SSL certificates per page, and the page-length menu lists that number once,
// in order with the others
$page_length = (int) ($_SESSION['s_number_of_ssl_certs'] ?? 0);
if ($page_length < 1) $page_length = 50;
$length_menu = array_unique(array($page_length, 50, 75, 100));
sort($length_menu);
// The overview, paged and sortable like TLD Price Comparison's, opening with the most certificates first. The selected
// type's providers are a short, plain table that the comparison swaps in place.
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
                            // The paged table names its paging, as the TLD Price Comparison tables do
                            var paging = new DataTable.Api(settings).table().container().querySelector(".dt-paging nav");
                            if (paging) paging.setAttribute("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Certificate Types')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                          }
                      });';

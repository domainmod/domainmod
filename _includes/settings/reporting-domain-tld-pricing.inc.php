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
$page_title = _('TLD Pricing Report');
$breadcrumb = _('TLD Pricing');
$software_section = "reporting";
$slug = "reporting-domain-tld-pricing";
$report_section = 'domains';
$report_filename = 'tld-pricing.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
// The overview and the savings, paged and sortable like the Domains list, each opening in the page's own order (most
// domains first, and largest saving first). The selected TLD's registrars are a short, plain table that the
// comparison swaps in place.
$datatable_options = 'var oldStart_overview = 0;
                      $(\'#' . $slug . '-overview\').DataTable({
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ [' . $_SESSION['s_number_of_domains'] . ', 50, 75, 100, -1], [' . $_SESSION['s_number_of_domains'] . ', 50, 75, 100, "All"] ],
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
                            var start = new DataTable.Api(settings).page.info().start;
                            if ( start != oldStart_overview ) {
                                var targetOffset = $("#' . $slug . '-overview").offset().top;
                                $("html,body").animate({scrollTop: targetOffset}, 0);
                                oldStart_overview = start;
                            }
                          }
                      });

                      var oldStart_savings = 0;
                      $(\'#' . $slug . '-savings\').DataTable({
                          "paging": true,
                          "lengthChange": true,
                          "lengthMenu": [ [' . $_SESSION['s_number_of_domains'] . ', 50, 75, 100, -1], [' . $_SESSION['s_number_of_domains'] . ', 50, 75, 100, "All"] ],
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
                            var start = new DataTable.Api(settings).page.info().start;
                            if ( start != oldStart_savings ) {
                                var targetOffset = $("#' . $slug . '-savings").offset().top;
                                $("html,body").animate({scrollTop: targetOffset}, 0);
                                oldStart_savings = start;
                            }
                          }
                      });';

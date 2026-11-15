<?php
/**
 * /_includes/settings/reporting-dw-potential-problems.inc.php
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
$page_title = _('DW Potential Problems Report');
$breadcrumb = _('DW Potential Problems');
$software_section = "reporting";
$slug = "reporting-dw-potential-problems";
$report_section = 'dw';
$report_filename = 'potential-problems.php';
$datatable_class = 'table table-striped dt-responsive cell-border compact';
// The servers' figures (a short list, in the page's order), then the three problem lists, which are paged like the
// Domains list and can be sorted by any column
$datatable_options = '$(\'#' . $slug . '-servers\').DataTable({
                          "paging": false,
                          "lengthChange": false,
                          "lengthMenu": [ [25, 50, 75, 100, -1], [25, 50, 75, 100, "All"] ],
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
                                           } ],
                          "ordering": false,
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\'
                      });

                      var oldStart_no_zone = 0;
                      $(\'#' . $slug . '-no-zone\').DataTable({
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
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            var start = new DataTable.Api(settings).page.info().start;
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            $(new DataTable.Api(settings).table().container()).find(".dt-paging nav").attr("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Accounts without a DNS Zone')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                            if ( start != oldStart_no_zone ) {
                                var targetOffset = $("#' . $slug . '-no-zone").offset().top;
                                $("html,body").animate({scrollTop: targetOffset}, 0);
                                oldStart_no_zone = start;
                            }
                          }
                      });

                      var oldStart_no_account = 0;
                      $(\'#' . $slug . '-no-account\').DataTable({
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
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            var start = new DataTable.Api(settings).page.info().start;
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            $(new DataTable.Api(settings).table().container()).find(".dt-paging nav").attr("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('DNS Zones without an Account')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                            if ( start != oldStart_no_account ) {
                                var targetOffset = $("#' . $slug . '-no-account").offset().top;
                                $("html,body").animate({scrollTop: targetOffset}, 0);
                                oldStart_no_account = start;
                            }
                          }
                      });

                      var oldStart_suspended = 0;
                      $(\'#' . $slug . '-suspended\').DataTable({
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
                          "order": [[ 1, "asc" ]],
                          "bSortClasses": false,
                          "dom": \'<"top"lif>rt<"bottom"ip><"clear">\',
                          "drawCallback": function (settings) {
                            var start = new DataTable.Api(settings).page.info().start;
                            // Each paged table names its paging, so the navigation landmarks on the page can be told apart
                            $(new DataTable.Api(settings).table().container()).find(".dt-paging nav").attr("aria-label", ' . json_encode(sprintf(_('Pages of %s'), _('Suspended Accounts')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');
                            if ( start != oldStart_suspended ) {
                                var targetOffset = $("#' . $slug . '-suspended").offset().top;
                                $("html,body").animate({scrollTop: targetOffset}, 0);
                                oldStart_suspended = start;
                            }
                          }
                      });';

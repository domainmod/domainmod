<?php if (empty($footer_no_gap)) { ?><BR><BR><?php } ?>
<?php
/**
 * /_includes/layout/footer.inc.php
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
// The page's actions go into the header's marker, now that the page has declared them all (see header.inc.php)
if (isset($domainmod_page_actions_buffer) && ob_get_level() === $domainmod_page_actions_buffer) {
    $domainmod_page_html = ob_get_clean();
    $domainmod_marker_at = strpos($domainmod_page_html, DomainMOD\Layout::PAGE_ACTIONS_MARKER);
    if ($domainmod_marker_at !== false) {
        $domainmod_page_html = substr_replace($domainmod_page_html, $layout->renderPageActions(), $domainmod_marker_at, strlen(DomainMOD\Layout::PAGE_ACTIONS_MARKER));
    }
    echo $domainmod_page_html;
} ?>
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.app-content -->
    </main>
    <!-- /.app-main -->

    <footer class="app-footer">
        <!-- To the right -->
        <div class="float-end d-none d-sm-inline">
            Version <?php echo SOFTWARE_VERSION; ?>
        </div>
        <!-- Default to the left -->
        <?php echo sprintf(_('%s%s%s is open source software released under the %sGNU/GPL License%s.'), '<a href="http://domainmod.org">', SOFTWARE_TITLE, '</a>', '<a href="http://www.gnu.org/licenses/">', '</a>'); ?>
        <?php echo _('Created by'); ?> <a href="https://greg.ca">Greg Chetcuti</a>.<BR>
    </footer>
</div>
<!-- /.app-wrapper -->
<?php $_SESSION['s_redirect'] = $_SERVER["REQUEST_URI"]; ?>
<?php require DIR_INC . '/layout/messages-announcer.inc.php'; ?>
<?php // Where a short success shows as a toast (js/messages.js). It isn't a live region: the announcer reads each toast. ?>
<div class="toast-container position-fixed top-0 end-0 p-3 domainmod-toasts"></div>
<?php
$full_filename = DIR_INC . '/layout/footer.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/footer.DEMO.inc.php';

}
?>
<!-- Bootstrap 5 (with Popper) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<!-- Tom Select (the multiple choice menus) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/tom-select/js/tom-select.complete.min.js'); ?>"></script>
<!-- DataTables, with Bootstrap 5 styling and Responsive -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables/js/dataTables.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables-responsive/js/responsive.bootstrap5.min.js'); ?>"></script>
<!-- Starts each list's DataTable (after DataTables), see domainmodTable() -->
<script src="<?php echo $layout->assetUrl('js/datatables.js'); ?>"></script>
<!-- htmx, for tasks that run in place (its settings are in head-tags.inc.php) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/htmx/htmx.min.js'); ?>"></script>
<!-- AdminLTE App (after Bootstrap) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/js/adminlte.min.js'); ?>"></script>
<!-- Saves the colour mode choice to the user's account (after AdminLTE) -->
<script src="<?php echo $layout->assetUrl('js/colour-mode-save.js'); ?>"></script>
<!-- Page messages: the screen reader announcements, the toasts, and where focus goes when one is closed (after Bootstrap) -->
<script src="<?php echo $layout->assetUrl('js/messages.js'); ?>"></script>
<script nonce="<?php echo CURRENT_NONCE; ?>">
    // Runs once the page is ready. The script sits at the end of the page, so that's usually straight away.
    var domainmodStart = function () {

        // Tooltips, such as on the lists' status badges (see Layout::statusBadge()). Each opens on hover or focus, stays
        // open while the pointer is over it or the tooltip itself, and Escape closes it. The badge's aria-label already
        // gives screen readers the same text, so the tooltip isn't also linked as its description. They're started on
        // the page and again on anything htmx swaps in.
        var startTooltips = function (root) {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                if (bootstrap.Tooltip.getInstance(element)) return;
                // No fade, so showing it again straight after hiding can't race Bootstrap's hide transition
                var tooltip = new bootstrap.Tooltip(element, {trigger: 'manual', animation: false});
                var hideUnlessInUse = function () {
                    setTimeout(function () {
                        var tip_in_use = tooltip.tip && tooltip.tip.matches(':hover');
                        if (!element.matches(':hover') && document.activeElement !== element && !tip_in_use) tooltip.hide();
                    }, 100);
                };
                // Bootstrap closes a manual tooltip that's shown again while already open, so it's only shown when closed
                var is_open = false;
                var showIfClosed = function () {
                    if (!is_open) tooltip.show();
                };
                element.addEventListener('mouseenter', showIfClosed);
                element.addEventListener('focus', showIfClosed);
                element.addEventListener('hidden.bs.tooltip', function () { is_open = false; });
                element.addEventListener('mouseleave', hideUnlessInUse);
                element.addEventListener('blur', hideUnlessInUse);
                element.addEventListener('shown.bs.tooltip', function () {
                    is_open = true;
                    element.removeAttribute('aria-describedby');
                    if (tooltip.tip) tooltip.tip.addEventListener('mouseleave', hideUnlessInUse);
                });
            });
        };
        startTooltips(document);
        // For rows a script adds later, such as the server-side lists' pages (see js/server-list.js)
        window.domainmodStartTooltips = startTooltips;

        // The expandable boxes' buttons (see Layout::expandableBoxTop()) say whether their box is open, following
        // AdminLTE's card widget, which dispatches these events on the card when it opens or closes
        ['expanded.lte.card-widget', 'collapsed.lte.card-widget'].forEach(function (event_name) {
            document.addEventListener(event_name, function (event) {
                var button = event.target.querySelector('[data-lte-toggle="card-collapse"][aria-expanded]');
                if (button) button.setAttribute('aria-expanded', event_name.indexOf('expanded') === 0 ? 'true' : 'false');
            });
        });
        // A list built inside a closed box, such as the Data Warehouse boxes on Edit Domain, was measured while hidden,
        // so its columns are sized again when the box opens, and Responsive works out again which ones fit (otherwise
        // the table runs off the side on a phone). AdminLTE sends the event once the box has finished opening.
        document.addEventListener('expanded.lte.card-widget', function () {
            if (typeof DataTable === 'undefined' || typeof DataTable.tables !== 'function') return;
            var tables = DataTable.tables({visible: true, api: true}).columns.adjust();
            if (tables.responsive) tables.responsive.recalc();
        });
        document.addEventListener('htmx:load', function (event) {
            startTooltips(event.detail.elt);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                var tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) tooltip.hide();
            });
        });

        // The multiple choice menus (see Form::showMultipleSelectTop()), each with a button to remove a choice. They have
        // no placeholder, since the label above each one already names it. Tom Select hides the real menu, so a mistake
        // marked on it (Form::addFieldError()) is copied to the box people type in, for screen readers.
        document.querySelectorAll('select.domainmod-multiple-select').forEach(function (select) {
            // Tom Select hides the real menu and builds its own, and moves the label's "for" to its own box, so the label
            // is found first, then names both menus
            var label = document.querySelector('label[for="' + select.id + '"]');
            var tom_select = new TomSelect(select, {plugins: ['remove_button']});
            if (label) {
                if (!label.id) label.id = select.id + '-label';
                select.setAttribute('aria-labelledby', label.id);
                tom_select.control_input.setAttribute('aria-labelledby', label.id);
            }
            ['aria-invalid', 'aria-describedby'].forEach(function (attribute) {
                if (select.hasAttribute(attribute)) tom_select.control_input.setAttribute(attribute, select.getAttribute(attribute));
            });
        });

        // htmx tasks (see Layout::htmxTaskLink() and DomainQueue::processingNotice()). A real result carries the
        // X-DomainMOD-Result header. Anything else, such as the sign-in page or the page a failed check redirected to,
        // isn't swapped in, and a message says it didn't work instead. A link is marked busy while its task runs.
        var htmx_error = <?php echo json_encode(_("That didn't work. Please reload the page and try again.")); ?>;
        var htmx_error_close = <?php echo json_encode(_('Close')); ?>;
        var showHtmxError = function (element, target) {
            // The same slim alert as the page messages (System::slimMessage()). It's added after the page loads, so its
            // role="alert" is announced, and the shared announcer doesn't read it again.
            var alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible d-flex align-items-start gap-2 py-2 domainmod-message';
            alert.setAttribute('role', 'alert');
            var icon = document.createElement('i');
            icon.className = 'bi bi-exclamation-octagon-fill domainmod-message-icon';
            icon.setAttribute('aria-hidden', 'true');
            var text = document.createElement('div');
            text.className = 'domainmod-message-text';
            text.textContent = htmx_error;
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('data-bs-dismiss', 'alert');
            close.setAttribute('aria-label', htmx_error_close);
            alert.append(icon, text, close);
            if (element.hasAttribute('hx-trigger') && element.getAttribute('hx-trigger').indexOf('every') === 0) {
                // A polling notice: swap in a plain copy, which htmx stops polling, with the message after it
                var copy = element.cloneNode(true);
                Array.prototype.slice.call(copy.attributes).forEach(function (attribute) {
                    if (attribute.name.indexOf('hx-') === 0) copy.removeAttribute(attribute.name);
                });
                element.replaceWith(copy);
                copy.insertAdjacentElement('afterend', alert);
            } else if (target) {
                target.insertAdjacentElement('afterbegin', alert);
            }
        };
        document.addEventListener('htmx:beforeSwap', function (event) {
            if (event.detail.xhr.getResponseHeader('X-DomainMOD-Result') === '1') return;
            event.detail.shouldSwap = false;
            event.detail.isError = false;
            showHtmxError(event.detail.elt, event.detail.target);
        });
        document.addEventListener('htmx:sendError', function (event) {
            showHtmxError(event.detail.elt, event.detail.target);
        });
        document.addEventListener('htmx:beforeRequest', function (event) {
            event.detail.elt.setAttribute('aria-busy', 'true');
        });
        document.addEventListener('htmx:afterRequest', function (event) {
            event.detail.elt.removeAttribute('aria-busy');
        });
        // A result swapped in by htmx (maintenance, Run Now, Refresh, and the reports' comparisons) is read out once by
        // the shared announcer, and focus is put back when the swap took it away. js/messages.js does both.

        // Every list's column headers cycle between ascending and descending, as before (DataTables 2 and later add a
        // third click that removes the sort)
        DataTable.defaults.column.orderSequence = ['asc', 'desc'];

        // DataTables' own text, in the user's language, for every list: the search box, the page length menu (its
        // "All" choice included, so the settings files in _includes/settings/ give only the numbers), the line that
        // counts the rows, the empty messages, and the pager and sort buttons' names for screen readers. Each value is
        // set on its own, so DataTables keeps its defaults for the rest, such as the number separators. The words in
        // capitals between underscores are DataTables' placeholders, filled in as the table draws.
        var dt_language = DataTable.defaults.language;
        Object.assign(dt_language, {
            search: <?php echo json_encode(_('Search') . ':'); ?>,
            <?php /* TRANSLATORS: Keep _MENU_ and _ENTRIES_ exactly as they are. _MENU_ becomes the menu of page sizes, and _ENTRIES_ becomes the word for entries. */ ?>
            lengthMenu: <?php echo json_encode(_('_MENU_ _ENTRIES_ per page')); ?>,
            lengthLabels: {'-1': <?php echo json_encode(_('All')); ?>},
            entries: {
                <?php /* TRANSLATORS: The word for more than one row of a list, or none, as in "Showing 1 to 25 of 80 entries" */ ?>
                _: <?php echo json_encode(_('entries')); ?>,
                <?php /* TRANSLATORS: The word for one row of a list, as in "Showing 1 to 1 of 1 entry" */ ?>
                1: <?php echo json_encode(_('entry')); ?>
            },
            <?php /* TRANSLATORS: Keep _START_, _END_, _TOTAL_, and _ENTRIES-TOTAL_ exactly as they are. They become the first and last row shown, the number of rows, and the word for entries. */ ?>
            info: <?php echo json_encode(_('Showing _START_ to _END_ of _TOTAL_ _ENTRIES-TOTAL_')); ?>,
            <?php /* TRANSLATORS: Keep _ENTRIES-TOTAL_ exactly as it is. It becomes the word for entries. */ ?>
            infoEmpty: <?php echo json_encode(_('Showing 0 to 0 of 0 _ENTRIES-TOTAL_')); ?>,
            <?php /* TRANSLATORS: Keep _MAX_ and _ENTRIES-MAX_ exactly as they are. They become the number of rows before the search and the word for entries. */ ?>
            infoFiltered: <?php echo json_encode(_('(filtered from _MAX_ total _ENTRIES-MAX_)')); ?>,
            zeroRecords: <?php echo json_encode(_('No matching entries found.')); ?>,
            emptyTable: <?php echo json_encode(_('There are no entries in this list.')); ?>,
            loadingRecords: <?php echo json_encode(_('Loading…')); ?>
        });
        // The sort buttons' names, after each column's title. DataTables calls a sorted column's next click "remove" when
        // it's at the end of orderSequence, but with only ascending and descending (above) that click reverses it again.
        var dt_reverse = ': ' + <?php echo json_encode(_('Activate to reverse the sort')); ?>;
        Object.assign(dt_language.aria, {
            orderable: ': ' + <?php echo json_encode(_('Activate to sort')); ?>,
            orderableReverse: dt_reverse,
            orderableRemove: dt_reverse
        });
        Object.assign(dt_language.aria.paginate, {
            first: <?php echo json_encode(_('First')); ?>,
            previous: <?php echo json_encode(_('Previous')); ?>,
            next: <?php echo json_encode(_('Next')); ?>,
            last: <?php echo json_encode(_('Last')); ?>
        });

        <?php echo $datatable_options ?? ''; ?>

        // DataTables names each pager "pagination" in English. A list that gives its pager its own name, such as the
        // Domains list (see js/server-list.js), keeps it.
        var dt_pages = <?php echo json_encode(_('Pages')); ?>;
        document.querySelectorAll('.dt-container nav[aria-label="pagination"]').forEach(function (nav) {
            nav.setAttribute('aria-label', dt_pages);
        });

    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', domainmodStart);
    } else {
        domainmodStart();
    }
</script>

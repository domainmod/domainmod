<BR><BR>
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
<?php
$full_filename = DIR_INC . '/layout/footer.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/footer.DEMO.inc.php';

}
?>
<!-- jQuery -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/jquery/jquery.min.js'); ?>"></script>
<!-- Bootstrap 5 (with Popper) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<!-- Tom Select (the multiple choice menus) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/tom-select/js/tom-select.complete.min.js'); ?>"></script>
<!-- DataTables (after jQuery, so it adds itself as a jQuery plugin), with Bootstrap 5 styling and Responsive -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables/js/dataTables.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables-responsive/js/responsive.bootstrap5.min.js'); ?>"></script>
<!-- htmx, for tasks that run in place (its settings are in head-tags.inc.php) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/htmx/htmx.min.js'); ?>"></script>
<!-- AdminLTE App (after Bootstrap) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/js/adminlte.min.js'); ?>"></script>
<!-- Saves the colour mode choice to the user's account (after AdminLTE) -->
<script src="<?php echo $layout->assetUrl('js/colour-mode-save.js'); ?>"></script>
<script nonce="<?php echo CURRENT_NONCE; ?>">
    $(function () {

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
        // no placeholder, since the label above each one already names it.
        document.querySelectorAll('select.domainmod-multiple-select').forEach(function (select) {
            new TomSelect(select, {plugins: ['remove_button']});
        });

        // htmx tasks (see Layout::htmxTaskLink() and DomainQueue::processingNotice()). A real result carries the
        // X-DomainMOD-Result header. Anything else, such as the sign-in page or the page a failed check redirected to,
        // isn't swapped in, and a message says it didn't work instead. A link is marked busy while its task runs.
        var htmx_error = <?php echo json_encode(_("That didn't work. Please reload the page and try again.")); ?>;
        var showHtmxError = function (element, target) {
            var alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.setAttribute('role', 'alert');
            alert.textContent = htmx_error;
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
        // A result (such as Run Now's) is read out through a polite live region that's on the page from the start, since
        // a region swapped in with its text isn't reliably announced. It takes the focus only when the swap took it away
        // (Run Now replaces the link that had it), so it never pulls focus from somewhere someone has moved on to.
        var htmx_announcer = document.createElement('div');
        htmx_announcer.className = 'visually-hidden';
        htmx_announcer.setAttribute('aria-live', 'polite');
        document.body.appendChild(htmx_announcer);
        document.addEventListener('htmx:afterSettle', function (event) {
            var focus_lost = !document.activeElement || document.activeElement === document.body;
            var result = event.detail.elt.querySelector('[data-domainmod-focus]');
            if (!result) return;
            htmx_announcer.textContent = result.textContent.replace(/\s+/g, ' ').trim();
            if (focus_lost) result.focus();
        });

        // Every list's column headers cycle between ascending and descending, as before (DataTables 2 and later add a
        // third click that removes the sort)
        DataTable.defaults.column.orderSequence = ['asc', 'desc'];

        <?php echo $datatable_options ?? ''; ?>

    });
</script>

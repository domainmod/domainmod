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
<!-- AdminLTE App (after Bootstrap) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/js/adminlte.min.js'); ?>"></script>
<!-- Saves the colour mode choice to the user's account (after AdminLTE) -->
<script src="<?php echo $layout->assetUrl('js/colour-mode-save.js'); ?>"></script>
<script nonce="<?php echo CURRENT_NONCE; ?>">
    $(function () {

        // The multiple choice menus (see Form::showMultipleSelectTop()), each with a button to remove a choice. They have
        // no placeholder, since the label above each one already names it.
        document.querySelectorAll('select.domainmod-multiple-select').forEach(function (select) {
            new TomSelect(select, {plugins: ['remove_button']});
        });

        // Every list's column headers cycle between ascending and descending, as before (DataTables 2 and later add a
        // third click that removes the sort)
        DataTable.defaults.column.orderSequence = ['asc', 'desc'];

        <?php echo $datatable_options ?? ''; ?>

    });
</script>

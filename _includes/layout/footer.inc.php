<BR><BR>
<?php
/**
 * /_includes/layout/footer.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
        Version <?php echo SOFTWARE_VERSION; ?>
    </div>
    <!-- Default to the left -->
    <?php echo sprintf(_('%s%s%s is open source software released under the %sGNU/GPL License%s.'), '<a href="http://domainmod.org">', SOFTWARE_TITLE, '</a>', '<a href="http://www.gnu.org/licenses/">', '</a>'); ?>
    <?php echo _('Created by'); ?> <a href="http://chetcuti.com">Greg Chetcuti</a>.<BR>
</footer>
</div>
<?php $_SESSION['s_redirect'] = $_SERVER["REQUEST_URI"]; ?>
<?php
$full_filename = DIR_INC . '/layout/footer.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/footer.DEMO.inc.php';

}
?>
<!-- jQuery -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Select2 -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/select2/js/select2.full.min.js"></script>
<!-- Bootstrap Switch -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/dist/js/adminlte.min.js"></script>
<script nonce="<?php echo CURRENT_NONCE; ?>">
    $(function () {

        //Initialize Select2 Elements
        $('.select2').select2()

        <?php echo $datatable_options ?? ''; ?>

        $("input[data-bootstrap-switch]").each(function(){
            $(this).bootstrapSwitch('state', $(this).prop('checked'));
        })

    });
</script>

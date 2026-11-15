<?php
/**
 * /_includes/layout/footer-login.inc.php
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
  </main>
</div>
<!-- /.login-box -->
<?php require DIR_INC . '/layout/messages-announcer.inc.php'; ?>
<?php
$full_filename = DIR_INC . '/layout/footer.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/footer.DEMO.inc.php';

}
$_SESSION['s_redirect'] = $_SERVER["REQUEST_URI"];
?>
<!-- Bootstrap 5 (with Popper) -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<!-- AdminLTE App (after Bootstrap), which follows the system's colour mode in Auto -->
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/js/adminlte.min.js'); ?>"></script>
<!-- Follows the system's colour mode when AdminLTE can't (after AdminLTE) -->
<script src="<?php echo $layout->assetUrl('js/colour-mode-save.js'); ?>"></script>
<!-- Page messages: the screen reader announcements, the toasts, and where focus goes when one is closed (after Bootstrap) -->
<script src="<?php echo $layout->assetUrl('js/messages.js'); ?>"></script>

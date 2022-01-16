<?php
/**
 * /_includes/layout/head-tags.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link rel="icon" type="image/ico" href="<?php echo $web_root; ?>/images/favicon.ico"/>
<?php require_once DIR_ROOT . '/css/detect-and-include-datatables-css.inc.php'; ?>
<!-- Tell the browser to be responsive to screen width -->
<!meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
<!-- Google Font: Source Sans Pro -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
<!-- Font Awesome Icons -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/fontawesome-free/css/all.min.css">
<!-- iCheck for checkboxes and radio inputs -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
<!-- Select2 -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<!-- Ionicons -->
<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<!-- Theme style -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/dist/css/adminlte.min.css">
<!-- Custom DomainMOD styles -->
<link rel="stylesheet" href="<?php echo $web_root; ?>/css/base.css">
<?php
$full_filename = DIR_INC . '/layout/head-tags.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/head-tags.DEMO.inc.php';

}

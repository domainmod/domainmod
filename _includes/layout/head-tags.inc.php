<?php
/**
 * /_includes/layout/head-tags.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2021 Greg Chetcuti <greg@chetcuti.com>
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
<link rel="icon" type="image/ico" href="<?php echo $web_root; ?>/images/favicon.ico"/>
<style type="text/css">
    html {
        overflow-y: scroll;
    }

    <?php echo $datatable_css; ?>
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title></title>
<!-- Tell the browser to be responsive to screen width -->
<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
<!-- Bootstrap 3.3.5 -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/bootstrap/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
<!-- Ionicons -->
<link rel="stylesheet" href="//code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
<!-- DataTables -->
<?php /* ?>
<!link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables/jquery.dataTables.css">
<?php */ ?>
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables/dataTables.bootstrap.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables/dataTables.responsive.css">
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/datatables/dataTables.fontAwesome.css">
 <!-- iCheck for checkboxes and radio inputs -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/iCheck/square/red.css">
<!-- Select2 -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/plugins/select2/select2.min.css">
<!-- Theme style -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/dist/css/AdminLTE.min.css">
<!-- AdminLTE Skins. Choose a skin from the css/skins
   folder instead of downloading all of them to reduce the load. -->
<link rel="stylesheet" href="<?php echo $web_root . '/' . WEBROOT_THEME; ?>/dist/css/skins/skin-red.min.css">
<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
<script src="//oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
<script src="//oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<?php
$full_filename = DIR_INC . '/layout/head-tags.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/head-tags.DEMO.inc.php';

}

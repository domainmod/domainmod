<?php
/**
 * /_includes/layout/head-tags.inc.php
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<!-- htmx's settings for the strict CSP: no eval, no inserted styles (its indicator rules are in base.css), no style
     attributes copied while it settles a swap (such as a chart canvas's), no scripts in swapped content, requests to
     this site only, and no copies of pages kept in the browser's storage, so going back to an address htmx added (such
     as a report's comparison) loads that page in full -->
<meta name="htmx-config" content='{"allowEval": false, "includeIndicatorStyles": false, "attributesToSettle": ["class", "width", "height"], "allowScriptTags": false, "selfRequestsOnly": true, "historyCacheSize": 0, "refreshOnHistoryMiss": true}'>
<!-- Sets the colour mode before anything is drawn, so it has to come before the stylesheets -->
<script src="<?php echo $layout->assetUrl('js/colour-mode-init.js'); ?>"></script>
<link rel="icon" type="image/ico" href="<?php echo $web_root; ?>/images/favicon.ico"/>
<?php require_once DIR_ROOT . '/css/detect-and-include-datatables-css.inc.php'; ?>
<!-- Font: Source Sans 3 -->
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/source-sans-3/source-sans-3.css'); ?>">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/bootstrap-icons/bootstrap-icons.min.css'); ?>">
<!-- Theme style (AdminLTE, which includes Bootstrap's styles), and its colours, which make red the main colour -->
<?php // In right-to-left languages (Layout::isRtl()), AdminLTE's mirrored builds of the same two files ?>
<?php $theme_css_suffix = $layout->isRtl() ? '.rtl.min.css' : '.min.css'; ?>
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte' . $theme_css_suffix); ?>">
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte-colors' . $theme_css_suffix); ?>">
<!-- Tom Select (the multiple choice menus), with Bootstrap 5 styling -->
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/tom-select/css/tom-select.bootstrap5.min.css'); ?>">
<!-- DataTables, with Bootstrap 5 styling -->
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables/css/dataTables.bootstrap5.min.css'); ?>">
<link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/datatables-responsive/css/responsive.bootstrap5.min.css'); ?>">
<!-- Custom DomainMOD styles -->
<link rel="stylesheet" href="<?php echo $layout->assetUrl('css/base.css'); ?>">
<?php
$full_filename = DIR_INC . '/layout/head-tags.DEMO.inc.php';

if (file_exists($full_filename)) {

    require_once DIR_INC . '/layout/head-tags.DEMO.inc.php';

}

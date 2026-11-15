<?php
/**
 * /offline.php
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
// The page the installed app shows when it can't reach the server (cached by service-worker.php). It's standalone: no
// database, no session, and none of DomainMOD's scripts, so it works with nothing but the files the service worker
// keeps. Its language is the one DomainMOD uses without a session (the server's, or English).
require_once __DIR__ . '/_includes/init.inc.php';

if (!file_exists(DIR_INC . '/config.inc.php')) {
    http_response_code(404);
    exit;
}

require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$layout = new DomainMOD\Layout();

// The strict policy, with nothing loosened. The page has no inline scripts or styles, so it needs no nonce.
header("Content-Security-Policy: default-src 'none'; font-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none';");
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<?php echo $layout->htmlTag(); ?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $layout->pageTitle(_("You're offline")); ?></title>
    <!-- Reads the colour mode the other pages saved, before anything is drawn, without changing it -->
    <script src="<?php echo $layout->assetUrl('js/offline-colour-mode.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/source-sans-3/source-sans-3.css'); ?>">
    <?php // The mirrored builds in right-to-left languages, the same choice head-tags.inc.php makes (and service-worker.php caches) ?>
    <?php $theme_css_suffix = $layout->isRtl() ? '.rtl.min.css' : '.min.css'; ?>
    <link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte' . $theme_css_suffix); ?>">
    <link rel="stylesheet" href="<?php echo $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte-colors' . $theme_css_suffix); ?>">
    <link rel="stylesheet" href="<?php echo $layout->assetUrl('css/base.css'); ?>">
</head>
<body class="bg-body-secondary">
<main class="container py-5">
    <div class="domainmod-offline text-center mx-auto">
        <img src="<?php echo WEB_ROOT; ?>/images/icons/icon-192.png" alt="" width="96" height="96" class="mb-3">
        <h1 class="h3"><?php echo _("You're offline"); ?></h1>
        <p class="text-body-secondary"><?php echo sprintf(_("%s can't reach its server right now."), SOFTWARE_TITLE); ?><br><?php echo _('Check your connection, then try again.'); ?></p>
        <a class="btn btn-danger" href="<?php echo WEB_ROOT; ?>/"><?php echo _('Try Again'); ?></a>
    </div>
</main>
</body>
</html>

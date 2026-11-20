<?php
/**
 * /_includes/layout/import-queue-tabs.inc.php
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
/*
 * The Import Queue's two parts, drawn the same way at the top of both status pages (queue/index.php and
 * queue/ssl/index.php): links styled as tabs, each with a badge counting its rows still waiting. Before the database
 * update there's only the Domain Queue, so no tabs are drawn.
 */
$import_queue_ssl = new DomainMOD\SslQueue();

if ($import_queue_ssl->isAvailable()) {

    list($import_queue_domain_count, $import_queue_ssl_count) = $import_queue_ssl->tabCounts();

    $import_queue_tabs = array(
        array('queue-main', $web_root . '/queue/', _('Domains'), $import_queue_domain_count),
        array('queue-ssl', $web_root . '/queue/ssl/', _('SSL Certificates'), $import_queue_ssl_count)
    ); ?>

    <nav aria-label="<?php echo _('Import Queue'); ?>">
    <ul class="nav nav-tabs mb-3"><?php

    foreach ($import_queue_tabs as $import_queue_tab) {

        list($tab_slug, $tab_url, $tab_label, $tab_count) = $import_queue_tab;
        $tab_active = $slug === $tab_slug; ?>

        <li class="nav-item">
            <a class="nav-link<?php echo $tab_active ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($tab_url, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $tab_active ? ' aria-current="page"' : ''; ?>><?php echo $tab_label;
            if ($tab_count > 0) { ?>
                <span class="badge text-bg-secondary ms-1" aria-hidden="true"><?php echo number_format($tab_count); ?></span><span class="visually-hidden"><?php
                    // TRANSLATORS: Read out by screen readers after an Import Queue tab's name (Domains or SSL Certificates). %s is a number.
                    echo ' (' . sprintf(ngettext('%s waiting', '%s waiting', $tab_count), number_format($tab_count)) . ')'; ?></span><?php
            } ?></a>
        </li><?php

    } ?>

    </ul>
    </nav><?php

}

/*
 * /js/service-worker-register.js
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

/*
 * Registers the service worker (service-worker.php), which gives the installed app its offline page. Its address comes
 * from the domainmod-service-worker meta tag (head-tags.inc.php), so this file has no PHP in it. Browsers only allow
 * service workers on HTTPS (or localhost), so on a plain HTTP install this does nothing, and nothing breaks.
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
    var meta = document.querySelector('meta[name="domainmod-service-worker"]');
    if (!meta) return;
    var url = meta.getAttribute('content');
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(url).catch(function () {
            // Not being able to register only means no offline page, so it isn't shown as an error
        });
    });
})();

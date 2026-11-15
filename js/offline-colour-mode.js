/*
 * /js/offline-colour-mode.js
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
 * The offline page's colour mode (offline.php). That page has no session, so the server can't print the user's choice,
 * and colour-mode-init.js would then save "auto" over it. This only reads: it takes the choice the other pages saved in
 * the browser's storage (lte-theme), follows the system setting for auto, and sets it on <html> before the page is
 * drawn. It never writes to storage.
 */
(function () {
    'use strict';

    var choice = 'auto';
    try {
        var saved = window.localStorage.getItem('lte-theme');
        if (saved === 'light' || saved === 'dark') choice = saved;
    } catch (e) {
        // Storage can't be read, so the system setting decides
    }

    if (choice === 'auto') {
        choice = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.setAttribute('data-bs-theme', choice);
})();

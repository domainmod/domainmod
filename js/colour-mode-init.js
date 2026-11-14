/*
 * /js/colour-mode-init.js
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
 * Sets the colour mode before the page is drawn, so it never flashes the wrong mode. Loaded in the <head> before any
 * stylesheet. The server prints the user's saved choice (light, dark, or auto) on <html data-bs-theme>, and this
 * follows AdminLTE's own snippet, with two changes:
 *
 * 1. The saved choice is copied into the browser's storage first, so a choice left there from another device or user
 *    never wins over the account.
 * 2. If the browser's storage can't be used, AdminLTE's colour mode switcher is turned off (it can't remember a choice
 *    without storage) and colour-mode-save.js runs the switcher instead. If reading localStorage itself throws,
 *    window.localStorage is replaced with undefined, since AdminLTE's sidebar script checks it outside a try block
 *    and would otherwise stop starting up.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var choice = root.getAttribute('data-bs-theme');
    if (choice !== 'light' && choice !== 'dark') choice = 'auto';
    root.setAttribute('data-domainmod-colour-mode', choice);

    var storage_works = true;
    try {
        window.localStorage.setItem('lte-theme', choice);
    } catch (e) {
        storage_works = false;
    }

    if (!storage_works) {
        root.setAttribute('data-lte-color-mode', 'off');
        try {
            void window.localStorage;
        } catch (e) {
            try {
                Object.defineProperty(window, 'localStorage', {value: undefined, configurable: true});
            } catch (e2) {
                // Nothing more can be done. The page still shows the saved choice.
            }
        }
    }

    var resolved = choice;
    if (resolved === 'auto') {
        resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    root.setAttribute('data-bs-theme', resolved);
    root.style.colorScheme = resolved;
    if (resolved !== choice) root.setAttribute('data-lte-theme-resolved', '');
}());

/*
 * /js/colour-mode-save.js
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
 * Saves the Light, Dark, or Auto choice to the user's account when it's changed in the header's switcher, so it
 * follows them to every browser. Loaded at the end of every page, after AdminLTE's script.
 *
 * AdminLTE's colour mode switcher normally switches the page and announces the change, and this saves it in the
 * background. It also keeps this page's own choice in memory: AdminLTE rereads the browser's storage when the system
 * setting changes, and that storage is shared by every tab, so another tab could otherwise change this one. When
 * colour-mode-init.js has turned AdminLTE's switcher off (the browser's storage can't be used), this does its job
 * instead, on every page, including the sign-in and installer pages.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var switcher = document.getElementById('domainmod-colour-mode');
    var choices = ['light', 'dark', 'auto'];
    var choice = root.getAttribute('data-domainmod-colour-mode') || 'auto';
    var adminlte_switcher = root.getAttribute('data-lte-color-mode') !== 'off';
    var system_dark = window.matchMedia('(prefers-color-scheme: dark)');

    function apply() {
        var resolved = choice === 'auto' ? (system_dark.matches ? 'dark' : 'light') : choice;
        root.setAttribute('data-bs-theme', resolved);
        root.style.colorScheme = resolved;
    }

    function show() {
        document.querySelectorAll('[data-bs-theme-value]').forEach(function (toggle) {
            var active = toggle.getAttribute('data-bs-theme-value') === choice;
            toggle.classList.toggle('active', active);
            toggle.setAttribute('aria-pressed', String(active));
            var check = toggle.querySelector('.bi-check-lg');
            if (check) check.classList.toggle('d-none', !active);
        });
        document.querySelectorAll('[data-lte-theme-icon]').forEach(function (icon) {
            icon.classList.toggle('d-none', icon.getAttribute('data-lte-theme-icon') !== choice);
        });
    }

    // The Display Settings page's own Colour Mode field, kept in step so saving that form never undoes a header change
    function updateSettingsField() {
        var field = document.querySelector('select[name="new_dark_mode"]');
        if (field) field.value = String(choices.indexOf(choice));
    }

    // One save at a time, so quick changes reach the server in order. A change made while a save is on its way is
    // sent when that one finishes, and only the latest choice is sent.
    var saving = false;
    var save_again = false;

    function save() {
        if (!switcher || !window.fetch) return;
        if (saving) {
            save_again = true;
            return;
        }
        saving = true;
        window.fetch(switcher.getAttribute('data-save-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(switcher.getAttribute('data-csrf-token')) + '&colour_mode=' +
                encodeURIComponent(choice)
        }).catch(function () {
            // A failed save (such as an expired session) leaves the page as it is, and the next page shows the
            // saved choice.
        }).then(function () {
            saving = false;
            if (save_again) {
                save_again = false;
                save();
            }
        });
    }

    function choose(picked) {
        choice = picked;
        updateSettingsField();
        save();
    }

    // A system change: AdminLTE (when it's on) handles it from shared storage, so this page's own choice is applied
    // straight after it.
    system_dark.addEventListener('change', function () {
        window.setTimeout(function () {
            apply();
            show();
        }, 0);
    });

    if (adminlte_switcher) {
        document.addEventListener('changed.lte.color-mode', function (event) {
            var picked = event.detail && event.detail.theme;
            if (choices.indexOf(picked) !== -1) choose(picked);
        });
        return;
    }

    // AdminLTE's switcher is off, so run the header's switcher here, as its own code would.
    if (!switcher) return;

    document.addEventListener('click', function (event) {
        var toggle = event.target instanceof Element ? event.target.closest('[data-bs-theme-value]') : null;
        if (!toggle) return;
        var picked = toggle.getAttribute('data-bs-theme-value');
        if (choices.indexOf(picked) === -1) return;
        choose(picked);
        apply();
        show();
    });

    show();
}());

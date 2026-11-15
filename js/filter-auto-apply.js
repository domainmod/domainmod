/**
 * /js/filter-auto-apply.js
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
 * The list filter panel (DomainMOD\FilterToolbar's offcanvas, on the Domains and SSL lists) applies a filter as soon as
 * it's changed, by submitting the panel's form, just as Apply Filters does. The page then draws every other menu from
 * the filters now active, and the panel opens again by itself, without its slide-in, scrolled to where it was, with
 * focus back on the control that was changed (found by its name), so a screen reader lands where it was.
 * - A menu applies when it's changed with the mouse or a touch, or picked from its open list. Arrowing through a closed
 *   menu changes its value at every key press in some browsers, so a change made that way waits until Enter or until
 *   focus leaves the menu, rather than reloading the page at every step.
 * - Keyword Search applies on Enter, or when it's left with a changed value, never at every key press.
 * - The Expiring Between date range applies when its calendar closes with a different, whole range (the date range
 *   picker clears a half-picked one), not when the first day of a range is picked.
 * - Moving to the Apply Filters button, out of the panel, or closing the panel doesn't apply a changed text field by
 *   itself: Apply Filters submits it as before, and leaving or closing the panel leaves it as before.
 * The signal to open the panel again goes into sessionStorage for the next page load only (and only on the same list
 * within a minute), so it never reaches an address, a chip, the export, Clear all, the paging links, or a bookmark.
 * Without JavaScript, or on the no-JavaScript page (expand=1), the form works as before with Apply Filters.
 * Loaded by domains/index.php and ssl/index.php, after the date range picker.
 */
(function () {
    'use strict';

    var panel = document.querySelector('.offcanvas.domainmod-filter-panel');
    if (!panel || typeof bootstrap === 'undefined') return;
    var form = panel.querySelector('form');
    if (!form) return;

    var storage_key = 'domainmod-filter-reopen';
    var submitting = false;

    // Each control's value when the page loaded, so leaving one unchanged doesn't reload the page
    var controls = Array.prototype.slice.call(form.querySelectorAll('select[name], input[type="text"][name]'));
    var initial = {};
    controls.forEach(function (control) {
        initial[control.name] = control.value;
    });

    var readSignal = function () {
        try {
            var saved = window.sessionStorage.getItem(storage_key);
            window.sessionStorage.removeItem(storage_key);
            return saved ? JSON.parse(saved) : null;
        } catch (error) {
            return null;
        }
    };

    var submitFrom = function (control) {
        if (submitting) return;
        // Set only briefly, against a change and a blur sending the form twice, so a submission stopped in the browser
        // doesn't leave the panel unable to apply anything
        submitting = true;
        setTimeout(function () {
            submitting = false;
        }, 3000);
        var body = panel.querySelector('.offcanvas-body');
        try {
            window.sessionStorage.setItem(storage_key, JSON.stringify({
                panel: panel.id,
                path: window.location.pathname,
                name: control.name,
                scroll: body ? body.scrollTop : 0,
                at: Date.now()
            }));
        } catch (error) {
            // Without storage the filter still applies, and the panel just doesn't open again
        }
        form.submit();
    };

    // Applied once the focus has settled, so a text field left for the Apply Filters button, for somewhere outside the
    // panel, or by closing the panel (its close button, Escape, or a click on the backdrop) behaves as it did before
    var applySoon = function (control) {
        if (submitting || control.value === initial[control.name]) return;
        setTimeout(function () {
            if (!panel.classList.contains('show') || panel.classList.contains('hiding')) return;
            var active = document.activeElement;
            if (active && active !== document.body && active !== control) {
                if (!panel.contains(active) || active.matches('[type="submit"], [data-bs-dismiss], a[href]')) return;
            }
            submitFrom(control);
        }, 0);
    };

    // Enter that confirms a word being composed (such as Japanese or Chinese input) belongs to the input method
    var composing = function (event) {
        return event.isComposing || event.keyCode === 229;
    };

    // The keys that change a closed menu's value without opening its list
    var stepping_keys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'];

    controls.forEach(function (control) {
        if (control.tagName === 'SELECT') {
            var stepped_at = -1;
            var waiting = false;
            control.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    if (control.value !== initial[control.name]) {
                        event.preventDefault();
                        submitFrom(control);
                    }
                    return;
                }
                if (stepping_keys.indexOf(event.key) !== -1 || event.key.length === 1) stepped_at = event.timeStamp;
            });
            control.addEventListener('change', function (event) {
                // A change straight after a stepping key or a typed letter came from the keyboard on the closed menu
                if (stepped_at >= 0 && event.timeStamp - stepped_at < 500) {
                    waiting = true;
                    return;
                }
                submitFrom(control);
            });
            control.addEventListener('blur', function () {
                if (waiting) applySoon(control);
            });
            return;
        }

        var picker = control._flatpickr;
        if (picker) {
            // The picker sends a change event as each day is picked, so the range applies only when the calendar closes
            // (after the page's own date range code has written the whole range or cleared a half-picked one)
            picker.config.onClose.push(function () {
                applySoon(control);
            });
            control.addEventListener('keydown', function (event) {
                // The picker applies a typed range and closes on Enter, so the form isn't also sent from here
                if (event.key === 'Enter' && !composing(event)) event.preventDefault();
            });
            return;
        }

        control.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || composing(event)) return;
            event.preventDefault();
            submitFrom(control);
        });
        control.addEventListener('change', function () {
            applySoon(control);
        });
    });

    // A page brought back from the browser's back-forward cache can apply filters again straight away
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) submitting = false;
    });

    // Opens the panel again after a filter applied from it, at once rather than sliding in
    var signal = readSignal();
    if (!signal || signal.panel !== panel.id || signal.path !== window.location.pathname) return;
    if (typeof signal.at !== 'number' || Date.now() - signal.at > 60000) return;

    var root = document.documentElement;
    var trigger = document.querySelector('[data-bs-toggle="offcanvas"][aria-controls="' + panel.id + '"]');
    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(panel);

    panel.addEventListener('shown.bs.offcanvas', function () {
        root.classList.remove('domainmod-filter-reopening');
        var control = null;
        controls.forEach(function (candidate) {
            if (!control && candidate.name === signal.name) control = candidate;
        });
        if (control) {
            control.focus({preventScroll: true});
            // Focus opens the date range picker's calendar, which isn't wanted on arrival
            if (control._flatpickr) control._flatpickr.close();
        }
        // Scrolled after the focus, since the calendar scrolls itself into view as it opens
        var body = panel.querySelector('.offcanvas-body');
        if (body && typeof signal.scroll === 'number') body.scrollTop = signal.scroll;
    }, {once: true});

    // Opened from here, Bootstrap doesn't know the Filters button opened it, so closing it puts focus back there itself
    panel.addEventListener('hidden.bs.offcanvas', function () {
        var active = document.activeElement;
        if (trigger && (!active || active === document.body || panel.contains(active))) trigger.focus();
    }, {once: true});

    root.classList.add('domainmod-filter-reopening');
    offcanvas.show();
})();

/*
 * /js/messages.js
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

// Page messages (System::slimMessage() and friends), in every layout:
// - The shared announcer (_includes/layout/messages-announcer.inc.php) reads each message to screen readers once.
//   Messages on the page when it loads are read after a short delay, so the live region is ready, and in page order.
//   A result htmx swaps in is read when it settles.
// - In the main layout, a short success (data-domainmod-toast) moves into a toast in the corner, which stays until
//   it's closed, replaced by a newer one, or the page changes. It never covers the focused control.
// - When a message is closed from the keyboard while it has the focus, the focus goes to a small "Message closed"
//   marker at the top of the main content, rather than being lost. A close with the mouse leaves no marker.
(function () {
    'use strict';

    var DomainMOD = window.DomainMOD = window.DomainMOD || {};
    var announcer = null;
    var ready = false;
    var held = [];
    var dismissed = [];
    var MAX_ENTRIES = 20;
    var READY_DELAY = 500;

    var label = function (name) {
        return announcer ? (announcer.getAttribute('data-label-' + name) || '') : '';
    };

    // Each announcement is its own entry in the log, so they're read in order and a repeat is read again
    DomainMOD.announce = function (text) {
        text = (text || '').trim();
        if (text === '') return;
        if (!ready || !announcer) {
            held.push(text);
            return;
        }
        var entry = document.createElement('div');
        entry.textContent = text;
        announcer.appendChild(entry);
        while (announcer.children.length > MAX_ENTRIES) announcer.removeChild(announcer.firstChild);
    };

    // A message's text as it's read: without its icon, its close button, and the notes of a closed "Why?", with a
    // line break at each <br> and between blocks, so joined messages aren't read as run-together words
    var BLOCKS = ['DIV', 'P', 'UL', 'OL', 'LI', 'SUMMARY', 'DETAILS'];
    DomainMOD.messageText = function (element) {
        var copy = element.cloneNode(true);
        copy.querySelectorAll('details:not([open])').forEach(function (details) {
            Array.prototype.slice.call(details.children).forEach(function (child) {
                if (child.tagName !== 'SUMMARY') child.remove();
            });
        });
        copy.querySelectorAll('[aria-hidden="true"], .btn-close').forEach(function (hidden) {
            hidden.remove();
        });
        var text = '';
        var walk = function (node) {
            node.childNodes.forEach(function (child) {
                if (child.nodeType === Node.TEXT_NODE) {
                    text += child.data;
                } else if (child.nodeType === Node.ELEMENT_NODE) {
                    if (child.tagName === 'BR') {
                        text += '\n';
                        return;
                    }
                    var block = BLOCKS.indexOf(child.tagName) !== -1;
                    if (block) text += '\n';
                    walk(child);
                    if (block) text += '\n';
                }
            });
        };
        walk(copy);
        return text.split('\n').map(function (line) {
            return line.replace(/\s+/g, ' ').trim();
        }).filter(function (line) {
            return line !== '';
        }).join('\n');
    };

    // A small focus target, hidden until it has focus, so a screen reader says only its word ("Message closed") instead
    // of reading the page again
    var makeMarker = function (text, attribute) {
        var marker = document.createElement('span');
        marker.setAttribute('tabindex', '-1');
        marker.className = 'visually-hidden-focusable domainmod-focus-marker';
        if (attribute) marker.setAttribute(attribute, '');
        marker.textContent = text;
        return marker;
    };

    // Whether the focused element was reached with the keyboard (:focus-visible), so a close with the mouse leaves no
    // "Message closed" marker. A browser without :focus-visible keeps the marker.
    var keyboardFocus = function () {
        var focused = document.activeElement;
        if (!focused || focused === document.body) return false;
        try {
            return focused.matches(':focus-visible');
        } catch (error) {
            return true;
        }
    };

    var focusLost = function () {
        return !document.activeElement || document.activeElement === document.body;
    };

    var focusClosedMarker = function () {
        var home = document.querySelector('[data-domainmod-focus-home]');
        if (!home) return;
        home.querySelectorAll('[data-domainmod-closed-marker]').forEach(function (old) {
            old.remove();
        });
        var marker = makeMarker(label('closed'), 'data-domainmod-closed-marker');
        marker.addEventListener('blur', function () {
            marker.remove();
        });
        home.insertBefore(marker, home.firstChild);
        marker.focus();
    };

    // --- Toasts (main layout only) ---

    var region = function () {
        return document.querySelector('.domainmod-toasts');
    };

    var removeToast = function (toast) {
        var had_focus = toast.contains(document.activeElement);
        var instance = window.bootstrap && bootstrap.Toast ? bootstrap.Toast.getInstance(toast) : null;
        if (instance) instance.dispose();
        toast.remove();
        if (had_focus) focusClosedMarker();
    };

    DomainMOD.convertToasts = function (root) {
        var sources = Array.prototype.slice.call((root || document).querySelectorAll('[data-domainmod-toast]'));
        if (sources.length === 0) return;
        var container = region();
        if (!container || !window.bootstrap || !bootstrap.Toast) {
            // No toasts here: the successes stay in the page as ordinary messages
            sources.forEach(function (source) {
                source.removeAttribute('data-domainmod-toast');
                source.classList.remove('domainmod-toast-source');
            });
            return;
        }
        sources.forEach(function (source) {
            // Only ever one toast: an older one goes at once, without its hide animation
            container.querySelectorAll('.toast').forEach(removeToast);

            var toast = document.createElement('div');
            toast.className = 'toast domainmod-toast';
            var body = document.createElement('div');
            body.className = 'toast-body d-flex align-items-start gap-2';
            Array.prototype.slice.call(source.children).forEach(function (child) {
                if (child.classList.contains('btn-close')) child.setAttribute('data-bs-dismiss', 'toast');
                body.appendChild(child);
            });
            toast.appendChild(body);
            container.appendChild(toast);
            source.remove();

            var keyboard_close = false;
            toast.addEventListener('hide.bs.toast', function () {
                keyboard_close = toast.contains(document.activeElement) && keyboardFocus();
                dismissed.push(source);
            });
            toast.addEventListener('hidden.bs.toast', function () {
                var instance = bootstrap.Toast.getInstance(toast);
                if (instance) instance.dispose();
                toast.remove();
                if (keyboard_close && focusLost()) focusClosedMarker();
                schedulePlacement();
            });
            bootstrap.Toast.getOrCreateInstance(toast, {autohide: false}).show();
        });
        schedulePlacement();
    };

    var CONTROLS = 'a[href], button, input:not([type="hidden"]), select, textarea';

    // Keep the toast off the focused control: the other corner if that's clear, otherwise a thin bar across the top
    var overlaps = function (a, b) {
        return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
    };
    var setCompact = function (container, compact) {
        container.classList.toggle('domainmod-toasts-compact', compact);
        document.documentElement.classList.toggle('domainmod-toasts-compact', compact);
    };
    var setCorner = function (container, corner) {
        container.classList.toggle('top-0', corner === 'top');
        container.classList.toggle('bottom-0', corner === 'bottom');
    };
    var placement_pending = false;
    var place = function () {
        placement_pending = false;
        var container = region();
        if (!container) return;
        if (!container.querySelector('.toast')) {
            setCompact(container, false);
            return;
        }
        var focused = document.activeElement;
        if (!focused || focused === document.body || container.contains(focused)) return;
        var focus_box = focused.getBoundingClientRect();
        setCompact(container, false);
        // A whole area that has focus (the main content after a click on its background or the skip link, since it can
        // take focus) isn't a control the toast could hide, so the toast stays in its corner. A tall control, such as a
        // large text box, is still kept clear.
        var is_control = focused.matches(CONTROLS) || focused.isContentEditable;
        if (focused.hasAttribute('data-domainmod-focus-home') || (!is_control && focus_box.height > window.innerHeight / 2)) {
            setCorner(container, 'top');
            return;
        }
        var first = container.classList.contains('bottom-0') ? 'bottom' : 'top';
        var corners = [first, first === 'top' ? 'bottom' : 'top'];
        for (var i = 0; i < corners.length; i++) {
            setCorner(container, corners[i]);
            if (!overlaps(focus_box, container.getBoundingClientRect())) return;
        }
        setCorner(container, 'top');
        setCompact(container, true);
        // Still covered (a tall wrapped bar on a short window): scroll the control down to just below the bar, once per
        // newly focused control, so scrolling the page afterwards isn't pulled back
        var bar = container.getBoundingClientRect();
        focus_box = focused.getBoundingClientRect();
        if (focused !== scrolled_for && overlaps(focus_box, bar)) {
            scrolled_for = focused;
            window.scrollBy(0, focus_box.top - bar.bottom - 8);
        }
    };
    var scrolled_for = null;
    var schedulePlacement = function () {
        if (placement_pending) return;
        placement_pending = true;
        window.requestAnimationFrame(place);
    };

    // --- Closing a message ---

    document.addEventListener('close.bs.alert', function (event) {
        var alert = event.target;
        dismissed.push(alert);
        if (!alert.contains(document.activeElement) || !keyboardFocus()) return;
        alert.addEventListener('closed.bs.alert', function () {
            if (focusLost()) focusClosedMarker();
            schedulePlacement();
        }, {once: true});
    });

    // --- htmx results ---

    // The control that sent a request (a form's submit button, for a form), so focus can go back to it
    var sentBy = function (event) {
        var control = event.detail.requestConfig ? event.detail.requestConfig.elt : null;
        if (control && control.tagName === 'FORM') control = control.querySelector('[type="submit"], button:not([type])');
        return control || null;
    };
    var visibleText = function (element) {
        var copy = element.cloneNode(true);
        copy.querySelectorAll('.visually-hidden, [aria-hidden="true"]').forEach(function (hidden) {
            hidden.remove();
        });
        return copy.textContent.replace(/\s+/g, ' ').trim();
    };

    // A control the swap replaces (a month link in the renewal calendar, a comparison's Compare button) is noted before
    // the swap, so focus can go to the same control in the new content
    var replaced = new WeakMap();
    document.addEventListener('htmx:beforeSwap', function (event) {
        var control = sentBy(event);
        var target = event.detail.target;
        if (!control || !target || !event.detail.requestConfig || !target.contains(control)) return;
        replaced.set(event.detail.requestConfig, {
            target: target,
            id: control.id,
            tag: control.tagName,
            text: visibleText(control),
            index: Array.prototype.indexOf.call(target.querySelectorAll(CONTROLS), control)
        });
    });

    // The new copy of a replaced control: the same id, or else the same kind of control with the same words, or else
    // the control in the same place
    var replacement = function (event, element) {
        var old = event.detail.requestConfig ? replaced.get(event.detail.requestConfig) : null;
        if (!old || (element !== old.target && (!old.target.id || element.id !== old.target.id))) return null;
        var controls = Array.prototype.slice.call(element.querySelectorAll(CONTROLS));
        var match = null;
        if (old.id) match = controls.filter(function (control) { return control.id === old.id; })[0];
        if (!match) match = controls.filter(function (control) { return control.tagName === old.tag && visibleText(control) === old.text; })[0];
        if (!match && old.index !== -1 && controls.length) match = controls[Math.min(old.index, controls.length - 1)];
        return match || null;
    };

    var requestControl = function (event, element) {
        var control = sentBy(event);
        return control && control.isConnected ? control : replacement(event, element);
    };

    document.addEventListener('htmx:afterSettle', function (event) {
        var lost = focusLost();
        var element = event.detail.elt;
        if (!element || !element.querySelector) return;
        var result = element.matches('[data-domainmod-focus]') ? element : element.querySelector('[data-domainmod-focus]');
        var refocus = element.matches('[data-domainmod-refocus]') ? element : element.querySelector('[data-domainmod-refocus]');

        // Read first, then convert any toasts, then announce, so the order is the order on the page
        var texts = [];
        if (result) {
            var messages = Array.prototype.slice.call(result.querySelectorAll('.domainmod-message'));
            texts = messages.length ? messages.map(DomainMOD.messageText) : [DomainMOD.messageText(result)];
            DomainMOD.convertToasts(result);
        }
        texts.forEach(DomainMOD.announce);

        // Focus lost during the request (its button was disabled) goes back to the page's chosen control, or else to the
        // control that sent it, which htmx may not have re-enabled yet. The result itself was announced above.
        if (lost) {
            var control = refocus || requestControl(event, element);
            // Its tooltip stays closed, since a tooltip opening over the result would hide part of it
            var focusQuietly = function () {
                control.focus();
                var tooltip = window.bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(control) : null;
                if (tooltip) tooltip.hide();
            };
            if (control) {
                if (control.disabled) {
                    window.setTimeout(function () {
                        if (focusLost() && control.isConnected && !control.disabled) focusQuietly();
                    }, 0);
                } else {
                    focusQuietly();
                }
            }
        }
        schedulePlacement();
    });

    // --- Page load ---

    document.addEventListener('DOMContentLoaded', function () {
        announcer = document.querySelector('[data-domainmod-announcer]');

        var on_load = Array.prototype.slice.call(document.querySelectorAll('.domainmod-message')).map(function (message) {
            return {element: message, text: DomainMOD.messageText(message)};
        });
        DomainMOD.convertToasts(document);

        window.setTimeout(function () {
            ready = true;
            on_load.forEach(function (item) {
                if (dismissed.indexOf(item.element) === -1) DomainMOD.announce(item.text);
            });
            var later = held;
            held = [];
            later.forEach(DomainMOD.announce);
        }, READY_DELAY);

        document.addEventListener('focusin', schedulePlacement);
        // A control that's left and focused again can be scrolled clear of the toast again
        document.addEventListener('focusout', function () {
            scrolled_for = null;
        });
        document.addEventListener('scroll', schedulePlacement, {capture: true, passive: true});
        window.addEventListener('resize', schedulePlacement);
        document.addEventListener('toggle', schedulePlacement, true);
        var main = document.querySelector('[data-domainmod-focus-home]');
        if (main && window.ResizeObserver) new ResizeObserver(schedulePlacement).observe(main);
    });
})();

/*
 * /js/ssl-refresh.js
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
 * The SSL certificate edit page's in-place Refresh (see ssl/edit.php), built the same way as js/domain-refresh.js.
 * Refresh reads the certificate the saved name's site serves and saves the expiry date, status, and a note straight
 * away. This script:
 * - stops the refresh when the user has changed the name or domain and not saved, since those decide which site is
 *   checked,
 * - asks first when the user has changed one of the fields Refresh saves and not saved, since the refresh replaces them,
 * - locks those fields, the name, the domain, the date picker, and Save while the lookup runs (it can take up to 45
 *   seconds),
 * - updates the fields to the saved values from the result, and highlights the ones that changed.
 * Loaded by ssl/edit.php after the date picker's footer. Without JavaScript, Refresh is a normal post.
 */
(function () {
    'use strict';

    var refresh_form = document.querySelector('form[data-domainmod-refresh]');
    var result_area = document.getElementById('domainmod-refresh-result');
    if (!refresh_form || !result_area) return;

    var refresh_button = refresh_form.querySelector('button[type="submit"]');
    var date_field = document.querySelector('input[name="datepick"]');
    var edit_form = date_field ? date_field.form : null;
    if (!edit_form) return;

    // The fields Refresh saves
    var fields = {
        expiry_date: date_field,
        active: edit_form.querySelector('select[name="new_active"]'),
        notes: edit_form.querySelector('textarea[name="new_notes"]')
    };

    // The fields that decide which site Refresh checks, and their saved values (from the database, even after a failed
    // Save)
    var name_field = edit_form.querySelector('input[name="new_name"]');
    var domain_field = edit_form.querySelector('select[name="new_domain_id"]');
    var saved = document.querySelector('[data-domainmod-refresh-saved]');

    var currentValue = function (field) {
        if (!field) return '';
        return field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
    };

    // What each field held when the page was drawn (or after the last refresh), to tell an unsaved edit
    var rememberSaved = function (field) {
        if (field) field.setAttribute('data-domainmod-saved', currentValue(field));
    };
    Object.keys(fields).forEach(function (key) {
        rememberSaved(fields[key]);
    });

    // A page shown again after a failed Save holds the user's values, not the saved ones. This only applies to the
    // fields Refresh saves, since the name and domain are compared with their saved values above.
    var unsaved_marker = document.querySelector('[data-domainmod-refresh-unsaved]');

    var siteChanged = function () {
        if (!saved) return false;
        var saved_domain = saved.getAttribute('data-domain-id');
        // The saved domain is None (0) for a certificate with no domain, including one whose domain is gone, and such a
        // certificate refreshes its own hostname. A saved value that isn't in the menu has nothing to undo, so it never
        // stops a refresh.
        var domain_listed = domain_field !== null && Array.prototype.some.call(domain_field.options, function (option) {
            return option.value === saved_domain;
        });
        // a text field drops line breaks from its value, so a saved name holding one is compared without them
        return (name_field !== null && name_field.value !== saved.getAttribute('data-name').replace(/[\r\n]/g, ''))
            || (domain_listed && domain_field.value !== saved_domain);
    };

    var fieldName = function (field) {
        var label = field.id ? document.querySelector('label[for="' + field.id + '"]') : null;
        var name = field.getAttribute('aria-label') || field.name;
        if (label) {
            // The label's own words, without links or hints inside it (such as the notes' "view full notes")
            var copy = label.cloneNode(true);
            Array.prototype.forEach.call(copy.querySelectorAll('a, small, .form-text, .domainmod-css-normal-font-weight'), function (element) {
                element.remove();
            });
            name = copy.textContent;
        }
        // Without the format hint, the required-field star, or the question mark
        return name.replace(/\(.*?\)|\[.*?\]/g, '').replace(/[*?]/g, '').replace(/\s+/g, ' ').trim();
    };

    var editedFields = function () {
        return Object.keys(fields).filter(function (key) {
            var field = fields[key];
            if (!field) return false;
            if (unsaved_marker) return true;
            return currentValue(field) !== field.getAttribute('data-domainmod-saved');
        }).map(function (key) {
            return fieldName(fields[key]);
        });
    };

    var joinNames = function (names) {
        if (window.Intl && Intl.ListFormat) {
            return new Intl.ListFormat(document.documentElement.lang || undefined, {type: 'conjunction'}).format(names);
        }
        return names.join(', ');
    };

    // Ask before replacing unsaved edits, or say why a refresh can't run yet
    var confirm_modal = document.getElementById('domainmod-refresh-confirm');
    var pending_request = null;
    var anyway_button = confirm_modal ? confirm_modal.querySelector('[data-domainmod-refresh-anyway]') : null;
    var cancel_button = confirm_modal ? confirm_modal.querySelector('[data-domainmod-refresh-cancel]') : null;
    var close_button = confirm_modal ? confirm_modal.querySelector('[data-domainmod-refresh-close]') : null;
    if (confirm_modal) {
        anyway_button.addEventListener('click', function () {
            var issue = pending_request;
            pending_request = null;
            bootstrap.Modal.getOrCreateInstance(confirm_modal).hide();
            if (issue) issue(true);
        });
        confirm_modal.addEventListener('hidden.bs.modal', function () {
            pending_request = null;
            if (refresh_button) refresh_button.focus();
        });
    }

    // The dialog with "Refresh anyway" and Cancel, or with only Close
    var showDialog = function (message, can_refresh) {
        anyway_button.classList.toggle('d-none', !can_refresh);
        cancel_button.classList.toggle('d-none', !can_refresh);
        close_button.classList.toggle('d-none', can_refresh);
        confirm_modal.querySelector('[data-domainmod-refresh-message]').textContent = message;
        bootstrap.Modal.getOrCreateInstance(confirm_modal).show();
    };

    refresh_form.addEventListener('htmx:confirm', function (event) {
        if (!confirm_modal) return;

        // Refresh always checks the saved name's site, so with an unsaved name or domain it would check a different site
        // from the one the form shows, and nothing is sent
        if (siteChanged()) {
            event.preventDefault();
            pending_request = null;
            showDialog(confirm_modal.getAttribute('data-name-message'), false);
            return;
        }

        var edited = editedFields();
        if (!edited.length) return;
        event.preventDefault();
        pending_request = event.detail.issueRequest;
        showDialog(confirm_modal.getAttribute('data-message').replace('%s', joinNames(edited)), true);
    });

    // Lock the fields Refresh saves, the name, the domain, the date picker, and Save while the lookup runs, and put them
    // back afterwards
    var save_button = edit_form.querySelector('button[type="submit"]');
    var locked = null;
    var lockFields = function () {
        locked = [];
        Object.keys(fields).map(function (key) {
            return fields[key];
        }).concat([name_field, domain_field]).forEach(function (field) {
            if (!field) return;
            if (field.tagName === 'TEXTAREA' || field === name_field) {
                locked.push([field, 'readOnly', field.readOnly]);
                field.readOnly = true;
            } else {
                locked.push([field, 'disabled', field.disabled]);
                field.disabled = true;
            }
        });
        var picker = date_field._flatpickr;
        if (picker) {
            picker.close();
            locked.push([picker, 'clickOpens', picker.config.clickOpens]);
            picker.set('clickOpens', false);
            if (picker.mobileInput) {
                locked.push([picker.mobileInput, 'disabled', picker.mobileInput.disabled]);
                picker.mobileInput.disabled = true;
            }
        }
        if (save_button) {
            locked.push([save_button, 'disabled', save_button.disabled]);
            save_button.disabled = true;
        }
        edit_form.setAttribute('aria-busy', 'true');
    };
    var unlockFields = function () {
        if (!locked) return;
        locked.forEach(function (item) {
            if (item[1] === 'clickOpens') {
                item[0].set('clickOpens', item[2]);
            } else {
                item[0][item[1]] = item[2];
            }
        });
        locked = null;
        edit_form.removeAttribute('aria-busy');
    };
    refresh_form.addEventListener('htmx:beforeRequest', lockFields);
    // The tooltip stays open after a click or tap (and opens again when the button comes back on under the pointer),
    // where it would cover the result, so it's closed when the lookup starts and again once the result is in
    var hideTooltip = function () {
        var tooltip = refresh_button ? bootstrap.Tooltip.getInstance(refresh_button) : null;
        if (tooltip) tooltip.hide();
    };
    refresh_form.addEventListener('htmx:beforeRequest', hideTooltip);
    result_area.addEventListener('htmx:afterSettle', hideTooltip);
    refresh_form.addEventListener('htmx:afterRequest', unlockFields);
    // A restore or upgrade's 503 message, or a conflict's 409 (plain text, see _includes/restore-gate.inc.php), is shown
    // in the result area.
    // htmx fires htmx:beforeSwap on the target, so it's handled here, and stopped before the page's own handler on the
    // document (in the footer) adds its own alert as well.
    result_area.addEventListener('htmx:beforeSwap', function (event) {
        var xhr = event.detail ? event.detail.xhr : null;
        // Only the gate's own answer (plain text, marked with X-DomainMOD-Refused): another 503 or 409, such as a
        // proxy's page, gets the general message
        if (!xhr || (xhr.status !== 503 && xhr.status !== 409) || !xhr.responseText
            || xhr.getResponseHeader('X-DomainMOD-Refused') !== '1'
            || (xhr.getResponseHeader('Content-Type') || '').indexOf('text/plain') !== 0) return;
        event.detail.shouldSwap = false;
        event.detail.isError = false;
        event.stopPropagation();
        var message = document.createElement('div');
        message.className = 'alert alert-danger py-2 mb-0';
        message.setAttribute('role', 'alert');
        message.textContent = xhr.responseText;
        result_area.replaceChildren(message);
    });

    // Clear a field's mistake (from a failed Save), since it now holds the saved value
    var clearError = function (field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        var kept = described.filter(function (id) {
            if (!/-error$/.test(id)) return true;
            var error = document.getElementById(id);
            if (error) error.textContent = '';
            return false;
        });
        if (kept.length) {
            field.setAttribute('aria-describedby', kept.join(' '));
        } else {
            field.removeAttribute('aria-describedby');
        }
    };

    var highlight = function (field) {
        field.classList.remove('domainmod-refreshed');
        void field.offsetWidth;
        field.classList.add('domainmod-refreshed');
        window.setTimeout(function () {
            field.classList.remove('domainmod-refreshed');
        }, 2000);
    };

    var setExpiryDate = function (value) {
        var picker = date_field._flatpickr;
        if (!picker) {
            date_field.value = value;
            return;
        }
        // The picker only allows a range of years, while Refresh saves any valid date, so widen it to fit first
        var date = picker.parseDate(value, 'Y-m-d');
        if (date && picker.config.minDate && date < picker.config.minDate) picker.set('minDate', date);
        if (date && picker.config.maxDate && date > picker.config.maxDate) picker.set('maxDate', date);
        // On phones Flatpickr uses the browser's own date field, whose limits it only sets when it starts
        if (picker.mobileInput) {
            if (picker.config.minDate) picker.mobileInput.min = picker.formatDate(picker.config.minDate, 'Y-m-d');
            if (picker.config.maxDate) picker.mobileInput.max = picker.formatDate(picker.config.maxDate, 'Y-m-d');
        }
        picker.setDate(value, false);
    };

    result_area.addEventListener('htmx:afterSwap', function () {
        var data_element = document.getElementById('domainmod-refresh-data');
        if (!data_element) return;

        var values;
        try {
            values = JSON.parse(data_element.getAttribute('data-values'));
        } catch (error) {
            return;
        }
        var changed = values.changed || [];

        // Fields locked during the lookup come back first, so the new values aren't overwritten on unlock
        unlockFields();

        Object.keys(fields).forEach(function (key) {
            var field = fields[key];
            if (!field || !(key in values)) return;
            var wanted = String(values[key]);
            var differs = currentValue(field) !== wanted;
            if (changed.indexOf(key) === -1 && !differs) return;

            if (key === 'expiry_date') {
                setExpiryDate(values.expiry_date);
            } else {
                field.value = wanted;
            }

            // Read back from the control itself (a textarea turns CRLF into LF), so a later check doesn't see an edit
            rememberSaved(field);
            clearError(field);
            if (changed.indexOf(key) !== -1) highlight(field);
        });

        if (unsaved_marker) {
            unsaved_marker.remove();
            unsaved_marker = null;
        }
    });
})();

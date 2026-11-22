/*
 * /js/watch-check.js
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
 * The watched domain page's in-place Check Now (see watch/edit.php), as js/domain-refresh.js does for a domain's
 * Refresh: htmx posts the form and puts the result under the button, with the Current State and Change History sections
 * redrawn by out-of-band swaps. This script shows a restore or upgrade's 503 message in the result area, and moves focus
 * to the result once it's in. Without JavaScript, Check Now is a normal post.
 */
(function () {
    'use strict';

    var check_form = document.querySelector('form[data-domainmod-watch-check]');
    var result_area = document.getElementById('domainmod-watch-check-result');
    if (!check_form || !result_area) return;

    // A restore or upgrade's 503 message (plain text, see _includes/restore-gate.inc.php) is shown in the result area.
    // htmx fires htmx:beforeSwap on the target, so it's handled here, before the page's own handler on the document (in
    // the footer) replaces any answer that isn't a result with its general "That didn't work" message.
    result_area.addEventListener('htmx:beforeSwap', function (event) {
        var xhr = event.detail ? event.detail.xhr : null;
        // Only the gate's own answer (plain text): another 503, such as a proxy's HTML page, gets the general message
        if (!xhr || xhr.status !== 503 || !xhr.responseText
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

    // The result is announced (the area is a live region), and keyboard users land on it
    result_area.addEventListener('htmx:afterSwap', function () {
        var message = result_area.firstElementChild;
        if (!message) return;
        message.setAttribute('tabindex', '-1');
        message.focus();
    });
})();

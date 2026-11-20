/*
 * /js/copy-command.js
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
 * The Copy buttons beside the Upgrade page's commands (see DomainMOD\UpgradeGuide::commandBlock()). Each button is
 * hidden until this script shows it, so without JavaScript there's no button that does nothing, and the command can
 * still be selected and copied by hand. Copying uses the Clipboard API where the browser allows it (a secure page),
 * and otherwise selects the command and copies the selection. The button says it's copied for a moment, and the status
 * after it tells screen readers. If neither works, the command is left selected and the button says to copy it. Loaded by admin/upgrade/index.php after the footer.
 */
(function () {
    'use strict';

    var copySelection = function (element) {
        var range = document.createRange();
        range.selectNodeContents(element);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }
        // Left selected when it couldn't be copied, so it can be copied by hand
        if (copied) selection.removeAllRanges();
        return copied;
    };

    // Each button's timer, so a second click restarts the moment rather than having the first click's timer end it
    var timers = new WeakMap();

    // The button and its status say what happened (attribute is the message's data attribute) for a moment
    var showResult = function (button, attribute) {
        var label = button.querySelector('span');
        var status = button.nextElementSibling;
        var message = button.getAttribute(attribute);
        if (!label.hasAttribute('data-domainmod-label')) label.setAttribute('data-domainmod-label', label.textContent);
        label.textContent = message;
        if (status) status.textContent = message;
        if (timers.has(button)) window.clearTimeout(timers.get(button));
        timers.set(button, window.setTimeout(function () {
            label.textContent = label.getAttribute('data-domainmod-label');
            if (status) status.textContent = '';
            timers.delete(button);
        }, 2000));
    };

    // The latest click on any Copy button. Copying can change the page's selection, so an earlier click's answer that
    // arrives late (from this button or another) is ignored.
    var clicks = 0;

    document.querySelectorAll('button[data-domainmod-copy]').forEach(function (button) {
        var command = document.getElementById(button.getAttribute('data-domainmod-copy'));
        if (!command) return;
        button.hidden = false;

        button.addEventListener('click', function () {
            var text = command.textContent;
            var click = ++clicks;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () {
                    if (click === clicks) showResult(button, 'data-domainmod-copied');
                }, function () {
                    if (click !== clicks) return;
                    showResult(button, copySelection(command) ? 'data-domainmod-copied' : 'data-domainmod-copy-failed');
                });
            } else {
                showResult(button, copySelection(command) ? 'data-domainmod-copied' : 'data-domainmod-copy-failed');
            }
        });
    });
})();

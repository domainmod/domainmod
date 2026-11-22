/*
 * /js/watch-list.js
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
 * The Watch List's bulk actions (see watch/index.php): the heading's tick box selects every row on the page, it's
 * cleared when the table draws another page, and the reason menu is only offered for the Change the Reason action.
 * Without JavaScript, each row's tick box still works and the reason menu is always shown.
 */
(function () {
    'use strict';

    var table = document.getElementById('watch-main');
    var action = document.getElementById('watch-bulk-action');
    var reason = document.getElementById('watch-bulk-reason');

    if (action && reason) {
        var reason_box = reason.parentElement;
        var showReason = function () {
            reason_box.hidden = action.value !== 'reason';
        };
        action.addEventListener('change', showReason);
        showReason();
    }

    if (!table) return;

    var rowBoxes = function () {
        return table.querySelectorAll('tbody input[name="wids[]"]');
    };

    table.addEventListener('change', function (event) {
        var box = event.target;
        if (box.matches('[data-domainmod-select-all]')) {
            Array.prototype.forEach.call(rowBoxes(), function (row_box) {
                row_box.checked = box.checked;
            });
        } else if (box.matches('input[name="wids[]"]')) {
            var all = table.querySelector('[data-domainmod-select-all]');
            if (all) all.checked = Array.prototype.every.call(rowBoxes(), function (row_box) {
                return row_box.checked;
            });
        }
    });

    // A new page of rows starts with nothing selected
    if (window.jQuery) {
        window.jQuery(table).on('draw.dt', function () {
            var all = table.querySelector('[data-domainmod-select-all]');
            if (all) all.checked = false;
        });
    }
})();

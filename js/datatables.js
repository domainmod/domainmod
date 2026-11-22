/*
 * /js/datatables.js
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

// Starts a list's DataTable without jQuery (the settings files in _includes/settings/ call it from the footer's script,
// which runs after this file and the DataTables files load):
// - It does nothing and returns null when the table isn't on the page, as a page prints its table options even when it
//   doesn't draw the table (an empty list, or the domain edit page without Data Warehouse data).
// - When a table changes page, the window jumps to the table's top, so the new rows start in view. The first draw
//   doesn't move anything. Pass {scrollOnPage: false} as the third argument for a table that shouldn't jump.
// - A drawCallback in the options still runs first, on every draw.
// - A table with the dense list style (domainmod-dense-table, see css/base.css) that sits straight inside the page's
//   scroll box (domainmod-list-scroll) gets a box of its own from DataTables, around just the table (its dom option
//   names it), so the search and paging stay outside it. The page's box, there for when JavaScript is off, then comes
//   off, and its keyboard focus, role, and name move to the new box. A draw with no matches scrolls the box back to the
//   start, where the "no matches" message is.
// It returns the table's DataTables API instance.
// In right-to-left languages, Currency::format() isolates each amount as left-to-right text with U+2066 and U+2069
// around it, so it keeps its order on screen. DataTables doesn't count the mark as part of a number, so on those pages an amount column
// gets its own type: the marks are taken out, then DataTables' own formatted number checks and sorting are used, and
// the column is styled as numeric, as it is in left-to-right languages.
(function () {
    if (document.documentElement.getAttribute('dir') !== 'rtl') return;
    if (typeof DataTable === 'undefined' || typeof DataTable.type !== 'function') return;
    var plain = DataTable.type('num-fmt');
    var html = DataTable.type('html-num-fmt');
    if (!plain || !html || !plain.detect || !html.detect || !html.order) return;
    var unmark = function (data) {
        return typeof data === 'string' ? data.replace(/[\u2066\u2069]/g, '') : data;
    };
    var isAmount = function (check, data, settings) {
        var value = unmark(data);
        return !!(plain.detect[check](value, settings) || html.detect[check](value, settings));
    };
    DataTable.type('domainmod-amount', {
        className: 'dt-type-numeric',
        detect: {
            allOf: function (data, settings) {
                return isAmount('allOf', data, settings);
            },
            oneOf: function (data, settings) {
                return typeof data === 'string' && data.indexOf('\u2066') !== -1 && isAmount('oneOf', data, settings);
            }
        },
        order: {
            pre: function (data, settings) {
                return html.order.pre(unmark(data), settings);
            }
        }
    });
}());

function domainmodTable(selector, options, settings) {
    var table = document.querySelector(selector);
    if (!table) return null;

    options = options || {};
    var page_box = table.classList.contains('domainmod-dense-table')
        && table.parentNode.classList.contains('domainmod-list-scroll') ? table.parentNode : null;
    var scrollOnPage = !(settings && settings.scrollOnPage === false);
    var callerDraw = options.drawCallback;
    var lastStart = null;

    options.drawCallback = function (dtSettings) {
        if (typeof callerDraw === 'function') callerDraw.call(this, dtSettings);
        var start = new DataTable.Api(dtSettings).page.info().start;
        if (lastStart !== null && start !== lastStart && scrollOnPage) {
            // A jump, not a glide, whatever scroll-behavior the theme sets
            window.scrollTo({top: table.getBoundingClientRect().top + window.scrollY, behavior: 'instant'});
        }
        lastStart = start;
    };

    var api = new DataTable(table, options);

    var box = table.parentNode;
    if (page_box && box !== page_box && box.classList.contains('domainmod-list-scroll')) {
        ['tabindex', 'role', 'aria-label'].forEach(function (name) {
            if (page_box.hasAttribute(name)) box.setAttribute(name, page_box.getAttribute(name));
        });
        page_box.replaceWith.apply(page_box, Array.prototype.slice.call(page_box.childNodes));
        api.on('draw', function () {
            if (api.page.info().recordsDisplay === 0) box.scrollLeft = 0;
        });
    }

    return api;
}

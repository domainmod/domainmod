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
// It returns the table's DataTables API instance.
function domainmodTable(selector, options, settings) {
    var table = document.querySelector(selector);
    if (!table) return null;

    options = options || {};
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

    return new DataTable(table, options);
}

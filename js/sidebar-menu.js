/*
 * /js/sidebar-menu.js
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
 * This script is for the sidebar's Assets, Settings, Maintenance, and Administration items (see menu-main.inc.php).
 * Each one's name is a link to its landing page, and its arrow is a separate button that opens and closes its submenu
 * in place. The footer loads it at the end of every page with the sidebar, after AdminLTE's script.
 *
 * AdminLTE's treeview opens or closes an item on any click inside it that isn't in its submenu, so the arrow button
 * needs nothing more. Its handler would also open or close the submenu while the name's link is leaving the page, so
 * that toggle is cancelled here, with the cancelable expand and collapse events AdminLTE sends first. AdminLTE also
 * marks the item's link as the control that opens the submenu (aria-expanded), which is the button's job here, so the
 * button's aria-expanded follows the submenu and the link's is removed whenever AdminLTE sets it.
 */
(function () {
    var menu = document.getElementById('navigation');
    if (!menu) return;

    // The item whose name was just clicked. This runs before AdminLTE's handler, which listens on the whole page.
    var leaving = null;
    menu.addEventListener('click', function (event) {
        var link = event.target instanceof Element ? event.target.closest('.domainmod-menu-parent > .nav-link') : null;
        leaving = link ? link.parentElement : null;
    });

    menu.querySelectorAll('.domainmod-menu-parent').forEach(function (item) {
        var link = item.querySelector(':scope > .nav-link');
        var toggle = item.querySelector(':scope > .domainmod-menu-toggle');
        if (!link || !toggle) return;

        var cancelIfLeaving = function (event) {
            if (leaving !== item) return;
            leaving = null;
            event.preventDefault();
        };
        item.addEventListener('expand.lte.treeview', cancelIfLeaving);
        item.addEventListener('collapse.lte.treeview', cancelIfLeaving);

        var sync = function () {
            toggle.setAttribute('aria-expanded', item.classList.contains('menu-open') ? 'true' : 'false');
            if (link.hasAttribute('aria-expanded')) link.removeAttribute('aria-expanded');
        };
        new MutationObserver(sync).observe(item, {attributes: true, attributeFilter: ['class']});
        new MutationObserver(sync).observe(link, {attributes: true, attributeFilter: ['aria-expanded']});
        sync();
    });
})();

/*
 * /js/step-tooltips.js
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

// The installer's step tooltips (Layout::stepIndicator()): each step that links shows the name of the step it goes to.
// It opens on hover or focus, stays open while the pointer is over the link or the tooltip itself, and Escape closes
// it, the same as the tooltips inside DomainMOD (footer.inc.php), which the installer's own footer doesn't load. The
// link already holds the step's name, so the tooltip isn't also linked as its description.
document.querySelectorAll('.domainmod-step-link[data-bs-toggle="tooltip"]').forEach(function (link) {
    // No fade, so showing it again straight after hiding can't race Bootstrap's hide transition
    var tooltip = new bootstrap.Tooltip(link, {trigger: 'manual', animation: false});
    var hideUnlessInUse = function () {
        setTimeout(function () {
            var tip_in_use = tooltip.tip && tooltip.tip.matches(':hover');
            if (!link.matches(':hover') && document.activeElement !== link && !tip_in_use) tooltip.hide();
        }, 100);
    };
    // Bootstrap closes a manual tooltip that's shown again while already open, so it's only shown when closed
    var is_open = false;
    var showIfClosed = function () {
        if (!is_open) tooltip.show();
    };
    link.addEventListener('mouseenter', showIfClosed);
    link.addEventListener('focus', showIfClosed);
    link.addEventListener('hidden.bs.tooltip', function () { is_open = false; });
    link.addEventListener('mouseleave', hideUnlessInUse);
    link.addEventListener('blur', hideUnlessInUse);
    link.addEventListener('shown.bs.tooltip', function () {
        is_open = true;
        link.removeAttribute('aria-describedby');
        if (tooltip.tip) tooltip.tip.addEventListener('mouseleave', hideUnlessInUse);
    });
});
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.domainmod-step-link[data-bs-toggle="tooltip"]').forEach(function (link) {
        var tooltip = bootstrap.Tooltip.getInstance(link);
        if (tooltip) tooltip.hide();
    });
});

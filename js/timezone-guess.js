/*
 * /js/timezone-guess.js
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

// Pre-selects the browser's own time zone on the installer's time zone step, but only when no time zone has been
// chosen yet (the page then adds a hidden marker naming the menu) and only when that time zone is on the list. It
// goes through the searchable menu (js/search-select.js) when there is one. Without JavaScript nothing changes.
//
// Browsers report the current names for a few time zones that the list still has under their older names.
var timezoneGuessAliases = {
    'Europe/Kyiv': 'Europe/Kiev',
    'Asia/Yangon': 'Asia/Rangoon',
    'Pacific/Kanton': 'Pacific/Enderbury',
    'America/Nuuk': 'America/Godthab'
};
document.querySelectorAll('[data-domainmod-guess-timezone]').forEach(function (marker) {
    var select = document.querySelector('select[name="' + marker.getAttribute('data-domainmod-guess-timezone') + '"]');
    if (!select) return;
    var zone = '';
    try {
        zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (error) {
        return;
    }
    if (timezoneGuessAliases[zone]) zone = timezoneGuessAliases[zone];
    var listed = Array.prototype.some.call(select.options, function (option) {
        return option.value === zone;
    });
    if (zone === '' || !listed) return;
    if (select.tomselect) {
        select.tomselect.setValue(zone, true);
    } else {
        select.value = zone;
    }
});

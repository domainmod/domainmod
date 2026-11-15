/*
 * /js/search-select.js
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

// The menus you can search (Form::showDropdownTop() with $searchable): typing narrows a long list, such as the
// installer's currencies and time zones. Every option stays available (Tom Select shows only 50 by default). Without
// JavaScript the plain menu works as before.
//
// Matching is plain and predictable: an option matches when its name contains what was typed, ignoring case and
// treating underscores as spaces (so "los angeles" finds America/Los_Angeles). Matches at the start of a word come
// first. Tom Select's own scoring is looser, and listed Cancun above Vancouver for "vanc". The match is highlighted
// the same way, since Tom Select's own highlighting doesn't treat underscores as spaces.
var searchSelectText = function (text) {
    return String(text).toLowerCase().replace(/_/g, ' ');
};
var searchSelectHighlight = function (tom_select) {
    var typed = searchSelectText(tom_select.inputValue()).trim();
    tom_select.dropdown_content.querySelectorAll('.option').forEach(function (option) {
        var text = option.textContent;
        option.textContent = text;
        var name = searchSelectText(text);
        var at = typed === '' ? -1 : name.indexOf(typed);
        // A name whose lowercase form has a different length can't be matched up character by character, so it isn't
        // highlighted
        if (at < 0 || name.length !== text.length) return;
        var match = document.createElement('span');
        match.className = 'highlight';
        match.textContent = text.substr(at, typed.length);
        option.textContent = '';
        option.append(text.slice(0, at), match, text.slice(at + typed.length));
    });
};
document.querySelectorAll('select.domainmod-search-select').forEach(function (select) {
    var tom_select = new TomSelect(select, {
        maxOptions: null,
        highlight: false,
        // The translated message from the page (Form::showDropdownTop()), in place of Tom Select's English one
        render: {
            no_results: function () {
                var message = document.createElement('div');
                message.className = 'no-results';
                message.textContent = select.getAttribute('data-no-results') || '';
                return message;
            }
        },
        score: function (search) {
            var typed = searchSelectText(search).trim();
            return function (option) {
                var name = searchSelectText(option.text);
                if (typed === '') return 1;
                var at = name.indexOf(typed);
                if (at < 0) return 0;
                return (at === 0 || /[\s\/(-]/.test(name.charAt(at - 1))) ? 2 : 1;
            };
        }
    });
    tom_select.hook('after', 'refreshOptions', function () {
        searchSelectHighlight(tom_select);
    });
});

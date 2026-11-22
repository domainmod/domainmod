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
// installer's currencies and time zones, or a domain's Hosting Service. Every option stays available (Tom Select shows
// only 50 by default). Without JavaScript the plain menu works as before.
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
    // Tom Select moves the label's "for" to its own box, so the label is found first, then names the hidden real menu
    // as well as Tom Select's box (below)
    var label = document.querySelector('label[for="' + select.id + '"]');
    var tom_select = new TomSelect(select, {
        maxOptions: null,
        highlight: false,
        render: {
            // Each option in the open list, and the chosen one in the box, takes its direction from its own text
            // (dir="auto"), so in a right-to-left language a left-to-right name, such as a currency or time zone,
            // starts at the left and wraps or is cut short the same way it reads. The chosen one has room beside the
            // arrow, from the right-to-left padding rule in css/base.css (.ts-wrapper.form-select.rtl .ts-control).
            option: function (data) {
                var option = document.createElement('div');
                option.dir = 'auto';
                option.textContent = data.text;
                return option;
            },
            item: function (data) {
                var item = document.createElement('div');
                item.dir = 'auto';
                item.textContent = data.text;
                return item;
            },
            // The translated message from the page (Form::showDropdownTop()), in place of Tom Select's English one
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
    // While something is typed, the chosen option is hidden under the search box, which lies over it (css/base.css), so
    // the menu keeps one height when it gains or loses focus
    var searchSelectTyping = function () {
        tom_select.wrapper.classList.toggle('domainmod-search-typing', tom_select.inputValue() !== '');
    };
    tom_select.control_input.addEventListener('input', searchSelectTyping);
    ['item_add', 'clear', 'blur', 'dropdown_close'].forEach(function (event) {
        tom_select.on(event, searchSelectTyping);
    });
    if (label) {
        if (!label.id) label.id = select.id + '-label';
        select.setAttribute('aria-labelledby', label.id);
        tom_select.control_input.setAttribute('aria-labelledby', label.id);
    }
    // A mistake marked on the real menu (Form::errorAttributes()) is marked on Tom Select's box too, so a screen reader
    // still reads it as invalid, with its message
    ['aria-invalid', 'aria-describedby'].forEach(function (attribute) {
        if (select.hasAttribute(attribute)) tom_select.control_input.setAttribute(attribute, select.getAttribute(attribute));
    });
});

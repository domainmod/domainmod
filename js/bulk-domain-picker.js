/*
 * /js/bulk-domain-picker.js
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

// The Bulk Updater's domain picker (Form::showDomainPicker()): a search box, with Tom Select, that finds domains as you
// type (domains/name-search.php) and adds the one picked to the domain list as a new line. The list stays the one field
// the form submits, so typing and pasting work as before, and the server checks the list as it always has. A count
// under the list (Form::showDomainListCount()) follows it as lines are picked, typed, or pasted. Without JavaScript,
// both stay hidden and the page is just the list.
//
// The script does the searching itself rather than through Tom Select's load option, which caches the searches it has
// started and counts the loads it expects to finish. Each change of the text is a new search, with its own number: it
// cancels any search still waiting or in flight, and an answer counts only while its number is still the current one,
// so nothing from an earlier search can be shown or picked once the text has changed.
(function () {
    'use strict';

    var WAIT = 300;

    // A line of the list as the server compares it, near enough: trimmed, in lowercase, without one trailing dot
    var lineKey = function (line) {
        var key = line.trim().toLowerCase();
        return key.slice(-1) === '.' ? key.slice(0, -1) : key;
    };

    var listLines = function (list) {
        return list.value.split(/\r\n|\r|\n/);
    };

    document.querySelectorAll('select.domainmod-domain-picker').forEach(function (select) {
        var field = select.closest('.domainmod-domain-picker-field');
        var list = document.getElementById(select.getAttribute('data-list') || '');
        var more = field ? field.querySelector('.domainmod-domain-picker-more') : null;
        var count = list ? document.querySelector('.domainmod-domain-list-count[data-list="' + list.id + '"]') : null;
        if (!field || !list || !more || typeof TomSelect === 'undefined') return;

        var text = function (name) {
            return select.getAttribute('data-' + name) || '';
        };
        var withDomain = function (message, domain) {
            return message.replace('%s', function () {
                return domain;
            });
        };
        var announce = function (message) {
            if (window.DomainMOD && DomainMOD.announce) DomainMOD.announce(message);
        };

        // Tom Select moves the label's "for" to its own box, so the label is found first
        var label = document.querySelector('label[for="' + select.id + '"]');
        var help_id = select.getAttribute('aria-describedby') || '';

        // The search's state, for the dropdown's message: '' (nothing typed), 'searching', 'results', 'none' (no
        // matches), 'cut-off' (no usable match before the read's limit), or 'error' (with error_text)
        var state = '';
        var error_text = '';
        var last_query = '';
        var search_number = 0;
        var timer = null;
        var controller = null;
        var composing = false;

        var tom_select = new TomSelect(select, {
            maxOptions: null,
            create: false,
            highlight: false,
            hideSelected: true,
            refreshThrottle: 0,
            valueField: 'domain',
            labelField: 'domain',
            searchField: ['domain'],
            // The server has already matched and ordered the results
            score: function () {
                return function () {
                    return 1;
                };
            },
            render: {
                // The name, kept left to right in a right-to-left language, and its status, greyed. A screen reader
                // reads the two with the page's translated separator between them ("example.com, Expired").
                option: function (data, escape) {
                    var status = data.status || '';
                    var spoken = text('option').replace('%1$s', function () {
                        return data.domain;
                    }).replace('%2$s', function () {
                        return status;
                    });
                    return '<div aria-label="' + escape(spoken) + '"><bdi dir="ltr" class="domainmod-domain-picker-name">'
                        + escape(data.domain) + '</bdi> <span class="domainmod-domain-picker-status">' + escape(status)
                        + '</span></div>';
                },
                no_results: function () {
                    var message = '';
                    if (state === 'searching') message = text('searching');
                    else if (state === 'none') message = text('no-results');
                    else if (state === 'cut-off') message = text('cut-off');
                    else if (state === 'error') message = error_text;
                    if (message === '') return null;
                    var element = document.createElement('div');
                    element.className = 'no-results';
                    element.textContent = message;
                    return element;
                }
            }
        });
        var input = tom_select.control_input;

        // The more-matches line goes at the foot of the open dropdown, outside its list of options, and is part of the
        // box's description only while it shows
        tom_select.dropdown.appendChild(more);
        var showMore = function (show) {
            more.hidden = !show;
            input.setAttribute('aria-describedby', help_id + (show ? ' ' + more.id : ''));
        };

        var refreshIfOpen = function () {
            if (tom_select.isOpen) tom_select.refreshOptions(false);
        };

        // Forgets the current search: its wait, its request, its results, and its more-matches line
        var reset = function () {
            window.clearTimeout(timer);
            timer = null;
            search_number++;
            if (controller) controller.abort();
            controller = null;
            tom_select.clearOptions();
            showMore(false);
            state = '';
        };

        var finish = function (message) {
            announce(message);
            tom_select.refreshOptions(true);
        };

        var fail = function (message) {
            state = 'error';
            error_text = message;
            finish(message);
        };

        var answer = function (status, body) {
            if (status === 200 && body && Array.isArray(body.results)) {
                var results = body.results.filter(function (result) {
                    return result && typeof result.domain === 'string';
                });
                var has_more = body.more === true;
                if (results.length) {
                    state = 'results';
                    tom_select.addOptions(results);
                    showMore(has_more);
                    // A list of results isn't announced (the options tell a screen reader how many there are), but the
                    // more-matches line is, since it isn't read reliably when it appears
                    if (has_more) announce(more.textContent);
                    tom_select.refreshOptions(true);
                } else {
                    state = has_more ? 'cut-off' : 'none';
                    finish(text(state === 'cut-off' ? 'cut-off' : 'no-results'));
                }
            } else if ((status === 403 || status === 503) && body && typeof body.error === 'string' && body.error !== '') {
                // A user made read-only, or the restore gate's refusal (a restore under way, or DomainMOD busy)
                fail(body.error);
            } else if (status === 401 && body && body.signin) {
                fail(text('signin'));
            } else {
                fail(text('error'));
            }
        };

        var send = function (query, number) {
            controller = new AbortController();
            var url = text('search-url') + '?' + new URLSearchParams({q: query}).toString();
            fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}, signal: controller.signal})
                .then(function (response) {
                    return response.json().then(function (body) {
                        return {status: response.status, body: body};
                    }, function () {
                        return {status: response.status, body: null};
                    });
                })
                .then(function (reply) {
                    if (number !== search_number) return;
                    controller = null;
                    answer(reply.status, reply.body);
                }, function () {
                    if (number !== search_number) return;
                    controller = null;
                    fail(text('error'));
                });
        };

        // Called whenever the box's text may have changed: a new text starts a new search, after a pause in typing
        var syncQuery = function () {
            if (composing) return;
            var query = input.value.trim();
            if (query === last_query) return;
            last_query = query;
            reset();
            if (query !== '') state = 'searching';
            refreshIfOpen();
            if (query === '') return;
            var number = search_number;
            timer = window.setTimeout(function () {
                timer = null;
                send(query, number);
            }, WAIT);
        };

        // Typing, before Tom Select's own handler (so it shows "Searching…" from the start), and the text Tom Select
        // sets itself (it empties the box after a pick, on Escape, and when the box closes)
        tom_select.wrapper.addEventListener('input', function (event) {
            if (event.target === input) syncQuery();
        }, true);
        input.addEventListener('update', syncQuery);

        // Text being composed with an input method is left to it: its keys never reach Tom Select (so Enter that confirms
        // a candidate never picks a result), and no search runs until the text is committed
        tom_select.wrapper.addEventListener('keydown', function (event) {
            if (event.isComposing || event.keyCode === 229) event.stopPropagation();
        }, true);
        input.addEventListener('compositionstart', function () {
            composing = true;
            reset();
            refreshIfOpen();
        });
        input.addEventListener('compositionend', function () {
            composing = false;
            last_query = null;
            syncQuery();
            tom_select.refreshOptions(true);
        });

        // Enter in the search box never submits the form (on Delete Domains, that would delete the whole list)
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') event.preventDefault();
        });

        var updateCount = function () {
            if (!count) return;
            var lines = listLines(list).filter(function (line) {
                return line.trim() !== '';
            }).length;
            var number = count.querySelector('.domainmod-domain-list-number');
            try {
                number.textContent = lines.toLocaleString(document.documentElement.lang || undefined);
            } catch (error) {
                number.textContent = String(lines);
            }
        };

        // A picked domain goes on its own line at the end of the list, unless it's already there (as typed, in punycode,
        // or with a trailing dot)
        var addToList = function (domain, ascii) {
            var keys = [domain.toLowerCase(), (ascii || '').toLowerCase()];
            var listed = listLines(list).some(function (line) {
                var key = lineKey(line);
                return key !== '' && keys.indexOf(key) !== -1;
            });
            if (listed) {
                announce(withDomain(text('already'), domain));
                return;
            }
            var value = list.value;
            if (value !== '' && !/[\r\n]$/.test(value)) value += '\n';
            list.value = value + domain;
            list.dispatchEvent(new Event('input', {bubbles: true}));
            announce(withDomain(text('added'), domain));
        };

        // A pick leaves nothing selected and nothing to pick again: the box is emptied and the search forgotten
        tom_select.on('item_add', function (value) {
            var option = tom_select.options[value] || {};
            var domain = typeof option.domain === 'string' ? option.domain : String(value);
            var ascii = typeof option.ascii === 'string' ? option.ascii : '';
            tom_select.clear(true);
            tom_select.setTextboxValue('');
            last_query = '';
            reset();
            addToList(domain, ascii);
            tom_select.focus();
        });

        // The match in each name, marked the same way as the searchable menus (js/search-select.js), but in the name
        // only, so the status stays as it is
        tom_select.hook('after', 'refreshOptions', function () {
            var typed = input.value.trim().toLowerCase();
            tom_select.dropdown_content.querySelectorAll('.domainmod-domain-picker-name').forEach(function (name) {
                var full = name.textContent;
                name.textContent = full;
                var lower = full.toLowerCase();
                var at = typed === '' ? -1 : lower.indexOf(typed);
                if (at < 0 || lower.length !== full.length) return;
                var match = document.createElement('span');
                match.className = 'highlight';
                match.textContent = full.substr(at, typed.length);
                name.textContent = '';
                name.append(full.slice(0, at), match, full.slice(at + typed.length));
            });
        });

        // The label names the hidden real menu and Tom Select's box, and the box is described by the help text and
        // typed left to right, as domains are
        if (label) {
            if (!label.id) label.id = select.id + '-label';
            select.setAttribute('aria-labelledby', label.id);
            input.setAttribute('aria-labelledby', label.id);
        }
        input.setAttribute('dir', 'ltr');
        showMore(false);

        list.addEventListener('input', updateCount);
        updateCount();
        field.hidden = false;
        if (count) count.hidden = false;
    });
}());

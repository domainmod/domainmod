/**
 * /js/server-list.js
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
 * A list's table in DataTables' server-side mode: the Domains list (see domains/index.php and domains/list-data.php)
 * and the SSL Certificates list (ssl/index.php and ssl/list-data.php). The page prints the first page itself, so the
 * table starts with deferLoading and makes no request until the user sorts, searches, or changes page. The table's
 * data-domainmod-list attribute carries the endpoint's address (with the list's whole state), the columns, the
 * counts, the page, its size, the messages, and the links that follow the search box.
 * This script:
 * - removes the no-JavaScript pager once DataTables has started, since it pages the table from there on, starting
 *   on the same page,
 * - for a list without Responsive (responsive false), puts the table alone in a box that scrolls sideways, and
 *   keeps each column at least as wide as on the first page, so later draws don't make the columns jump,
 * - starts the tooltips on each draw's new rows and on a phone's opened child rows,
 * - moves back to the last page when a page has emptied (rows deleted while the table was open),
 * - keeps the search box's search in the Export and Raw List links, so they hold the rows the table shows,
 * - gives a cut-short cell its full text as a tooltip, for a list whose styles cut long values short (cellTitles),
 * - shows one message above the table when a request fails: the session has ended (with a sign-in link), Display
 *   Settings changed in another tab (with a reload link to the list's whole state, config.reloadUrl), a custom field
 *   in the filters was changed or deleted (with a reload link to the state without the custom field filters,
 *   config.filterReloadUrl), or the usual "That didn't work" message. Returning false
 *   from DataTables' xhr event skips its own alert, and the processing indicator is turned off.
 * Loaded by each list's page, and started from the footer's script by domainmodServerList().
 */
function domainmodServerList(selector) {
    'use strict';

    var table = document.querySelector(selector);
    if (!table || !table.hasAttribute('data-domainmod-list')) return null;

    var config;
    try {
        config = JSON.parse(table.getAttribute('data-domainmod-list'));
    } catch (error) {
        return null;
    }
    var messages = config.messages || {};

    // A list without Responsive (config.responsive false, such as the Domains list) keeps every column and scrolls
    // sideways in its own box. The page wraps the table in one for when JavaScript is off, which comes off once
    // DataTables has started (it makes its own around just the table, between the controls above and below it), so
    // the page's box stays if DataTables can't start.
    var scrolls = config.responsive === false;
    var page_box = scrolls && table.parentNode.classList.contains('domainmod-list-scroll') ? table.parentNode : null;

    // The page's own pager follows the table (or the page's scroll box around it). It comes off once DataTables has
    // started, as the page's box does.
    var pager = (page_box || table).parentNode.querySelector('.domainmod-list-pager');

    var message_area = document.getElementById(table.id + '-message');
    var message_kind = '';
    var showMessage = function (kind, text, link_text, link_url) {
        if (!message_area) return;
        // The generic message never replaces the session or display settings message, which say what to do, and a busy
        // message (a restore or an upgrade) replaces only the generic one
        if (message_kind !== '' && kind === 'error') return;
        if (kind === 'busy' && message_kind !== '' && message_kind !== 'error' && message_kind !== 'busy') return;
        message_kind = kind;
        // The same slim alert as the page messages (System::slimMessage()), added after the page loads, so its
        // role="alert" is announced
        var alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible d-flex align-items-start gap-2 py-2 domainmod-message';
        alert.setAttribute('role', 'alert');
        var icon = document.createElement('i');
        icon.className = 'bi bi-exclamation-octagon-fill domainmod-message-icon';
        icon.setAttribute('aria-hidden', 'true');
        var body = document.createElement('div');
        body.className = 'domainmod-message-text';
        body.textContent = text;
        if (link_text && link_url) {
            var link = document.createElement('a');
            link.href = link_url;
            link.className = 'alert-link ms-1';
            link.textContent = link_text;
            body.append(' ', link);
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', messages.close || '');
        alert.append(icon, body, close);
        alert.addEventListener('closed.bs.alert', function () {
            message_kind = '';
        });
        message_area.replaceChildren(alert);
    };

    var startTooltips = function () {
        if (typeof window.domainmodStartTooltips === 'function') window.domainmodStartTooltips(table);
    };

    var columns = (config.columns || []).map(function (column) {
        var options = {orderable: !!column.orderable};
        // Responsive needs the control class on its details column, Ajax-drawn cells included. A column can also carry
        // the list's own classes (config.columns' className), which DataTables adds to its heading and cells.
        var classes = [];
        if (column.control) classes.push('control');
        if (column.className) classes.push(column.className);
        if (classes.length) options.className = classes.join(' ');
        return options;
    });

    var api = domainmodTable(selector, {
        "serverSide": true,
        "processing": true,
        "deferLoading": [config.total, config.total],
        "displayStart": config.start,
        "pageLength": config.length,
        // DataTables sends six columns[n][...] keys for each column, which the endpoints never read and which make a wide
        // list's address several kilobytes long, so they're left out (draw, start, length, search, and order stay)
        "ajax": {
            url: config.url,
            data: function (data) {
                delete data.columns;
            }
        },
        "searchDelay": 400,
        "paging": true,
        "lengthChange": true,
        "lengthMenu": config.sizes,
        "searching": true,
        "info": true,
        "autoWidth": true,
        "bAutoWidth": false,
        "responsive": scrolls ? false : {
            details: {
                type: "column"
            }
        },
        "columns": columns,
        "ordering": true,
        "order": config.order,
        "bSortClasses": false,
        // The loading indicator sits outside the scroll box, so it stays in view however far the table is scrolled
        "dom": '<"top"lif>' + (scrolls ? 'r<"domainmod-list-scroll"t>' : 'rt') + '<"bottom"ip><"clear">'
    });
    if (!api) return null;
    if (pager) pager.remove();
    if (page_box) page_box.replaceWith.apply(page_box, Array.prototype.slice.call(page_box.childNodes));

    // The scroll box can take keyboard focus, to scroll with the arrow keys, and is named for screen readers
    var scroll_box = scrolls ? table.closest('.domainmod-list-scroll') : null;
    if (scroll_box) {
        scroll_box.setAttribute('tabindex', '0');
        scroll_box.setAttribute('role', 'region');
        if (messages.scroll) scroll_box.setAttribute('aria-label', messages.scroll);

        // Each column keeps at least the width it has on the first page, so a later page, sort, or search can't make
        // it narrower and the columns don't jump about (a longer value can still widen one, up to the list's limit).
        // It's measured once the page's fonts have loaded, so the widths are the ones the page shows. The pinned first
        // column is left to its own limit (a share of the window's width, see the list's styles), which a fixed
        // minimum would override once the window is narrowed. A change of the box's width measures them again from
        // scratch, so widths taken from a wide window, where the table stretches to fill the box, don't hold the
        // columns wide once it's narrowed.
        //
        // The box's scroll padding at the start is the pinned column's width, so a link tabbed to behind that column
        // is scrolled out from under it. It follows the column's width as the window changes.
        var pinned = table.querySelector('thead th:first-child');
        var keepWidths = function () {
            var headings = table.querySelectorAll('thead th:not(:first-child)');
            headings.forEach(function (heading) {
                heading.style.minWidth = '';
            });
            // Never more than the cells' own limit (18rem, or 40vw on a phone, see the list's styles), so a column that
            // stretched to fill the box can't keep a column wider than the space beside the pinned one
            var root_size = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
            var limit = Math.min(18 * root_size, 0.4 * window.innerWidth);
            headings.forEach(function (heading) {
                heading.style.minWidth = Math.min(heading.getBoundingClientRect().width, limit) + 'px';
            });
            padStart();
        };
        var padStart = function () {
            if (pinned) scroll_box.style.scrollPaddingInlineStart = pinned.getBoundingClientRect().width + 'px';
        };
        // The box's own width is watched (where the browser can), so opening or closing the sidebar counts too
        var width_timer = null;
        var box_width = scroll_box.clientWidth;
        var widthChanged = function () {
            if (scroll_box.clientWidth === box_width) return;
            box_width = scroll_box.clientWidth;
            clearTimeout(width_timer);
            width_timer = setTimeout(keepWidths, 200);
        };
        if (window.ResizeObserver) {
            new ResizeObserver(widthChanged).observe(scroll_box);
        } else {
            window.addEventListener('resize', widthChanged);
        }
        // A draw with no matches at all scrolls the box back to the start, where the "no matches" message is
        api.on('draw', function () {
            padStart();
            if (api.page.info().recordsDisplay === 0) scroll_box.scrollLeft = 0;
        });
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(keepWidths);
        } else {
            keepWidths();
        }
    }

    /*
     * A list whose styles cut long values short (config.cellTitles, such as the Domains list, see
     * css/datatables/domains-main.css) shows a cut-short cell's full text as a tooltip when the pointer is over it. The
     * text is read at that moment, so it follows each draw and each change of width, and leaves out the hidden text
     * that screen readers get (such as a status icon's code).
     */
    if (config.cellTitles) {
        table.addEventListener('mouseover', function (event) {
            var cell = event.target.closest('td');
            if (!cell || cell.closest('table') !== table) return;
            if (cell.classList.contains('control') || cell.classList.contains('child')) return;
            if (cell.scrollWidth > cell.clientWidth) {
                var copy = cell.cloneNode(true);
                // Neither screen reader text nor a closed disclosure's contents (the Change History's details) go in
                copy.querySelectorAll('.visually-hidden, details:not([open]) > :not(summary)').forEach(function (hidden) {
                    hidden.remove();
                });
                // A line break reads as a space, so two lines don't run together
                copy.querySelectorAll('br').forEach(function (line_break) {
                    line_break.replaceWith(' ');
                });
                cell.title = copy.textContent.replace(/\s+/g, ' ').trim();
            } else {
                cell.removeAttribute('title');
            }
        });

        // A cut-short cell with nothing in it to tab to (plain text, such as a Text custom field) can be tabbed to
        // itself, so it opens up in full on keyboard focus as a cell with a link does. Checked after each draw and
        // each change of width, and left alone while it has focus, since it isn't cut short then.
        var reachCutShort = function () {
            table.querySelectorAll('tbody td').forEach(function (cell) {
                if (cell.contains(document.activeElement)) return;
                var marked = cell.hasAttribute('data-domainmod-cut');
                if (!marked && cell.querySelector('a, button, input, select, textarea, [tabindex]')) return;
                if (cell.scrollWidth > cell.clientWidth) {
                    cell.setAttribute('tabindex', '0');
                    cell.setAttribute('data-domainmod-cut', '');
                } else if (marked) {
                    cell.removeAttribute('tabindex');
                    cell.removeAttribute('data-domainmod-cut');
                }
            });
        };
        // The table's own size is watched (where the browser can), so a column narrowed by a change of the box's width,
        // such as opening the sidebar, is checked too
        var reach_timer = null;
        var reachLater = function () {
            clearTimeout(reach_timer);
            reach_timer = setTimeout(reachCutShort, 200);
        };
        if (window.ResizeObserver) {
            new ResizeObserver(reachLater).observe(table);
        } else {
            window.addEventListener('resize', reachLater);
        }
        api.on('draw', reachCutShort);
        // A cell skipped while it had focus is checked again once focus has left it
        table.addEventListener('focusout', function () {
            setTimeout(reachCutShort, 0);
        });
        reachCutShort();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(reachCutShort);
        }
    }

    // The paging controls' name for screen readers, as on the other lists
    var paging = table.closest('.dt-container') ? table.closest('.dt-container').querySelector('nav') : null;
    if (paging && messages.paging) paging.setAttribute('aria-label', messages.paging);

    /*
     * Export and the Raw List follow the search box: after each draw, their links carry the search the rows on screen
     * came from as dt_search, with the values that tie it to this table (config.searchLinks, by link id: the column
     * layout as dt_layout, so a change to Display Settings since is caught, as the endpoint catches it), or leave them
     * all out when there's no search. The Raw List link's own dt_list, dt_type, and dt_cfk (the saved list it's for) are
     * part of its address from the page and stay whatever the search. A request that fails
     * doesn't draw, so the links keep the search of the rows still showing. Only these two links change, so the chips,
     * Clear all, and the page's own address (and bookmarks of it) never carry it.
     */
    var search_links = Object.keys(config.searchLinks || {}).map(function (id) {
        return {link: document.getElementById(id), extra: config.searchLinks[id] || {}};
    }).filter(function (item) {
        return item.link !== null;
    });
    var followSearch = function () {
        var params = api.ajax.params();
        var value = params && params.search && typeof params.search.value === 'string' ? params.search.value : '';
        search_links.forEach(function (item) {
            var url = new URL(item.link.href, window.location.href);
            if ((url.searchParams.get('dt_search') || '') === value) return;
            if (value === '') {
                url.searchParams.delete('dt_search');
            } else {
                url.searchParams.set('dt_search', value);
            }
            Object.keys(item.extra).forEach(function (key) {
                if (value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, item.extra[key]);
                }
            });
            item.link.href = url.href;
        });
    };

    var moving_back = false;
    api.on('draw', function () {
        followSearch();
        startTooltips();
        var info = api.page.info();
        if (!moving_back && info.recordsDisplay > 0 && info.start >= info.recordsDisplay) {
            moving_back = true;
            // After this draw has finished, as a draw started inside its own draw event is overwritten by it
            setTimeout(function () {
                api.page('last').draw('page');
            }, 0);
            return;
        }
        moving_back = false;
    });
    api.on('responsive-display', startTooltips);

    api.on('xhr', function (event, settings, json, xhr) {
        if (json !== null && xhr && xhr.status >= 200 && xhr.status < 300) {
            // A good answer clears an earlier "That didn't work" or busy message, but never the session or settings message
            if ((message_kind === 'error' || message_kind === 'busy') && message_area) {
                message_area.replaceChildren();
                message_kind = '';
            }
            return;
        }
        var status = xhr ? xhr.status : 0;
        var answer = null;
        try {
            answer = xhr && xhr.responseText ? JSON.parse(xhr.responseText) : null;
        } catch (error) {
            answer = null;
        }
        if (status === 401) {
            showMessage('signin', messages.signin, messages.signinLink, config.signinUrl);
        } else if (status === 409 && answer && answer.reload && answer.reason === 'filter') {
            showMessage('reload', messages.filterReload || messages.reload, messages.reloadLink,
                config.filterReloadUrl || window.location.href);
        } else if (status === 409 && answer && answer.reload) {
            showMessage('reload', messages.reload, messages.reloadLink, config.reloadUrl || window.location.href);
        } else if (status === 503 && answer && typeof answer.error === 'string' && answer.error !== '') {
            // A restore or an upgrade is running (see _includes/restore-gate.inc.php), so its own message says so
            showMessage('busy', answer.error);
        } else {
            showMessage('error', messages.error);
        }
        // DataTables 3.1.5 leaves a failed server-side draw marked as still drawing, which makes it ignore the call
        // that hides the processing indicator, so the mark is cleared first (the next draw sets it again)
        settings.doingDraw = false;
        api.processing(false);
        return false;
    });

    return api;
}

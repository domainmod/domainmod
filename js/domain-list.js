/**
 * /js/domain-list.js
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
 * The Domains list's table in DataTables' server-side mode (see domains/index.php and domains/list-data.php). The page
 * prints the first page itself, so the table starts with deferLoading and makes no request until the user sorts,
 * searches, or changes page. The table's data-domainmod-list attribute carries the endpoint's address (with the list's
 * whole state), the columns, the counts, the page, its size, and the messages. This script:
 * - removes the no-JavaScript pager, since DataTables pages the table from here on, starting on the same page,
 * - starts the tooltips on each draw's new rows and on a phone's opened child rows,
 * - moves back to the last page when a page has emptied (domains deleted while the table was open),
 * - shows one message above the table when a request fails: the session has ended (with a sign-in link), Display
 *   Settings changed in another tab (with a reload link), or the usual "That didn't work" message. Returning false
 *   from DataTables' xhr event skips its own alert, and the processing indicator is turned off.
 * Loaded by domains/index.php, and started from the footer's script by domainmodDomainList().
 */
function domainmodDomainList(selector) {
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

    var pager = table.parentNode.querySelector('.domainmod-list-pager');
    if (pager) pager.remove();

    var message_area = document.getElementById(table.id + '-message');
    var message_kind = '';
    var showMessage = function (kind, text, link_text, link_url) {
        if (!message_area) return;
        // The generic message never replaces the session or display settings message, which say what to do
        if (message_kind !== '' && kind === 'error') return;
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
        // Responsive needs the control class on its details column, Ajax-drawn cells included
        if (column.control) options.className = 'control';
        return options;
    });

    var api = domainmodTable(selector, {
        "serverSide": true,
        "processing": true,
        "deferLoading": [config.total, config.total],
        "displayStart": config.start,
        "pageLength": config.length,
        "ajax": {url: config.url},
        "searchDelay": 400,
        "paging": true,
        "lengthChange": true,
        "lengthMenu": config.sizes,
        "searching": true,
        "info": true,
        "autoWidth": true,
        "bAutoWidth": false,
        "responsive": {
            details: {
                type: "column"
            }
        },
        "columns": columns,
        "ordering": true,
        "order": config.order,
        "bSortClasses": false,
        "dom": '<"top"lif>rt<"bottom"ip><"clear">'
    });
    if (!api) return null;

    // The paging controls' name for screen readers, as on the other lists
    var paging = table.closest('.dt-container') ? table.closest('.dt-container').querySelector('nav') : null;
    if (paging && messages.paging) paging.setAttribute('aria-label', messages.paging);

    var moving_back = false;
    api.on('draw', function () {
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
            // A good answer clears an earlier "That didn't work", but never the session or settings message
            if (message_kind === 'error' && message_area) {
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
        } else if (status === 409 && answer && answer.reload) {
            showMessage('reload', messages.reload, messages.reloadLink, window.location.href);
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

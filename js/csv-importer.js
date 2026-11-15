/*
 * /js/csv-importer.js
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

// The CSV Importer's Import button (domains/csv-importer/): once the form is sent, the button shows a spinner and
// "Importing…" and can't be pressed again, since a large file takes a while and a second import of the same file at
// the same time could add a domain twice. The form is sent as normal, so it works the same without JavaScript.
// The button comes back if the page is shown again from the browser's back-forward cache, if Escape stops the upload,
// or if another file is chosen (such as after stopping it with the browser's Stop button).
document.querySelectorAll('form.domainmod-csv-importer-form').forEach(function (form) {
    var sent = false;
    var button = form.querySelector('button[type="submit"]');
    var button_html = button ? button.innerHTML : '';
    var reset = function () {
        if (!sent) return;
        sent = false;
        if (button) {
            button.innerHTML = button_html;
            button.classList.remove('disabled');
            button.removeAttribute('aria-disabled');
        }
        form.removeAttribute('aria-busy');
    };
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) reset();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') reset();
    });
    form.addEventListener('change', reset);
    form.addEventListener('submit', function (event) {
        if (sent) {
            event.preventDefault();
            return;
        }
        sent = true;
        if (!button) return;
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('aria-hidden', 'true');
        button.textContent = '';
        button.appendChild(spinner);
        button.appendChild(document.createTextNode(' ' + (form.getAttribute('data-busy-text') || '')));
        button.classList.add('disabled');
        button.setAttribute('aria-disabled', 'true');
        form.setAttribute('aria-busy', 'true');
    });
});

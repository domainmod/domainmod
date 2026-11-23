/*
 * /js/two-factor-qr.js
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
 * Draws the two-factor setup page's QR code (settings/security/index.php) on a canvas, from the otpauth:// address
 * in the element's data-domainmod-otpauth attribute, with the vendored qrcode-generator library. Drawing on a canvas
 * isn't affected by the Content Security Policy, unlike inline styles. The code is always dark on white, with a quiet
 * zone of four modules, so phone cameras read it in dark mode too. The key is always shown as text as well, so setup
 * works without JavaScript. Loaded by settings/security/index.php after the library.
 */
(function () {
    'use strict';

    if (typeof qrcode !== 'function') return;

    document.querySelectorAll('[data-domainmod-otpauth]').forEach(function (holder) {
        var qr = qrcode(0, 'M');
        qr.addData(holder.getAttribute('data-domainmod-otpauth'));
        qr.make();

        var modules = qr.getModuleCount();
        var cell = 5;
        var margin = 4;
        var size = (modules + margin * 2) * cell;
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', holder.getAttribute('data-domainmod-qr-label') || '');

        var context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size, size);
        context.fillStyle = '#000000';
        for (var row = 0; row < modules; row++) {
            for (var col = 0; col < modules; col++) {
                if (qr.isDark(row, col)) context.fillRect((col + margin) * cell, (row + margin) * cell, cell, cell);
            }
        }

        holder.appendChild(canvas);
    });
})();

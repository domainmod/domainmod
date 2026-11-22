/*
 * /js/hosting-service-ip.js
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

// A domain's Hosting Service menu (Form::showHostingServiceMenu(), given the IP Address menu's name in data-ip-menu)
// sets the domain's IP Address to the chosen service's, as a convenience before saving, and keeps following the
// service until the user changes the IP Address themselves on this visit. Only a service's IP address (data-ip-id)
// that the IP Address menu lists is used, so a service with none, or with one that no longer exists, and None change
// nothing. On a domain that's already saved (data-ip-keep-saved, on Edit Domain), the IP address it was saved with is
// never replaced: only the "[no ip address]" placeholder (id 1, which every install has), or an IP address the script
// filled in on this visit, follows the service. A form shown again after a failed save names no IP Address menu, so
// the IP Address chosen before the save stays as it is. The domain's IP Address is where it points, which can differ from its
// service's (such as behind a proxy), so it can still be changed afterwards. Without JavaScript nothing is filled in.
var hostingServiceIpPlaceholder = '1';
document.querySelectorAll('select[data-ip-menu]').forEach(function (service_menu) {
    var ip_menu = service_menu.form ? service_menu.form.elements.namedItem(service_menu.getAttribute('data-ip-menu')) : null;
    if (!ip_menu || ip_menu.tagName !== 'SELECT') return;
    var keep_saved = service_menu.getAttribute('data-ip-keep-saved') === '1';
    // Whether the user has changed the IP Address on this visit, and the IP address the script last filled in. The
    // script's own fill sends a change event too, which isn't the user's (filling is true while it's sent).
    var changed_by_user = false;
    var filled = null;
    var filling = false;
    ip_menu.addEventListener('change', function () {
        if (!filling) changed_by_user = true;
    });
    service_menu.addEventListener('change', function () {
        // Not while a domain refresh has the fields locked (js/domain-refresh.js), or once the user has chosen one
        if (ip_menu.disabled || changed_by_user) return;
        if (keep_saved && ip_menu.value !== hostingServiceIpPlaceholder && ip_menu.value !== filled) return;
        var service = Array.prototype.find.call(service_menu.options, function (option) {
            return option.value === service_menu.value;
        });
        var ip_id = service ? service.getAttribute('data-ip-id') : null;
        if (!ip_id || ip_id === hostingServiceIpPlaceholder) return;
        var listed = Array.prototype.some.call(ip_menu.options, function (option) {
            return option.value === ip_id;
        });
        if (!listed) return;
        filled = ip_id;
        if (ip_menu.value === ip_id) return;
        ip_menu.value = ip_id;
        // So the page notices the change, as it does when the user picks the IP address
        filling = true;
        try {
            ip_menu.dispatchEvent(new Event('change', { bubbles: true }));
        } finally {
            filling = false;
        }
    });
});

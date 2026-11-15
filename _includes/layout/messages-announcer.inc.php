<?php
/**
 * /_includes/layout/messages-announcer.inc.php
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
?>
<?php
// The shared announcer: an empty, visually hidden live log that's in the page from the start, so screen readers have
// registered it before js/messages.js writes each message to it, once. Its labels are here because js/messages.js is
// an external file and can't translate them itself.
?>
<div class="visually-hidden" role="log" aria-live="polite" aria-relevant="additions" aria-label="<?php echo htmlspecialchars(_('Messages'), ENT_QUOTES, 'UTF-8'); ?>" data-domainmod-announcer data-label-closed="<?php echo htmlspecialchars(_('Message closed'), ENT_QUOTES, 'UTF-8'); ?>"></div>

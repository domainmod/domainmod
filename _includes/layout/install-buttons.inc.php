<?php
/**
 * /_includes/layout/install-buttons.inc.php
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
/*
 * The installer's Back and Next buttons, at the bottom of each step. The page sets, just before including this:
 * $install_back_url (the previous step's address, or '' on the first step), $install_next_label (the translated text
 * of the main button), $install_next_url ('' for a button that submits the step's form, or an address for a link, on
 * a step with no form), and $install_next_large (true for the last step's larger Install button). Back is on the start
 * side and Next on the end side, so in a right-to-left language the row is mirrored by the browser. The arrows are
 * mirrored by the right-to-left layout's icon rule, not here.
 */
$install_back_url = $install_back_url ?? '';
$install_next_url = $install_next_url ?? '';
$install_next_large = $install_next_large ?? false;
$install_next_class = 'btn btn-danger' . ($install_next_large ? ' btn-lg' : '');
?>
<div class="domainmod-install-buttons"><?php
    if ($install_back_url !== '') { ?>
    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($install_back_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?php echo _('Back'); ?></a><?php
    } else { ?>
    <span></span><?php
    }
    if ($install_next_url !== '') { ?>
    <a class="<?php echo $install_next_class; ?>" href="<?php echo htmlspecialchars($install_next_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($install_next_label, ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php
    } else { ?>
    <button type="submit" class="<?php echo $install_next_class; ?>"><?php if ($install_next_large) { ?><i class="bi bi-check2-circle" aria-hidden="true"></i> <?php } echo htmlspecialchars($install_next_label, ENT_QUOTES, 'UTF-8'); if (!$install_next_large) { ?> <i class="bi bi-arrow-right" aria-hidden="true"></i><?php } ?></button><?php
    } ?>
</div>

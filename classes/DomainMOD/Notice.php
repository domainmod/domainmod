<?php
/**
 * /classes/DomainMOD/Notice.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
//@formatter:off
namespace DomainMOD;

class Notice
{

    public $system;

    public function dbUpgrade()
    {
        $this->system = new System();
        $layout = new Layout();

        list($null, $null, $requirements) = $this->system->getRequirements();

        $_SESSION['s_notice_page_title'] = _('Database Upgrade Available') . '<BR><em>v' . $_SESSION['s_system_db_version'] . ' ' . strtolower(_('To')) . ' v' . SOFTWARE_VERSION . '</em>';

        $_SESSION['s_notice'] = "<BR>" . sprintf(_('Your %s software was recently updated, so we now need to upgrade your database.'), SOFTWARE_TITLE) . "<BR><BR>" .

            _('We recommend checking the') . ' <a target="_blank" href="https://domainmod.org/news/">' . _('News') . '</a> ' . _("section of our website to ensure that your server is ready for the new version. If your server has software incompatibilities, the upgrade may fail.") . "<BR><BR>" .

            "<strong>v" . SOFTWARE_VERSION . _(' Software Requirements') . "</strong><BR><BR>" . $requirements . "<BR>" .

            "<strong><span style='font-size: 200%; color: red;'><i class=\"fa fa-exclamation-triangle\"></i> ***** " . strtoupper(_('Critical Warning')) . " -- " . strtoupper(_('Please Read')) . " ***** <i class=\"fa fa-exclamation-triangle\"></i></span><BR><BR>" .
            strtoupper(sprintf(_('We strongly recommend that you backup your %s installation directory and database before proceeding with the upgrade'), SOFTWARE_TITLE)) . ". " .
            strtoupper(_("If something goes wrong during the upgrade and you haven't created a backup, there may be no way to recover your data")) . ". " .
            strtoupper(sprintf(_('You should also make a note of your current version (%s), as this may be required by the recovery process'), $_SESSION['s_system_db_version'])) . ".<BR>
            <BR><span style='font-size: 200%; color: red; line-height: 45px;'><i class=\"fa fa-exclamation-triangle\"></i> ***** " . strtoupper(_('Critical Warning')) . " -- " . strtoupper(_('Please Read')) . " ***** <i class=\"fa fa-exclamation-triangle\"></i></span></strong><BR><BR>" .

            _('Please be patient, this may take a moment. The older your current version is, the longer the upgrade will take.') . "<BR><BR><a href='checks.php?u=1'>" . $layout->showButton('button', _('Upgrade Database')) . "</a>";

    }

} //@formatter:on

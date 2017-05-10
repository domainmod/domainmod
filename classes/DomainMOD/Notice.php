<?php
/**
 * /classes/DomainMOD/Notice.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

    public function dbUpgrade()
    {
        $layout = new Layout();
        $_SESSION['s_notice_page_title'] = "Database Upgrade Available";
        $_SESSION['s_notice'] = "<BR>Your " . SOFTWARE_TITLE . " software was recently updated, so we now need to
            upgrade your database<BR><BR>Please be patient, this may take a moment<BR><BR>
            <a href='checks.php?u=1'>" . $layout->showButton('button', 'Upgrade Database') . "</a>";

        return true;

    }

} //@formatter:on

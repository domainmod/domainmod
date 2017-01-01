<?php
/**
 * /classes/DomainMOD/Layout.php
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

class Layout
{

    public function jumpMenu()
    {

        ob_start(); ?>

        <script type="text/javascript">
            <!--
            function MM_jumpMenu(targ, selObj, restore) { //v3.0
                eval(targ + ".location='" + selObj.options[selObj.selectedIndex].value + "'");
                if (restore) selObj.selectedIndex = 0;
            }
            //-->
        </script><?php

        return ob_get_clean();

    }

    public function showButton($button_type, $button_text)
    {

        ob_start(); ?>
        <button type="<?php echo $button_type; ?>" class="btn btn-primary btn-danger"><?php echo $button_text; ?></button><?php
        return ob_get_clean();

    }

    public function highlightText($text_to_display)
    {

        ob_start(); ?>
        <strong><span style="color:#a30000;"><?php echo $text_to_display; ?></span></strong><?php
        return ob_get_clean();

    }

} //@formatter:on

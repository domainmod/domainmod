<?php
/**
 * /classes/DomainMOD/Dashboard.php
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
//@formatter:off
namespace DomainMOD;

class Dashboard
{
    public $layout;

    public function __construct()
    {
        $this->layout = new Layout();
    }

    /*
     * A small box with a count. $colour and $icon keep the names the dashboard has always passed (AdminLTE 3's colours
     * and Ionicons names), which are turned into Bootstrap's colours and Bootstrap Icons here.
     */
    public function displayPanel($title, $count, $colour, $icon, $url)
    {
        $colours = array('green' => 'success', 'yellow' => 'warning', 'red' => 'danger', 'info' => 'info');
        $icons = array('checkmark-circled' => 'bi-check-circle-fill', 'android-cart' => 'bi-cart-fill', 'clock' => 'bi-clock-fill');
        $bootstrap_colour = $colours[$colour] ?? 'secondary';
        $link_colour = in_array($bootstrap_colour, array('warning', 'info'), true) ? 'link-dark' : 'link-light';
        ob_start(); ?>

        <div class="col-lg-2 col-6">
            <div class="small-box text-bg-<?php echo $bootstrap_colour; ?>">
                <div class="inner">
                    <h3><?php echo number_format($count); ?></h3>
                    <p><?php echo $title; ?></p>
                </div>
                <i class="small-box-icon bi <?php echo $icons[$icon] ?? 'bi-circle-fill'; ?>" aria-hidden="true"></i>
                <?php if ($count != 0) { ?>
                    <a href="<?php echo WEB_ROOT; ?><?php echo $url; ?>" class="small-box-footer <?php echo $link_colour; ?> link-underline-opacity-0 link-underline-opacity-50-hover"><?php echo _('View'); ?> <i class="bi bi-arrow-right-circle-fill"></i></a>
                <?php } else { ?>
                    <span class="small-box-footer">&nbsp;</span>
                <?php } ?>
            </div>
        </div><?php

        return ob_get_clean();
    }

    public function displayExpPanel($title, $count, $url)
    {
        ob_start(); ?>

            <div class="col-md-2 col-sm-6 col-12">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-danger shadow-sm"><a href="<?php echo WEB_ROOT; ?><?php echo $url; ?>" class="link-light" aria-label="<?php echo $title; ?>"><i class="bi bi-x-circle"></i></a></span>
                    <div class="info-box-content align-items-center">
                        <span class="info-box-text"><?php echo $title; ?></span>
                        <span class="info-box-number"><?php echo number_format($count); ?></span>
                    </div>
                </div>
            </div><?php

        return ob_get_clean();
    }

} //@formatter:on

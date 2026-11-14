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
     * A count in an outline card: the number, the title, the icon in the card's colour, and a View link to the list
     * (left out when the count is zero), which screen readers hear with the title, the count, and the dashboard
     * section, so links with the same title (such as Renewals for domains and SSL certificates) differ. $colour is a
     * Bootstrap colour, used the same way across the dashboard (danger for urgent, warning for waiting, success for
     * done, and secondary for plain counts), and $icon is a Bootstrap Icons class. $detail is an optional short line
     * under the title, such as a total cost (escaped here, so pass it as plain text).
     */
    public function displayPanel($title, $count, $colour, $icon, $url, $section = '', $detail = '')
    {
        $bootstrap_colour = $colour;
        ob_start(); ?>

        <div class="col-lg-2 col-sm-4 col-6">
            <div class="card card-outline card-<?php echo $bootstrap_colour; ?> h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fs-2 fw-semibold lh-1"><?php echo number_format($count); ?></div>
                            <div class="text-body-secondary mt-1"><?php echo $title; ?></div>
                            <?php if ($detail != '') { ?>
                                <div class="small text-body-secondary"><?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php } ?>
                        </div>
                        <i class="bi <?php echo $icon; ?> fs-3 text-<?php echo $bootstrap_colour; ?>" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <?php if ($count != 0) { ?>
                        <a href="<?php echo WEB_ROOT; ?><?php echo $url; ?>" class="link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo _('View'); ?><span class="visually-hidden"> <?php echo $title; ?> (<?php echo number_format($count); ?>)<?php if ($section != '') echo ', ' . $section; ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    <?php } else { ?>
                        &nbsp;
                    <?php } ?>
                </div>
            </div>
        </div><?php

        return ob_get_clean();
    }

    /*
     * The count of domains or SSL certificates expiring soon, in the same card as the other counts, marked urgent
     */
    public function displayExpPanel($title, $count, $url, $section = '')
    {
        return $this->displayPanel($title, $count, 'danger', 'bi-calendar-event', $url, $section);
    }

} //@formatter:on

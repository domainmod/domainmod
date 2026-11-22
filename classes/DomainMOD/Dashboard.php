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
    // The first expiry date Coming Up counts and its links ask for, so each count is what its link lists
    const DUE_FROM = '2000-01-01';
    // The longest Coming Up window, in days
    const MAX_WINDOW_DAYS = 36500;

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
     * How many of a table's items are in each status (its active column), as status => count, for the Dashboard's
     * totals and Pending block. $table is domains, ssl_certs, or hosting_services.
     */
    public static function statusCounts(\PDO $pdo, $table)
    {
        if (!in_array($table, array('domains', 'ssl_certs', 'hosting_services'), true)) {
            throw new \InvalidArgumentException('Unknown table: ' . $table);
        }

        $counts = array();
        foreach ($pdo->query("
            SELECT active, count(*)
            FROM " . $table . "
            GROUP BY active")->fetchAll(\PDO::FETCH_KEY_PAIR) as $status => $count) {
            $counts[(int) $status] = (int) $count;
        }
        return $counts;
    }

    /*
     * The total of statusCounts()' counts for the statuses in $statuses, or with $exclude, for every other status
     */
    public static function countOf(array $counts, array $statuses, $exclude = false)
    {
        $total = 0;
        foreach ($counts as $status => $count) {
            if (in_array((int) $status, $statuses, true) !== (bool) $exclude) $total += $count;
        }
        return $total;
    }

    /*
     * Coming Up's columns, in days: 7, 30, and the Expiration Days setting (the old "Expiring in the next N days"),
     * smallest first and each once, so a setting of 7 or 30 doesn't repeat a column. The setting counts up to 100 years
     * (MAX_WINDOW_DAYS), so a window's last day always has a four-digit year and compares correctly as a string.
     */
    public static function comingUpWindows($expiration_days)
    {
        $windows = array(7, 30);
        if ((int) $expiration_days > 0) $windows[] = min((int) $expiration_days, self::MAX_WINDOW_DAYS);
        $windows = array_values(array_unique($windows));
        sort($windows);
        return $windows;
    }

    /*
     * How many of a type's items are due within each of Coming Up's windows, counted as the old expiring tiles counted
     * them: domains that aren't Expired or Sold, hosting services that aren't Cancelled, and SSL certificates that
     * aren't Expired (and have a type), whose expiry date is from DUE_FROM through the window's last day, overdue ones
     * included. $type is domains, hosting, or ssl, and $end_dates maps each window to its last day (YYYY-MM-DD). One
     * query fetches the count for each expiry date up to the latest last day, and countByWindow() adds them up.
     */
    public static function dueCounts(\PDO $pdo, $type, array $end_dates)
    {
        if (!$end_dates) return array();

        if ($type === 'domains') {
            $sql = "
                SELECT expiry_date, count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND expiry_date >= :start_date
                  AND expiry_date <= :end_date
                GROUP BY expiry_date";
        } elseif ($type === 'hosting') {
            $sql = "
                SELECT expiry_date, count(*)
                FROM hosting_services
                WHERE active <> 0
                  AND expiry_date >= :start_date
                  AND expiry_date <= :end_date
                GROUP BY expiry_date";
        } elseif ($type === 'ssl') {
            $sql = "
                SELECT sslc.expiry_date, count(*)
                FROM ssl_certs AS sslc, ssl_cert_types AS sslt
                WHERE sslc.type_id = sslt.id
                  AND sslc.active NOT IN ('0')
                  AND sslc.expiry_date >= :start_date
                  AND sslc.expiry_date <= :end_date
                GROUP BY sslc.expiry_date";
        } else {
            throw new \InvalidArgumentException('Unknown type: ' . $type);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('start_date', self::DUE_FROM, \PDO::PARAM_STR);
        $stmt->bindValue('end_date', max($end_dates), \PDO::PARAM_STR);
        $stmt->execute();
        $date_counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $stmt->closeCursor();

        return self::countByWindow($date_counts, $end_dates);
    }

    /*
     * dueCounts()' totals from the count for each expiry date (date => count): for each window, the items whose date is
     * on or before its last day
     */
    public static function countByWindow(array $date_counts, array $end_dates)
    {
        $counts = array();
        foreach ($end_dates as $window => $end_date) {
            $counts[$window] = 0;
            foreach ($date_counts as $date => $count) {
                if ((string) $date <= $end_date) $counts[$window] += (int) $count;
            }
        }
        return $counts;
    }

    /*
     * Whether to show the Getting Started checklist: only on an install with no domains, SSL certificates, or hosting
     * services in any status, since sold and inactive domains, certificates tracked without a domain, and hosting
     * services show a user has clearly got started, and only while the signed-in user hasn't hidden it (from the 4.26.0
     * database update on, loaded at sign-in). Once a domain, a certificate, or a hosting service exists, the checklist
     * is gone for everyone.
     */
    public function showGettingStarted()
    {
        if (!User::showsGettingStarted($_SESSION['s_display_getting_started'] ?? null)) return false;

        $pdo = Database::getInstance()->cnxx;
        if ((int) $pdo->query("SELECT COUNT(*) FROM domains")->fetchColumn() !== 0) return false;
        if ((int) $pdo->query("SELECT COUNT(*) FROM ssl_certs")->fetchColumn() !== 0) return false;
        // Hosting services count too, once the database update has added their table
        if (Hosting::isAvailable($pdo) && (int) $pdo->query("SELECT COUNT(*) FROM hosting_services")->fetchColumn() !== 0) return false;
        return true;
    }

    /*
     * The Getting Started checklist for a new install: the steps to get DomainMOD tracking domains, each worked out from
     * the database on every load, so it's never out of date. Done steps are ticked and keep their row without a button,
     * the first step still to do has the main (red) button, and later ones have outline buttons. The scheduled task step
     * is for administrators only (the Task Scheduler is admin-only), and read-only users see the steps without buttons,
     * since they can't add anything. The circles look like the step indicator's (Layout::stepIndicator()), but this is a
     * list of tasks, not a flow, so it isn't a nav. Once the database has the per-user setting (the 4.26.0 update), a
     * "Hide this checklist" button posts to settings/toggles/getting-started/, a plain form so it works without
     * JavaScript, and Display Settings brings the checklist back. Read-only users can hide it too, since it's their own
     * display choice, not a change to anyone's data.
     * The fees step is optional (domains can be added first), so it only ever has an outline button.
     */
    public function gettingStarted()
    {
        $pdo = Database::getInstance()->cnxx;
        $is_admin = (($_SESSION['s_is_admin'] ?? 0) === 1);
        $read_only = (($_SESSION['s_read_only'] ?? '') == '1');
        $esc = function ($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); };

        $has_registrar = (int) $pdo->query("SELECT COUNT(*) FROM registrars")->fetchColumn() > 0;
        $has_account = (int) $pdo->query("SELECT COUNT(*) FROM registrar_accounts")->fetchColumn() > 0;
        $has_fee = (int) $pdo->query("SELECT COUNT(*) FROM fees")->fetchColumn() > 0;
        $queue_waiting = (int) $pdo->query("
            SELECT COUNT(*)
            FROM domain_queue
            WHERE finished != '1'")->fetchColumn() > 0;

        if ($queue_waiting) {
            $domains_text = sprintf(_('Your Domain Queue has domains waiting. See the %sDomain Queue%s for their progress.'), '<a href="' . WEB_ROOT . '/queue/">', '</a>');
        } else {
            $domains_text = $esc(sprintf(_('Add them one at a time, let %s fetch them from your registrar, or import a CSV file.'), SOFTWARE_TITLE));
        }

        // Each step: done, title, explanation (HTML), the button (URL and label), extra links (URL and label), and
        // whether it's optional (an optional step never has the main button, which goes to the next step that isn't)
        $steps = array(
            array(true, sprintf(_('Install %s'), SOFTWARE_TITLE), '', '', '', array()),
            array($has_registrar, _('Add your registrar'),
                $esc(_('Your registrar is the company your domains are registered with, such as Namecheap or GoDaddy.')),
                '/assets/add/registrar.php', _('Add Registrar'), array()),
            array($has_account, _('Add your registrar account'),
                $esc(sprintf(_('With its API details, %s can fetch your domains and their expiry dates for you.'), SOFTWARE_TITLE)),
                '/assets/add/registrar-account.php', _('Add Registrar Account'), array()),
            array($has_fee, _('Add your fees'),
                $esc(sprintf(_("Optional. With what your registrar charges for each TLD, %s can work out your costs. A domain whose TLD has no fee is hidden from the Domains list until one is added."), SOFTWARE_TITLE)),
                '/reporting/domains/registrar-fees.php?all=1', _('Domain Fees'), array(), true),
            array(false, _('Add your domains'), $domains_text, '/domains/add.php', _('Add Domain'),
                array(array('/queue/intro.php', _('Add Domains Automatically')),
                      array('/domains/csv-importer/', _('CSV Importer')))));
        if ($is_admin) {
            $steps[] = array((new Scheduler())->hasRunRecently(), _('Set up the scheduled task'),
                $esc(_('The scheduled task sends expiry emails, works through the Domain Queue, updates currency conversion rates, and checks for new versions for you.')),
                '/admin/scheduler/', _('Task Scheduler'),
                array(array('https://domainmod.org/docs/userguide/getting-started/#cron-job', _('How to set up the scheduled task'), true)));
        }

        $total = count($steps);
        $done = count(array_filter($steps, function ($step) { return $step[0]; }));
        $next_found = false;

        ob_start(); ?>

        <div class="card card-outline card-danger domainmod-getting-started mb-0">
            <div class="card-body">
                <h2 class="h5"><?php echo $esc(_('Getting Started')); ?></h2>
                <p class="mb-2"><?php echo $esc(sprintf(_('Follow these steps to get %s tracking your domains. Each one ticks itself off as you go.'), SOFTWARE_TITLE)); ?></p><?php
                if ($read_only) { ?>
                <p class="mb-2 text-body-secondary"><?php echo $esc(_('Your account is read-only, so another user who can make changes needs to add these.')); ?></p><?php
                } ?>
                <p class="small mb-1" id="domainmod-getting-started-progress"><?php echo $esc(sprintf(ngettext('%1$s of %2$s steps is done.', '%1$s of %2$s steps are done.', $done), number_format($done), number_format($total))); ?></p>
                <progress class="domainmod-getting-started-progress mb-3" value="<?php echo $done; ?>" max="<?php echo $total; ?>" aria-labelledby="domainmod-getting-started-progress"></progress>
                <ol class="domainmod-getting-started-steps"><?php
                foreach ($steps as $index => $step) {
                    list($is_done, $title, $text, $url, $label, $links) = $step;
                    $is_next = !$is_done && !$next_found && empty($step[6]);
                    if ($is_next) $next_found = true; ?>
                    <li class="domainmod-getting-started-step<?php echo $is_done ? ' is-done' : ($is_next ? ' is-next' : ''); ?>">
                        <span class="domainmod-step-number" aria-hidden="true"><?php if ($is_done) { ?><i class="bi bi-check-lg"></i><?php } else { echo $index + 1; } ?></span>
                        <div class="domainmod-getting-started-text">
                            <span class="visually-hidden"><?php echo $esc(sprintf($is_done ? _('Done: %s') : _('To do: %s'), $title)); ?></span>
                            <span class="domainmod-getting-started-title" aria-hidden="true"><?php echo $esc($title); ?></span><?php
                            if (!$is_done && $text !== '') { ?>
                            <span class="domainmod-getting-started-explanation"><?php echo $text; ?></span><?php
                            }
                            if (!$is_done && !$read_only && $links) { ?>
                            <span class="domainmod-getting-started-links"><?php
                                foreach ($links as $link) {
                                    $href = strpos($link[0], 'http') === 0 ? $link[0] : WEB_ROOT . $link[0]; ?>
                                <a href="<?php echo $esc($href); ?>"<?php if (!empty($link[2])) echo ' target="_blank"'; ?>><?php echo $esc($link[1]); ?></a><?php
                                } ?>
                            </span><?php
                            } ?>
                        </div><?php
                        if (!$is_done && !$read_only && $url !== '') { ?>
                        <a class="btn btn-sm <?php echo $is_next ? 'btn-danger' : 'btn-outline-secondary'; ?> domainmod-getting-started-button" href="<?php echo $esc(WEB_ROOT . $url); ?>"><?php echo $esc($label); ?></a><?php
                        } ?>
                    </li><?php
                } ?>
                </ol>
                <p class="mb-1 small"><?php echo sprintf(_('Tracking SSL certificates too? The %sSSL Certificates%s page walks you through it.'), '<a href="' . WEB_ROOT . '/ssl/">', '</a>'); ?></p>
                <p class="mb-0 small"><?php echo sprintf(_('See %sFirst Run%s in the User Guide for more.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/first-run/">', '</a>'); ?></p><?php
                if ((new User())->canHideGettingStarted()) { ?>
                <form method="post" action="<?php echo $esc(WEB_ROOT . '/settings/toggles/getting-started/'); ?>" class="mt-2 mb-0">
                    <?php echo (new Csrf())->field(); ?>
                    <button type="submit" class="btn btn-link p-0 align-baseline domainmod-getting-started-hide"><?php echo $esc(_('Hide this checklist')); ?></button>
                </form><?php
                } ?>
            </div>
        </div><?php

        return ob_get_clean();
    }

} //@formatter:on

<?php
/**
 * /classes/DomainMOD/Notice.php
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

class Notice
{

    public $system;

    /*
     * Whether notice.php has the database upgrade notice to show: it was asked for (?a=u), and the database's version
     * differs from the software's, so an upgrade is waiting (checks.php sends people here on the same test). Anything
     * else, such as a direct visit or a database that's already been upgraded, has no notice to show.
     */
    public static function showsUpgrade($action, $db_version, $software_version)
    {
        return $action === 'u' && is_string($db_version) && $db_version !== '' && $db_version !== $software_version;
    }

    /*
     * Whether a user may run the database upgrade: an administrator who isn't read-only, since the upgrade changes the
     * database and read-only users never make changes. $admin and $read_only are the users table's columns, as the
     * database returns them (a user who can't be found has neither, so can't run it).
     */
    public static function canRunUpgrade($admin, $read_only)
    {
        return in_array($admin, array(1, '1'), true) && in_array($read_only, array(0, '0'), true);
    }

    /*
     * Whether the signed-in user ($user_id) may run the upgrade (canRunUpgrade()). It's read from the database, since
     * checks.php only puts the user's admin and read-only settings in the session after the upgrade has run.
     */
    public function userCanRunUpgrade($user_id)
    {
        $result = (new Login())->getUserInfo($user_id);
        return $result !== false && self::canRunUpgrade($result->admin, $result->read_only);
    }

    public function dbUpgrade()
    {
        $this->system = new System();
        $db_version = $_SESSION['s_system_db_version'];

        $_SESSION['s_notice_page_title'] = $this->upgradeTitle($db_version);
        $_SESSION['s_notice'] = $this->upgradeBody($db_version, $this->system->requirementsChecklist(false, true), (new Csrf())->query());
    }

    // The notice for anyone who can't run the upgrade (canRunUpgrade()): the same title, without the button
    public function dbUpgradeWaiting()
    {
        $db_version = $_SESSION['s_system_db_version'];

        $_SESSION['s_notice_page_title'] = $this->upgradeTitle($db_version);
        $_SESSION['s_notice'] = $this->upgradeWaitingBody();
    }

    // The notice's title, with the versions the upgrade goes from and to
    public function upgradeTitle($db_version)
    {
        // The range has its own message, not the "To" an email log uses, so translators can word each one for its place
        return _('Database Upgrade Available') . ' - <em>' . sprintf(_('v%1$s to v%2$s'), htmlspecialchars((string) $db_version, ENT_QUOTES, 'UTF-8'), SOFTWARE_VERSION) . '</em>';
    }

    /*
     * The notice itself: why the upgrade is needed, the new version's requirements as the installer's checklist
     * ($requirements, from System::requirementsChecklist()), one warning to back up first, and the button that starts
     * the upgrade, whose link carries the security token ($csrf_query, from Csrf::query()).
     */
    public function upgradeBody($db_version, $requirements, $csrf_query)
    {
        $layout = new Layout();
        $esc = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };

        ob_start(); ?>
        <p><?php echo sprintf(_('Your %s software was recently updated, so we now need to upgrade your database.'), SOFTWARE_TITLE); ?></p>
        <p><?php echo _('We recommend checking the') . ' <a target="_blank" href="https://domainmod.org/news/">' . _('News') . '</a> ' . _("section of our website to ensure that your server is ready for the new version. If your server has software incompatibilities, the upgrade may fail."); ?></p>
        <?php // The msgid starts with a space, which some translations leave out, so the space is added here instead ?>
        <h3 class="h5 mt-4 mb-3">v<?php echo SOFTWARE_VERSION . ' ' . trim(_(' Software Requirements')); ?></h3>
        <?php echo $requirements; ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 py-2 my-4 domainmod-message">
            <i class="bi bi-exclamation-triangle-fill domainmod-message-icon" aria-hidden="true"></i>
            <div class="domainmod-message-text">
                <h3 class="h6 alert-heading"><?php echo _('Critical Warning'); ?></h3>
                <p class="mb-0"><?php
                    echo sprintf(_('We strongly recommend that you back up your %s installation directory and database before proceeding with the upgrade'), SOFTWARE_TITLE) . '. ' .
                        _("If something goes wrong during the upgrade and you haven't created a backup, there may be no way to recover your data") . '. ' .
                        sprintf(_('You should also make a note of your current version (%s), as this may be required by the recovery process'), $esc($db_version)) . '.'; ?></p>
            </div>
        </div>
        <p><?php echo _('Please be patient, this may take a moment. The older your current version is, the longer the upgrade will take.'); ?></p>
        <div class="mt-4 mb-3"><?php echo $layout->linkButton('checks.php?u=1&' . $csrf_query, _('Upgrade Database'), 'primary'); ?></div><?php
        return ob_get_clean();
    }

    /*
     * The notice for anyone who can't run the upgrade: why it's needed, that an administrator has to run it, and a way
     * to sign out. There's no upgrade button, and checks.php won't run the upgrade for them.
     */
    public function upgradeWaitingBody()
    {
        $layout = new Layout();

        ob_start(); ?>
        <p><?php echo sprintf(_('Your %s software was recently updated, so we now need to upgrade your database.'), SOFTWARE_TITLE); ?></p>
        <?php // TRANSLATORS: Shown to users who aren't administrators (or are read-only) while a database upgrade is waiting ?>
        <p><?php echo _('An administrator needs to sign in and run the upgrade before you can continue. Please ask one to do this, and then sign in again.'); ?></p>
        <div class="mt-4 mb-3"><?php echo $layout->linkButton('logout.php', _('Logout')); ?></div><?php
        return ob_get_clean();
    }

} //@formatter:on

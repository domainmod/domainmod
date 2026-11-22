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
     * The placeholder hosting providers DomainMOD made itself, by the creation type it gave them: [no hosting], which the
     * installer added in its language (so every translation DomainMOD ships is listed), and [created by queue], which the
     * Domain Queue always added in English. HostingLinkImport::placeholderMatch() matches them (exact name and creation
     * type) for the upgrade notice's count, the 4.27.0 update block's placeholder delete, and the import of the domains'
     * links, so all three leave out the same rows.
     */
    const PLACEHOLDER_PROVIDERS = array(
        'Installation' => array('[no hosting]', '[لا استضافة]', '[কোন হোস্টিং নেই]',
            '[kein Hosting]', '[sin alojamiento]', '[بدون میزبانی]', "[pas d'hébergement]", '[कोई होस्टिंग नहीं]',
            '[tidak ada hosting]', '[ホスティングなし]', '[호스팅 없음]', '[होस्टिंग नाही]', '[geen hosting]',
            '[brak hostingu]', '[sem hospedagem]', '[нет хостинга]', '[ஹோஸ்டிங் இல்லை]', '[హోస్టింగ్ లేదు]',
            '[barındırma yok]', '[کوئی میزبانی نہیں]', '[không lưu trữ]', '[无托管]', '[無託管]'),
        'Queue' => array('[created by queue]'));

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
     * How many domains have a Web Hosting Provider (domains.hosting_id) that the 4.27.0 upgrade turns into a hosting
     * service: the domains HostingLinkImport links, with a provider that exists and isn't one of DomainMOD's own
     * placeholders (PLACEHOLDER_PROVIDERS), and a registrar account and owner that exist. It's 0 when there's nothing to
     * say (a database at 4.27.0 or later, or one without the column), and null when it can't be worked out: something
     * the count needs is missing (hosting.creation_type_id or the creation_types table, which a database older than
     * 4.02 doesn't have yet), or a query fails. $pdo defaults to the shared connection.
     */
    public static function hostingLinkCount($db_version, $pdo = null)
    {
        if (!is_string($db_version) || $db_version === '' || version_compare($db_version, Hosting::AVAILABLE_FROM, '>=')) {
            return 0;
        }

        try {

            $pdo = $pdo ?? Database::getInstance()->cnxx;

            // Each table's columns the count needs, checked before any of them is read
            $rows = $pdo->query("
                SELECT `TABLE_NAME`, `COLUMN_NAME`
                FROM `INFORMATION_SCHEMA`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                  AND ((`TABLE_NAME` = 'domains' AND `COLUMN_NAME` IN ('hosting_id', 'account_id'))
                    OR (`TABLE_NAME` = 'hosting' AND `COLUMN_NAME` IN ('id', 'name', 'creation_type_id'))
                    OR (`TABLE_NAME` = 'registrar_accounts' AND `COLUMN_NAME` IN ('id', 'owner_id'))
                    OR (`TABLE_NAME` = 'owners' AND `COLUMN_NAME` = 'id')
                    OR (`TABLE_NAME` = 'creation_types' AND `COLUMN_NAME` IN ('id', 'name')))")->fetchAll(\PDO::FETCH_NUM);
            $columns = array();
            foreach ($rows as $row) {
                $columns[strtolower($row[0])][strtolower($row[1])] = true;
            }
            if (!isset($columns['domains']['hosting_id'])) return 0;
            foreach (array('domains' => array('account_id'), 'hosting' => array('id', 'name', 'creation_type_id'),
                         'registrar_accounts' => array('id', 'owner_id'), 'owners' => array('id'),
                         'creation_types' => array('id', 'name')) as $table => $needed) {
                foreach ($needed as $column) {
                    if (!isset($columns[$table][$column])) return null;
                }
            }

            $placeholder = HostingLinkImport::placeholderMatch('h');
            $stmt = $pdo->prepare( // Legacy: domains.hosting_id before 4.27.0
                "SELECT COUNT(*)
                FROM domains AS d
                " . HostingLinkImport::ELIGIBLE_JOINS . "
                WHERE d.hosting_id <> 0
                  AND NOT " . $placeholder['sql']);
            foreach ($placeholder['params'] as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
            $stmt->execute();
            $count = $stmt->fetchColumn();
            $stmt->closeCursor();
            return $count === false ? null : (int) $count;

        } catch (\Throwable $e) {

            return null;

        }
    }

    /*
     * The upgrade notice's text about the domains' Web Hosting Provider links the 4.27.0 upgrade turns into hosting
     * services, for a count from hostingLinkCount(): '' for 0, and without a number when the count is null
     */
    public static function hostingLinkMessage($count)
    {
        if ($count === 0) return '';
        if ($count === null) {
            // TRANSLATORS: In the database upgrade notice, when the number of domains affected can't be worked out. "Imported" is the username the upgrade gives the new hosting accounts. Keep the username "Imported" unchanged.
            $first = _("This upgrade replaces each domain's Web Hosting Provider field with a Hosting Service. Each domain will be linked to a new service named after its provider, in a new hosting account with the username \"Imported\", if that provider still exists and isn't one of DomainMOD's placeholders, such as [no hosting].");
        } else {
            // TRANSLATORS: In the database upgrade notice. %s is the number of domains the upgrade will link to a hosting service (those whose provider still exists and isn't one of DomainMOD's placeholders). "Imported" is the username the upgrade gives the new hosting accounts. Keep the username "Imported" unchanged.
            $first = sprintf(ngettext("This upgrade replaces each domain's Web Hosting Provider field with a Hosting Service. %s domain will be linked to a new service named after its provider, in a new hosting account with the username \"Imported\".",
                "This upgrade replaces each domain's Web Hosting Provider field with a Hosting Service. %s domains will each be linked to a new service named after its provider, in a new hosting account with the username \"Imported\".",
                $count), number_format($count));
        }
        return $first . ' ' . _('Domains with the same provider and owner share one service. These services have no fees, and their renewal dates are placeholders, so check them under Hosting after the upgrade.');
    }

    /*
     * The notice itself: why the upgrade is needed, the new version's requirements as the installer's checklist
     * ($requirements, from System::requirementsChecklist()), one warning to back up first, and the button that starts
     * the upgrade, whose link carries the security token ($csrf_query, from Csrf::query()). Above the warning, a
     * paragraph says how the 4.27.0 upgrade turns the domains' Web Hosting Provider links into hosting services, when
     * there are any (see hostingLinkCount(), which reads $pdo, the shared connection by default).
     */
    public function upgradeBody($db_version, $requirements, $csrf_query, $pdo = null)
    {
        $layout = new Layout();
        $esc = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };
        $hosting_message = self::hostingLinkMessage(self::hostingLinkCount($db_version, $pdo));

        ob_start(); ?>
        <p><?php echo sprintf(_('Your %s software was recently updated, so we now need to upgrade your database.'), SOFTWARE_TITLE); ?></p>
        <p><?php echo _('We recommend checking the') . ' <a target="_blank" href="https://domainmod.org/news/">' . _('News') . '</a> ' . _("section of our website to ensure that your server is ready for the new version. If your server has software incompatibilities, the upgrade may fail."); ?></p>
        <?php // The msgid starts with a space, which some translations leave out, so the space is added here instead ?>
        <h3 class="h5 mt-4 mb-3">v<?php echo SOFTWARE_VERSION . ' ' . trim(_(' Software Requirements')); ?></h3>
        <?php echo $requirements; ?>
        <?php if ($hosting_message !== '') echo '<p class="mt-4">' . $esc($hosting_message) . '</p>'; ?>
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
        <p><?php echo _('Please be patient. This may take a moment. The older your current version is, the longer the upgrade will take.'); ?></p>
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

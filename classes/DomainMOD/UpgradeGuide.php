<?php
/**
 * /classes/DomainMOD/UpgradeGuide.php
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

/*
 * The Upgrade page's steps for each kind of install (admin/upgrade/, see InstallType), as HTML. Commands, paths, file
 * names, versions, and URLs are never translated: they go into the translated text through placeholders, escaped, and
 * paths in commands are quoted for the shell. Each command is shown in a block with a Copy button, which
 * js/copy-command.js shows and runs (without JavaScript, the button stays hidden and the command can be selected).
 */
class UpgradeGuide
{
    const DOWNLOAD_URL = 'https://domainmod.org/download/';
    const RELEASE_NOTES_URL = 'https://github.com/domainmod/domainmod/releases/tag/v';
    const SOFTACULOUS_URL = 'https://www.softaculous.com/docs/How_to_upgrade_installations';
    const DOCKER_IMAGE = 'domainmod/domainmod';

    // The order the types are offered in, and shown in when the type isn't known
    const TYPES = array('zip', 'git', 'docker', 'softaculous');

    public $layout;
    private $command_count = 0;

    public function __construct()
    {
        $this->layout = new Layout();
    }

    // A type's name, as shown on the page
    public function typeName($type)
    {
        $names = array('zip' => _('Zip File'), 'git' => _('Git'), 'docker' => _('Docker'),
                       'softaculous' => 'Softaculous', 'unknown' => _('Not Sure'));
        return $names[$type] ?? $names['unknown'];
    }

    // Why InstallType::detect() chose its type, one sentence per signal (HTML)
    public function signalText($signal)
    {
        $code = array($this, 'code');

        switch ($signal) {
            case 'override':
                // TRANSLATORS: %1$s is a setting ($install_type) and %2$s a file name (config.inc.php). Don't translate them.
                return sprintf(_('It\'s set with %1$s in %2$s.'), $code('$install_type'), $code('config.inc.php'));
            case 'docker_marker':
                return _('The official Docker image says so.');
            case 'docker_files':
                return _('The files the official Docker image adds were found.');
            case 'git_folder':
                // TRANSLATORS: %s is a folder name (.git). Don't translate it.
                return sprintf(_('A %s folder was found in the installation folder.'), $code('.git'));
            case 'git_file':
                // TRANSLATORS: %s is a file name (.git). Don't translate it.
                return sprintf(_('A %s file was found in the installation folder, so it\'s a Git worktree or submodule.'), $code('.git'));
            case 'archive_marker':
                return _('The files came from a release archive, such as the zip file.');
            default:
                return _('No sign of a Docker, Git, or zip file installation was found.');
        }
    }

    /*
     * The steps for one type ('zip', 'git', 'docker', or 'softaculous') as a numbered list, or every type's, each under
     * its own heading, for 'unknown'. $context has:
     *   'root'        the installation folder (DIR_ROOT)
     *   'version'     the newest release ('' when it isn't known)
     *   'git'         InstallType::detect()'s Git details, or null
     *   'backup_url'  the Backup & Restore page
     */
    public function steps($type, $context)
    {
        if (!in_array($type, self::TYPES, true)) return $this->allSteps($context);

        $steps = array();
        $steps[] = $type === 'docker' ? $this->backupDatabaseStep($context) : $this->backupStep($context);

        if ($type === 'zip') {

            $steps[] = sprintf(_('Download the new zip file from %s.'), $this->link(self::DOWNLOAD_URL, 'domainmod.org/download'));
            // TRANSLATORS: %2$s is a folder path and %3$s a file name (_includes/config.inc.php). Don't translate them.
            $steps[] = sprintf(_('Unpack it over your current %1$s folder (%2$s), keeping your %3$s file.'), SOFTWARE_TITLE,
                $this->layout->ltr($context['root']), $this->code('_includes/config.inc.php'));
            $steps[] = _('Delete any files the release notes say were removed.');

        } elseif ($type === 'git') {

            $steps = array_merge($steps, $this->gitSteps($context));

        } elseif ($type === 'docker') {

            $steps = array_merge($steps, $this->dockerSteps($context));

        } else {

            $steps[] = sprintf(_('Use the upgrade in Softaculous, in your hosting control panel. See the %sSoftaculous upgrade instructions%s.'),
                '<a href="' . self::SOFTACULOUS_URL . '" target="_blank" rel="noopener">', '</a>');

        }

        if ($type !== 'docker') $steps[] = _('Restart PHP so the new translations load (PHP-FPM if your server uses it, otherwise your web server). If you can\'t restart it yourself, your web hosting provider can do it for you.');

        if ($type === 'docker') {

            // The image's working folder is the installation folder, so docker exec runs update.php from there
            // TRANSLATORS: %2$s is a command (docker exec CONTAINER php update.php). Don't translate it. Leave CONTAINER as it is, since the sentence names it.
            $steps[] = sprintf(_('Sign in again. If %1$s asks you to upgrade the database, press Upgrade Database, or run %2$s on the host, with your container\'s name in place of CONTAINER.'),
                SOFTWARE_TITLE, $this->code('docker exec CONTAINER php update.php'));

        } else {

            // TRANSLATORS: %2$s is a command (php update.php). Don't translate it.
            $steps[] = sprintf(_('Sign in again. If %1$s asks you to upgrade the database, press Upgrade Database, or run %2$s in the installation folder.'),
                SOFTWARE_TITLE, $this->code('php update.php'));

        }

        return '<ol class="domainmod-upgrade-steps mb-0">' . implode('', array_map(function ($step) {
            return '<li class="mb-2">' . $step . '</li>';
        }, $steps)) . '</ol>';
    }

    // Every type's steps, each under its own heading, for an installation whose type isn't known
    public function allSteps($context)
    {
        $html = '';
        foreach (self::TYPES as $type) {
            $html .= '<h3 class="h6 mt-3">' . htmlspecialchars($this->typeName($type), ENT_QUOTES, 'UTF-8') . '</h3>'
                . $this->steps($type, $context);
        }
        return $html;
    }

    /*
     * A value quoted for a POSIX shell, so a path with spaces or other special characters can be pasted as it is. A
     * value of only safe characters is left as it is, so the usual paths stay easy to read.
     */
    public static function shellQuote($value)
    {
        $value = (string) $value;
        if ($value !== '' && preg_match('#^[A-Za-z0-9_./:@%+=,-]+\z#', $value)) return $value;
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    /*
     * Commands in a block with a Copy button. Each of $lines is one command, plain text, escaped here. The button is
     * hidden until js/copy-command.js shows it, and the status after it tells screen readers when it's copied.
     */
    public function commandBlock($lines)
    {
        $this->command_count++;
        $id = 'domainmod-command-' . $this->command_count;
        $commands = htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8');

        return '<div class="domainmod-command my-2">'
            . '<pre class="bg-body-tertiary border rounded p-2 mb-1" dir="ltr"><code id="' . $id . '">' . $commands . '</code></pre>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-domainmod-copy="' . $id . '" data-domainmod-copied="'
            . htmlspecialchars(_('Copied'), ENT_QUOTES, 'UTF-8') . '" data-domainmod-copy-failed="'
            . htmlspecialchars(_('Selected. Copy it yourself.'), ENT_QUOTES, 'UTF-8') . '" hidden><i class="bi bi-clipboard me-1" aria-hidden="true"></i>'
            . '<span>' . htmlspecialchars(_('Copy'), ENT_QUOTES, 'UTF-8') . '</span></button>'
            . '<span class="visually-hidden" role="status"></span></div>';
    }

    // The newest release's version for a command or tag, or a placeholder when it isn't known
    private function versionOrPlaceholder($context)
    {
        return System::isReleaseVersion($context['version'] ?? '') ? $context['version'] : 'X.Y.Z';
    }

    /*
     * The newest release's version when it's newer than this installation, or the placeholder. A saved version can be
     * older than the code (checked before a manual upgrade), and a command that checks it out would go backwards.
     */
    private function newerVersionOrPlaceholder($context)
    {
        $version = $this->versionOrPlaceholder($context);
        if ($version === 'X.Y.Z' || version_compare($version, SOFTWARE_VERSION, '>')) return $version;
        return 'X.Y.Z';
    }

    private function backupStep($context)
    {
        return sprintf(_('Back up your %1$s files and database. You can back up the database on the %2$sBackup & Restore%3$s page.'),
            SOFTWARE_TITLE, '<a href="' . htmlspecialchars($context['backup_url'], ENT_QUOTES, 'UTF-8') . '">', '</a>');
    }

    private function backupDatabaseStep($context)
    {
        return sprintf(_('Back up your database. You can do this on the %sBackup & Restore%s page.'),
            '<a href="' . htmlspecialchars($context['backup_url'], ENT_QUOTES, 'UTF-8') . '">', '</a>');
    }

    /*
     * A clone on master that tracks master of the official repository gets git pull. Anything else (another branch, a
     * release tag, a fork, no upstream, or a worktree) gets an example to adapt, since its setup can't be known. A Git
     * type with no .git entry (chosen, not detected), or a clone whose HEAD or config can't be read, gets the plain git
     * pull, as the User Guide gives it, rather than being told it's set up another way. The commands
     * are joined with &&, so if the cd fails (the path is wrong on the server they're pasted into), nothing else runs.
     */
    private function gitSteps($context)
    {
        $cd = 'cd ' . self::shellQuote($context['root']);
        $git = $context['git'] ?? null;

        if ($git === null || ($git['pull_ready'] ?? null) !== false) {

            return array(sprintf(_('Run this command as the user who owns the %s files:'), SOFTWARE_TITLE)
                . $this->commandBlock(array($cd . ' && git pull')));

        }

        $version = $this->newerVersionOrPlaceholder($context);
        // TRANSLATORS: %1$s is a branch name (master). Don't translate it.
        $step = sprintf(_('Upgrades follow the %1$s branch and the release tags. This clone isn\'t on %1$s tracking the official repository (it could be on another branch or a release tag, a fork, or a worktree), so these commands are only an example. Adjust them to fit your setup, and run them as the user who owns the %2$s files:'),
            $this->code('master'), SOFTWARE_TITLE)
            . $this->commandBlock(array($cd . ' && git fetch --tags && git checkout --detach refs/tags/v' . $version));

        // TRANSLATORS: %s is a placeholder for a version number (X.Y.Z). Don't translate it.
        if ($version === 'X.Y.Z') $step .= sprintf(_('Replace %s with the newest version.'), $this->code('X.Y.Z'));

        return array($step);
    }

    private function dockerSteps($context)
    {
        $version = $this->versionOrPlaceholder($context);
        $steps = array();

        // The tag comes first, since pulling a tag the new release isn't under gets the same image again. The examples
        // are the installed version's exact and minor version tags, the ones a compose file pinned to it would name.
        $installed = explode('.', SOFTWARE_VERSION);
        // TRANSLATORS: %1$s and %2$s are version tags (such as 4.26.0 and 4.26) and %3$s the tag latest. Don't translate them or docker run.
        $steps[] = sprintf(_('If your compose file or docker run command names a version tag that the new version isn\'t under (such as %1$s or %2$s), change it to the new version, or to %3$s, before you pull.'),
            $this->code(SOFTWARE_VERSION), $this->code($installed[0] . '.' . ($installed[1] ?? '0')), $this->code('latest'));
        // Only DomainMOD's service, so a database in the same compose file isn't pulled or recreated with it
        // TRANSLATORS: %s is a placeholder in the commands (SERVICE). Don't translate it.
        $steps[] = sprintf(_('If you use Docker Compose, run these commands in the folder that has your compose file, with your DomainMOD service\'s name in place of %s:'),
                $this->code('SERVICE'))
            . $this->commandBlock(array('docker compose pull SERVICE && docker compose up -d --no-deps SERVICE'))
            // TRANSLATORS: %1$s and %2$s are command options (-p and -f) and %3$s a command (docker-compose). Don't translate them.
            . sprintf(_('Run them the way you usually run Compose for this stack, with the same options (such as %1$s or %2$s), or with %3$s if that\'s what you use, while the rest of the stack is running.'),
                $this->code('-p'), $this->code('-f'), $this->code('docker-compose'));
        // TRANSLATORS: %1$s is the tag latest. Don't translate it.
        $steps[] = sprintf(_('Otherwise, pull the new image, then stop and remove your container and create it again with the same settings. If your docker run command names a tag other than %1$s, use that tag in place of %1$s here:'),
                $this->code('latest'))
            . $this->commandBlock(array('docker pull ' . self::DOCKER_IMAGE . ':latest'));

        $notes = _('Restarting the same container doesn\'t upgrade it. It has to be created again from the new image.');

        // Not when the saved version is older than this one (checked before a manual upgrade), whose tags aren't the newest
        if ($version !== 'X.Y.Z' && version_compare($version, SOFTWARE_VERSION, '>=')) {

            $parts = explode('.', $version);
            $tags = array('latest', $parts[0], $parts[0] . '.' . $parts[1], $version);
            // TRANSLATORS: %s is a list of image tags (such as latest, 4, 4.27, 4.27.0). Don't translate them.
            $notes .= ' ' . sprintf(_('The newest image has these tags: %s.'),
                    implode(', ', array_map(array($this, 'code'), $tags)));

        }

        $steps[] = $notes;

        return $steps;
    }

    // A link that opens in a new tab, with its text escaped
    private function link($url, $text)
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    // A command, file name, or other code inside a sentence, escaped and kept left to right
    public function code($text)
    {
        return '<bdi dir="ltr"><code>' . htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8') . '</code></bdi>';
    }

} //@formatter:on

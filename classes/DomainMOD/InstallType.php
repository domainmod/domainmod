<?php
/**
 * /classes/DomainMOD/InstallType.php
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
 * Works out how DomainMOD was installed (Docker, a Git clone, or the zip file), so the Upgrade page can show the right
 * steps and the install and upgrade activity can say which kind of install it was. It only reads a few files: it never
 * runs a command, never reads outside the install folder apart from the fixed Docker paths, and treats anything it
 * can't read as "no signal", so it can't stop a page. Softaculous leaves nothing the app can read, so it's never
 * detected, only set (the $install_type override in config.inc.php, or the Upgrade page's switch).
 */
class InstallType
{
    // The types detect() can return, and the ones an admin can set or choose ('softaculous' is never detected)
    const DETECTED_TYPES = array('docker', 'git', 'zip', 'unknown');
    const CHOSEN_TYPES = array('docker', 'git', 'zip', 'softaculous');

    // The archive marker: git archive (the zip, GitHub's archive, and the Docker build) writes the release's commit
    // into this file (export-subst in .gitattributes), while a clone keeps the placeholder
    const MARKER_FILE = '_includes/release-commit.txt';

    // The variable the official Docker image sets (ENV in its Dockerfile)
    const DOCKER_ENV = 'DOMAINMOD_INSTALL_TYPE';

    public $root;
    public $dockerenv_file;
    public $entrypoint_file;
    public $docker_root;

    /*
     * $root is the install folder (DIR_ROOT). The other paths are where the official Docker image puts things, used for
     * images built before it set DOMAINMOD_INSTALL_TYPE. Tests point them all at fixture folders.
     */
    public function __construct($root = null, $dockerenv_file = '/.dockerenv',
                                $entrypoint_file = '/usr/local/bin/entrypoint.sh', $docker_root = '/var/www/html')
    {
        $this->root = rtrim($root ?? DIR_ROOT, '/\\');
        $this->dockerenv_file = $dockerenv_file;
        $this->entrypoint_file = $entrypoint_file;
        $this->docker_root = $docker_root;
    }

    /*
     * The install type and why. $override is the $install_type setting from config.inc.php (INSTALL_TYPE), which wins
     * when it's one of CHOSEN_TYPES. Returns an array with:
     *   'type'     docker, git, zip, or unknown (or softaculous, only from the override)
     *   'signals'  what was found, in the order checked: 'override', 'docker_marker' (the image's variable),
     *              'docker_files' (an older image's files), 'git_folder', 'git_file' (a worktree or submodule), and
     *              'archive_marker'
     *   'git'      for a .git entry: 'kind' (folder or file), 'branch' (the branch name, '' when HEAD isn't on a branch,
     *              or null when it can't be read), and 'pull_ready' (true on master tracking master of the official
     *              repository, so git pull is all it needs, false when it's set up another way, or null when HEAD or
     *              .git/config can't be read). 'git' is null when there's no .git entry.
     * The first match wins, in this order: the override, Docker, Git, then the zip.
     */
    public function detect($override = '')
    {
        $override = strtolower(trim((string) $override));
        $git = $this->gitDetails();
        $result = array('type' => 'unknown', 'signals' => array(), 'git' => $git);

        if (in_array($override, self::CHOSEN_TYPES, true)) {

            $result['type'] = $override;
            $result['signals'][] = 'override';
            return $result;

        }

        $docker_signal = $this->dockerSignal();
        if ($docker_signal !== '') {

            $result['type'] = 'docker';
            $result['signals'][] = $docker_signal;
            return $result;

        }

        if ($git !== null) {

            $result['type'] = 'git';
            $result['signals'][] = $git['kind'] === 'folder' ? 'git_folder' : 'git_file';
            return $result;

        }

        if ($this->hasArchiveMarker()) {

            $result['type'] = 'zip';
            $result['signals'][] = 'archive_marker';

        }

        return $result;
    }

    /*
     * Whether the web server can write to DomainMOD's own files, from a quick look at a few folders and one file (no
     * file is written). 'yes' when all of them are writable, 'no' when any isn't, and 'unknown' from the command line,
     * where the user running PHP usually isn't the web server's.
     */
    public function filesWritable()
    {
        if ($this->isCli()) return 'unknown';

        foreach (array('', '/_includes', '/classes/DomainMOD', '/vendor', '/index.php') as $path) {

            if (!@is_writable($this->root . $path)) return 'no';

        }

        return 'yes';
    }

    protected function isCli()
    {
        return PHP_SAPI === 'cli';
    }

    // 'docker_marker' when the official image's variable is set, 'docker_files' when an older image's files are all
    // there, or '' for neither
    protected function dockerSignal()
    {
        if ($this->envValue(self::DOCKER_ENV) === 'docker') return 'docker_marker';

        // Older images: /.dockerenv is a Docker convention, not a promise, so all three must be there
        $real_root = @realpath($this->root);
        if (@file_exists($this->dockerenv_file) && $real_root !== false && $real_root === $this->docker_root
            && @file_exists($this->entrypoint_file)) {

            return 'docker_files';

        }

        return '';
    }

    // An environment variable's value, or '' when it isn't set (getenv() reads the web server's environment too)
    protected function envValue($name)
    {
        $value = getenv($name);
        return $value === false ? '' : trim((string) $value);
    }

    // Whether the archive marker holds a commit hash (SHA-1, or SHA-256 for a repository that uses it)
    protected function hasArchiveMarker()
    {
        $contents = $this->readFile($this->root . '/' . self::MARKER_FILE);
        return $contents !== null && preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/', trim($contents)) === 1;
    }

    /*
     * The install folder's own .git entry, or null without one. Only the install folder itself is checked, so a zip
     * install inside a bigger repository isn't mistaken for a clone. A .git file (a worktree or submodule) points
     * outside the install folder, so its branch isn't read.
     */
    protected function gitDetails()
    {
        $git_path = $this->root . '/.git';

        if (@is_dir($git_path)) {

            // Unknown (null) when HEAD can't be read, or when it's on master and the config can't be read
            $branch = $this->gitBranch($git_path);
            $pull_ready = $branch === null ? null : ($branch === 'master' ? $this->tracksOfficialMaster($git_path) : false);
            return array('kind' => 'folder', 'branch' => $branch, 'pull_ready' => $pull_ready);

        }

        if (@is_file($git_path)) {

            return array('kind' => 'file', 'branch' => null, 'pull_ready' => false);

        }

        return null;
    }

    // The branch HEAD names, '' when HEAD isn't on a branch (such as a release tag), or null when it can't be read
    protected function gitBranch($git_path)
    {
        $head = $this->readFile($git_path . '/HEAD');
        if ($head === null) return null;

        if (preg_match('#^ref:\s*refs/heads/(\S+)\s*$#', trim($head), $matches)) return $matches[1];

        return '';
    }

    // Whether .git/config says master tracks the master branch of a remote whose URL is the official repository, or
    // null when the config can't be read
    protected function tracksOfficialMaster($git_path)
    {
        $config = $this->readFile($git_path . '/config');
        if ($config === null) return null;

        $sections = self::parseGitConfig($config);
        $branch = $sections['branch "master"'] ?? array();
        if (($branch['merge'] ?? '') !== 'refs/heads/master' || ($branch['remote'] ?? '') === '') return false;

        $remote = $sections['remote "' . $branch['remote'] . '"'] ?? array();
        return self::isOfficialRepository($remote['url'] ?? '');
    }

    /*
     * A Git config file's sections and their settings, as array('branch "master"' => array('merge' => '...')), enough
     * for the branch and remote sections. Section and setting names are lowercased (Git treats them that way), and a
     * subsection's name (in quotes) is kept as it is. A setting that's repeated keeps its last value, as Git reads
     * it, apart from a remote's url, which keeps its first (the one Git fetches from), and a branch's merge, which
     * becomes '' when it's set more than once (Git merges every one, so it isn't one branch).
     */
    public static function parseGitConfig($config)
    {
        $sections = array();
        $current = '';

        foreach (preg_split('/\r\n|\r|\n/', (string) $config) as $line) {

            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;

            // A section header can have a comment after it
            if (preg_match('/^\[\s*([A-Za-z0-9.-]+)(?:\s+"((?:[^"\\\\]|\\\\.)*)")?\s*\]\s*(?:[#;].*)?$/', $line, $matches)) {

                $current = strtolower($matches[1]);
                if (isset($matches[2])) $current .= ' "' . stripcslashes($matches[2]) . '"';
                continue;

            }

            if ($current !== '' && preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s*=\s*(.*)$/', $line, $matches)) {

                $key = strtolower($matches[1]);
                if ($key === 'url' && isset($sections[$current][$key])) continue;
                $value = self::gitConfigValue($matches[2]);
                if ($key === 'merge' && isset($sections[$current][$key])) $value = '';
                $sections[$current][$key] = $value;

            }

        }

        return $sections;
    }

    /*
     * A setting's value as Git reads it: double quotes are removed (keeping what's inside them), the escapes Git knows
     * (\" \\ \n \t \b) are turned into their characters, and a # or ; outside quotes starts a comment, which is left
     * out. Spaces inside quotes are kept. Git refuses a config with any other escape, so that value is '' (it can't
     * match anything).
     */
    public static function gitConfigValue($raw)
    {
        $value = '';
        $quoted = false;
        $length = strlen($raw);
        $escapes = array('"' => '"', '\\' => '\\', 'n' => "\n", 't' => "\t", 'b' => "\x08");
        $kept = 0; // the length up to the last quote, which trimming never cuts into

        for ($i = 0; $i < $length; $i++) {

            $character = $raw[$i];

            if ($character === '\\') {

                $next = $i + 1 < $length ? $raw[++$i] : '';
                if (!isset($escapes[$next])) return '';
                $value .= $escapes[$next];

            } elseif ($character === '"') {

                $quoted = !$quoted;
                $kept = strlen($value);

            } elseif (!$quoted && ($character === '#' || $character === ';')) {

                break;

            } else {

                $value .= $character;

            }

        }

        // Spaces outside quotes at the end are left out, as Git does, and spaces inside quotes are kept
        return substr($value, 0, $kept) . rtrim(substr($value, $kept));
    }

    // Whether a remote's URL is the official domainmod/domainmod repository on GitHub (HTTPS or SSH, with or without
    // .git at the end)
    public static function isOfficialRepository($url)
    {
        return preg_match('#^(?:https://(?:[^@/]+@)?github\.com/|ssh://git@github\.com/|git@github\.com:)'
            . 'domainmod/domainmod(?:\.git)?/?\z#i', (string) $url) === 1;
    }

    // A small file's contents, or null when it can't be read (including a path open_basedir hides)
    protected function readFile($path)
    {
        if (!@is_file($path) || !@is_readable($path)) return null;

        $contents = @file_get_contents($path, false, null, 0, 65536);
        return $contents === false ? null : $contents;
    }

} //@formatter:on

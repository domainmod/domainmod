<?php
/**
 * /tests/Unit/InstallTypeTest.php
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
namespace DomainMOD\Tests\Unit;

use DomainMOD\InstallType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * How the Upgrade page and the install and upgrade activity tell a Docker, Git, or zip install apart
 * (DomainMOD\InstallType). Each test builds its own install folder, and its own Docker files where it needs them, in a
 * temporary folder.
 */
class InstallTypeTest extends TestCase
{
    private const COMMIT = '0123456789abcdef0123456789abcdef01234567';
    private const OFFICIAL_CONFIG = "[core]\n\tbare = false\n[remote \"origin\"]\n\turl = https://github.com/domainmod/domainmod.git\n\tfetch = +refs/heads/*:refs/remotes/origin/*\n[branch \"master\"]\n\tremote = origin\n\tmerge = refs/heads/master\n";

    private $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/domainmod-install-type-' . bin2hex(random_bytes(6));
        mkdir($this->base . '/install/_includes', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->base);
    }

    public function testAPlainFolderIsUnknown()
    {
        $result = $this->detector()->detect();

        $this->assertSame('unknown', $result['type']);
        $this->assertSame(array(), $result['signals']);
        $this->assertNull($result['git']);
    }

    public function testTheArchiveMarkerWithACommitIsAZipInstall()
    {
        $this->marker(self::COMMIT . "\n");
        $result = $this->detector()->detect();

        $this->assertSame('zip', $result['type']);
        $this->assertSame(array('archive_marker'), $result['signals']);
    }

    public function testASha256CommitCountsToo()
    {
        $this->marker(str_repeat('ab', 32));

        $this->assertSame('zip', $this->detector()->detect()['type']);
    }

    public static function unsubstitutedMarkers()
    {
        return array(
            'the placeholder a clone keeps' => array('$Format:%H$'),
            'empty' => array(''),
            'a short hash' => array('0123456'),
            'capital letters' => array(strtoupper(self::COMMIT)),
            'something after the hash' => array(self::COMMIT . ' (HEAD -> master)'),
        );
    }

    #[DataProvider('unsubstitutedMarkers')]
    public function testAMarkerWithoutACommitIsNoSignal($contents)
    {
        $this->marker($contents);

        $this->assertSame('unknown', $this->detector()->detect()['type']);
    }

    public function testTheShippedMarkerIsThePlaceholderAndArchivesFillItIn()
    {
        $this->assertSame("\$Format:%H\$\n", file_get_contents(DIR_ROOT . '/' . InstallType::MARKER_FILE));

        $attributes = file_get_contents(DIR_ROOT . '/.gitattributes');
        $this->assertNotFalse($attributes);
        $this->assertMatchesRegularExpression('#^/' . preg_quote(InstallType::MARKER_FILE, '#') . '\s+export-subst\s*$#m', $attributes);
    }

    public function testAGitFolderIsAGitInstallEvenWithTheMarker()
    {
        $this->marker(self::COMMIT);
        $this->gitFolder("ref: refs/heads/master\n", self::OFFICIAL_CONFIG);
        $result = $this->detector()->detect();

        $this->assertSame('git', $result['type']);
        $this->assertSame(array('git_folder'), $result['signals']);
        $this->assertSame(array('kind' => 'folder', 'branch' => 'master', 'pull_ready' => true), $result['git']);
    }

    public static function officialUrls()
    {
        return array(
            'HTTPS with .git' => array('https://github.com/domainmod/domainmod.git'),
            'HTTPS without .git' => array('https://github.com/domainmod/domainmod'),
            'HTTPS with a slash at the end' => array('https://github.com/domainmod/domainmod/'),
            'HTTPS with a user name' => array('https://greg@github.com/domainmod/domainmod.git'),
            'another case' => array('https://GitHub.com/DomainMOD/DomainMOD.git'),
            'SSH' => array('git@github.com:domainmod/domainmod.git'),
            'SSH URL' => array('ssh://git@github.com/domainmod/domainmod.git'),
        );
    }

    #[DataProvider('officialUrls')]
    public function testRecognisesTheOfficialRepository($url)
    {
        $this->assertTrue(InstallType::isOfficialRepository($url));
    }

    public static function otherUrls()
    {
        return array(
            'a fork' => array('https://github.com/someone/domainmod.git'),
            'another repository' => array('https://github.com/domainmod/userguide.git'),
            'a longer name' => array('https://github.com/domainmod/domainmod-fork.git'),
            'another host' => array('https://gitlab.com/domainmod/domainmod.git'),
            'a look-alike host' => array('https://github.com.example.com/domainmod/domainmod.git'),
            'plain HTTP' => array('http://github.com/domainmod/domainmod.git'),
            'a local copy' => array('/srv/git/domainmod.git'),
            'a space at the end (kept from quotes)' => array('https://github.com/domainmod/domainmod.git '),
            'a newline at the end (kept from quotes)' => array("https://github.com/domainmod/domainmod.git\n"),
            'empty' => array(''),
        );
    }

    #[DataProvider('otherUrls')]
    public function testOtherRemotesAreNotTheOfficialRepository($url)
    {
        $this->assertFalse(InstallType::isOfficialRepository($url));
    }

    public static function notPullReady()
    {
        $fork = str_replace('domainmod/domainmod.git', 'someone/domainmod.git', self::OFFICIAL_CONFIG);
        $other_merge = str_replace('merge = refs/heads/master', 'merge = refs/heads/dev', self::OFFICIAL_CONFIG);
        $no_upstream = "[remote \"origin\"]\n\turl = https://github.com/domainmod/domainmod.git\n";
        $other_remote = str_replace('remote = origin', 'remote = upstream', self::OFFICIAL_CONFIG);

        return array(
            'another branch' => array("ref: refs/heads/dev\n", self::OFFICIAL_CONFIG, 'dev'),
            'a release tag (detached)' => array(self::COMMIT . "\n", self::OFFICIAL_CONFIG, ''),
            'a fork' => array("ref: refs/heads/master\n", $fork, 'master'),
            'master tracking another branch' => array("ref: refs/heads/master\n", $other_merge, 'master'),
            'no upstream' => array("ref: refs/heads/master\n", $no_upstream, 'master'),
            'an upstream remote that isn\'t set up' => array("ref: refs/heads/master\n", $other_remote, 'master'),
        );
    }

    #[DataProvider('notPullReady')]
    public function testOnlyMasterTrackingTheOfficialRepositoryIsReadyToPull($head, $config, $branch)
    {
        $this->gitFolder($head, $config);
        $result = $this->detector()->detect();

        $this->assertSame('git', $result['type']);
        $this->assertSame($branch, $result['git']['branch']);
        $this->assertFalse($result['git']['pull_ready']);
    }

    public function testAnUnreadableHeadHasNoBranchAndMightBeReadyToPull()
    {
        $this->gitFolder(null, self::OFFICIAL_CONFIG);
        $result = $this->detector()->detect();

        $this->assertSame('git', $result['type']);
        $this->assertNull($result['git']['branch']);
        $this->assertNull($result['git']['pull_ready']);
    }

    public function testMasterWithAnUnreadableConfigMightBeReadyToPull()
    {
        $this->gitFolder("ref: refs/heads/master\n", null);

        $this->assertNull($this->detector()->detect()['git']['pull_ready']);
    }

    public function testAnotherBranchWithAnUnreadableConfigIsNotReadyToPull()
    {
        $this->gitFolder("ref: refs/heads/dev\n", null);

        $this->assertFalse($this->detector()->detect()['git']['pull_ready']);
    }

    public function testAGitFileIsAWorktreeWithoutABranch()
    {
        file_put_contents($this->base . '/install/.git', "gitdir: /somewhere/else/.git/worktrees/install\n");
        $result = $this->detector()->detect();

        $this->assertSame('git', $result['type']);
        $this->assertSame(array('git_file'), $result['signals']);
        $this->assertSame(array('kind' => 'file', 'branch' => null, 'pull_ready' => false), $result['git']);
    }

    public function testOnlyTheInstallFolderItselfCounts()
    {
        // A zip install unpacked inside a bigger repository
        mkdir($this->base . '/.git');
        file_put_contents($this->base . '/.git/HEAD', "ref: refs/heads/master\n");
        $this->marker(self::COMMIT);

        $this->assertSame('zip', $this->detector()->detect()['type']);
    }

    public function testTheDockerVariableMakesItDocker()
    {
        $this->gitFolder("ref: refs/heads/master\n", self::OFFICIAL_CONFIG);
        $result = $this->detector('docker')->detect();

        $this->assertSame('docker', $result['type']);
        $this->assertSame(array('docker_marker'), $result['signals']);
    }

    public function testAnotherValueInTheVariableIsIgnored()
    {
        $this->marker(self::COMMIT);

        $this->assertSame('zip', $this->detector('zip')->detect()['type']);
    }

    public function testAnOlderImageNeedsAllThreeDockerSignals()
    {
        $this->dockerFiles(true, true);
        $result = $this->detector('', true)->detect();

        $this->assertSame('docker', $result['type']);
        $this->assertSame(array('docker_files'), $result['signals']);
    }

    public static function partialDockerSignals()
    {
        return array(
            'no /.dockerenv' => array(false, true, true),
            'no entrypoint' => array(true, false, true),
            'another install folder' => array(true, true, false),
        );
    }

    #[DataProvider('partialDockerSignals')]
    public function testAnyMissingDockerSignalIsNotDocker($dockerenv, $entrypoint, $same_root)
    {
        $this->dockerFiles($dockerenv, $entrypoint);

        $this->assertSame('unknown', $this->detector('', $same_root)->detect()['type']);
    }

    public static function overrides()
    {
        return array(
            'zip' => array('zip', 'zip'),
            'git' => array('git', 'git'),
            'docker' => array('docker', 'docker'),
            'softaculous' => array('softaculous', 'softaculous'),
            'capitals and spaces' => array(' Softaculous ', 'softaculous'),
        );
    }

    #[DataProvider('overrides')]
    public function testTheOverrideWinsOverEverySignal($override, $expected)
    {
        $this->gitFolder("ref: refs/heads/master\n", self::OFFICIAL_CONFIG);
        $result = $this->detector('docker')->detect($override);

        $this->assertSame($expected, $result['type']);
        $this->assertSame(array('override'), $result['signals']);
        // The Git details are still there, so a Git override knows whether git pull fits
        $this->assertTrue($result['git']['pull_ready']);
    }

    public static function ignoredOverrides()
    {
        return array(
            'empty' => array(''),
            'unknown' => array('unknown'),
            'a typo' => array('zipp'),
        );
    }

    #[DataProvider('ignoredOverrides')]
    public function testAnOverrideThatIsntATypeIsIgnored($override)
    {
        $this->marker(self::COMMIT);
        $result = $this->detector()->detect($override);

        $this->assertSame('zip', $result['type']);
        $this->assertSame(array('archive_marker'), $result['signals']);
    }

    public function testAMissingInstallFolderIsUnknown()
    {
        $detector = new InstallType($this->base . '/missing', $this->base . '/.dockerenv', $this->base . '/entrypoint.sh', $this->base . '/missing');

        $this->assertSame('unknown', $detector->detect()['type']);
    }

    public function testParsesGitConfigSections()
    {
        $sections = InstallType::parseGitConfig("# a comment\n[Branch \"Master\"]\n\tRemote = origin\n; another\n[remote \"origin\"]\n\turl = \"https://github.com/domainmod/domainmod.git\"\n[core]\nbare=false\n");

        $this->assertSame(array(
            'branch "Master"' => array('remote' => 'origin'),
            'remote "origin"' => array('url' => 'https://github.com/domainmod/domainmod.git'),
            'core' => array('bare' => 'false'),
        ), $sections);
    }

    public static function configValues()
    {
        return array(
            'plain' => array('https://github.com/domainmod/domainmod.git', 'https://github.com/domainmod/domainmod.git'),
            'a # comment' => array('https://github.com/domainmod/domainmod.git # upstream', 'https://github.com/domainmod/domainmod.git'),
            'a ; comment' => array('origin ; the main remote', 'origin'),
            'quoted' => array('"https://github.com/domainmod/domainmod.git"', 'https://github.com/domainmod/domainmod.git'),
            'a # inside quotes' => array('"a # b" # comment', 'a # b'),
            'an escaped quote' => array('a\\"b', 'a"b'),
            'an escaped backslash' => array('a\\\\b', 'a\\b'),
            'an escape Git refuses' => array('a\\#b', ''),
            'only a comment' => array('# nothing', ''),
            'spaces inside quotes are kept' => array('" origin " # comment', ' origin '),
            'spaces outside quotes are left out' => array('origin   ', 'origin'),
        );
    }

    #[DataProvider('configValues')]
    public function testReadsSettingValuesAsGitDoes($raw, $expected)
    {
        $this->assertSame($expected, InstallType::gitConfigValue($raw));
    }

    // Git fetches from a remote's first url, so a fork listed first isn't the official repository
    public static function remoteUrls()
    {
        return array(
            'the fork first' => array("\turl = https://github.com/someone/domainmod.git\n\turl = https://github.com/domainmod/domainmod.git\n", false),
            'the official repository first' => array("\turl = https://github.com/domainmod/domainmod.git\n\turl = https://github.com/someone/domainmod.git\n", true),
        );
    }

    public function testMasterMergingMoreThanOneBranchIsNotReadyToPull()
    {
        $this->gitFolder("ref: refs/heads/master\n", self::OFFICIAL_CONFIG . "\tmerge = refs/heads/dev\n");

        $this->assertFalse($this->detector()->detect()['git']['pull_ready']);
    }

    #[DataProvider('remoteUrls')]
    public function testOnlyTheFirstRemoteUrlCounts($urls, $expected)
    {
        $this->gitFolder("ref: refs/heads/master\n", "[remote \"origin\"]\n" . $urls . "[branch \"master\"]\n\tremote = origin\n\tmerge = refs/heads/master\n");

        $this->assertSame($expected, $this->detector()->detect()['git']['pull_ready']);
    }

    public function testAnOfficialCloneWithCommentsIsStillReadyToPull()
    {
        $this->gitFolder("ref: refs/heads/master\n", "[remote \"origin\"] # the official one\n\turl = https://github.com/domainmod/domainmod.git # upstream\n[branch \"master\"]\n\tremote = origin ; tracked\n\tmerge = refs/heads/master # master\n");

        $this->assertTrue($this->detector()->detect()['git']['pull_ready']);
    }

    public function testFilesAreWritableOnlyWhenEveryPathIs()
    {
        $this->appFiles();
        $this->assertSame('yes', $this->detector('', false, false)->filesWritable());

        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root, which can write to read-only files');
        }

        chmod($this->base . '/install/index.php', 0400);
        $this->assertSame('no', $this->detector('', false, false)->filesWritable());
        chmod($this->base . '/install/index.php', 0600);
    }

    public function testAMissingPathIsNotWritable()
    {
        $this->appFiles();
        rmdir($this->base . '/install/vendor');

        $this->assertSame('no', $this->detector('', false, false)->filesWritable());
    }

    public function testTheCommandLineDoesNotKnowWhetherTheWebServerCanWrite()
    {
        $this->appFiles();

        $this->assertSame('unknown', $this->detector()->filesWritable());
    }

    // An InstallType for the test's install folder. $env is DOMAINMOD_INSTALL_TYPE's value, $same_root says whether the
    // Docker install folder is the test's install folder, and $cli whether it's running from the command line.
    private function detector($env = '', $same_root = false, $cli = true)
    {
        $root = $this->base . '/install';
        $docker_root = $same_root ? realpath($root) : '/var/www/html';

        return new class($root, $this->base . '/.dockerenv', $this->base . '/entrypoint.sh', $docker_root, $env, $cli) extends InstallType {
            private $env;
            private $cli;

            public function __construct($root, $dockerenv_file, $entrypoint_file, $docker_root, $env, $cli)
            {
                parent::__construct($root, $dockerenv_file, $entrypoint_file, $docker_root);
                $this->env = $env;
                $this->cli = $cli;
            }

            protected function envValue($name)
            {
                return $name === InstallType::DOCKER_ENV ? $this->env : '';
            }

            protected function isCli()
            {
                return $this->cli;
            }
        };
    }

    private function marker($contents)
    {
        file_put_contents($this->base . '/install/' . InstallType::MARKER_FILE, $contents);
    }

    // A .git folder with HEAD and config (either left out when null)
    private function gitFolder($head, $config)
    {
        mkdir($this->base . '/install/.git');
        if ($head !== null) file_put_contents($this->base . '/install/.git/HEAD', $head);
        if ($config !== null) file_put_contents($this->base . '/install/.git/config', $config);
    }

    private function dockerFiles($dockerenv, $entrypoint)
    {
        if ($dockerenv) file_put_contents($this->base . '/.dockerenv', '');
        if ($entrypoint) file_put_contents($this->base . '/entrypoint.sh', "#!/bin/bash\n");
    }

    // The folders and file filesWritable() looks at
    private function appFiles()
    {
        mkdir($this->base . '/install/classes/DomainMOD', 0700, true);
        mkdir($this->base . '/install/vendor', 0700);
        file_put_contents($this->base . '/install/index.php', '');
    }

    private function remove($path)
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }

} //@formatter:on

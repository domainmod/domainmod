<?php
/**
 * /tests/Unit/UpgradeGuideTest.php
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

use DomainMOD\UpgradeGuide;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The Upgrade page's steps for each kind of install (DomainMOD\UpgradeGuide). SOFTWARE_TITLE is a constant, so each test
 * runs in its own process and defines it itself.
 */
#[RunTestsInSeparateProcesses]
class UpgradeGuideTest extends TestCase
{
    protected function setUp(): void
    {
        define('SOFTWARE_TITLE', 'DomainMOD');
        define('SOFTWARE_VERSION', '4.27.0');
    }

    public static function quoting()
    {
        return array(
            'a plain path' => array('/var/www/html/dm', '/var/www/html/dm'),
            'a space' => array('/home/me/public html/dm', "'/home/me/public html/dm'"),
            'a quote' => array("/srv/greg's site", "'/srv/greg'\\''s site'"),
            'a dollar sign' => array('/srv/$HOME', "'/srv/\$HOME'"),
            'a semicolon' => array('/srv/a;rm -rf x', "'/srv/a;rm -rf x'"),
            'a backtick' => array('/srv/`id`', "'/srv/`id`'"),
            'empty' => array('', "''"),
            'a newline at the end' => array("/srv/dm\n", "'/srv/dm\n'"),
        );
    }

    #[DataProvider('quoting')]
    public function testQuotesPathsForTheShell($path, $expected)
    {
        $this->assertSame($expected, UpgradeGuide::shellQuote($path));
    }

    public function testTheZipStepsNameThePathAndTheDownload()
    {
        $html = (new UpgradeGuide())->steps('zip', $this->context());

        $this->assertStringContainsString('<bdi dir="ltr">/var/www/html/dm</bdi>', $html);
        $this->assertStringContainsString('href="https://domainmod.org/download/"', $html);
        $this->assertStringContainsString('_includes/config.inc.php', $html);
        $this->assertStringContainsString('href="/dm/admin/backup/"', $html);
        $this->assertStringContainsString('Restart PHP', $html);
        $this->assertStringContainsString('php update.php', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    public function testACloneReadyToPullGetsGitPull()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('git' => array('kind' => 'folder', 'branch' => 'master', 'pull_ready' => true))));

        $this->assertStringContainsString(">cd /var/www/html/dm &amp;&amp; git pull</code>", $html);
        $this->assertStringNotContainsString('git checkout', $html);
        $this->assertStringContainsString('the user who owns', $html);
    }

    public function testAnyOtherCloneGetsAnExampleWithTheNewestVersion()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('git' => array('kind' => 'folder', 'branch' => 'dev', 'pull_ready' => false))));

        $this->assertStringContainsString(">cd /var/www/html/dm &amp;&amp; git fetch --tags &amp;&amp; git checkout --detach refs/tags/v4.28.0</code>", $html);
        $this->assertStringContainsString('only an example', $html);
        $this->assertStringNotContainsString('X.Y.Z', $html);
    }

    public static function notNewer()
    {
        return array(
            'the installed version' => array('4.27.0'),
            'an older version' => array('4.26.0'),
        );
    }

    // A saved version from before a manual upgrade would check out older code
    #[DataProvider('notNewer')]
    public function testTheExampleNeverChecksOutAVersionThatIsntNewer($version)
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('version' => $version, 'git' => array('kind' => 'folder', 'branch' => '', 'pull_ready' => false))));

        $this->assertStringContainsString('git checkout --detach refs/tags/vX.Y.Z</code>', $html);
        $this->assertStringNotContainsString('git checkout --detach refs/tags/v' . $version, $html);
    }

    // Joined with &&, a cd to a path that isn't there stops the git commands running in another folder
    public function testTheGitCommandsOnlyRunAfterTheCdWorks()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('git' => array('kind' => 'folder', 'branch' => 'dev', 'pull_ready' => false))));

        $this->assertSame(1, preg_match('#<code id="domainmod-command-\d+">([^<]*)</code>#', $html, $matches));
        $this->assertStringNotContainsString("\n", $matches[1]);
        $this->assertStringStartsWith('cd /var/www/html/dm &amp;&amp; ', $matches[1]);
    }

    public function testTheTagStepComesBeforeTheDockerCommands()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context());

        $tag = strpos($html, 'before you pull');
        $this->assertNotFalse($tag);
        $this->assertLessThan(strpos($html, 'docker compose pull'), $tag);
        $this->assertLessThan(strpos($html, 'docker pull domainmod'), $tag);
    }

    public function testWithoutTheNewestVersionTheExampleHasAPlaceholder()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('version' => '', 'git' => array('kind' => 'file', 'branch' => null, 'pull_ready' => false))));

        $this->assertStringContainsString('git checkout --detach refs/tags/vX.Y.Z</code>', $html);
        $this->assertStringContainsString('Replace <bdi dir="ltr"><code>X.Y.Z</code></bdi> with the newest version.', $html);
    }

    // When HEAD or the config can't be read, the clone may well be on master, so it isn't told it's set up another way
    public function testAnUnreadableCloneGetsThePlainPull()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('git' => array('kind' => 'folder', 'branch' => null, 'pull_ready' => null))));

        $this->assertStringContainsString('&amp;&amp; git pull</code>', $html);
        $this->assertStringNotContainsString('only an example', $html);
    }

    public function testTheTagExampleIsTheInstalledVersion()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context());

        $this->assertStringContainsString('(such as <bdi dir="ltr"><code>4.27.0</code></bdi> or <bdi dir="ltr"><code>4.27</code></bdi>)', $html);
    }

    public function testAChosenGitTypeWithoutAGitFolderGetsThePlainPull()
    {
        $html = (new UpgradeGuide())->steps('git', $this->context(array('git' => null)));

        $this->assertStringContainsString("git pull</code>", $html);
    }

    public function testTheDockerStepsListTheCommandsAndTagsWithoutAPhpRestart()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context());

        $this->assertStringContainsString(">docker compose pull SERVICE &amp;&amp; docker compose up -d --no-deps SERVICE</code>", $html);
        $this->assertStringContainsString('>docker pull domainmod/domainmod:latest</code>', $html);
        $this->assertStringContainsString('<code>latest</code></bdi>, <bdi dir="ltr"><code>4</code></bdi>, <bdi dir="ltr"><code>4.28</code></bdi>, <bdi dir="ltr"><code>4.28.0</code></bdi>', $html);
        $this->assertStringContainsString('Restarting the same container', $html);
        $this->assertStringContainsString('while the rest of the stack is running', $html);
        $this->assertStringContainsString('<code>docker exec CONTAINER php update.php</code>', $html);
        $this->assertStringContainsString('stop and remove your container', $html);
        $this->assertStringNotContainsString('Restart PHP', $html);
        $this->assertStringNotContainsString('/var/www/html/dm', $html);
    }

    public function testAnOlderSavedVersionLeavesTheTagsOut()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context(array('version' => '4.26.0')));

        $this->assertStringNotContainsString('these tags', $html);
    }

    public function testTheInstalledVersionsTagsAreShownWhenItsTheNewest()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context(array('version' => '4.27.0')));

        $this->assertStringContainsString('<code>4.27.0</code></bdi>.', $html);
    }

    public function testTheDockerPullUsesTheSameTagAsTheRunCommand()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context());

        $this->assertStringContainsString('use that tag in place of', $html);
    }

    public function testWithoutTheNewestVersionDockerLeavesTheTagsOut()
    {
        $html = (new UpgradeGuide())->steps('docker', $this->context(array('version' => 'not a version')));

        $this->assertStringNotContainsString('these tags', $html);
    }

    public function testSoftaculousLinksToItsInstructions()
    {
        $html = (new UpgradeGuide())->steps('softaculous', $this->context());

        $this->assertStringContainsString('href="' . UpgradeGuide::SOFTACULOUS_URL . '"', $html);
        $this->assertStringContainsString('Restart PHP', $html);
    }

    public function testAnUnknownTypeShowsEveryTypeUnderItsOwnHeading()
    {
        $html = (new UpgradeGuide())->steps('unknown', $this->context());

        foreach (array('Zip File', 'Git', 'Docker', 'Softaculous') as $name) {
            $this->assertStringContainsString('<h3 class="h6 mt-3">' . $name . '</h3>', $html);
        }
        $this->assertSame(4, substr_count($html, '<ol'));
    }

    public function testEveryCommandBlockHasItsOwnCopyButton()
    {
        $html = (new UpgradeGuide())->steps('unknown', $this->context());

        // Git has one block and Docker two
        $this->assertSame(3, preg_match_all('/<code id="(domainmod-command-\d+)">/', $html, $ids));
        $this->assertSame($ids[1], array_unique($ids[1]));
        foreach ($ids[1] as $id) {
            $this->assertStringContainsString('data-domainmod-copy="' . $id . '"', $html);
        }
        $this->assertSame(3, substr_count($html, ' hidden><i class="bi bi-clipboard'));
    }

    public function testPathsAreEscapedAndQuoted()
    {
        $context = $this->context(array('root' => '/srv/<b>"me" & you\'s</b>', 'git' => null));
        $guide = new UpgradeGuide();

        $git = $guide->steps('git', $context);
        $this->assertStringNotContainsString('<b>', $git);
        $this->assertStringContainsString('cd &#039;/srv/&lt;b&gt;&quot;me&quot; &amp; you&#039;\&#039;&#039;s&lt;/b&gt;&#039; &amp;&amp; git pull', $git);

        $zip = $guide->steps('zip', $context);
        $this->assertStringNotContainsString('<b>', $zip);
        $this->assertStringContainsString('<bdi dir="ltr">/srv/&lt;b&gt;&quot;me&quot; &amp; you&#039;s&lt;/b&gt;</bdi>', $zip);
    }

    public function testTheVersionIsEscapedWhereverItsUsed()
    {
        // Only a release's version is used, so anything else falls back to the placeholder
        $html = (new UpgradeGuide())->steps('git', $this->context(array('version' => '4.28.0<script>', 'git' => array('kind' => 'folder', 'branch' => '', 'pull_ready' => false))));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('git checkout --detach refs/tags/vX.Y.Z', $html);
    }

    public static function signals()
    {
        return array(
            'override' => array('override', '<code>$install_type</code>'),
            'docker_marker' => array('docker_marker', 'Docker image says so'),
            'docker_files' => array('docker_files', 'Docker image adds'),
            'git_folder' => array('git_folder', '<code>.git</code></bdi> folder'),
            'git_file' => array('git_file', 'worktree or submodule'),
            'archive_marker' => array('archive_marker', 'release archive'),
            'none' => array('', 'No sign of a Docker, Git, or zip file installation was found.'),
        );
    }

    #[DataProvider('signals')]
    public function testExplainsEachSignal($signal, $expected)
    {
        $this->assertStringContainsString($expected, (new UpgradeGuide())->signalText($signal));
    }

    public function testNamesEachType()
    {
        $guide = new UpgradeGuide();

        $this->assertSame('Zip File', $guide->typeName('zip'));
        $this->assertSame('Softaculous', $guide->typeName('softaculous'));
        $this->assertSame('Not Sure', $guide->typeName('unknown'));
        $this->assertSame('Not Sure', $guide->typeName('something else'));
    }

    private function context($changes = array())
    {
        return array_merge(array('root' => '/var/www/html/dm', 'version' => '4.28.0', 'git' => null,
                                 'backup_url' => '/dm/admin/backup/'), $changes);
    }

} //@formatter:on

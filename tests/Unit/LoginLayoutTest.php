<?php
/**
 * /tests/Unit/LoginLayoutTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * The sign-in layout (_includes/layout/header-login.inc.php) opens the page's login box itself, so a page that uses it
 * never opens another. A second, nested box came out narrower and pinned to the left on phones (reset.php, found at
 * 4.27.0's [K]).
 */
class LoginLayoutTest extends TestCase
{

    public function testNoPageNestsASecondLoginBox()
    {
        $root = dirname(__DIR__, 2);
        $this->assertStringContainsString('<div class="login-box">', file_get_contents($root . '/_includes/layout/header-login.inc.php'));

        $pages = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            if ($file->getExtension() !== 'php' || preg_match('#^(vendor|tests|\.data|\.git|_includes)/#', $path)) continue;
            $code = file_get_contents($file->getPathname());
            if (strpos($code, "/layout/header-login.inc.php'") === false) continue;
            $pages++;
            $this->assertDoesNotMatchRegularExpression('/class=["\'][^"\']*(?<![\\w-])login-box(?![\\w-])/', $code, $path);
        }
        $this->assertGreaterThanOrEqual(3, $pages);
    }

} //@formatter:on

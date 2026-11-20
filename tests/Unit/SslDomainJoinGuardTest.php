<?php
/**
 * /tests/Unit/SslDomainJoinGuardTest.php
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
 * A guard against bringing back an inner join between SSL certificates and their domain, which would hide every
 * certificate with no domain. It reads every PHP file in the folders that query certificates (comments left out) and
 * fails on:
 *   - sslc.domain_id = d.id (or d.id = sslc.domain_id) outside an ON condition. A condition handed over as data to be
 *     written as a LEFT JOIN's ON (the fourth element of an SslList::JOIN_ORDER entry, and a CostReport grouping's
 *     left_join 'on') counts as an ON condition.
 *   - a string holding ssl_certs and a comma list entry for domains (such as "ssl_certs AS sslc, domains AS d").
 *   - any JOIN domains that isn't a LEFT JOIN (STRAIGHT_JOIN included), in a query that reads ssl_certs.
 * ALLOWED holds the one deliberate inner join: the SSL list's Domain filter menu, which lists domains, so only
 * certificates with one matter there. Each allowed query must still be in its file, so the list can't go stale.
 */
class SslDomainJoinGuardTest extends TestCase
{
    const FOLDERS = array('classes', 'ssl', 'reporting', 'dashboard', 'bulk', 'domains', 'queue', '_includes/layout');

    const ALLOWED = array(
        'ssl/index.php' => array('FROM ssl_certs AS sslc JOIN domains AS d ON d.id = sslc.domain_id'),
    );

    public function testNoQueryInnerJoinsCertificatesToTheirDomain()
    {
        $problems = array();
        $files = $this->files();
        $this->assertNotEmpty($files);

        foreach ($files as $path => $relative) {
            $problems = array_merge($problems, self::problems($relative, (string) file_get_contents($path)));
        }

        $this->assertSame(array(), $problems, implode("\n", $problems));
    }

    // The checks catch each form of the inner join, and leave the LEFT JOIN forms and the domain subqueries alone
    public function testTheChecksCatchEachInnerJoin()
    {
        $bad = array(
            '<?php $sql = "SELECT * FROM ssl_certs AS sslc, domains AS d WHERE sslc.domain_id = d.id";',
            '<?php $sql = "SELECT * FROM domains AS d, ssl_certs AS sslc WHERE d.id = sslc.domain_id";',
            '<?php $sql = "FROM ssl_certs AS sslc, ssl_fees AS f, domains AS d";',
            '<?php $sql = "FROM ssl_certs AS sslc INNER JOIN domains AS d ON d.id = sslc.domain_id";',
            '<?php $sql = "FROM ssl_certs AS sslc STRAIGHT_JOIN domains AS d";',
            "<?php \$where = array('sslc.domain_id = d.id');",
            '<?php $sql = "SELECT * FROM ssl_certs AS sslc JOIN domains AS d ON d.id = sslc.domain_id WHERE sslc.active = 1";',
        );
        foreach ($bad as $code) {
            $this->assertNotEmpty(self::problems('example.php', $code), $code);
        }

        $good = array(
            '<?php $sql = "FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ssl_fees AS f";',
            '<?php $sql = "FROM ssl_providers AS sslp, ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id";',
            '<?php $sql = "LEFT JOIN domains AS d ON sslc.domain_id = d.id";',
            '<?php $sql = "LEFT OUTER JOIN domains AS d ON sslc.domain_id = d.id";',
            '<?php $sql = " AND NOT EXISTS (SELECT 1 FROM domains AS nd WHERE nd.id = sslc.domain_id) ";',
            '<?php $sql = "SELECT * FROM domains AS d, fees AS f WHERE d.fee_id = f.id";',
            '<?php $sql = "SELECT d.domain FROM domains AS d JOIN fees AS f ON f.id = d.fee_id JOIN registrars AS r ON r.id = d.registrar_id";',
            '<?php $sql = "SELECT dq.id FROM domain_queue AS dq JOIN domains AS d ON d.id = dq.domain_id";',
            "<?php const JOIN_ORDER = array(array('domains', 'd', 'PRIMARY', 'd.id = sslc.domain_id'));",
            "<?php \$join = array('table' => 'domains', 'alias' => 'd', 'on' => 'd.id = sslc.domain_id');",
            "<?php // a comment about FROM ssl_certs AS sslc, domains AS d WHERE sslc.domain_id = d.id\n",
        );
        foreach ($good as $code) {
            $this->assertSame(array(), self::problems('example.php', $code), $code);
        }

        // The allowed query is allowed only in its own file, and a missing one is reported
        $menu = '<?php $sql = "SELECT d.id FROM ssl_certs AS sslc JOIN domains AS d ON d.id = sslc.domain_id WHERE d.active = 1";';
        $this->assertSame(array(), self::problems('ssl/index.php', $menu));
        $this->assertNotEmpty(self::problems('ssl/other.php', $menu));
        $this->assertNotEmpty(self::problems('ssl/index.php', '<?php $sql = "SELECT 1";'));
    }

    // Every PHP file under the folders, as its full path => its path from the repository root
    private function files()
    {
        $root = dirname(__DIR__, 2);
        $files = array();
        foreach (self::FOLDERS as $folder) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $folder,
                \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;
                $files[$file->getPathname()] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        ksort($files);
        return $files;
    }

    // What's wrong in one file's PHP $source, each as "file: problem", or none
    private static function problems($relative, $source)
    {
        $problems = array();
        list($code, $strings) = self::codeAndStrings($source);

        foreach (self::ALLOWED[$relative] ?? array() as $allowed) {
            if (strpos($code, $allowed) === false) {
                $problems[] = $relative . ': the allowed query "' . $allowed . '" is gone, so remove it from ALLOWED';
                continue;
            }
            $code = str_replace($allowed, ' ', $code);
            $strings = array_map(function ($string) use ($allowed) {
                return str_replace($allowed, ' ', $string);
            }, $strings);
        }

        // The join condition outside an ON
        preg_match_all('/sslc\.domain_id\s*=\s*d\.id\b|\bd\.id\s*=\s*sslc\.domain_id/i', $code, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $before = substr($code, max(0, $match[1] - 60), min(60, $match[1]));
            if (preg_match("/(\\bON\\s+|'on'\\s*=>\\s*'|array\\('domains',\\s*'\\w+',\\s*'\\w*',\\s*')$/i", $before)) continue;
            $problems[] = $relative . ': "' . $match[0] . '" outside an ON condition';
        }

        // JOIN domains that isn't a LEFT JOIN, in a query that reads the SSL certificates
        foreach ($strings as $string) {
            if (!preg_match('/\bssl_certs\b|\bsslc\./i', $string)) continue;
            preg_match_all('/JOIN\s+`?domains`?\b/i', $string, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $before = substr($string, max(0, $match[1] - 20), min(20, $match[1]));
                if (preg_match('/\bLEFT\s+(OUTER\s+)?$/i', $before)) continue;
                $problems[] = $relative . ': "' . trim(substr($before, -12) . $match[0]) . '" isn\'t a LEFT JOIN';
            }
        }

        // A string with ssl_certs and domains as a comma list entry
        foreach ($strings as $string) {
            if (!preg_match('/\bssl_certs\b/i', $string)) continue;
            if (preg_match('/,\s*`?domains`?\b|\b`?domains`?(\s+AS)?(\s+\w+)?\s*,/i', $string, $match)) {
                $problems[] = $relative . ': "' . trim($match[0]) . '" joins the domains in a comma list with ssl_certs';
            }
        }

        return $problems;
    }

    /*
     * The source without its comments, and each string literal's text (a double-quoted or heredoc string with
     * variables in it as one text, each variable a space)
     */
    private static function codeAndStrings($source)
    {
        $code = '';
        $strings = array();
        $current = null;

        foreach (token_get_all($source) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                $code .= str_repeat("\n", substr_count($text, "\n")) . ' ';
                continue;
            }
            $code .= $text;

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $strings[] = $text;
            } elseif ($text === '"' || $id === T_START_HEREDOC || $id === T_END_HEREDOC) {
                if ($current === null) {
                    $current = '';
                } else {
                    $strings[] = $current;
                    $current = null;
                }
            } elseif ($current !== null) {
                $current .= $id === T_ENCAPSED_AND_WHITESPACE ? $text : ' ';
            }
        }

        return array($code, $strings);
    }

} //@formatter:on

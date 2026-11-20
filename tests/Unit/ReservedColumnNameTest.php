<?php
/**
 * /tests/Unit/ReservedColumnNameTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Column names that a supported database reserves have to be quoted with backticks in the SQL. MariaDB 12.3 reserves
 * CONVERSION, so SQL that named currency_conversions' conversion column on its own (SET conversion = :rate) was a
 * syntax error (1064) there, and the Dashboard failed right after sign-in. A name after a table alias (cc.conversion)
 * is always read as a column, so only the name on its own needs the backticks. The strings in DomainMOD's own PHP
 * files are checked, the old update files included, since an upgrade from an old version still runs them.
 */
class ReservedColumnNameTest extends TestCase
{
    // Add a name here whenever a supported database reserves another of DomainMOD's column names.
    const RESERVED = array('conversion');

    // The folders that hold no DomainMOD SQL: libraries, the theme, private notes, translations, the tests (whose
    // examples below are bare on purpose), and the saved caches.
    const SKIPPED = array('.data', '.git', 'locales', 'node_modules', 'temp', 'tests', 'theme', 'vendor');

    // Local scratch files for trying out classes by hand, which git ignores, so they aren't part of DomainMOD.
    const SKIPPED_FILES = array('/modules/test.php', '/test.php');

    /*
     * Every string in some PHP code as PHP builds it, with its line: literals joined by the . operator are one string,
     * a value joined to them (such as $timestamp or $time->stamp()) or written into a double-quoted string or heredoc
     * (such as $id or {$row['id']}) becomes 1, and the escapes a double-quoted string reads (such as \n) are decoded.
     * The strings inside a joined value's brackets (such as a ternary's or a call's arguments) are listed separately.
     */
    private function strings($code)
    {
        $strings = array();
        $text = null;
        $text_line = 0;
        $line = 1;
        $quoted = false;
        $raw = false;
        $depth = 0; // inside {$...} or ${...} in a double-quoted string or heredoc
        $nest = 0; // inside brackets in a value joined to the string, whose code is checked on its own
        $nested = '';
        $nested_line = 0;
        $joined = false; // after the . operator
        $joined_value = false; // in a value joined to the string
        $value_start = array(T_VARIABLE, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_LNUMBER,
            T_DNUMBER, '(');
        $value_part = array(T_VARIABLE, T_STRING, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, '(', '[',
            '{');
        foreach (token_get_all($code) as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $value = is_array($token) ? $token[1] : $token;
            if ($depth > 0) {
                if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                    $depth++;
                } elseif ($id === '}') {
                    $depth--;
                }
            } elseif ($nest > 0) {
                if (in_array($id, array('(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) {
                    $nest++;
                } elseif (in_array($id, array(')', ']', '}'), true)) {
                    $nest--;
                }
                if ($nest > 0) {
                    $nested .= $value;
                } else {
                    foreach ($this->strings('<?php ' . $nested) as $string) {
                        $strings[] = array($nested_line + $string[0] - 1, $string[1]);
                    }
                }
            } elseif ($quoted && ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES)) {
                $depth = 1;
                $text .= '1';
            } elseif (in_array($id, array('"', 'b"', 'B"'), true) || $id === T_START_HEREDOC || $id === T_END_HEREDOC
                || $id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                if ($text === null) {
                    $text = '';
                    $text_line = $line;
                }
                $joined = $joined_value = false;
                if (in_array($id, array('"', 'b"', 'B"'), true)) {
                    $quoted = !$quoted;
                } elseif ($id === T_START_HEREDOC) {
                    $quoted = true;
                    $raw = strpos($value, "'") !== false; // a nowdoc
                } elseif ($id === T_END_HEREDOC) {
                    $quoted = $raw = false;
                } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
                    $text .= $raw ? $value : $this->decode($value);
                } else {
                    $value = ltrim($value, 'bB'); // a binary string, such as b'...'
                    $text .= $value[0] === "'" ? str_replace(array('\\\\', "\\'"), array('\\', "'"), substr($value, 1, -1))
                        : $this->decode(substr($value, 1, -1));
                }
            } elseif ($quoted) {
                $text .= '1';
            } elseif ($text === null || in_array($id, array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                // not in a string, or between its parts
            } elseif ($id === '.') {
                $joined = true;
                $joined_value = false;
            } elseif (($joined && in_array($id, $value_start, true)) || ($joined_value && in_array($id, $value_part, true))) {
                if ($joined) $text .= ' 1 '; // spaced, since the value could be empty
                $joined = false;
                $joined_value = true;
                if (in_array($id, array('(', '[', '{'), true)) {
                    $nest = 1;
                    $nested = '';
                    $nested_line = $line;
                }
            } else {
                $strings[] = array($text_line, $text);
                $text = null;
                $joined = $joined_value = false;
            }
            $line += substr_count($value, "\n");
        }
        if ($text !== null) $strings[] = array($text_line, $text);

        return $strings;
    }

    private function decode($value)
    {
        return preg_replace_callback('/\\\\(?:([nrtvef\\\\$"])|[xX]([0-9A-Fa-f]{1,2})|([0-7]{1,3})|u\{([0-9A-Fa-f]+)\})/',
            function ($match) {
                $escapes = array('n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f");
                if (($match[2] ?? '') !== '') return chr(hexdec($match[2]));
                if (($match[3] ?? '') !== '') return chr(octdec($match[3]) & 255);
                if (($match[4] ?? '') !== '') return html_entity_decode('&#x' . $match[4] . ';', ENT_QUOTES, 'UTF-8');
                return $escapes[$match[1]] ?? $match[1];
            }, $value);
    }

    /*
     * The uses of a column name on its own in a string's SQL. The SQL's quoted strings, backticked names, and comments
     * are left out (but not executable comments, which start /*! or /*M!, since a database can run them), as are names
     * after a table alias (cc.name or cc . name), placeholders (:name), variables (@name), and longer names (name_id).
     * DomainMOD writes SQL keywords in capitals, so in a string with no keyword and no executable comment, which is
     * text or a piece of SQL joined to others in code, only a name after SQL punctuation (an opening bracket, a comma,
     * or an operator) or at the start, and before SQL punctuation or at the end, counts, as in this piece of SQL:
     * '.total_cost * name)'. A name on its own, such as a placeholder's 'name' in bindValue(), doesn't. The patterns
     * are possessive, so a long quoted string can't overflow PCRE's stack.
     */
    private function bareUses($text, $name)
    {
        $sql = preg_replace_callback('/\'[^\'\\\\]*+(?:(?:\\\\.|\'\')[^\'\\\\]*+)*+\'|"[^"\\\\]*+(?:(?:\\\\.|"")[^"\\\\]*+)*+"'
            . '|`[^`]*+(?:``[^`]*+)*+`|\/\*(?!M?!)(?:[^*]++|\*(?!\/))*+(?:\*\/|$)|(?:--(?=\s)|#)[^\n]*+/s', function ($match) {
                return in_array($match[0][0], array("'", '"', '`'), true) ? "''" : ' ';
            }, $text);
        $this->assertIsString($sql);
        $sql = preg_replace('/\s*+\.\s*+/', '.', $sql);
        $this->assertIsString($sql);
        $bare = '(?<![\w.$:@])' . preg_quote($name, '/') . '(?!\w)';
        $keywords = 'SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|ADD|CHANGE|MODIFY|COLUMN|AFTER|FROM|INTO|JOIN|ON'
            . '|WHERE|AND|OR|NOT|IS|IN|LIKE|BETWEEN|HAVING|BY|ASC|DESC|LIMIT|SET|VALUES|AS|DISTINCT|CASE|WHEN|THEN|ELSE|END';
        if (!preg_match('/\b(?:' . $keywords . ')\b|\/\*M?!/', $sql)) {
            // A minus counts only with a space on either side, so slugs such as update-name-rates don't.
            $before = '(?:[(,=<>+*\/]\s*+|\s-\s*+|-\s++)';
            $after = '(?:\s*+(?:[),=<>+*\/]|!=)|\s++-|-\s)';
            $bare = '(?:' . $before . $bare . '(?=\s*+$|' . $after . ')|^\s*+' . $bare . '(?=' . $after . '))';
        }
        $this->assertNotFalse(preg_match_all('/' . $bare . '/i', $sql, $matches));

        return $matches[0];
    }

    public static function codeProvider()
    {
        return array(
            'insert column list' => array('$q = "INSERT INTO currency_conversions\n(currency_id, user_id, conversion, insert_time)";', 1),
            'update' => array("\$q = 'UPDATE currency_conversions SET conversion = :conversion_rate';", 1),
            'select and where' => array('$q = "SELECT id, conversion FROM currencies WHERE conversion != \'0\'";', 2),
            'column definition' => array("\$q = 'CREATE TABLE t (id INT, conversion BIGINT NOT NULL)';", 1),
            'alias' => array("\$q = 'SELECT cc.id AS conversion FROM currency_conversions cc';", 1),
            'alias without AS' => array("\$q = 'SELECT id conversion FROM currency_conversions';", 1),
            'arithmetic' => array("\$q = 'SELECT 2 * conversion FROM currency_conversions';", 1),
            'joined literals' => array('$q = "SELECT conv" . \'ersion FROM currency_conversions\';', 1),
            'escaped newline' => array('$q = "SELECT\nconversion FROM currency_conversions";', 1),
            'interpolated' => array('$q = "UPDATE currency_conversions SET conversion = $rate WHERE id = {$row->id}";', 1),
            'heredoc' => array("\$q = <<<SQL\nSELECT conversion FROM currency_conversions\nSQL;\n", 1),
            'quoted' => array("\$q = 'INSERT INTO currency_conversions (currency_id, `conversion`) VALUES (1, 2)';", 0),
            'after a table alias' => array("\$q = 'SELECT SUM(d.total_cost * cc.conversion) AS total FROM x';", 0),
            'placeholder' => array("\$q = 'INSERT INTO x VALUES (:currency_id, :conversion, :update_time)';", 0),
            'text in the SQL' => array("\$q = \"INSERT INTO t VALUES ('Tax and conversion, together', 'It\\'s a conversion')\";", 0),
            'text' => array("echo _('Review tax and conversion, then save.');", 0),
            'piece of SQL' => array("\$cost = 'SUM(' . \$alias . '.total_cost * conversion)';", 1),
            'piece of SQL with a column list' => array("\$columns = 'currency_id, conversion, insert_time';", 1),
            'text without SQL' => array("echo 'Conversion rates are updated daily, and each conversion is logged.';", 0),
            'slug and placeholder name' => array("\$slug = 'update-conversion-rates'; \$stmt->bindValue('conversion', 1);", 0),
            'after an empty SQL comment' => array('$q = "SELECT id FROM currencies --\nWHERE conversion != 0";', 1),
            'piece of SQL before !=' => array("\$q = 'conversion != :rate';", 1),
            'executable comment' => array("\$q = 'SELECT 1 /*M! + conversion */ FROM currency_conversions';", 1),
            'clause' => array("\$sql .= ' ORDER BY conversion'; \$sql .= ' GROUP BY conversion';", 2),
            'executable comment in a piece of SQL' => array("\$q = '0 /*M! conversion */';", 1),
            'quoted value joined from a variable' => array('$q = "UPDATE currency_conversions SET update_time = \'" . $timestamp . "\', conversion = \'1\' WHERE user_id = :user_id";', 1),
            'quoted value joined from a call' => array('$q = "SET a = \'" . $time->stamp() . "\', b = \'" . implode(\', \', $x[\'y\']) . "\', conversion = 1";', 1),
            'in a joined ternary' => array('$q = "SELECT id FROM currency_conversions" . ($by_rate ? " ORDER BY conversion" : " ORDER BY id");', 1),
            'in a joined call' => array('$q = "SELECT " . implode(\', \', array(\'id\', \'conversion AS rate\')) . " FROM x";', 1),
            'piece of SQL with a minus' => array("\$cost = 'SUM(f.total_cost * (1 - conversion))';", 1),
            'piece of SQL with a minus spaced on one side' => array("\$a = '(1- conversion)'; \$b = '(conversion -1)';", 2),
            'SQL comment' => array("\$q = 'SELECT c.id /* conversion is looked up later */ FROM currencies c -- conversion\nWHERE 1';", 0),
            'longer name' => array("\$q = 'SELECT conversion_rate, conversions FROM x';", 0),
            'after a long quoted value' => array("\$q = \"SELECT '" . str_repeat('x', 20000) . "', conversion FROM x\";", 1),
            'quotes in an interpolated key' => array('$q = "SELECT {$a["\'"]}, conversion, {$a["\'"]} FROM currencies";', 1),
            'binary string' => array('$q = b"SELECT $id, conversion";', 1),
            'binary string with a capital B' => array('$q = B"SELECT $id, conversion";', 1),
            'hexadecimal escape' => array('$q = "SELECT conv\x65rsion FROM currencies";', 1),
            'hexadecimal escape with a capital X' => array('$q = "SELECT conv\X65rsion FROM currencies";', 1),
            'octal escape' => array('$q = "SELECT conv\145rsion FROM currencies";', 1),
            'Unicode escape' => array('$q = "SELECT conv\u{65}rsion FROM currencies";', 1),
            'braces nested in an interpolated value' => array('$q = "{$a[$obj->{$key} . \'SELECT conversion FROM x\']}";', 0),
            'spaced table alias' => array("\$q = 'SELECT cc . conversion FROM currency_conversions cc';", 0),
            'in a backticked name' => array("\$q = 'SELECT 1 AS `historical conversion rate` FROM x';", 0),
        );
    }

    #[DataProvider('codeProvider')]
    public function testBareUsesAreFound($code, $expected)
    {
        $found = 0;
        foreach ($this->strings('<?php ' . $code) as $string) {
            $found += count($this->bareUses($string[1], 'conversion'));
        }
        $this->assertSame($expected, $found);
    }

    public function testNoSqlUsesAReservedColumnNameUnquoted()
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(DIR_ROOT, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) {
                if ($iterator->hasChildren()) {
                    return $iterator->getSubPath() !== '' || !in_array($file->getFilename(), self::SKIPPED, true);
                }

                return $file->getExtension() === 'php'
                    && !in_array(substr($file->getPathname(), strlen(DIR_ROOT)), self::SKIPPED_FILES, true);
            }));

        $checked = 0;
        $found = array();
        foreach ($files as $file) {
            $code = file_get_contents($file->getPathname());
            $this->assertNotFalse($code);
            $checked++;
            foreach ($this->strings($code) as $string) {
                foreach (self::RESERVED as $name) {
                    if ($this->bareUses($string[1], $name) !== array()) {
                        $found[] = substr($file->getPathname(), strlen(DIR_ROOT)) . ':' . $string[0] . ' (' . $name . ')';
                    }
                }
            }
        }

        $this->assertGreaterThan(100, $checked);
        $this->assertSame(array(), $found);
    }

} //@formatter:on

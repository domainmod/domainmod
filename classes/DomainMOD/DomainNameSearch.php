<?php
/**
 * /classes/DomainMOD/DomainNameSearch.php
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
 * The Bulk Updater's domain picker search (see domains/name-search.php and js/bulk-domain-picker.js): the domains whose
 * names contain what was typed, best matches first, a few at a time. Only names the Bulk Updater can use are offered,
 * and a name held by more than one record is one result.
 */
class DomainNameSearch
{
    // The longest search the endpoint accepts, the same as the domains column
    const MAX_QUERY_LENGTH = 255;

    // How many names a search shows
    const RESULTS = 50;

    // How many grouped names one search reads at most, however broad it is
    const READ_LIMIT = 5001;

    protected $pdo;
    protected $domain;

    public function __construct(\PDO $pdo, Domain $domain)
    {
        $this->pdo = $pdo;
        $this->domain = $domain;
    }

    /*
     * The search terms for the typed text: the text in lowercase, as domains are stored, and its stored form too when
     * that's different (so xn--mnchen-3ya.de finds münchen.de, as the Domains list's search does)
     */
    public static function terms($text)
    {
        $text = trim((string) $text);
        $terms = array(mb_strtolower($text, 'UTF-8'));
        $normalized = Domain::normalize($text);
        if ($normalized !== '' && $normalized !== $terms[0]) $terms[] = $normalized;
        return $terms;
    }

    /*
     * The search's SQL and its bound parameters, for one or two terms from terms(). Names that start with a term come
     * first, then names where a term starts a part after a dot or a hyphen (so "shop" finds your-shop.com before
     * myshop.com), then the rest, each group in name order. Every pattern is its own parameter, since the connection's
     * native prepares can't use a name twice.
     */
    public static function query(array $terms)
    {
        $contains = array();
        $prefix = array();
        $part = array();
        $params = array();

        foreach (array_values($terms) as $i => $term) {
            $n = $i + 1;
            $escaped = addcslashes($term, '\\%_');
            $params['c' . $n] = ListQuery::likeValue($term);
            $params['p' . $n] = $escaped . '%';
            $params['d' . $n] = '%.' . $escaped . '%';
            $params['h' . $n] = '%-' . $escaped . '%';
            $contains[] = "domain LIKE :c" . $n . " ESCAPE '\\\\'";
            $prefix[] = "domain LIKE :p" . $n . " ESCAPE '\\\\'";
            $part[] = "domain LIKE :d" . $n . " ESCAPE '\\\\'";
            $part[] = "domain LIKE :h" . $n . " ESCAPE '\\\\'";
        }

        $sql = "SELECT domain, GROUP_CONCAT(DISTINCT active ORDER BY active) AS statuses, COUNT(*) AS records
                FROM domains
                WHERE " . implode(' OR ', $contains) . "
                GROUP BY domain
                ORDER BY CASE
                    WHEN " . implode(' OR ', $prefix) . " THEN 0
                    WHEN " . implode(' OR ', $part) . " THEN 1
                    ELSE 2
                END, domain
                LIMIT " . self::READ_LIMIT;

        return array('sql' => $sql, 'params' => $params);
    }

    /*
     * The search's results, as array('results' => a list of array('domain' => ..., 'ascii' => ..., 'status' => ...),
     * 'more' => whether more names match than are shown). One read, of at most READ_LIMIT names, gone through in order
     * until RESULTS + 1 usable ones are found. When it runs out first and the read was cut short, more is true too.
     */
    public function find($text)
    {
        $query = self::query(self::terms($text));
        $stmt = $this->pdo->prepare($query['sql']);
        foreach ($query['params'] as $name => $value) {
            $stmt->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $stmt->execute();

        $results = array();
        $rows = 0;
        $more = false;
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $rows++;
            $result = $this->result($row);
            if ($result === null) continue;
            if (count($results) === self::RESULTS) {
                $more = true;
                break;
            }
            $results[] = $result;
        }
        $stmt->closeCursor();

        if (!$more && $rows >= self::READ_LIMIT) $more = true;

        return array('results' => $results, 'more' => $more);
    }

    /*
     * One grouped row as a result, or null when the Bulk Updater couldn't use the name: one stored before today's
     * checks, which the invalid domain check would refuse, or one the form would look up in another form (such as an
     * IDN the IDN update left as it was), which the missing domain check would refuse. The collation pads with spaces,
     * so a name and a legacy copy of it with trailing spaces are one group, and either may be returned: the name is
     * checked and offered without them, which is how the Bulk Updater's lookups match both.
     */
    public function result(array $row)
    {
        $name = rtrim((string) $row['domain'], ' ');
        if (!$this->domain->checkFormat($name) || Domain::normalizeOrKeep($name) !== $name) return null;

        return array('domain' => $name, 'ascii' => Domain::toAscii($name),
            'status' => self::status((string) $row['statuses'], (int) $row['records']));
    }

    /*
     * A result's status: every status its records have (GROUP_CONCAT's comma-separated codes), joined with ", ", and
     * when the name has more than one record, how many, since submitting the name acts on all of them
     */
    public static function status($codes, $records)
    {
        $names = array();
        foreach (explode(',', $codes) as $code) {
            if ($code === '') continue;
            $names[] = ChangeHistoryListColumns::statusName('domain', (int) $code);
        }
        if ($records > 1) {
            $names[] = sprintf(ngettext('%s record', '%s records', $records), number_format($records));
        }
        return implode(', ', $names);
    }

} //@formatter:on

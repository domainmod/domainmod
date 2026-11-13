<?php
/**
 * /classes/DomainMOD/RdapAssets.php
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
 * Finds or creates the registrar and registrar account for a domain imported with RDAP. Callers hold the
 * 'domainmod_rdap_assets' lock and an open transaction around resolve(), so overlapping cron runs can't create the
 * same registrar or account twice.
 */
class RdapAssets
{
    public $assets;
    public $deeb;
    public $log;
    public $sanitize;
    public $system;
    public $time;

    // Resolved ids for this PHP process, written only after the caller's transaction commits (see remember())
    protected static $registrar_cache = array();
    protected static $account_cache = array();
    protected $pending = array();

    const UNKNOWN_REGISTRAR = 'Unknown Registrar (RDAP)';
    const IMPORT_USERNAME = 'RDAP Import';

    /*
     * IANA Registrar IDs of registrars DomainMOD has an API for, mapped to the names DomainMOD uses, so "GoDaddy.com,
     * LLC" in RDAP matches a registrar the user calls "GoDaddy". Checked against IANA's registrar list on 2026-11-12.
     * Left out on purpose: 69 (Tucows, shared by OpenSRS, Hover, and others) and 303 (PDR, used by ResellerClub and
     * many resellers), where one ID covers more than one brand.
     */
    public static $known_registrars = array(
        '940' => 'Above.com',
        '1910' => 'Cloudflare',
        '1730' => 'DNSimple',
        '431' => 'DreamHost',
        '472' => 'Dynadot',
        '48' => 'eNom',
        '411' => 'Fabulous',
        '81' => 'Gandi',
        '146' => 'GoDaddy',
        '1636' => 'Hostinger',
        '625' => 'Name.com',
        '1441' => 'NameBright',
        '1068' => 'Namecheap',
        '1479' => 'NameSilo',
        '433' => 'OVH',
        '1861' => 'Porkbun');

    public function __construct()
    {
        $this->assets = new Assets();
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.rdapassets');
        $this->sanitize = new Sanitize();
        $this->system = new System();
        $this->time = new Time();
    }

    // array(registrar_id, account_id) for the RDAP registrar details and the owner
    public function resolve($registrar_name, $iana_id, $registrar_url, $owner_id, $created_by)
    {
        $this->pending = array();
        $registrar_id = $this->resolveRegistrar($registrar_name, $iana_id, $registrar_url, $created_by);
        $account_id = $this->resolveAccount($registrar_id, $owner_id, $created_by);
        return array($registrar_id, $account_id);
    }

    // Call after the caller's transaction commits, so ids from a rolled-back transaction are never reused
    public function remember()
    {
        foreach ($this->pending as $entry) {

            if ($entry[0] === 'registrar') {
                self::$registrar_cache[$entry[1]] = $entry[2];
            } else {
                self::$account_cache[$entry[1]] = $entry[2];
            }

        }

        $this->pending = array();
    }

    public function forget()
    {
        $this->pending = array();
    }

    /*
     * The form a registrar name is stored in, used for both matching and creating, so a later run computes the same
     * name: whitespace trimmed and collapsed, characters outside the Basic Multilingual Plane removed (the tables use
     * 3-byte utf8), HTML-escaped as the registrar forms do (several pages print names unescaped), then cut to 100
     * characters (registrars.name is VARCHAR(100), counted in characters) without splitting an escape
     */
    public function storedName($name)
    {
        $name = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        // escaping only makes a name longer, so anything past 100 characters can't be kept anyway (and cutting first
        // keeps the loop below short, whatever size of name RDAP sends)
        $name = rtrim(mb_substr($name, 0, 100));
        $escaped = $this->sanitize->text($name);

        while (mb_strlen($escaped) > 100 && $name !== '') {

            $name = mb_substr($name, 0, mb_strlen($name) - 1);
            $escaped = $this->sanitize->text(rtrim($name));

        }

        return $escaped;
    }

    // A usable registrar URL (https, valid, fits registrars.url once escaped), or ''
    public function storedUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '' || stripos($url, 'https://') !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false) return '';

        $escaped = $this->sanitize->text($url);
        return mb_strlen($escaped) <= 100 ? $escaped : '';
    }

    protected function resolveRegistrar($registrar_name, $iana_id, $registrar_url, $created_by)
    {
        $iana_id = ctype_digit((string) $iana_id) ? (string) (int) $iana_id : '';
        $name = ($iana_id !== '' && isset(self::$known_registrars[$iana_id])) ? self::$known_registrars[$iana_id] : $registrar_name;
        $stored = $this->storedName($name);
        if ($stored === '') $stored = self::UNKNOWN_REGISTRAR;

        $cache_key = $this->comparable($stored);

        if (isset(self::$registrar_cache[$cache_key]) && $this->registrarExists(self::$registrar_cache[$cache_key])) {

            return self::$registrar_cache[$cache_key];

        }

        $matches = $this->registrarsNamed($stored);

        if (count($matches) === 1) {

            $registrar_id = $matches[0];

        } elseif (count($matches) > 1 && $stored === self::UNKNOWN_REGISTRAR) {

            $registrar_id = $matches[0];

        } elseif (count($matches) > 1) {

            // two registrars with the same name: don't guess which one the domain belongs to
            $log_message = 'More than one registrar has this name, so the domain goes under ' . self::UNKNOWN_REGISTRAR;
            $log_extra = array('Registrar' => $stored, 'Registrar IDs' => implode(', ', $matches));
            $this->log->notice($log_message, $log_extra);
            return $this->resolveRegistrar(self::UNKNOWN_REGISTRAR, '', '', $created_by);

        } else {

            $registrar_id = $this->createRegistrar($stored, $this->storedUrl($registrar_url), $created_by);

        }

        $this->pending[] = array('registrar', $cache_key, $registrar_id);
        return $registrar_id;
    }

    protected function resolveAccount($registrar_id, $owner_id, $created_by)
    {
        $cache_key = $registrar_id . ':' . $owner_id;

        if (isset(self::$account_cache[$cache_key])
            && $this->accountExists(self::$account_cache[$cache_key], $registrar_id, $owner_id)) {

            return self::$account_cache[$cache_key];

        }

        $pdo = $this->deeb->cnxx;

        // the owner's only account at this registrar, if there's exactly one
        $stmt = $pdo->prepare("
            SELECT id
            FROM registrar_accounts
            WHERE registrar_id = :registrar_id
              AND owner_id = :owner_id
            ORDER BY id
            LIMIT 2");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->execute();
        $accounts = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (count($accounts) === 1) {

            $account_id = (int) $accounts[0];

        } else {

            // otherwise the owner's "RDAP Import" account at this registrar, created the first time
            $stmt = $pdo->prepare("
                SELECT id
                FROM registrar_accounts
                WHERE registrar_id = :registrar_id
                  AND owner_id = :owner_id
                  AND username = :username
                ORDER BY id
                LIMIT 1");
            $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
            $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
            $stmt->bindValue('username', self::IMPORT_USERNAME, \PDO::PARAM_STR);
            $stmt->execute();
            $account_id = (int) $stmt->fetchColumn();

            if (!$account_id) {

                $temp_notes = $this->time->timeBasic() . ' - Created by queue.';
                $account_id = (int) $this->assets->createRegistrarAccount($owner_id, $registrar_id, '',
                    self::IMPORT_USERNAME, '', '', 0, '', '', '', '', 0, $temp_notes,
                    $this->system->getCreationTypeId('Queue'), $created_by);

            }

        }

        $this->pending[] = array('account', $cache_key, $account_id);
        return $account_id;
    }

    // Ids of registrars whose name matches the stored form, ignoring case and runs of whitespace (compared in PHP, since
    // older MySQL versions have no REGEXP_REPLACE)
    protected function registrarsNamed($stored)
    {
        $wanted = $this->comparable($stored);
        $ids = array();

        $result = $this->deeb->cnxx->query("
            SELECT id, `name`
            FROM registrars
            ORDER BY id")->fetchAll();

        foreach ($result as $row) {

            if ($this->comparable($row->name) === $wanted) $ids[] = (int) $row->id;

        }

        return $ids;
    }

    protected function comparable($name)
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name) ?? ''));
    }

    protected function createRegistrar($stored, $url, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        // give it the matching API (by DomainMOD's name for the registrar), never the RDAP source itself
        $stmt = $pdo->prepare("
            SELECT id
            FROM api_registrars
            WHERE `name` = :name
              AND `name` != 'RDAP'");
        $stmt->bindValue('name', html_entity_decode($stored, ENT_QUOTES, 'UTF-8'), \PDO::PARAM_STR);
        $stmt->execute();
        $api_registrar_id = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO registrars
            (`name`, url, api_registrar_id, notes, creation_type_id, created_by, insert_time, update_time)
            VALUES
            (:name, :url, :api_registrar_id, :notes, :creation_type_id, :created_by, :insert_time, :update_time)");
        $stmt->bindValue('name', $stored, \PDO::PARAM_STR);
        $stmt->bindValue('url', $url, \PDO::PARAM_STR);
        $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
        $temp_notes = $this->time->timeBasic() . ' - Created by queue.';
        $stmt->bindValue('notes', $temp_notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $this->system->getCreationTypeId('Queue'), \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return (int) $pdo->lastInsertId('id');
    }

    protected function registrarExists($registrar_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM registrars
            WHERE id = :id");
        $stmt->bindValue('id', $registrar_id, \PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    protected function accountExists($account_id, $registrar_id, $owner_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM registrar_accounts
            WHERE id = :id
              AND registrar_id = :registrar_id
              AND owner_id = :owner_id");
        $stmt->bindValue('id', $account_id, \PDO::PARAM_INT);
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
} //@formatter:on

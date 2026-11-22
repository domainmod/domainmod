<?php
/**
 * /classes/DomainMOD/SslAssets.php
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
 * Finds or creates the SSL provider and SSL provider account for a certificate the SSL Queue imports with "Match each
 * certificate's issuer", as RdapAssets does for registrars. The caller holds an open transaction around resolve(), and
 * resolve() takes its own named lock (released by release() once the caller's transaction ends), so overlapping runs
 * can't create the same provider or account twice. Every search is a current read (FOR UPDATE).
 */
class SslAssets
{
    public $deeb;
    public $log;
    public $sanitize;
    public $system;
    public $time;

    const UNKNOWN_PROVIDER = 'Unknown SSL Provider';
    const IMPORT_USERNAME = 'SSL Import';

    // Issuer names (the cleaned organisation, or common name) of well-known certificate authorities, by their compared
    // form, mapped to the plain names DomainMOD uses
    public static $known_issuers = array(
        "let's encrypt" => "Let's Encrypt",
        'sectigo limited' => 'Sectigo',
        'digicert inc' => 'DigiCert',
        'globalsign nv-sa' => 'GlobalSign',
        'google trust services' => 'Google Trust Services',
        'google trust services llc' => 'Google Trust Services',
        'amazon' => 'Amazon',
        'zerossl' => 'ZeroSSL',
        'godaddy.com, inc.' => 'GoDaddy',
        'entrust, inc.' => 'Entrust');

    protected $locked = false;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.sslassets');
        $this->sanitize = new Sanitize();
        $this->system = new System();
        $this->time = new Time();
    }

    /*
     * The plain provider name for an issuer name SslLookup chose (already cleaned): a well-known issuer's plain name,
     * otherwise the issuer as written. A name that's empty, or starts with a character a spreadsheet reads as a formula
     * (=, +, -, or @), gives UNKNOWN_PROVIDER, so formula text never becomes a provider name, and a name can't be
     * stripped into an existing provider's name.
     */
    public static function providerName($issuer)
    {
        $issuer = trim((string) $issuer);
        if ($issuer === '' || strpos('=+-@', $issuer[0]) !== false) return self::UNKNOWN_PROVIDER;

        $compared = mb_strtolower(preg_replace('/\s+/u', ' ', $issuer) ?? '');
        return self::$known_issuers[$compared] ?? $issuer;
    }

    /*
     * array(provider_id, account_id, owner_id) for a certificate from this issuer, owned by $owner_id. The provider and
     * owner come from the locked account row. An account that was moved to another provider or owner meanwhile throws
     * an exception, so the caller rolls back and saves the row as save_failed, and the next run starts afresh.
     */
    public function resolve($issuer, $owner_id, $created_by)
    {
        if (!$this->locked) {

            $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, 10)");
            $stmt->bindValue('lock_name', $this->lockName(), \PDO::PARAM_STR);
            $stmt->execute();
            if ((string) $stmt->fetchColumn() !== '1') throw new \RuntimeException('Unable to get the SSL assets lock');
            $this->locked = true;

        }

        $provider_id = $this->resolveProvider($this->storedName(self::providerName($issuer)), $created_by);
        $account_id = $this->resolveAccount($provider_id, (int) $owner_id, $created_by);

        $stmt = $this->deeb->cnxx->prepare("
            SELECT ssl_provider_id, owner_id
            FROM ssl_accounts
            WHERE id = :account_id
            FOR UPDATE");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $account = $stmt->fetch();
        $stmt->closeCursor();

        if (!$account || (int) $account->ssl_provider_id !== $provider_id || (int) $account->owner_id !== (int) $owner_id) {
            throw new \RuntimeException('The SSL provider account changed while it was being used');
        }

        return array($provider_id, $account_id, (int) $account->owner_id);
    }

    // Releases the named lock, once the caller's transaction has ended
    public function release()
    {
        if (!$this->locked) return;

        $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
        $stmt->bindValue('lock_name', $this->lockName(), \PDO::PARAM_STR);
        $stmt->execute();
        $this->locked = false;
    }

    /*
     * The form a provider name is stored in, as RdapAssets::storedName() makes a registrar's: whitespace collapsed,
     * characters outside the Basic Multilingual Plane removed, HTML-escaped, and cut to the 100 characters
     * ssl_providers.name holds without splitting an escape.
     */
    public function storedName($name)
    {
        $name = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = rtrim(mb_substr($name, 0, 100));
        $escaped = $this->sanitize->text($name);

        while (mb_strlen($escaped) > 100 && $name !== '') {

            $name = mb_substr($name, 0, mb_strlen($name) - 1);
            $escaped = $this->sanitize->text(rtrim($name));

        }

        return $escaped;
    }

    protected function resolveProvider($stored, $created_by)
    {
        if ($stored === '') $stored = self::UNKNOWN_PROVIDER;

        $matches = $this->providersNamed($stored);

        if (count($matches) === 1 || (count($matches) > 1 && $stored === self::UNKNOWN_PROVIDER)) {

            return $matches[0];

        }

        if (count($matches) > 1) {

            // two providers with the same name: don't guess which one the certificate belongs to
            $log_message = 'More than one SSL provider has this name, so the certificate goes under ' . self::UNKNOWN_PROVIDER;
            $log_extra = array('SSL Provider' => $stored, 'SSL Provider IDs' => implode(', ', $matches));
            $this->log->notice($log_message, $log_extra);
            return $this->resolveProvider(self::UNKNOWN_PROVIDER, $created_by);

        }

        return $this->createProvider($stored, $created_by);
    }

    // Ids of providers whose name matches the stored form, ignoring case and runs of whitespace, lowest id first
    protected function providersNamed($stored)
    {
        $wanted = $this->comparable($stored);
        $ids = array();

        $result = $this->deeb->cnxx->query("
            SELECT id, `name`
            FROM ssl_providers
            ORDER BY id
            FOR UPDATE")->fetchAll();

        foreach ($result as $row) {

            if ($this->comparable($row->name) === $wanted) $ids[] = (int) $row->id;

        }

        return $ids;
    }

    /*
     * A name as compared: unescaped first (only what Sanitize::text() escapes), since names are stored HTML-escaped but
     * older or imported rows can hold the plain form (Let&#039;s Encrypt and Let's Encrypt are the same provider)
     */
    protected function comparable($name)
    {
        $name = (new Unsanitize())->text((string) $name);
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));
    }

    // The owner's only account at the provider when there's exactly one, otherwise its "SSL Import" account there,
    // created the first time
    protected function resolveAccount($provider_id, $owner_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM ssl_accounts
            WHERE ssl_provider_id = :provider_id
              AND owner_id = :owner_id
            ORDER BY id
            LIMIT 2
            FOR UPDATE");
        $stmt->bindValue('provider_id', $provider_id, \PDO::PARAM_INT);
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->execute();
        $accounts = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (count($accounts) === 1) return (int) $accounts[0];

        $stmt = $pdo->prepare("
            SELECT id
            FROM ssl_accounts
            WHERE ssl_provider_id = :provider_id
              AND owner_id = :owner_id
              AND username = :username
            ORDER BY id
            LIMIT 1
            FOR UPDATE");
        $stmt->bindValue('provider_id', $provider_id, \PDO::PARAM_INT);
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->bindValue('username', self::IMPORT_USERNAME, \PDO::PARAM_STR);
        $stmt->execute();
        $account_id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($account_id) return $account_id;

        $stmt = $pdo->prepare("
            INSERT INTO ssl_accounts
            (owner_id, ssl_provider_id, email_address, username, `password`, reseller, reseller_id, notes,
             creation_type_id, created_by, insert_time)
            VALUES
            (:owner_id, :provider_id, '', :username, '', '0', '', :notes, :creation_type_id, :created_by, :insert_time)");
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->bindValue('provider_id', $provider_id, \PDO::PARAM_INT);
        $stmt->bindValue('username', self::IMPORT_USERNAME, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $this->time->timeBasic() . ' - Created by the SSL Queue.', \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $this->system->getCreationTypeId('Queue'), \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->execute();

        return (int) $pdo->lastInsertId('id');
    }

    protected function createProvider($stored, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            INSERT INTO ssl_providers
            (`name`, url, notes, creation_type_id, created_by, insert_time)
            VALUES
            (:name, '', :notes, :creation_type_id, :created_by, :insert_time)");
        $stmt->bindValue('name', $stored, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $this->time->timeBasic() . ' - Created by the SSL Queue.', \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $this->system->getCreationTypeId('Queue'), \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->execute();

        return (int) $pdo->lastInsertId('id');
    }

    // Named locks are shared by every database on the server, so the name includes a short hash of this database's name
    protected function lockName()
    {
        return 'domainmod_ssl_assets_' . substr(md5(DB_NAME), 0, 12);
    }

} //@formatter:on

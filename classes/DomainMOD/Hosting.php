<?php
/**
 * /classes/DomainMOD/Hosting.php
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
 * Hosting's shared rules: whether the database has its tables yet, the one application lock every hosting write holds,
 * billing terms and the yearly figure, statuses, the hosting date ("today" in the hosting time zone), a service's
 * renewal state once its date has passed (Confirm renewal or Overdue), and the renewal dates a term moves a service
 * through (used by Mark Renewed and the projected reports).
 */
class Hosting
{
    // The database version that adds the hosting tables (see the update block and install/go.php)
    const AVAILABLE_FROM = '4.27.0';

    // The billing terms a service or fee can have, in months
    const BILLING_TERMS = array(1, 3, 6, 12, 24, 36);

    // Statuses (hosting_services.active), with SSL's numbers: 0 is Cancelled here (SSL calls it Expired)
    const ACTIVE = 1;
    const PENDING_SETUP = 5;
    const PENDING_RENEWAL = 3;
    const PENDING_OTHER = 4;
    const CANCELLED = 0;

    // How long a page waits for the hosting lock, and how long the scheduled steps wait
    const PAGE_WAIT = 10;
    const TASK_WAIT = 60;

    // The scheduler slug of the expiration email task, whose time zone is the hosting time zone
    const ZONE_SLUG = 'expiration-email';

    // How many days after its renewal date an Active, auto-renewing service waits for the renewal to be confirmed (Mark
    // Renewed) before it shows as overdue, since DomainMOD can't check with the host (see renewalState())
    const CONFIRM_DAYS = 14;

    // A service's renewal states once its date has passed (see renewalState()), and '' for none
    const STATE_CONFIRM = 'confirm';
    const STATE_OVERDUE = 'overdue';

    // Whether the hosting tables exist, by connection, worked out once per request
    protected static $available = array();

    // legacyDomainColumn()'s answers, by connection
    protected static $legacy_column = array();

    // Whether the database has been updated to a version with the hosting tables. Every hosting page, task, and query
    // checks this first, so code deployed before the database update doesn't read tables that aren't there yet.
    public static function isAvailable($pdo = null)
    {
        try {
            $pdo = $pdo ?? Database::getInstance()->cnxx;
        } catch (\Throwable $e) {
            return false; // no database to ask (such as a page rendered in a unit test)
        }
        if (!is_object($pdo)) return false; // a shared database object with no connection
        $key = spl_object_id($pdo);

        if (!isset(self::$available[$key])) {

            try {
                $version = $pdo->query("SELECT db_version FROM settings LIMIT 1")->fetchColumn();
            } catch (\Throwable $e) {
                $version = false;
            }
            self::$available[$key] = $version !== false && version_compare((string) $version, self::AVAILABLE_FROM, '>=');

        }

        return self::$available[$key];
    }

    // Forgets the cached availability (the upgrade, and tests)
    public static function forgetAvailability()
    {
        self::$available = array();
        self::$legacy_column = array();
    }

    /*
     * Whether domains still has the hosting provider column it had before 4.27.0 (domains.hosting_id), which the
     * statements marked as legacy write or read: before the update, but not once a stopped upgrade has dropped the
     * column without moving the version yet. Then those statements are left out, and a domain is written as it is after
     * the update, with no hosting service (the service menus aren't offered until hosting is available). A database
     * that can't be asked counts as not having it.
     */
    public static function legacyDomainColumn($pdo = null)
    {
        if (self::isAvailable($pdo)) return false;
        try {
            $pdo = $pdo ?? Database::getInstance()->cnxx;
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_object($pdo)) return false;
        $key = spl_object_id($pdo);

        if (!isset(self::$legacy_column[$key])) {

            try {
                self::$legacy_column[$key] = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM `INFORMATION_SCHEMA`.`COLUMNS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND `TABLE_NAME` = 'domains'
                      AND `COLUMN_NAME` = 'hosting_id'")->fetchColumn() > 0;
            } catch (\Throwable $e) {
                self::$legacy_column[$key] = false;
            }

        }

        return self::$legacy_column[$key];
    }

    // The message every hosting page shows before the database update
    public static function unavailableMessage()
    {
        return _('Hosting becomes available after the next database update.');
    }

    // The message a page shows when the hosting lock can't be had in time
    public static function busyMessage()
    {
        return _('The hosting data is busy. Please try again in a moment.');
    }

    /*
     * The hosting lock's name. Named locks are shared by every database on the server, so it includes a short hash of
     * this database's name, and two installations on one server don't block each other.
     */
    public static function lockName()
    {
        return 'domainmod_hosting_' . substr(md5(DB_NAME), 0, 12);
    }

    /*
     * Takes the hosting lock, waiting up to $wait seconds, and says whether it got it. Every write that touches hosting
     * services or their fees (and every delete whose in-use check reads hosting tables) holds it from before its
     * transaction begins until after its commit or rollback, so those writes take turns, and the lock is always taken
     * before any row lock.
     */
    public static function lock(\PDO $pdo, $wait = self::PAGE_WAIT)
    {
        $stmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, :wait)");
        $stmt->bindValue('lock_name', self::lockName(), \PDO::PARAM_STR);
        $stmt->bindValue('wait', (int) $wait, \PDO::PARAM_INT);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        return $got;
    }

    // Lets go of the hosting lock (a failure, such as a lost connection, is ignored: the lock goes with it)
    public static function unlock(\PDO $pdo)
    {
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', self::lockName(), \PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
        }
    }

    /*
     * A whole number sent by a form or link, or $invalid when it's anything but digits (such as an array, which an (int)
     * cast would turn into 1, or a negative number), so a malformed value finds nothing rather than the first asset. A
     * number larger than PHP_INT_MAX (2,147,483,647 on 32-bit PHP) is invalid too, never clamped to another row's id.
     */
    public static function postedInt($value, $invalid = 0)
    {
        if (!is_string($value) || !ctype_digit($value)) return $invalid;
        $number = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
        if ($number === false) {
            // filter_var() refuses leading zeros, which a digit string can still have ("007")
            $trimmed = ltrim($value, '0');
            if ($trimmed === '') return 0;
            $number = filter_var($trimmed, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
        }
        return $number === false ? $invalid : $number;
    }

    /*
     * The domain pages' Hosting Service menu: service id => label for every service that isn't Cancelled, plus $keep_id
     * (the domain's current service) even when it's Cancelled, sorted by name, then provider, owner, account, and id
     */
    public static function serviceMenu(\PDO $pdo, $keep_id = 0)
    {
        $stmt = $pdo->prepare(self::serviceLabelSql("hs.active <> :cancelled OR hs.id = :keep_id"));
        $stmt->bindValue('cancelled', self::CANCELLED, \PDO::PARAM_INT);
        $stmt->bindValue('keep_id', (int) $keep_id, \PDO::PARAM_INT);
        $stmt->execute();
        return self::serviceLabelList($stmt->fetchAll(\PDO::FETCH_OBJ));
    }

    /*
     * The domain writes' check of the hosting service they link to, run under the hosting lock and inside their
     * transaction, after the domain rows are locked (the lock order: the hosting lock, the domains, then the hosting
     * rows). It returns '' when the link can be written, or the field error to show. 0 (None) needs no check. A
     * Cancelled service is refused unless it's $keep_id, the domain's existing link, so a domain already on a cancelled
     * service still saves while a form opened before the service was cancelled can't make a new link to it.
     */
    public static function serviceError(\PDO $pdo, $service_id, $keep_id = 0)
    {
        $service_id = (int) $service_id;
        if ($service_id === 0) return '';

        $stmt = $pdo->prepare("
            SELECT active
            FROM hosting_services
            WHERE id = :service_id
            LOCK IN SHARE MODE");
        $stmt->bindValue('service_id', $service_id, \PDO::PARAM_INT);
        $stmt->execute();
        $active = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($active === false) return self::serviceGoneMessage();
        if ((int) $active === self::CANCELLED && $service_id !== (int) $keep_id) {
            return _('The hosting service you chose is cancelled. Please choose another one.');
        }
        return '';
    }

    // The field error for a hosting service that no longer exists (or a malformed choice, which finds no service)
    public static function serviceGoneMessage()
    {
        return _('The hosting service you chose no longer exists. Please choose another one.');
    }

    // Service id => label for the given service ids, in the menu's format and order (ids that no longer exist are left out)
    public static function serviceLabels(\PDO $pdo, array $ids)
    {
        $ids = self::serviceIds($ids);
        if (!$ids) return array();
        $result = $pdo->query(self::serviceLabelSql("hs.id IN (" . implode(',', $ids) . ")"))->fetchAll(\PDO::FETCH_OBJ);
        return self::serviceLabelList($result);
    }

    /*
     * Service id => IP address id for the given service ids, for the domain pages' Hosting Service menu
     * (Form::showHostingServiceMenu()), which fills in a domain's IP Address from the service chosen. A service with no
     * IP address, the "[no ip address]" placeholder (id 1), or an IP address that no longer exists is left out.
     */
    public static function serviceIps(\PDO $pdo, array $ids)
    {
        $ids = self::serviceIds($ids);
        if (!$ids) return array();
        $ips = array();
        foreach ($pdo->query("
            SELECT hs.id, hs.ip_id
            FROM hosting_services AS hs
            INNER JOIN ip_addresses AS ip ON ip.id = hs.ip_id
            WHERE hs.id IN (" . implode(',', $ids) . ")
              AND hs.ip_id <> 1")->fetchAll(\PDO::FETCH_OBJ) as $row) {
            $ips[(int) $row->id] = (int) $row->ip_id;
        }
        return $ips;
    }

    // The given service ids as unique whole numbers above 0, safe to list in a query
    protected static function serviceIds(array $ids)
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
    }

    // The query behind serviceMenu() and serviceLabels(), given its condition
    protected static function serviceLabelSql($condition)
    {
        return "SELECT hs.id, hs.name, COALESCE(h.name, '') AS provider_name, COALESCE(o.name, '') AS owner_name,
                       COALESCE(hpa.username, '') AS username
                FROM hosting_services AS hs
                LEFT JOIN hosting_provider_accounts AS hpa ON hpa.id = hs.account_id
                LEFT JOIN hosting AS h ON h.id = hpa.hosting_id
                LEFT JOIN owners AS o ON o.id = hpa.owner_id
                WHERE " . $condition . "
                ORDER BY hs.name, h.name, o.name, hpa.username, hs.id";
    }

    /*
     * The labels, "Service name (Provider, Owner, account username)", with the account shown as the Hosting list's
     * Account column shows it (cut at 15 characters). " #id" is added only to labels that would otherwise repeat.
     */
    protected static function serviceLabelList(array $rows)
    {
        $labels = array();
        foreach ($rows as $row) {
            // Cut by character, not byte, so a name with accents isn't broken in the middle of a letter
            $username = mb_substr((string) $row->username, 0, 15, 'UTF-8')
                . (mb_strlen((string) $row->username, 'UTF-8') >= 16 ? '...' : '');
            $labels[(int) $row->id] = $row->name . ' (' . $row->provider_name . ', ' . $row->owner_name . ', ' . $username . ')';
        }
        $counts = array_count_values($labels);
        foreach ($labels as $id => $label) {
            if ($counts[$label] > 1) $labels[$id] = $label . ' #' . $id;
        }
        return $labels;
    }

    // Whether $term is one of the allowed billing terms
    public static function validTerm($term)
    {
        return is_numeric($term) && (string) (int) $term === (string) $term && in_array((int) $term, self::BILLING_TERMS, true);
    }

    // A billing term's label, such as "1 Month" or "12 Months"
    public static function termLabel($term)
    {
        $term = (int) $term;
        return sprintf(ngettext('%s Month', '%s Months', $term), $term);
    }

    // The billing terms as a value => label list, for the forms and filters
    public static function termOptions()
    {
        $options = array();
        foreach (self::BILLING_TERMS as $term) $options[$term] = self::termLabel($term);
        return $options;
    }

    /*
     * The yearly figure of one term's cost (cost * 12 / term), rounded to cents, or null when the term isn't an allowed
     * one, so an invalid stored term is left out of a total rather than divided by
     */
    public static function yearlyCost($cost, $term)
    {
        if (!self::validTerm($term)) return null;
        return round((float) $cost * 12 / (int) $term, 2);
    }

    // The SQL for the yearly figure of a cost column, given the table alias (an invalid term gives NULL, left out of SUM())
    public static function yearlyCostSql($alias = 'hs', $cost_sql = null)
    {
        $cost_sql = $cost_sql ?? $alias . '.total_cost';
        return "(CASE WHEN " . $alias . ".billing_term IN (" . implode(', ', self::BILLING_TERMS) . ") THEN " . $cost_sql .
            " * 12 / " . $alias . ".billing_term ELSE NULL END)";
    }

    // The statuses as value => label, in the order the forms list them
    public static function statusOptions()
    {
        return array(
            self::ACTIVE => _('Active'),
            self::PENDING_SETUP => _('Pending (Setup)'),
            self::PENDING_RENEWAL => _('Pending (Renewal)'),
            self::PENDING_OTHER => _('Pending (Other)'),
            self::CANCELLED => _('Cancelled'));
    }

    // A status's label, or '' for a value that isn't a status
    public static function statusName($active)
    {
        $options = self::statusOptions();
        return $options[(int) $active] ?? '';
    }

    /*
     * The hosting time zone: the expiration email task's zone (the installation's, or the zone of the administrator who
     * last picked a different hour for the email), read from the database each time, so every hosting view agrees, or
     * UTC when the row or zone isn't usable
     */
    public static function hostingZone(\PDO $pdo)
    {
        try {
            $stmt = $pdo->prepare("
                SELECT `timezone`
                FROM scheduler
                WHERE slug = :slug
                LIMIT 1");
            $stmt->bindValue('slug', self::ZONE_SLUG, \PDO::PARAM_STR);
            $stmt->execute();
            $zone = $stmt->fetchColumn();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
            $zone = false;
        }

        $zone = $zone === false ? '' : (string) $zone;
        return Time::isKnownZone($zone) ? $zone : 'UTC';
    }

    /*
     * The hosting date (Y-m-d): today in the hosting time zone. Every hosting date comparison (the Hosting list and the
     * service page, the email's hosting section, At-Risk, Hosting Renewals, the Renewal Calendar's marks for hosting
     * services, and the dashboard's hosting panels and chart) uses it, so they all agree on when a renewal date has
     * passed.
     */
    public static function hostingDate(\PDO $pdo, $now = null)
    {
        return self::dateInZone(self::hostingZone($pdo), $now);
    }

    // Today's date (Y-m-d) in $zone at the Unix time $now (default now)
    public static function dateInZone($zone, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        return (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone($zone))->format('Y-m-d');
    }

    // $date (Y-m-d) plus $days calendar days
    public static function addDays($date, $days)
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->modify(((int) $days >= 0 ? '+' : '') .
            (int) $days . ' days')->format('Y-m-d');
    }

    // The whole calendar months from $from to $to (Y-m-d dates, by year and month only, never negative)
    protected static function monthsBetween($from, $to)
    {
        $months = ((int) substr($to, 0, 4) - (int) substr($from, 0, 4)) * 12 + (int) substr($to, 5, 2) -
            (int) substr($from, 5, 2);
        return max(0, $months);
    }

    /*
     * A service's renewal state on $today (the hosting date, see hostingDate()): '' while its renewal date hasn't passed
     * (the date itself included) and for a Cancelled service, STATE_CONFIRM ("Confirm renewal") for an Active service
     * with auto-renew on and a valid billing term from the day after its date through the CONFIRM_DAYS-th day after it,
     * since the host should have renewed it but DomainMOD can't check, and STATE_OVERDUE otherwise (auto-renew off, a
     * Pending status, a term that isn't an allowed one, which can't be marked renewed, or a date more than CONFIRM_DAYS
     * days ago). Every view of a hosting service's passed date asks this, so they all agree.
     */
    public static function renewalState($active, $autorenew, $expiry_date, $billing_term, $today)
    {
        if ((int) $active === self::CANCELLED) return '';
        $date = (string) $expiry_date;
        if ($date >= (string) $today) return '';
        if ((int) $active === self::ACTIVE && (int) $autorenew === 1 && self::validTerm($billing_term)
            && $date >= self::addDays((string) $today, -self::CONFIRM_DAYS)) {
            return self::STATE_CONFIRM;
        }
        return self::STATE_OVERDUE;
    }

    // The renewal state's label: "Confirm renewal", "Overdue", or ''
    public static function stateLabel($state)
    {
        if ($state === self::STATE_CONFIRM) return _('Confirm renewal');
        if ($state === self::STATE_OVERDUE) return _('Overdue');
        return '';
    }

    /*
     * The projected renewal dates of one service inside [$start, $end] (Y-m-d, inclusive): the stored date itself, then
     * the stored date plus each whole number of terms, landing on $day. The first one in the range is worked out directly
     * from the months between the stored date and $start, so a service dated years before the range costs nothing
     * extra. Returns an empty list for an invalid term, date, or range.
     */
    public static function renewalsInRange($date, $term, $day, $start, $end)
    {
        $dates = new Date();
        if (!self::validTerm($term) || !$dates->checkDateFormat((string) $date) || !$dates->checkDateFormat((string) $start) ||
            !$dates->checkDateFormat((string) $end) || $end < $start) {
            return array();
        }

        $term = (int) $term;
        $found = array();
        $n = 0;

        if ($date < $start) {
            $n = max(1, intdiv(self::monthsBetween($date, $start), $term) - 1);
            while (true) {
                $candidate = $dates->addMonths($date, $n * $term, $day);
                if ($candidate === '' || $candidate >= $start) break;
                $n++;
            }
        }

        while (true) {
            $candidate = $n === 0 ? $date : $dates->addMonths($date, $n * $term, $day);
            if ($candidate === '' || $candidate > $end) break;
            if ($candidate >= $start) $found[] = $candidate;
            $n++;
        }

        return $found;
    }

    /*
     * The "Renews On Day" an edit saves: what the user entered when they changed it, otherwise the new date's day when
     * the date changed (a provider moving billing from the 31st to the 15th means editing only the date), except when the
     * new date is the last day of a month too short for the stored day (the 31st moved to February 28 is that day
     * clamped, so the stored day stays). Unchanged otherwise.
     */
    public static function renewalDayAfterEdit($old_date, $old_day, $new_date, $posted_day)
    {
        $old_day = (int) $old_day;
        $posted_day = (int) $posted_day;
        if ($posted_day !== $old_day) return $posted_day;
        if ($new_date === $old_date) return $old_day;

        $dates = new Date();
        if (!$dates->checkDateFormat((string) $new_date)) return $old_day;

        $new_day = (int) substr($new_date, 8, 2);
        $last = $dates->daysInMonth((int) substr($new_date, 0, 4), (int) substr($new_date, 5, 2));
        if ($new_day === $last && $old_day > $last) return $old_day;

        return $new_day;
    }

    /*
     * The Renews On Day menu's choice for a stored service: "Same as the Renewal Date" (0) when the stored day is the
     * renewal date's own day, otherwise the stored day
     */
    public static function renewalDayChoice($date, $day)
    {
        $day = (int) $day;
        $dates = new Date();
        return $dates->checkDateFormat((string) $date) && (int) substr((string) $date, 8, 2) === $day ? 0 : $day;
    }

    /*
     * The "Renews On Day" the edit page saves, from the menu's posted choice and the choice the form opened with (a
     * hidden field, posted back unchanged when a failed Save shows the form again, so a retry still sees the user's
     * change). An unchanged choice counts as an unchanged day, so renewalDayAfterEdit() gets the stored day and its
     * date-only rules apply. A changed choice is saved as given, with "Same as the Renewal Date" (0) taking the posted
     * renewal date's day.
     */
    public static function renewalDayToSave($old_date, $old_day, $new_date, $posted_choice, $opened_choice)
    {
        $posted_choice = (int) $posted_choice;
        if ($posted_choice === (int) $opened_choice) return self::renewalDayAfterEdit($old_date, $old_day, $new_date, $old_day);
        if ($posted_choice === 0) return (int) substr((string) $new_date, 8, 2);
        return $posted_choice;
    }

} //@formatter:on

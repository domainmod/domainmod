<?php
/**
 * /classes/DomainMOD/Reporting.php
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

class Reporting
{
    const AT_RISK_DAYS = 30; // The At-Risk reports always look this far ahead, separately from the Expiration Days setting

    public function getRangeString($column, $new_start_date, $new_end_date)
    {
        if ($new_start_date == '' && $new_end_date == '') {

            $range_string = '';

        } else {

            $range_string = " AND " . $column . " between '" . $new_start_date . "' AND '" . $new_end_date . "' ";

        }
        return $range_string;
    }

    /*
     * The live domains (not Expired or Sold) expiring before $end_date (YYYY-MM-DD, not included), overdue ones
     * included, for Domain Renewals, At-Risk Domains, and the Dashboard. Fees and conversion rates are left joins, so
     * a domain with no fee, or whose fee's currency has no rate for the user, is still listed: its converted_cost is
     * null, and has_fee and has_rate say why. Sorted by expiry date. $start_date (YYYY-MM-DD, included) limits the rows
     * to those expiring on or after it, for the Renewal Calendar's month. Left empty, there's no lower bound.
     */
    public function renewalDomains($end_date, $start_date = '')
    {
        return $this->renewalDomainRows($end_date, $start_date, false);
    }

    /*
     * renewalDomains() with each domain's transfer_lock and a registry_problem flag (1 when the registry has it on hold
     * or is deleting or restoring it, see DomainStatus), for At-Risk Domains and the Dashboard's At Risk tile. Before
     * the 4.27.0 database update adds the columns, it returns exactly what renewalDomains() returns.
     */
    public function renewalDomainsWithStatus($end_date, $start_date = '')
    {
        return $this->renewalDomainRows($end_date, $start_date, DomainStatus::isAvailable(Database::getInstance()->cnxx));
    }

    // The statement behind renewalDomains() and renewalDomainsWithStatus(), with the status columns when $with_status
    private function renewalDomainRows($end_date, $start_date, $with_status)
    {
        $pdo = Database::getInstance()->cnxx;
        $status_columns = $with_status ? ", d.transfer_lock, (" . DomainStatus::findInSet(array_merge(DomainStatus::HOLD_CODES,
            DomainStatus::DELETION_CODES), 'd.epp_status') . ") AS registry_problem" : "";

        $stmt = $pdo->prepare("
            SELECT d.id, d.domain, d.expiry_date, d.autorenew, d.privacy, r.name AS registrar_name,
                o.name AS owner_name, ra.username, (f.id IS NOT NULL) AS has_fee, (cc.id IS NOT NULL) AS has_rate,
                d.total_cost * cc.conversion AS converted_cost" . $status_columns . "
            FROM domains AS d
            LEFT JOIN registrars AS r ON d.registrar_id = r.id
            LEFT JOIN registrar_accounts AS ra ON d.account_id = ra.id
            LEFT JOIN owners AS o ON d.owner_id = o.id
            LEFT JOIN fees AS f ON d.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE d.active NOT IN ('0', '10')
              AND d.expiry_date < :end_date" . ($start_date !== '' ? "
              AND d.expiry_date >= :start_date" : "") . "
            ORDER BY d.expiry_date, d.domain");
        $stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue('end_date', $end_date, \PDO::PARAM_STR);
        if ($start_date !== '') $stmt->bindValue('start_date', $start_date, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * The live SSL certificates (not Expired or status 10) expiring before $end_date (YYYY-MM-DD, not included), overdue
     * ones included, for SSL Renewals and At-Risk SSL Certificates: the SSL twin of renewalDomains(). Each row
     * also carries its domain's id, name, expiry date, and status, for the At-Risk report's domain checks. Fees and
     * conversion rates are left joins, so a certificate with no fee, or whose fee's currency has no rate for the user,
     * is still listed with has_fee and has_rate saying why. Sorted by expiry date. $start_date (YYYY-MM-DD, included)
     * limits the rows to those expiring on or after it, as renewalDomains() does.
     */
    public function renewalCerts($end_date, $start_date = '')
    {
        $pdo = Database::getInstance()->cnxx;

        $stmt = $pdo->prepare("
            SELECT sslc.id, sslc.name, sslc.expiry_date, sslp.name AS provider_name, o.name AS owner_name,
                sslpa.username, sslcf.type, d.id AS domain_id, d.domain, d.expiry_date AS domain_expiry_date,
                d.active AS domain_active, (f.id IS NOT NULL) AS has_fee, (cc.id IS NOT NULL) AS has_rate,
                sslc.total_cost * cc.conversion AS converted_cost
            FROM ssl_certs AS sslc
            LEFT JOIN ssl_providers AS sslp ON sslc.ssl_provider_id = sslp.id
            LEFT JOIN ssl_accounts AS sslpa ON sslc.account_id = sslpa.id
            LEFT JOIN owners AS o ON sslc.owner_id = o.id
            LEFT JOIN ssl_cert_types AS sslcf ON sslc.type_id = sslcf.id
            LEFT JOIN domains AS d ON sslc.domain_id = d.id
            LEFT JOIN ssl_fees AS f ON sslc.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE sslc.active NOT IN ('0', '10')
              AND sslc.expiry_date < :end_date" . ($start_date !== '' ? "
              AND sslc.expiry_date >= :start_date" : "") . "
            ORDER BY sslc.expiry_date, sslc.name");
        $stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue('end_date', $end_date, \PDO::PARAM_STR);
        if ($start_date !== '') $stmt->bindValue('start_date', $start_date, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * The first day of the month 12 months after $today's (YYYY-MM-DD), the end (not included) of the renewal chart's
     * 12 months
     */
    public function renewalChartEnd($today)
    {
        $first = new \DateTime(substr($today, 0, 7) . '-01');
        $first->modify('+12 months');
        return $first->format('Y-m-d');
    }

    /*
     * The renewal chart's bars from renewalDomains() rows: Overdue (expiring before $today, shown only when there are
     * some), then one per month from $today's month through the 11th month after it. Each bar has its label, the number
     * of domains, the cost of those with a fee and a rate, and how many are left out of the cost for having neither.
     */
    public function renewalMonths($rows, $today)
    {
        $this_month = (int) substr($today, 0, 4) * 12 + (int) substr($today, 5, 2) - 1;

        $overdue = array('label' => _('Overdue'), 'count' => 0, 'cost' => 0.0, 'missing' => 0, 'overdue' => true);
        $months = array();
        for ($i = 0; $i < 12; $i++) {
            $month_number = ($this_month + $i) % 12 + 1;
            $year = intdiv($this_month + $i, 12);
            $months[$i] = array('label' => $this->monthName($month_number) . ' ' . $year, 'count' => 0, 'cost' => 0.0,
                'missing' => 0, 'overdue' => false);
        }

        foreach ($rows as $row) {
            if ($row->expiry_date < $today) {
                $bucket = &$overdue;
            } else {
                $index = (int) substr($row->expiry_date, 0, 4) * 12 + (int) substr($row->expiry_date, 5, 2) - 1 -
                    $this_month;
                if ($index < 0 || $index > 11) continue;
                $bucket = &$months[$index];
            }
            $bucket['count']++;
            if ($row->has_fee && $row->has_rate) {
                $bucket['cost'] += (float) $row->converted_cost;
            } else {
                $bucket['missing']++;
            }
            unset($bucket);
        }

        return $overdue['count'] > 0 ? array_merge(array($overdue), $months) : $months;
    }

    /*
     * The hosting services' payments in the renewal chart's months, as month totals: for every service in any status
     * except Cancelled, each renewal date from $start through $end (YYYY-MM-DD, both included) that
     * Hosting::renewalsInRange() gives adds one term's cost to its month. A service whose stored date is before
     * $overdue_before (the hosting date, see Hosting::hostingDate()) has a renewal state (Hosting::renewalState()): an
     * overdue one's stored date goes in 'overdue' instead of its month, a stored date waiting for its renewal to be
     * confirmed goes in no bucket (it may already be paid, and it isn't overdue), and either way its later renewals are
     * projected into their months as usual. Returns 'YYYY-MM' (and
     * 'overdue', when any service is) => array('count' => the payments, 'cost' => the cost of those with a fee and a
     * rate, 'missing' => the payments left out of the cost for having neither), or an empty list before the database
     * update that adds the hosting tables.
     */
    public function renewalHostingMonths($start, $end, $overdue_before = '')
    {
        $pdo = Database::getInstance()->cnxx;
        if (!Hosting::isAvailable($pdo)) return array();

        $stmt = $pdo->prepare("
            SELECT hs.expiry_date, hs.billing_term, hs.renewal_day, hs.active, hs.autorenew, (f.id IS NOT NULL) AS has_fee,
                (cc.id IS NOT NULL) AS has_rate, hs.total_cost * cc.conversion AS converted_cost
            FROM hosting_services AS hs
            LEFT JOIN hosting_fees AS f ON hs.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE hs.active <> 0
              AND hs.expiry_date <= :end_date
            ORDER BY hs.id");
        $stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue('end_date', $end, \PDO::PARAM_STR);
        $stmt->execute();

        return $this->hostingMonths($stmt->fetchAll(), $start, $end, $overdue_before);
    }

    /*
     * Month totals of the payments of hosting service rows (expiry_date, billing_term, renewal_day, active, autorenew,
     * has_fee, has_rate, and converted_cost) from $start through $end, with the overdue stored dates before
     * $overdue_before (YYYY-MM-DD, or '' for none) in 'overdue' rather than their months, and those waiting for their
     * renewal to be confirmed in neither, as renewalHostingMonths() returns them. A row without active or autorenew
     * counts as Active without auto-renew. A service with a billing term outside the allowed list has no projected
     * payments, but an overdue stored date is still in 'overdue', as on Hosting Renewals.
     */
    public function hostingMonths($rows, $start, $end, $overdue_before = '')
    {
        $months = array();
        $add = function ($key, $has_cost, $cost) use (&$months) {
            if (!isset($months[$key])) $months[$key] = array('count' => 0, 'cost' => 0.0, 'missing' => 0);
            $months[$key]['count']++;
            if ($has_cost) {
                $months[$key]['cost'] += (float) $cost;
            } else {
                $months[$key]['missing']++;
            }
        };
        foreach ($rows as $row) {
            $has_cost = $row->has_fee && $row->has_rate;
            $stored = (string) $row->expiry_date;
            $state = $overdue_before !== '' ? Hosting::renewalState($row->active ?? Hosting::ACTIVE, $row->autorenew ?? 0,
                $stored, $row->billing_term, $overdue_before) : '';
            if ($state === Hosting::STATE_OVERDUE) $add('overdue', $has_cost, $row->converted_cost);
            // A billing term DomainMOD doesn't know gives no projected renewals (an overdue stored date is counted above)
            if (!Hosting::validTerm($row->billing_term)) continue;
            foreach (Hosting::renewalsInRange($stored, $row->billing_term, $row->renewal_day, $start, $end) as $date) {
                // A passed stored date leaves its own month: an overdue one is in the Overdue bar, and one waiting for
                // its renewal to be confirmed is in no bar
                if ($state !== '' && $date === $stored) continue;
                $add(substr($date, 0, 7), $has_cost, $row->converted_cost);
            }
        }
        return $months;
    }

    /*
     * The Dashboard's renewal chart's bars from renewalDomains() and renewalCerts() rows: renewalMonths() for each, lined
     * up bar by bar. The Overdue bar is shown when either has overdue items, and the other gets an empty one. Each bar
     * has its label, whether it's the Overdue bar, its domains' and SSL certificates' renewalMonths() buckets, and the
     * costs together. With $hosting_months (renewalHostingMonths()'s month totals), each bar also has its hosting bucket,
     * matched to the month by its year and month (never by position), so a payment before the first month isn't shown,
     * and the Overdue bar's hosting bucket holds the overdue services' stored dates. An overdue service shows the
     * Overdue bar by itself, with empty domain and SSL certificate buckets. The months stay anchored to $today (the
     * user's date), while the hosting date has already decided which services are overdue.
     */
    public function renewalMonthsBoth($domain_rows, $cert_rows, $today, $hosting_months = null)
    {
        $domains = $this->renewalMonths($domain_rows, $today);
        $certs = $this->renewalMonths($cert_rows, $today);

        $empty = array('label' => _('Overdue'), 'count' => 0, 'cost' => 0.0, 'missing' => 0, 'overdue' => true);
        $hosting_overdue = !empty($hosting_months['overdue']['count']);
        if ($hosting_overdue && empty($domains[0]['overdue']) && empty($certs[0]['overdue'])) {
            array_unshift($domains, $empty);
            array_unshift($certs, $empty);
        }
        if (!empty($domains[0]['overdue']) && empty($certs[0]['overdue'])) array_unshift($certs, $empty);
        if (!empty($certs[0]['overdue']) && empty($domains[0]['overdue'])) array_unshift($domains, $empty);

        $this_month = (int) substr($today, 0, 4) * 12 + (int) substr($today, 5, 2) - 1;
        $month_offset = 0;

        $months = array();
        foreach ($domains as $index => $domain_month) {
            $month = array(
                'label' => $domain_month['label'],
                'overdue' => $domain_month['overdue'],
                'domains' => $domain_month,
                'certs' => $certs[$index],
                'cost' => $domain_month['cost'] + $certs[$index]['cost']);
            if ($hosting_months !== null) {
                $hosting = array('label' => $domain_month['label'], 'count' => 0, 'cost' => 0.0, 'missing' => 0,
                    'overdue' => $domain_month['overdue']);
                if ($domain_month['overdue']) {
                    if ($hosting_overdue) $hosting = array_merge($hosting, $hosting_months['overdue']);
                } else {
                    $key = sprintf('%04d-%02d', intdiv($this_month + $month_offset, 12), ($this_month + $month_offset) % 12 + 1);
                    $month_offset++;
                    if (isset($hosting_months[$key])) $hosting = array_merge($hosting, $hosting_months[$key]);
                }
                $month['hosting'] = $hosting;
                $month['cost'] += $hosting['cost'];
            }
            $months[] = $month;
        }
        return $months;
    }

    /*
     * The At-Risk Domains from renewalDomains() rows, the domains that might not renew: every overdue domain, and every
     * domain expiring from $today through $risk_end (the next 30 days) with auto-renewal off. With
     * renewalDomainsWithStatus() rows, a domain in that window without a transfer lock, or that the registry has on
     * hold or is deleting or restoring, is at risk too (an overdue one is listed anyway).
     */
    public function atRiskDomains($rows, $today, $risk_end)
    {
        return array_values(array_filter($rows, function ($row) use ($today, $risk_end) {
            if ($row->expiry_date < $today) return true;
            if ($row->expiry_date > $risk_end) return false;
            if ($row->autorenew != '1') return true;
            return isset($row->transfer_lock) && ((int) $row->transfer_lock === DomainStatus::LOCK_OFF
                || (int) $row->registry_problem === 1);
        }));
    }

    /*
     * The At-Risk SSL Certificates from renewalCerts() rows: every certificate that's overdue or expires from $today
     * through $risk_end (the next 30 days). DomainMOD doesn't record whether a certificate renews itself, so every
     * certificate in the window is listed. Each row gains its reasons: overdue, expiring (only when not overdue),
     * domain (with domain_problem naming what's wrong with its domain, never for a certificate with no domain), and
     * nofee.
     */
    public function atRiskCerts($rows, $today, $risk_end)
    {
        $listed = array();
        foreach ($rows as $row) {
            if ($row->expiry_date > $risk_end) continue;
            $reasons = array();
            if ($row->expiry_date < $today) {
                $reasons[] = 'overdue';
            } else {
                $reasons[] = 'expiring';
            }
            // A certificate with no domain (a NULL joined domain) has no domain problem, since it doesn't need one
            $row->domain_problem = '';
            if ($row->domain_id === null) {
                // No domain to check
            } elseif ($row->domain_active == '0') {
                $row->domain_problem = 'inactive';
            } elseif ($row->domain_expiry_date < $today) {
                $row->domain_problem = 'expired';
            } elseif ($row->domain_expiry_date < $row->expiry_date) {
                $row->domain_problem = 'first';
            }
            if ($row->domain_problem != '') $reasons[] = 'domain';
            if (!$row->has_fee || !$row->has_rate) $reasons[] = 'nofee';
            $row->reasons = $reasons;
            $listed[] = $row;
        }
        return $listed;
    }

    /*
     * The renewal chart's figures as a table (from renewalMonths()), for screen readers and people without JavaScript:
     * each bar's label, its number of domains (or SSL certificates, when $item is 'certs'), and its cost, noting any
     * left out of the cost
     */
    public function renewalMonthsTable($months, $item = 'domains')
    {
        $currency = new Currency();
        ob_start(); ?>

        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Month'); ?></th>
                <th scope="col" class="text-end"><?php echo $item == 'certs' ? _('SSL Certificates') : _('Domains'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Renewal Cost'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($months as $month) { ?>

                <tr>
                    <td><?php echo $month['label']; ?></td>
                    <td class="text-end"><?php echo number_format($month['count']); ?></td>
                    <td class="text-end"><?php echo $currency->format($month['cost'], $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']); ?><?php
                    if ($month['missing'] > 0) { ?>
                        <br><span class="small text-body-secondary"><?php
                        if ($item == 'certs') {
                            echo sprintf(ngettext('%s SSL certificate has no fee or rate', '%s SSL certificates have no fee or rate', $month['missing']), number_format($month['missing']));
                        } else {
                            echo sprintf(ngettext('%s domain has no fee or rate', '%s domains have no fee or rate', $month['missing']), number_format($month['missing']));
                        } ?></span><?php
                    } ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

        return ob_get_clean();
    }

    /*
     * The Dashboard's renewal chart's figures as a table (from renewalMonthsBoth()), for screen readers and people
     * without JavaScript: each bar's label, its domains' and SSL certificates' renewal costs (and its hosting services'
     * payments, when the bars have them), noting any left out of a cost, and the costs together
     */
    public function renewalMonthsBothTable($months)
    {
        $currency = new Currency();
        $format = function ($amount) use ($currency) {
            return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
        };
        $with_hosting = isset($months[0]['hosting']);
        ob_start(); ?>

        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Month'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Domains'); ?></th><?php
                if ($with_hosting) { ?>
                    <th scope="col" class="text-end"><?php echo _('Hosting'); ?></th><?php
                } ?>
                <th scope="col" class="text-end"><?php echo _('SSL Certificates'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Total Cost'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($months as $month) { ?>

                <tr>
                    <th scope="row" class="fw-normal"><?php echo $month['label']; ?></th>
                    <td class="text-end"><?php echo $format($month['domains']['cost']); ?><?php
                    if ($month['domains']['missing'] > 0) { ?>
                        <br><span class="small text-body-secondary"><?php echo sprintf(ngettext('%s domain has no fee or rate', '%s domains have no fee or rate', $month['domains']['missing']), number_format($month['domains']['missing'])); ?></span><?php
                    } ?></td><?php
                    if ($with_hosting) { ?>
                        <td class="text-end"><?php echo $format($month['hosting']['cost']); ?><?php
                        if ($month['hosting']['missing'] > 0) { ?>
                            <br><span class="small text-body-secondary"><?php echo sprintf(ngettext('%s hosting service has no fee or rate', '%s hosting services have no fee or rate', $month['hosting']['missing']), number_format($month['hosting']['missing'])); ?></span><?php
                        } ?></td><?php
                    } ?>
                    <td class="text-end"><?php echo $format($month['certs']['cost']); ?><?php
                    if ($month['certs']['missing'] > 0) { ?>
                        <br><span class="small text-body-secondary"><?php echo sprintf(ngettext('%s SSL certificate has no fee or rate', '%s SSL certificates have no fee or rate', $month['certs']['missing']), number_format($month['certs']['missing'])); ?></span><?php
                    } ?></td>
                    <td class="text-end"><?php echo $format($month['cost']); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

        return ob_get_clean();
    }

    /*
     * Every registrar fee and every live domain, with their amounts converted to the user's currency, for TLD Price
     * Comparison. A fee whose currency has no rate for the user is kept with has_rate 0, so the page can say how many
     * were left out. Each fee row gains its yearly price for each price type (the fee plus the misc fee) and its
     * privacy fee, and each domain its current yearly cost (its own fee's renewal and misc fees, plus privacy when
     * privacy is on), as Maintenance's fee update works out total_cost.
     */
    public function tldPrices()
    {
        $pdo = Database::getInstance()->cnxx;
        $user_id = (int) ($_SESSION['s_user_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT f.id AS fee_id, f.registrar_id, r.name AS registrar_name, f.tld, f.initial_fee, f.renewal_fee,
                f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, (cc.id IS NOT NULL) AS has_rate,
                cc.conversion
            FROM fees AS f
            JOIN registrars AS r ON f.registrar_id = r.id
            JOIN currencies AS c ON f.currency_id = c.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            ORDER BY f.tld, r.name");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $fees = $stmt->fetchAll();

        foreach ($fees as $fee) {
            $conversion = (float) $fee->conversion;
            $fee->price = array(
                'renewal' => ((float) $fee->renewal_fee + (float) $fee->misc_fee) * $conversion,
                'initial' => ((float) $fee->initial_fee + (float) $fee->misc_fee) * $conversion,
                'transfer' => ((float) $fee->transfer_fee + (float) $fee->misc_fee) * $conversion);
            $fee->converted = array(
                'renewal' => (float) $fee->renewal_fee * $conversion,
                'initial' => (float) $fee->initial_fee * $conversion,
                'transfer' => (float) $fee->transfer_fee * $conversion,
                'privacy' => (float) $fee->privacy_fee * $conversion,
                'misc' => (float) $fee->misc_fee * $conversion);
        }

        $stmt = $pdo->prepare("
            SELECT d.id, d.domain, d.tld, d.registrar_id, r.name AS registrar_name, d.privacy,
                (f.id IS NOT NULL) AS has_fee, (cc.id IS NOT NULL) AS has_rate,
                (f.renewal_fee + f.misc_fee + IF(d.privacy = '1', f.privacy_fee, 0)) * cc.conversion AS current_cost
            FROM domains AS d
            LEFT JOIN registrars AS r ON d.registrar_id = r.id
            LEFT JOIN fees AS f ON d.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE d.active NOT IN ('0', '10')
            ORDER BY d.domain");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $domains = $stmt->fetchAll();

        return array('fees' => $fees, 'domains' => $domains);
    }

    /*
     * TLD Price Comparison's overview from tldPrices(): one row per TLD that has at least one fee with a rate, with its
     * fees (cheapest first by renewal price), the cheapest and most expensive registrars (ties share), the spread, how
     * many registrars have a fee for it, and how many of the user's live domains use it. Sorted by domains, most first,
     * then by TLD. Also returns how many fees were left out for having no rate.
     */
    public function tldOverview($prices)
    {
        $by_tld = array();
        $left_out = 0;
        foreach ($prices['fees'] as $fee) {
            if (!$fee->has_rate) {
                $left_out++;
                continue;
            }
            $by_tld[$fee->tld][] = $fee;
        }

        $domain_counts = array();
        foreach ($prices['domains'] as $domain) {
            $domain_counts[$domain->tld] = ($domain_counts[$domain->tld] ?? 0) + 1;
        }

        $rows = array();
        foreach ($by_tld as $tld => $fees) {
            $rows[] = $this->tldRow((string) $tld, $fees, $domain_counts[$tld] ?? 0, 'renewal');
        }

        usort($rows, function ($a, $b) {
            if ($a['domains'] !== $b['domains']) return $b['domains'] - $a['domains'];
            return strcmp($a['tld'], $b['tld']);
        });

        return array('rows' => $rows, 'left_out' => $left_out);
    }

    /*
     * One TLD's fees ranked by a price type (renewal, initial, or transfer), cheapest first: the fees, the cheapest and
     * most expensive fees (ties share, compared to the cent), the spread, and the number of registrars
     */
    public function tldRow($tld, $fees, $domain_count, $price_type)
    {
        $ranked = $this->rankFees($fees, $price_type, 'registrar_name', 'registrar_id');

        return array(
            'tld' => $tld,
            'fees' => $ranked['fees'],
            'cheapest' => $ranked['cheapest'],
            'most_expensive' => $ranked['most_expensive'],
            'low' => $ranked['low'],
            'high' => $ranked['high'],
            'spread' => $ranked['spread'],
            'registrars' => $ranked['sellers'],
            'domains' => $domain_count);
    }

    /*
     * Fees ranked by a price type, cheapest first (ties by the seller's name, from $name_key): the cheapest and most
     * expensive fees by index (ties share, compared to the cent), the lowest and highest prices, the spread, and how
     * many sellers (registrars or SSL providers, from $seller_key) have a fee, for TLD Price Comparison and SSL Price
     * Comparison
     */
    private function rankFees($fees, $price_type, $name_key, $seller_key)
    {
        usort($fees, function ($a, $b) use ($price_type, $name_key) {
            $difference = round($a->price[$price_type], 2) <=> round($b->price[$price_type], 2);
            return $difference !== 0 ? $difference : strcmp($a->$name_key, $b->$name_key);
        });

        $low = round($fees[0]->price[$price_type], 2);
        $high = round($fees[count($fees) - 1]->price[$price_type], 2);
        $cheapest = $most_expensive = array();
        $sellers = array();
        foreach ($fees as $index => $fee) {
            if (round($fee->price[$price_type], 2) == $low) $cheapest[] = $index;
            if (round($fee->price[$price_type], 2) == $high) $most_expensive[] = $index;
            $sellers[$fee->$seller_key] = true;
        }

        return array(
            'fees' => $fees,
            'cheapest' => $cheapest,
            'most_expensive' => $most_expensive,
            'low' => $low,
            'high' => $high,
            'spread' => $high - $low,
            'sellers' => count($sellers));
    }

    /*
     * TLD Price Comparison's possible savings from tldPrices(): every live domain on a TLD with fees (with rates) at
     * two or more registrars where another registrar's full yearly cost for that domain (renewal and misc, plus privacy
     * when the domain has privacy on) is lower than its current cost. The destination is the registrar with the lowest
     * full cost for that domain (ties: the first by name), worked out per domain. Sorted by saving, largest first. Also
     * returns how many domains on those TLDs were left out because their own fee is missing or has no rate.
     */
    public function tldSavings($prices)
    {
        $by_tld = array();
        foreach ($prices['fees'] as $fee) {
            if ($fee->has_rate) $by_tld[$fee->tld][] = $fee;
        }
        $compared = array();
        foreach ($by_tld as $tld => $fees) {
            $registrars = array();
            foreach ($fees as $fee) $registrars[$fee->registrar_id] = true;
            if (count($registrars) >= 2) $compared[$tld] = $fees;
        }

        $rows = array();
        $left_out = 0;
        foreach ($prices['domains'] as $domain) {
            if (!isset($compared[$domain->tld])) continue;
            if (!$domain->has_fee || !$domain->has_rate) {
                $left_out++;
                continue;
            }
            $current = round((float) $domain->current_cost, 2);
            $best = null;
            $best_cost = null;
            foreach ($compared[$domain->tld] as $fee) {
                if ((int) $fee->registrar_id === (int) $domain->registrar_id) continue;
                $cost = round($fee->price['renewal'] + ($domain->privacy == '1' ? $fee->converted['privacy'] : 0), 2);
                if ($best === null || $cost < $best_cost
                    || ($cost == $best_cost && strcmp($fee->registrar_name, $best->registrar_name) < 0)) {
                    $best = $fee;
                    $best_cost = $cost;
                }
            }
            if ($best !== null && $best_cost < $current) {
                $rows[] = array(
                    'domain' => $domain,
                    'current' => $current,
                    'destination' => $best,
                    'destination_cost' => $best_cost,
                    'saving' => $current - $best_cost);
            }
        }

        usort($rows, function ($a, $b) {
            $difference = round($b['saving'], 2) <=> round($a['saving'], 2);
            return $difference !== 0 ? $difference : strcmp($a['domain']->domain, $b['domain']->domain);
        });

        return array('rows' => $rows, 'left_out' => $left_out, 'tlds_compared' => count($compared));
    }

    /*
     * Every SSL provider fee, with its amounts converted to the user's currency, and the user's active SSL certificates
     * counted by type and provider, for SSL Price Comparison. A fee whose currency has no rate for the user is kept
     * with has_rate 0, so the page can say how many were left out. Each fee gains its yearly price for each price type
     * (the fee plus the misc fee), as Maintenance's fee update works out total_cost.
     */
    public function sslPrices()
    {
        $pdo = Database::getInstance()->cnxx;

        $stmt = $pdo->prepare("
            SELECT f.id AS fee_id, f.ssl_provider_id, sslp.name AS ssl_provider_name, f.type_id, sslt.type,
                f.initial_fee, f.renewal_fee, f.misc_fee, c.currency, (cc.id IS NOT NULL) AS has_rate, cc.conversion
            FROM ssl_fees AS f
            JOIN ssl_providers AS sslp ON f.ssl_provider_id = sslp.id
            JOIN ssl_cert_types AS sslt ON f.type_id = sslt.id
            JOIN currencies AS c ON f.currency_id = c.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            ORDER BY sslt.type, sslp.name");
        $stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->execute();
        $fees = $stmt->fetchAll();

        foreach ($fees as $fee) {
            $conversion = (float) $fee->conversion;
            $fee->price = array(
                'renewal' => ((float) $fee->renewal_fee + (float) $fee->misc_fee) * $conversion,
                'initial' => ((float) $fee->initial_fee + (float) $fee->misc_fee) * $conversion);
            $fee->converted = array(
                'renewal' => (float) $fee->renewal_fee * $conversion,
                'initial' => (float) $fee->initial_fee * $conversion,
                'misc' => (float) $fee->misc_fee * $conversion);
        }

        $certs = $pdo->query("
            SELECT type_id, ssl_provider_id, COUNT(*) AS certs
            FROM ssl_certs
            WHERE active NOT IN ('0')
            GROUP BY type_id, ssl_provider_id")->fetchAll();

        return array('fees' => $fees, 'certs' => $certs);
    }

    /*
     * SSL Price Comparison's overview from sslPrices(): one row per certificate type that has at least one fee with a
     * rate, as sslRow() ranks it by renewal price. Sorted by the user's active certificates of that type, most first,
     * then by type. Also returns how many fees were left out for having no rate.
     */
    public function sslOverview($prices)
    {
        $by_type = array();
        $left_out = 0;
        foreach ($prices['fees'] as $fee) {
            if (!$fee->has_rate) {
                $left_out++;
                continue;
            }
            $by_type[(int) $fee->type_id][] = $fee;
        }

        $cert_counts = array();
        foreach ($prices['certs'] as $row) {
            $cert_counts[(int) $row->type_id] = ($cert_counts[(int) $row->type_id] ?? 0) + (int) $row->certs;
        }

        $rows = array();
        foreach ($by_type as $type_id => $fees) {
            $rows[] = $this->sslRow($type_id, $fees, $cert_counts[$type_id] ?? 0, 'renewal');
        }

        usort($rows, function ($a, $b) {
            if ($a['certs'] !== $b['certs']) return $b['certs'] - $a['certs'];
            return strcmp($a['type'], $b['type']);
        });

        return array('rows' => $rows, 'left_out' => $left_out);
    }

    /*
     * One certificate type's fees ranked by a price type (renewal or initial), cheapest first: the fees, the cheapest
     * and most expensive fees (ties share, compared to the cent), the spread, and the number of providers
     */
    public function sslRow($type_id, $fees, $cert_count, $price_type)
    {
        $ranked = $this->rankFees($fees, $price_type, 'ssl_provider_name', 'ssl_provider_id');

        return array(
            'type_id' => (int) $type_id,
            'type' => (string) $ranked['fees'][0]->type,
            'fees' => $ranked['fees'],
            'cheapest' => $ranked['cheapest'],
            'most_expensive' => $ranked['most_expensive'],
            'low' => $ranked['low'],
            'high' => $ranked['high'],
            'spread' => $ranked['spread'],
            'providers' => $ranked['sellers'],
            'certs' => (int) $cert_count);
    }

    /*
     * The Renewal Calendar's weeks for $month (YYYY-MM): one array of seven dates (YYYY-MM-DD) per week, Sunday first,
     * with null for the days before the month starts and after it ends, so every week is a full row
     */
    public function calendarWeeks($month)
    {
        $day = new \DateTime($month . '-01');
        $days_in_month = (int) $day->format('t');
        $week = array_fill(0, (int) $day->format('w'), null);
        $weeks = array();
        for ($i = 1; $i <= $days_in_month; $i++) {
            $week[] = $month . '-' . sprintf('%02d', $i);
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = array();
            }
        }
        if ($week) $weeks[] = array_pad($week, 7, null);
        return $weeks;
    }

    // A month's name (1 to 12), translated, as the Cost by Month reports show it
    public function monthName($month)
    {
        $names = array(1 => _('January'), 2 => _('February'), 3 => _('March'), 4 => _('April'), 5 => _('May'),
            6 => _('June'), 7 => _('July'), 8 => _('August'), 9 => _('September'), 10 => _('October'),
            11 => _('November'), 12 => _('December'));
        return $names[(int) $month] ?? '';
    }

} //@formatter:on

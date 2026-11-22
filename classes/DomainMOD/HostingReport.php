<?php
/**
 * /classes/DomainMOD/HostingReport.php
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
 * The Hosting reports' projected renewals: Hosting Costs grouped by Month, Hosting Renewals, the Renewal Calendar's
 * hosting services, and At-Risk Hosting Services. A monthly plan renews twelve times a year, so rather than putting a
 * service's cost in the month of its stored renewal date (as the SSL reports do), each one's renewals inside a range are
 * projected with Hosting::renewalsInRange(): the stored date itself, then that date plus each whole number of terms.
 * Every hosting service except Cancelled ones is read, Pending ones included.
 */
class HostingReport
{
    // The Month grouping's range: the 12 months starting with the current month by default, and at most 36 months
    const DEFAULT_RANGE_MONTHS = 12;
    const MAX_RANGE_MONTHS = 36;

    protected $pdo;
    protected $user_id;

    // $pdo is the database connection (Database::getInstance()->cnxx), and $user_id the user whose conversion rates
    // turn every cost into their default currency
    public function __construct($pdo, $user_id)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
    }

    /*
     * Every hosting service that isn't Cancelled, with what the reports show: its provider, hosting account, and service
     * type, and its cost in the user's currency. Fees and conversion rates are left joins, so a service with no fee, or
     * whose fee's currency has no rate for the user, is still listed with has_fee and has_rate saying why. $end_date
     * (YYYY-MM-DD, included) leaves out services whose stored date is after it, since they have no renewal before then.
     * Sorted by renewal date, then name. Returns nothing before the database update.
     */
    public function services($end_date = '')
    {
        if (!Hosting::isAvailable($this->pdo)) return array();

        $stmt = $this->pdo->prepare("
            SELECT hs.id, hs.name, hs.expiry_date, hs.billing_term, hs.renewal_day, hs.autorenew, hs.active,
                h.name AS provider_name, o.name AS owner_name, hpa.username, ht.type, (f.id IS NOT NULL) AS has_fee,
                (cc.id IS NOT NULL) AS has_rate, hs.total_cost * cc.conversion AS converted_cost
            FROM hosting_services AS hs
            LEFT JOIN hosting AS h ON hs.hosting_id = h.id
            LEFT JOIN hosting_provider_accounts AS hpa ON hs.account_id = hpa.id
            LEFT JOIN owners AS o ON hs.owner_id = o.id
            LEFT JOIN hosting_types AS ht ON hs.type_id = ht.id
            LEFT JOIN hosting_fees AS f ON hs.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE hs.active NOT IN ('" . Hosting::CANCELLED . "')" . ($end_date !== '' ? "
              AND hs.expiry_date <= :end_date" : "") . "
            ORDER BY hs.expiry_date, hs.name");
        $stmt->bindValue('user_id', $this->user_id, \PDO::PARAM_INT);
        if ($end_date !== '') $stmt->bindValue('end_date', (string) $end_date, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * Each projected renewal of $services (services() rows) inside [$start, $end] (YYYY-MM-DD, inclusive), as a copy of
     * its service row with renewal_date set and projected saying whether it's a later renewal (true) or the stored date
     * itself (false). A service with an invalid billing term has none. Sorted by date, then name.
     */
    public static function payments($services, $start, $end)
    {
        $payments = array();
        foreach ($services as $service) {
            foreach (Hosting::renewalsInRange($service->expiry_date, $service->billing_term, $service->renewal_day,
                $start, $end) as $date) {
                $payment = clone $service;
                $payment->renewal_date = $date;
                $payment->projected = $date !== $service->expiry_date;
                $payments[] = $payment;
            }
        }
        usort($payments, function ($a, $b) {
            return array($a->renewal_date, strtolower((string) $a->name), (int) $a->id) <=>
                array($b->renewal_date, strtolower((string) $b->name), (int) $b->id);
        });
        return $payments;
    }

    /*
     * The Month grouping's figures from $services (services() rows): the projected payments inside [$start, $end], kept
     * as month totals rather than a row per payment. Only services with a fee and a rate are counted, as the other
     * Hosting Costs groupings count them. Returns 'rows' (each month with a payment, in order, with its year and month),
     * 'month' (keyed year-month, such as 2026-3) and 'year' (keyed by year), each a cost, 'total', the sum of every
     * payment, and 'count', the number of services with at least one payment.
     */
    public static function monthTotals($services, $start, $end)
    {
        $months = array();
        $years = array();
        $total = 0.0;
        $count = 0;
        foreach ($services as $service) {
            if (!$service->has_fee || !$service->has_rate) continue;
            $dates = Hosting::renewalsInRange($service->expiry_date, $service->billing_term, $service->renewal_day,
                $start, $end);
            if (!$dates) continue;
            $count++;
            foreach ($dates as $date) {
                $year = (int) substr($date, 0, 4);
                $key = $year . '-' . (int) substr($date, 5, 2);
                $months[$key] = ($months[$key] ?? 0.0) + (float) $service->converted_cost;
                $years[$year] = ($years[$year] ?? 0.0) + (float) $service->converted_cost;
                $total += (float) $service->converted_cost;
            }
        }

        uksort($months, function ($a, $b) {
            return array_map('intval', explode('-', $a)) <=> array_map('intval', explode('-', $b));
        });
        ksort($years);

        $rows = array();
        foreach ($months as $key => $cost) {
            list($year, $month) = array_map('intval', explode('-', $key));
            $months[$key] = round($cost, 2);
            $rows[] = (object) array('year' => $year, 'month' => $month);
        }
        foreach ($years as $year => $cost) $years[$year] = round($cost, 2);

        return array('rows' => $rows, 'month' => $months, 'year' => $years, 'total' => round($total, 2),
            'count' => $count);
    }

    // The Month grouping's default range: the 12 months starting with $today's month (YYYY-MM-DD)
    public static function defaultMonthRange($today)
    {
        $start = substr($today, 0, 7) . '-01';
        $dates = new Date();
        return array($start, Hosting::addDays($dates->addMonths($start, self::DEFAULT_RANGE_MONTHS, 1), -1));
    }

    /*
     * The range the Month grouping shows for a date range picker value: the range itself when both dates are real dates,
     * the end isn't before the start, and it's no longer than 36 months, or otherwise the 12-month default. Returns the
     * start, the end, and why the default was used: '' (it wasn't, or there was no range), 'invalid' (not a valid
     * range, the end before the start included), or 'too_long'.
     */
    public static function resolveMonthRange($daterange, $today)
    {
        $daterange = is_string($daterange) ? trim($daterange) : '';
        if ($daterange === '') return array_merge(self::defaultMonthRange($today), array(''));

        $dates = new Date();
        list($start, $end) = $dates->splitAndCheckRange($daterange);
        if ($start === '' || $end < $start) return array_merge(self::defaultMonthRange($today), array('invalid'));

        $longest = $dates->addMonths($start, self::MAX_RANGE_MONTHS, (int) substr($start, 8, 2));
        if ($longest !== '' && $end > Hosting::addDays($longest, -1)) {
            return array_merge(self::defaultMonthRange($today), array('too_long'));
        }

        return array($start, $end, '');
    }

    /*
     * Hosting Renewals' chart bars from $services (services() rows), as Reporting::renewalMonths() gives them for SSL
     * certificates: Overdue (services whose stored date has passed and that are overdue on $today, the hosting date, see
     * Hosting::renewalState(), shown only when there are some), then one bar per month from $today's month through the
     * 11th month after it, each holding that month's projected renewals from $today on. A service waiting for its
     * renewal to be confirmed isn't overdue, and its stored date is in no bar (it may already be paid), while its later
     * renewals are in their months as usual. Each bar has its label, the number of renewals, the cost of those with a fee and
     * a rate, and how many are left out of the cost for having neither.
     */
    public static function renewalMonths($services, $today)
    {
        $reporting = new Reporting();
        $this_month = (int) substr($today, 0, 4) * 12 + (int) substr($today, 5, 2) - 1;
        $last_day = Hosting::addDays($reporting->renewalChartEnd($today), -1);

        $overdue = array('label' => _('Overdue'), 'count' => 0, 'cost' => 0.0, 'missing' => 0, 'overdue' => true);
        $months = array();
        for ($i = 0; $i < 12; $i++) {
            $month_number = ($this_month + $i) % 12 + 1;
            $year = intdiv($this_month + $i, 12);
            $months[$i] = array('label' => $reporting->monthName($month_number) . ' ' . $year, 'count' => 0,
                'cost' => 0.0, 'missing' => 0, 'overdue' => false);
        }

        $add = function (&$bucket, $service) {
            $bucket['count']++;
            if ($service->has_fee && $service->has_rate) {
                $bucket['cost'] += (float) $service->converted_cost;
            } else {
                $bucket['missing']++;
            }
        };

        foreach ($services as $service) {
            if (self::state($service, $today) === Hosting::STATE_OVERDUE) $add($overdue, $service);
            // A billing term DomainMOD doesn't know gives no projected renewals (an overdue stored date is counted above)
            if (!Hosting::validTerm($service->billing_term)) continue;
            foreach (Hosting::renewalsInRange($service->expiry_date, $service->billing_term, $service->renewal_day,
                $today, $last_day) as $date) {
                $index = (int) substr($date, 0, 4) * 12 + (int) substr($date, 5, 2) - 1 - $this_month;
                if ($index < 0 || $index > 11) continue;
                $add($months[$index], $service);
            }
        }

        return $overdue['count'] > 0 ? array_merge(array($overdue), $months) : $months;
    }

    /*
     * Hosting Renewals' list from $services (services() rows): each service whose stored date is before $today (the
     * hosting date) once, with its renewal state (Hosting::renewalState(), Overdue or Confirm renewal), then every
     * projected renewal from $today through $end, each a copy of its service row with renewal_date, projected, state,
     * overdue, and confirm set. The passed stored dates come first, by date (as $services is sorted), then the rest by
     * date, which is the order the page's default sort by Renewal Date gives too.
     */
    public static function renewalList($services, $today, $end)
    {
        $passed = array();
        $valid = array();
        foreach ($services as $service) {
            // A billing term DomainMOD doesn't know gives no projected renewals, but a passed stored date is still listed
            if (Hosting::validTerm($service->billing_term)) $valid[] = $service;
            if ($service->expiry_date < $today) {
                $row = clone $service;
                $row->renewal_date = $service->expiry_date;
                $row->projected = false;
                $row->state = self::state($service, $today);
                $row->overdue = $row->state === Hosting::STATE_OVERDUE;
                $row->confirm = $row->state === Hosting::STATE_CONFIRM;
                $passed[] = $row;
            }
        }

        $upcoming = self::payments($valid, $today, $end);
        foreach ($upcoming as $row) {
            $row->state = '';
            $row->overdue = false;
            $row->confirm = false;
        }

        return array_merge($passed, $upcoming);
    }

    /*
     * Hosting Renewals' tiles from renewalList() rows: for each of $windows (numbers of days), the renewals from $today
     * (the hosting date) through that many days ahead ('count'), the cost of those with a fee and a rate ('cost'), and
     * how many have neither ('missing'). A stored date that has passed (Overdue or Confirm renewal) is never in them, so
     * a service due yesterday adds only its next renewal.
     */
    public static function windowTotals($rows, $today, array $windows)
    {
        $totals = array();
        foreach ($windows as $days) {
            $end = Hosting::addDays($today, (int) $days);
            $totals[$days] = array('count' => 0, 'cost' => 0.0, 'missing' => 0);
            foreach ($rows as $row) {
                if ($row->renewal_date < $today || $row->renewal_date > $end) continue;
                $totals[$days]['count']++;
                if ($row->has_fee && $row->has_rate) {
                    $totals[$days]['cost'] += (float) $row->converted_cost;
                } else {
                    $totals[$days]['missing']++;
                }
            }
        }
        return $totals;
    }

    /*
     * How many of $rows (services() rows) are waiting for their renewal to be confirmed on $today (the hosting date), by
     * the same Hosting::renewalState() call atRisk() lists them with, so the Dashboard's tile matches the At-Risk list
     * it opens
     */
    public static function confirmCount($rows, $today)
    {
        $count = 0;
        foreach ($rows as $row) {
            if (self::state($row, $today) === Hosting::STATE_CONFIRM) $count++;
        }
        return $count;
    }

    // A services() row's renewal state on $today (see Hosting::renewalState())
    protected static function state($row, $today)
    {
        return Hosting::renewalState($row->active, $row->autorenew, $row->expiry_date, $row->billing_term, $today);
    }

    /*
     * The At-Risk Hosting Services from $rows (services() rows), with option B: every service whose stored date is
     * before $today (the hosting date), every service renewing from $today through $risk_end without auto-renew, and an
     * auto-renewing service in that window only when it has a missing fee or rate. A yearly service renewing months
     * from now isn't looked at, whatever its fee. Each listed row gains its reasons, as Reporting::atRiskCerts() gives
     * them for SSL certificates: overdue or confirm (a passed date, by its renewal state, see Hosting::renewalState()),
     * expiring (only when the date hasn't passed and the service doesn't auto-renew), and nofee.
     */
    public static function atRisk($rows, $today, $risk_end)
    {
        $listed = array();
        foreach ($rows as $row) {
            if ($row->expiry_date > $risk_end) continue;
            $reasons = array();
            $state = self::state($row, $today);
            if ($state !== '') {
                $reasons[] = $state;
            } elseif ($row->autorenew != '1') {
                $reasons[] = 'expiring';
            }
            if (!$row->has_fee || !$row->has_rate) $reasons[] = 'nofee';
            // An auto-renewing service in the window that has nothing wrong with it isn't at risk
            if (!$reasons) continue;
            $row->reasons = $reasons;
            $listed[] = $row;
        }
        return $listed;
    }

    /*
     * Hosting Renewals' chart figures as a table (from renewalMonths()), for screen readers and people without
     * JavaScript, as Reporting::renewalMonthsTable() gives them for domains and SSL certificates
     */
    public static function renewalMonthsTable($months)
    {
        $currency = new Currency();
        ob_start(); ?>

        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Month'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Renewals'); ?></th>
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
                        <br><span class="small text-body-secondary"><?php echo sprintf(ngettext('%s renewal has no fee or rate', '%s renewals have no fee or rate', $month['missing']), number_format($month['missing'])); ?></span><?php
                    } ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

        return ob_get_clean();
    }

} //@formatter:on

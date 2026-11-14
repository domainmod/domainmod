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
    const AT_RISK_DAYS = 30; // At-Risk Domains always looks this far ahead, separately from the Expiration Days setting

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
     * included, for Upcoming Renewals, At-Risk Domains, and the Dashboard. Fees and conversion rates are left joins, so
     * a domain with no fee, or whose fee's currency has no rate for the user, is still listed: its converted_cost is
     * null, and has_fee and has_rate say why. Sorted by expiry date.
     */
    public function renewalDomains($end_date)
    {
        $pdo = Database::getInstance()->cnxx;

        $stmt = $pdo->prepare("
            SELECT d.id, d.domain, d.expiry_date, d.autorenew, d.privacy, r.name AS registrar_name,
                o.name AS owner_name, ra.username, (f.id IS NOT NULL) AS has_fee, (cc.id IS NOT NULL) AS has_rate,
                d.total_cost * cc.conversion AS converted_cost
            FROM domains AS d
            LEFT JOIN registrars AS r ON d.registrar_id = r.id
            LEFT JOIN registrar_accounts AS ra ON d.account_id = ra.id
            LEFT JOIN owners AS o ON d.owner_id = o.id
            LEFT JOIN fees AS f ON d.fee_id = f.id
            LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
            WHERE d.active NOT IN ('0', '10')
              AND d.expiry_date < :end_date
            ORDER BY d.expiry_date, d.domain");
        $stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue('end_date', $end_date, \PDO::PARAM_STR);
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
     * The At-Risk Domains from renewalDomains() rows: every overdue domain, and every domain expiring from $today
     * through $risk_end (the next 30 days) with auto-renewal or privacy off
     */
    public function atRiskDomains($rows, $today, $risk_end)
    {
        return array_values(array_filter($rows, function ($row) use ($today, $risk_end) {
            if ($row->expiry_date < $today) return true;
            return $row->expiry_date <= $risk_end && ($row->autorenew != '1' || $row->privacy != '1');
        }));
    }

    /*
     * The renewal chart's figures as a table (from renewalMonths()), for screen readers and people without JavaScript:
     * each bar's label, its number of domains, and its cost, noting any domains left out of the cost
     */
    public function renewalMonthsTable($months)
    {
        $currency = new Currency();
        ob_start(); ?>

        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Month'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Domains'); ?></th>
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
                        <br><span class="small text-body-secondary"><?php echo sprintf(ngettext('%s domain has no fee or rate', '%s domains have no fee or rate', $month['missing']), number_format($month['missing'])); ?></span><?php
                    } ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

        return ob_get_clean();
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

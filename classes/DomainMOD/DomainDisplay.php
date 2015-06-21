<?php
/**
 * /classes/DomainMOD/DomainDisplay.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
?>
<?php
namespace DomainMOD;

class DomainDisplay
{

    public function getActiveString($is_active)
    {

        switch ($is_active) {

            case "0":
                $is_active_string = " AND d.active = '0' "; break;
            case "1":
                $is_active_string = " AND d.active = '1' "; break;
            case "2":
                $is_active_string = " AND d.active = '2' "; break;
            case "3":
                $is_active_string = " AND d.active = '3' "; break;
            case "4":
                $is_active_string = " AND d.active = '4' "; break;
            case "5":
                $is_active_string = " AND d.active = '5' "; break;
            case "6":
                $is_active_string = " AND d.active = '6' "; break;
            case "7":
                $is_active_string = " AND d.active = '7' "; break;
            case "8":
                $is_active_string = " AND d.active = '8' "; break;
            case "9":
                $is_active_string = " AND d.active = '9' "; break;
            case "10":
                $is_active_string = " AND d.active = '10' "; break;
            case "LIVE":
                $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; break;
            case "ALL":
                $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; break;
            default:
                $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";

        }

        return $is_active_string;

    }

    public function getActiveStringTld($is_active)
    {

        switch ($is_active) {

            case "0":
                $is_active_string = " WHERE active = '0' "; break;
            case "1":
                $is_active_string = " AND active = '1' "; break;
            case "2":
                $is_active_string = " AND active = '2' "; break;
            case "3":
                $is_active_string = " AND active = '3' "; break;
            case "4":
                $is_active_string = " AND active = '4' "; break;
            case "5":
                $is_active_string = " AND active = '5' "; break;
            case "6":
                $is_active_string = " AND active = '6' "; break;
            case "7":
                $is_active_string = " AND active = '7' "; break;
            case "8":
                $is_active_string = " AND active = '8' "; break;
            case "9":
                $is_active_string = " AND active = '9' "; break;
            case "10":
                $is_active_string = " AND active = '10' "; break;
            case "LIVE":
                $is_active_string = " AND active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; break;
            case "ALL":
                $is_active_string = " AND active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; break;
            default:
                $is_active_string = " AND active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";

        }

        return $is_active_string;

    }

    public function getOrder($sort_by)
    {

        switch ($sort_by) {

            case "ed_d":
                $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc "; break;
            case "pc_a":
                $sort_by_string = " ORDER BY cat.name asc "; break;
            case "pc_d":
                $sort_by_string = " ORDER BY cat.name desc "; break;
            case "dn_a":
                $sort_by_string = " ORDER BY d.domain asc "; break;
            case "dn_d":
                $sort_by_string = " ORDER BY d.domain desc "; break;
            case "df_a":
                $sort_by_string = " ORDER BY d.total_cost asc "; break;
            case "df_d":
                $sort_by_string = " ORDER BY d.total_cost desc "; break;
            case "dns_a":
                $sort_by_string = " ORDER BY dns.name asc "; break;
            case "dns_d":
                $sort_by_string = " ORDER BY dns.name desc "; break;
            case "tld_a":
                $sort_by_string = " ORDER BY d.tld asc "; break;
            case "tld_d":
                $sort_by_string = " ORDER BY d.tld desc "; break;
            case "ip_a":
                $sort_by_string = " ORDER BY ip.name asc, ip.ip asc"; break;
            case "ip_d":
                $sort_by_string = " ORDER BY ip.name desc, ip.ip desc"; break;
            case "wh_a":
                $sort_by_string = " ORDER BY h.name asc"; break;
            case "wh_d":
                $sort_by_string = " ORDER BY h.name desc"; break;
            case "o_a":
                $sort_by_string = " ORDER BY o.name asc, d.domain asc "; break;
            case "o_d":
                $sort_by_string = " ORDER BY o.name desc, d.domain asc "; break;
            case "r_a":
                $sort_by_string = " ORDER BY r.name asc, d.domain asc "; break;
            case "r_d":
                $sort_by_string = " ORDER BY r.name desc, d.domain asc "; break;
            case "ra_a":
                $sort_by_string = " ORDER BY r.name asc, d.domain asc "; break;
            case "ra_d":
                $sort_by_string = " ORDER BY r.name desc, d.domain asc "; break;
            default:
                $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc ";

        }

        return $sort_by_string;

    }

}

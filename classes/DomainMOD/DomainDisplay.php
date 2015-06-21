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

        if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; }
        elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; }
        elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; }
        elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; }
        elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; }
        elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; }
        elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; }
        elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; }
        elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; }
        elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; }
        elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; }
        elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; }
        elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; }
        else { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; }

        return $is_active_string;

    }

    public function getActiveStringTld($is_active)
    {

        if ($is_active == "0") { $is_active_string = " WHERE active = '0' "; }
        elseif ($is_active == "1") { $is_active_string = " WHERE active = '1' "; }
        elseif ($is_active == "2") { $is_active_string = " WHERE active = '2' "; }
        elseif ($is_active == "3") { $is_active_string = " WHERE active = '3' "; }
        elseif ($is_active == "4") { $is_active_string = " WHERE active = '4' "; }
        elseif ($is_active == "5") { $is_active_string = " WHERE active = '5' "; }
        elseif ($is_active == "6") { $is_active_string = " WHERE active = '6' "; }
        elseif ($is_active == "7") { $is_active_string = " WHERE active = '7' "; }
        elseif ($is_active == "8") { $is_active_string = " WHERE active = '8' "; }
        elseif ($is_active == "9") { $is_active_string = " WHERE active = '9' "; }
        elseif ($is_active == "10") { $is_active_string = " WHERE active = '10' "; }
        elseif ($is_active == "LIVE") { $is_active_string = " WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; }
        elseif ($is_active == "ALL") { $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; }
        else { $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; }

        return $is_active_string;

    }

    public function getOrder($sort_by)
    {

        if ($sort_by == "ed_a") { $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc "; }
        elseif ($sort_by == "ed_d") { $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc "; }
        elseif ($sort_by == "pc_a") { $sort_by_string = " ORDER BY cat.name asc "; }
        elseif ($sort_by == "pc_d") { $sort_by_string = " ORDER BY cat.name desc "; }
        elseif ($sort_by == "dn_a") { $sort_by_string = " ORDER BY d.domain asc "; }
        elseif ($sort_by == "dn_d") { $sort_by_string = " ORDER BY d.domain desc "; }
        elseif ($sort_by == "df_a") { $sort_by_string = " ORDER BY d.total_cost asc "; }
        elseif ($sort_by == "df_d") { $sort_by_string = " ORDER BY d.total_cost desc "; }
        elseif ($sort_by == "dns_a") { $sort_by_string = " ORDER BY dns.name asc "; }
        elseif ($sort_by == "dns_d") { $sort_by_string = " ORDER BY dns.name desc "; }
        elseif ($sort_by == "tld_a") { $sort_by_string = " ORDER BY d.tld asc "; }
        elseif ($sort_by == "tld_d") { $sort_by_string = " ORDER BY d.tld desc "; }
        elseif ($sort_by == "ip_a") { $sort_by_string = " ORDER BY ip.name asc, ip.ip asc"; }
        elseif ($sort_by == "ip_d") { $sort_by_string = " ORDER BY ip.name desc, ip.ip desc"; }
        elseif ($sort_by == "wh_a") { $sort_by_string = " ORDER BY h.name asc"; }
        elseif ($sort_by == "wh_d") { $sort_by_string = " ORDER BY h.name desc"; }
        elseif ($sort_by == "o_a") { $sort_by_string = " ORDER BY o.name asc, d.domain asc "; }
        elseif ($sort_by == "o_d") { $sort_by_string = " ORDER BY o.name desc, d.domain asc "; }
        elseif ($sort_by == "r_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc "; }
        elseif ($sort_by == "r_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc "; }
        elseif ($sort_by == "ra_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc "; }
        elseif ($sort_by == "ra_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc "; }
        else { $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc "; }

        return $sort_by_string;

    }

}

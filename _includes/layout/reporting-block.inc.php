<?php
/**
 * /_includes/layout/reporting-block.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
$layout = new DomainMOD\Layout();
echo $layout->jumpMenu();
?>
    Before running any reports you should <a href="<?php echo $web_root; ?>/settings/update-conversions.php">update
    the conversion rates</a>.<BR><BR><BR>
    <div class="reporting-block-outer">
        <div class="reporting-block-left">
            <div class="subheadline">Domain Reports</div>
            <BR>

            <form name="reporting_menu_domain_form" method="post" action="index.php">
                <select name="reporting_menu_domains" onChange="MM_jumpMenu('parent',this,0)">
                    <option
                        value="<?php echo $web_root; ?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>
                        Click to select a Domain Report
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-category.php?all=1"<?php if ($report_name == "domain-cost-by-category-report") echo " selected" ?>>
                        Cost by Category
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-dns.php?all=1"<?php if ($report_name == "domain-cost-by-dns-report") echo " selected" ?>>
                        Cost by DNS Profile
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-ip-address.php?all=1"<?php if ($report_name == "domain-cost-by-ip-address-report") echo " selected" ?>>
                        Cost by IP Address
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-month.php?all=1"<?php if ($report_name == "domain-cost-by-month-report") echo " selected" ?>>
                        Cost by Month
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-owner.php?all=1"<?php if ($report_name == "domain-cost-by-owner-report") echo " selected" ?>>
                        Cost by Owner
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-registrar.php?all=1"<?php if ($report_name == "domain-cost-by-registrar-report") echo " selected" ?>>
                        Cost by Registrar
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-tld.php?all=1"<?php if ($report_name == "domain-cost-by-tld-report") echo " selected" ?>>
                        Cost by TLD
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/cost-by-host.php?all=1"<?php if ($report_name == "domain-cost-by-host-report") echo " selected" ?>>
                        Cost by Web Host
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/domains/registrar-fees.php?all=0"<?php if ($report_name == "domain-registrar-fee-report") echo " selected" ?>>
                        Registrar Fees
                    </option>
                </select>
            </form>
        </div>
        <div class="reporting-block-center">
            <div class="subheadline">SSL Certificate Reports</div>
            <BR>

            <form name="reporting_menu_ssl_form" method="post" action="index.php">
                <select name="reporting_menu_ssl" onChange="MM_jumpMenu('parent',this,0)">
                    <option
                        value="<?php echo $web_root; ?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>
                        Click to select an SSL Report
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-category.php?all=1"<?php if ($report_name == "ssl-cost-by-category-report") echo " selected" ?>>
                        Cost by Category
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-domain.php?all=1"<?php if ($report_name == "ssl-cost-by-domain-report") echo " selected" ?>>
                        Cost by Domain
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-ip-address.php?all=1"<?php if ($report_name == "ssl-cost-by-ip-address-report") echo " selected" ?>>
                        Cost by IP Address
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-month.php?all=1"<?php if ($report_name == "ssl-cost-by-month-report") echo " selected" ?>>
                        Cost by Month
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-owner.php?all=1"<?php if ($report_name == "ssl-cost-by-owner-report") echo " selected" ?>>
                        Cost by Owner
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-provider.php?all=1"<?php if ($report_name == "ssl-cost-by-provider-report") echo " selected" ?>>
                        Cost by Provider
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/cost-by-type.php?all=1"<?php if ($report_name == "ssl-cost-by-type-report") echo " selected" ?>>
                        Cost by Type
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/ssl/provider-fees.php?all=0"<?php if ($report_name == "ssl-provider-fee-report") echo " selected" ?>>
                        Provider Fees
                    </option>
                </select>
            </form>
        </div>
        <div class="reporting-block-right">
            <div class="subheadline">Data Warehouse Reports</div>
            <BR>

            <form name="reporting_menu_dw_form" method="post" action="index.php">
                <select name="reporting_menu_dw" onChange="MM_jumpMenu('parent',this,0)">
                    <option
                        value="<?php echo $web_root; ?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>
                        Click to select a DW Report
                    </option>
                    <option
                        value="<?php echo $web_root; ?>/reporting/dw/potential-problems.php?generate=1"<?php if ($report_name == "dw-potential-problems-report") echo " selected" ?>>
                        Potential Problems
                    </option>
                </select>
            </form>
        </div>
    </div>
    <div style="clear: both;"></div>
<?php
if ($report_name != "") {
    echo "<BR>";
}

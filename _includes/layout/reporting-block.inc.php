<?php
// /_includes/layout/reporting-block.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<script type="text/javascript">
<!--
function MM_jumpMenu(targ,selObj,restore){ //v3.0
  eval(targ+".location='"+selObj.options[selObj.selectedIndex].value+"'");
  if (restore) selObj.selectedIndex=0;
}
//-->
</script>
Before running any reports you should <a href="<?=$web_root?>/_includes/system/update-conversion-rates.inc.php?direct=1">update the conversion rates</a>.<BR><BR><BR>
<div class="reporting-block-outer">
	<div class="reporting-block-left">
        <font class="subheadline">Domain Reports</font><BR><BR>
        <form name="reporting_menu_domain_form" method="post" action="<?=$PHP_SELF?>">
            <select name="reporting_menu_domains" onChange="MM_jumpMenu('parent',this,0)">
            <option value="<?=$web_root?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>Click to select a Domain Report</option>
            <option value="<?=$web_root?>/reporting/domains/cost-by-category.php"<?php if ($report_name == "domain-cost-by-category-report") echo " selected" ?>>Cost by Category</option>
            <option value="<?=$web_root?>/reporting/domains/cost-by-month.php"<?php if ($report_name == "domain-cost-by-month-report") echo " selected" ?>>Cost by Month</option>
            <option value="<?=$web_root?>/reporting/domains/cost-by-registrar.php"<?php if ($report_name == "domain-cost-by-registrar-report") echo " selected" ?>>Cost by Registrar</option>
            <option value="<?=$web_root?>/reporting/domains/registrar-fees.php"<?php if ($report_name == "domain-registrar-fee-report") echo " selected" ?>>Registrar Fees</option>
            <option value="<?=$web_root?>/reporting/domains/renewals.php"<?php if ($report_name == "domain-renewal-report") echo " selected" ?>>Renewals</option>
            </select>
		</form>
	</div>
	<div class="reporting-block-center">
        <font class="subheadline">SSL Certificate Reports</font><BR><BR>
        <form name="reporting_menu_domain_form" method="post" action="<?=$PHP_SELF?>">
            <select name="reporting_menu_ssl" onChange="MM_jumpMenu('parent',this,0)">
            <option value="<?=$web_root?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>Click to select an SSL Report</option>
            <option value="<?=$web_root?>/reporting/ssl/cost-by-category.php"<?php if ($report_name == "ssl-cost-by-category-report") echo " selected" ?>>Cost by Category</option>
            <option value="<?=$web_root?>/reporting/ssl/cost-by-month.php"<?php if ($report_name == "ssl-cost-by-month-report") echo " selected" ?>>Cost by Month</option>
            <option value="<?=$web_root?>/reporting/ssl/cost-by-provider.php"<?php if ($report_name == "ssl-cost-by-provider-report") echo " selected" ?>>Cost by Provider</option>
            <option value="<?=$web_root?>/reporting/ssl/provider-fees.php"<?php if ($report_name == "ssl-provider-fee-report") echo " selected" ?>>Provider Fees</option>
            <option value="<?=$web_root?>/reporting/ssl/renewals.php"<?php if ($report_name == "ssl-renewal-report") echo " selected" ?>>Renewals</option>
            </select>
		</form>
	</div>
	<div class="reporting-block-right">
        <font class="subheadline">General Reports</font><BR><BR>
        <form name="reporting_menu_domain_form" method="post" action="<?=$PHP_SELF?>">
            <select name="reporting_menu_ssl" onChange="MM_jumpMenu('parent',this,0)">
            <option value="<?=$web_root?>/reporting/"<?php if ($report_name == "") echo " selected" ?>>Click to select a General Report</option>
<?php /* ?>
            <option value="<?=$web_root?>/reporting/"<?php if ($report_name == "error-report") echo " selected" ?>>Error Report</option>
<?php */ ?>
            </select>
		</form>
	</div>
</div>
<div style="clear: both;"></div>
<?php if ($report_name != "") echo "<BR>"; ?>
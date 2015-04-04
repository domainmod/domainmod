<?php
/**
 * /_includes/system/check-domain-fees.inc.php
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
$sql_find_missing_domain_fees = "SELECT count(id) AS total_count
								 FROM domains
								 WHERE fee_id = '0'
								   AND active NOT IN ('0', '10')";
$result_find_missing_domain_fees = mysqli_query($connection, $sql_find_missing_domain_fees);

while ($row_find_missing_domain_fees = mysqli_fetch_object($result_find_missing_domain_fees)) { $total_results_find_missing_domain_fees = $row_find_missing_domain_fees->total_count; }

if ($total_results_find_missing_domain_fees != 0) {
    $_SESSION['missing_domain_fees'] = 1;
} else {
    $_SESSION['missing_domain_fees'] = 0;
}

<?php
// /_includes/system/functions/check-date-format.inc.php
// 
// DomainMOD - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
function CheckDateFormat( $temp_input_date ) {
   if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $temp_input_date, $piece)) {
	  return checkdate($piece[2] , $piece[3] , $piece[1]);
   } else {
	  return false;
   }
} 	
?>

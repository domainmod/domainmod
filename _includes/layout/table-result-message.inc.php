<?php
// /_includes/layout/table-result-message.inc.php
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
<table width="100%" border="0" cellspacing="0" cellpadding="0" bordercolor="#000000">
	<tr>
    	<td bordercolor="#000000">

            <table width="100%" cellspacing="0" cellpadding="0">
            	<tr>
                	<td class="cell-result-message">
                    <BR><strong>
						<?=$_SESSION['session_result_message']?>
					</strong><BR>
                    </td>
				</tr>
			</table>

		</td>
	</tr>
</table>
<BR>
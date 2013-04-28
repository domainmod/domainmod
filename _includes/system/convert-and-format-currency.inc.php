<?php
// /_includes/system/convert-and-format-currency.inc.php
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
<?php
// // CODE TO USE
// // 
// // Currency Conversion & Formatting
// // Input: $temp_input_amount  /  Conversion: $temp_input_conversion (assign empty variable if no conversion is necessary)
// // Output: $temp_output_amount
// $temp_input_amount = $xxxxx;
// $temp_input_conversion = "";
// $temp_input_currency_symbol = "";
// $temp_input_currency_symbol_order = "";
// $temp_input_currency_symbol_space = "";
// include("_includes/system/convert-and-format-currency.inc.php");
// $xxxxx = $temp_output_amount;
?>
<?php
if ($temp_input_conversion == "") {

	if ($temp_input_currency_symbol_order == "0") {

		if ($temp_input_currency_symbol_space == "0") {

			$temp_output_amount = $temp_input_currency_symbol . number_format($temp_input_amount, 2, '.', ',');

		} else {

			$temp_output_amount = $temp_input_currency_symbol . " " . number_format($temp_input_amount, 2, '.', ',');

		}

	} else {

		if ($temp_input_currency_symbol_space == "0") {

			$temp_output_amount = number_format($temp_input_amount, 2, '.', ',') . $temp_input_currency_symbol;
		
		} else {

			$temp_output_amount = number_format($temp_input_amount, 2, '.', ',') . " " . $temp_input_currency_symbol;
		
		}

	}

} else {

	if ($temp_input_currency_symbol_order == "0") {

		if ($temp_input_currency_symbol_space == "0") {

			$temp_converted_fee = $temp_input_amount * $temp_input_conversion;
			$temp_output_amount = $temp_input_currency_symbol . number_format($temp_converted_fee, 2, '.', ',');

		} else {

			$temp_converted_fee = $temp_input_amount * $temp_input_conversion;
			$temp_output_amount = $temp_input_currency_symbol . " " . number_format($temp_converted_fee, 2, '.', ',');

		}

	} else {

		if ($temp_input_currency_symbol_space == "0") {

			$temp_converted_fee = $temp_input_amount * $temp_input_conversion;
			$temp_output_amount = number_format($temp_converted_fee, 2, '.', ',') . $temp_input_currency_symbol;
		
		} else {

			$temp_converted_fee = $temp_input_amount * $temp_input_conversion;
			$temp_output_amount = number_format($temp_converted_fee, 2, '.', ',') . " " . $temp_input_currency_symbol;
		
		}

	}

}
?>
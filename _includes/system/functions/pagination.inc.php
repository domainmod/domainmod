<?php
/**
 * /_includes/system/functions/pagination.inc.php
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
function pageBrowser($totalrows, $numLimit, $amm, $queryStr, $numBegin, $begin, $num) {

    $larrow = "&nbsp;&laquo; Prev &nbsp;";
    $rarrow = "&nbsp;Next &raquo;&nbsp;";
    $wholePiece = "<B>Page:</B> ";

    if ($totalrows > 0) {

        $numSoFar = 1;
        $cycle = ceil($totalrows/$amm);

        if (!isset($numBegin) || $numBegin < 1) {
            $numBegin = 1;
            $num = 1;
        }

        $minus = $numBegin-1;
        $start = $minus*$amm;

        if (!isset($begin)) {
            $begin = $start;
        }

        $preBegin = $numBegin-$numLimit;
        $preVBegin = $start-$amm;
        $preRedBegin = $numBegin-1;

        if ($start > 0 || $numBegin > 1) {

            $wholePiece .= "<a href='?num=" . $preRedBegin
                . "&numBegin=" . $preBegin
                . "&begin=" . $preVBegin
                . $queryStr . "'>"
                . $larrow . "</a>\n";

        }

        for ($i=$numBegin; $i<=$cycle; $i++) {

            if ($numSoFar == $numLimit+1) {

                $piece = "<a href='?numBegin=" . $i
                    . "&num=" . $i
                    . "&begin=" . $start
                    . $queryStr . "'>"
                    . $rarrow . "</a>\n";
                $wholePiece .= $piece;
                break;

            }

            $piece = "<a href='?begin=" . $start
                . "&num=" . $i
                . "&numBegin=" . $numBegin
                . $queryStr
                . "'>";

            if ($num == $i) {
                $piece .= "</a><b>$i</b><a>";
            } else {
                $piece .= "$i";
            }

            $piece .= "</a>\n";
            $start = $start+$amm;
            $numSoFar++;
            $wholePiece .= $piece;

        }

        $wholePiece .= "\n";
        $wheBeg = $begin+1;
        $wheEnd = $begin+$amm;
        $wheToWhe = "<b>" . number_format($wheBeg) . "</b>-<b>";

        if ($totalrows <= $wheEnd) {
            $wheToWhe .= $totalrows . "</b>";
        } else {
            $wheToWhe .= number_format($wheEnd) . "</b>";
        }

        $sqlprod = " LIMIT " . $begin . ", " . $amm;

    } else {

        $wholePiece = "";
        $wheToWhe = "<b>0</b> - <b>0</b>";
        $sqlprod = "";

    }

    return array($sqlprod,$wheToWhe,$wholePiece);

}

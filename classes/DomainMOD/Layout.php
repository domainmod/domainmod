<?php
/**
 * /classes/DomainMOD/Layout.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
//@formatter:off
namespace DomainMOD;

class Layout
{

    public function pageTitle($page_title)
    {
        return SOFTWARE_TITLE . ' :: ' . $page_title;
    }

    public function showButton($button_type, $button_text)
    {
        ob_start(); ?><button type="<?php echo $button_type; ?>" class="btn btn-primary btn-danger domainmod-css-button"><?php echo $button_text; ?></button>&nbsp;<?php
        return ob_get_clean();
    }

    public function highlightText($colour, $text_to_display)
    {
        if ($colour == 'red') {
            $class = 'domainmod-css-text-highlight-red';
        } elseif ($colour == 'green') {
            $class = 'domainmod-css-text-highlight-green';
        }
        ob_start(); ?><strong><span class="<?php echo $class; ?>"><?php echo $text_to_display; ?></span></strong><?php
        return ob_get_clean();
    }

    public function pageBrowser($parameters)
    {
        list($totalrows, $numLimit, $amm, $queryStr, $numBegin, $begin, $num) = $parameters;
        $larrow = "&nbsp;&laquo; Prev &nbsp;";
        $rarrow = "&nbsp;Next &raquo;&nbsp;";
        $wholePiece = "<B>Page:</B> ";
        if ($totalrows > 0) {
            $numSoFar = 1;
            $cycle = ceil($totalrows / $amm);
            if (!isset($numBegin) || $numBegin < 1) {
                $numBegin = 1;
                $num = 1;
            }
            $minus = $numBegin - 1;
            $start = $minus * $amm;
            if (!isset($begin)) {
                $begin = $start;
            }
            $preBegin = $numBegin - $numLimit;
            $preVBegin = $start - $amm;
            $preRedBegin = $numBegin - 1;
            if ($start > 0 || $numBegin > 1) {
                $wholePiece .= "<a href='?num=" . $preRedBegin
                    . "&numBegin=" . $preBegin
                    . "&begin=" . $preVBegin
                    . $queryStr . "'>"
                    . $larrow . "</a>\n";
            }
            for ($i = $numBegin; $i <= $cycle; $i++) {
                if ($numSoFar == $numLimit + 1) {
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
                $start = $start + $amm;
                $numSoFar++;
                $wholePiece .= $piece;
            }
            $wholePiece .= "\n";
            $wheBeg = $begin + 1;
            $wheEnd = $begin + $amm;
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
        return array($sqlprod, $wheToWhe, $wholePiece);
    }

    public function deleteButton($item_type, $item_name, $url)
    {
        echo $this->modalButton(strtoupper(_('Delete This')) . ' ' . strtoupper($item_type));
        echo $this->modal(_('Delete') . ' ' . $item_type, $item_name, $url, strtoupper(_('Cancel')), strtoupper(_('Yes, Delete')));
    }

    public function modalButton($name)
    {
        ob_start(); ?>

            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModal">
              <?php echo $name; ?>
            </button><?php

        return ob_get_clean();
    }

    public function modal($title, $item_name, $url, $left_button, $right_button)
    {
        ob_start(); ?>

            <div class="modal fade" id="myModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title"><?php echo $title; ?></h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><?php echo sprintf(_('Are you sure you want to delete %s'), '<strong>' . $item_name . '</strong>') . '?'; ?></p>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $left_button; ?></button>
                            <a href="<?php echo $url; ?>"><button type="button" class="btn btn-danger"><?php echo $right_button; ?></button></a>
                        </div>
                    </div>
                    <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
            </div>
            <!-- /.modal --><?php

        return ob_get_clean();
    }

    public function expandableBoxTop($title, $url, $url_text)
    {
        ob_start(); ?>

        <div class="card card-outline card-danger collapsed-card">
            <div class="card-header">
                <h3 class="card-title">
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>&nbsp;&nbsp;&nbsp;&nbsp;
                        </button>
                        <?php echo $title; ?><?php if ($url != '') { ?>&nbsp;&nbsp;[<a href='<?php echo $url; ?>'><?php echo $url_text; ?></a>]<?php } ?>
                    </div>
                </h3>
                <!-- /.card-tools -->
            </div>
            <!-- /.card-header -->
            <div class="card-body"><?php

        return ob_get_clean();

    }

    public function expandableBoxBottom()
    {
        ob_start(); ?>

                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card --><?php

        return ob_get_clean();

    }

    public function contentBoxTop($title, $width)
    {
        ob_start(); ?>

            <div class="col-md-<?php echo $width; ?>">
                <div class="card card-outline card-danger">
                    <?php if ($title != '') { ?>
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $title; ?></h3>
                        </div>
                    <?php } ?>
                    <div class="card-body"><?php

        return ob_get_clean();

    }

    public function contentBoxBottom()
    {
        ob_start(); ?>

                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col --><?php

        return ob_get_clean();

    }
    public function sidebarDarkMode()
    {
        if (isset($_SESSION['s_dark_mode']) && $_SESSION['s_dark_mode'] === 1) return ' sidebar-dark-red';
        return ' sidebar-light-red domainmod-css-logo-background-colour';
    }

    public function bodyDarkMode()
    {
        if (isset($_SESSION['s_dark_mode']) && $_SESSION['s_dark_mode'] === 1) return ' dark-mode';
        return '';
    }

} //@formatter:on

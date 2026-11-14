<?php
/**
 * /classes/DomainMOD/Layout.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

    /*
     * A list's status code (such as the translated letter for a pending transfer) as a small coloured badge. Hovering
     * or focusing it shows what it means (Bootstrap's tooltip, started in the footer), and screen readers read the
     * description instead of the code. The description is in attributes, not text, so the lists still sort and
     * search the cell as before (the code and the name).
     */
    public function statusBadge($code, $description, $colour)
    {
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        ob_start(); ?><span class="badge text-bg-<?php echo $colour; ?>" role="img" aria-label="<?php echo $description; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo $description; ?>" tabindex="0"><?php echo $code; ?></span><?php
        return ob_get_clean();
    }

    /*
     * A page's introduction (a few sentences explaining the page) in an AdminLTE callout box
     */
    public function calloutTop()
    {
        ob_start(); ?>
<div class="callout callout-info"><?php
        return ob_get_clean();
    }

    public function calloutBottom()
    {
        ob_start(); ?>
</div><?php
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
        // Deleting changes data, so the link carries the session's security token (see DomainMOD\Csrf).
        $csrf = new Csrf();
        $url .= (strpos($url, '?') === false ? '?' : '&') . $csrf->query();

        echo $this->modalButton(Format::toUpper(_('Delete This')) . ' ' . Format::toUpper($item_type));
        echo $this->modal(_('Delete') . ' ' . $item_type, $item_name, $url, Format::toUpper(_('Cancel')), Format::toUpper(_('Yes, Delete')));
    }

    public function modalButton($name)
    {
        ob_start(); ?>

            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#myModal">
              <?php echo $name; ?>
            </button><?php

        return ob_get_clean();
    }

    public function modal($title, $item_name, $url, $left_button, $right_button)
    {
        ob_start(); ?>

            <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="myModalTitle"><?php echo $title; ?></h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo _('Close'); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <p><?php echo sprintf(_('Are you sure you want to delete %s'), '<strong>' . $item_name . '</strong>') . '?'; ?></p>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $left_button; ?></button>
                            <a href="<?php echo $url; ?>" class="btn btn-danger"><?php echo $right_button; ?></a>
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
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" aria-label="<?php echo _('Show or hide'); ?>">
                            <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                            <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                        </button>&nbsp;&nbsp;
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
    /*
     * The colour mode to show: the signed-in user's saved choice (0 Light, 1 Dark, 2 Auto), or Auto when nobody's signed
     * in (the sign-in and password reset pages, and the installer).
     */
    public function colourMode()
    {
        if (($_SESSION['s_is_logged_in'] ?? 0) != 1) return 'auto';
        $dark_mode = $_SESSION['s_dark_mode'] ?? 2;
        if ($dark_mode === 0) return 'light';
        if ($dark_mode === 1) return 'dark';
        return 'auto';
    }

    /*
     * The page's opening <html> tag, printed in one place so the theme's attributes only need setting here. The colour
     * mode is the user's choice, which js/colour-mode-init.js turns into light or dark before the page is drawn, and
     * data-lte-primary makes Bootstrap's red DomainMOD's main colour (buttons included). It ends with a line break
     * because PHP drops the one that follows the closing tag of the code that prints it.
     */
    public function htmlTag()
    {
        // The page's language for browsers and screen readers, from DomainMOD's language code (en_US.UTF-8 is en-US).
        // Before sign-in it can be the server's locale, such as C.UTF-8, which isn't a language, so that's English.
        $language = 'en';
        if (preg_match('/^([a-z]{2,3})_([A-Z]{2})/', DEFAULT_LANGUAGE, $parts)) {
            $language = $parts[1] . '-' . $parts[2];
        }
        return '<html lang="' . $language . '" data-bs-theme="' . $this->colourMode() . '" data-lte-primary="danger">' . "\n";
    }

    /*
     * The page's opening <body> tag. 'main' is a page with the sidebar and header, 'login' the sign-in and password
     * reset pages, 'install' the installer, and 'raw' a bare page (such as the Raw List). Like htmlTag(), it ends with a
     * line break.
     */
    public function bodyTag($type = 'main')
    {
        if ($type === 'login') return '<body class="login-page bg-body-secondary">' . "\n";
        if ($type === 'install') return '<body class="bg-body-secondary">' . "\n";
        if ($type === 'raw') return '<body class="bg-body">' . "\n";
        return '<body class="layout-fixed sidebar-expand-lg sidebar-mini compact-mode nav-indent bg-body-tertiary">' . "\n";
    }

    /*
     * The URL of one of DomainMOD's own stylesheets or scripts ($path is relative to the root, such as
     * 'theme/dist/css/adminlte.min.css'), with the version and the file's modification time added, so browsers fetch a
     * fresh copy after an upgrade rather than reusing an old one they've cached.
     */
    public function assetUrl($path)
    {
        return WEB_ROOT . '/' . $path . '?v=' . rawurlencode(SOFTWARE_VERSION) . '.' . (int) @filemtime(DIR_ROOT . '/' . $path);
    }

    // A custom field's text, escaped for the page, since custom field values are saved as typed.
    public function customFieldText($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /*
     * A URL custom field's "open" link. Only web, FTP, and mail addresses (or ones with no scheme) become links, so a
     * saved javascript: or data: address is shown as text instead of running when it's clicked.
     */
    public function customFieldLink($url)
    {
        $url = trim((string) $url);
        if ($url === '') return '';

        // Browsers ignore tabs, line breaks, and other control characters in a link, so they're ignored here too.
        $scheme_check = preg_replace('/[\x00-\x20\x7f]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $scheme_check, $matches) && !in_array(strtolower($matches[1]), array('http', 'https', 'ftp', 'ftps', 'sftp', 'mailto'), true)) {

            return $this->customFieldText($url);

        }

        return "[<a target='_blank' rel='noopener noreferrer' href='" . $this->customFieldText($url) . "'>" . _('open') . '</a>]';
    }

} //@formatter:on

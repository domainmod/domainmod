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
    // How many expandable boxes this page has drawn, for their unique ids (see expandableBoxTop())
    private static $expandable_box_count = 0;

    // The page's actions, drawn in the page header beside the title (see addPageAction() and renderPageActions()).
    // They're static, so it doesn't matter which Layout object a page or an include declares them on.
    private static $page_actions = array();
    private static $page_add_action = null;
    private static $page_add_menu = array();

    // Where the header leaves room for the page's actions. The footer swaps it for renderPageActions(), so a page can
    // declare its actions after the header is drawn, once it knows whether they apply (such as Export only with rows).
    const PAGE_ACTIONS_MARKER = '<!--domainmod-page-actions-->';


    public function pageTitle($page_title)
    {
        return SOFTWARE_TITLE . ' :: ' . $page_title;
    }

    public function showButton($button_type, $button_text)
    {
        // The gap after the button is a margin (css/base.css) rather than a trailing space, which a link wrapped around
        // the button would underline on hover
        ob_start(); ?><button type="<?php echo $button_type; ?>" class="btn btn-primary btn-danger domainmod-css-button"><?php echo $button_text; ?></button><?php
        return ob_get_clean();
    }

    /*
     * A page action, drawn in the page header as an outline button with an icon ($icon is a Bootstrap Icons class,
     * such as 'bi-download'). $label is plain translated text and $url a plain URL (not HTML-escaped), both escaped
     * when drawn. $options can set a 'target' (such as '_blank'), a 'rel', and an 'id'.
     */
    public function addPageAction($label, $url, $icon, $options = array())
    {
        self::$page_actions[] = array('label' => $label, 'url' => $url, 'icon' => $icon, 'options' => $options);
    }

    // The page's main add action, the one red button in the page header. A second call replaces the first.
    public function setPageAddAction($label, $url, $icon = 'bi-plus-lg')
    {
        self::$page_add_action = array('label' => $label, 'url' => $url, 'icon' => $icon, 'options' => array());
    }

    /*
     * Another way to add, shown in the Add button's dropdown. Without a main add action it's drawn as an outline
     * button instead.
     */
    public function addPageAddMenuItem($label, $url, $icon)
    {
        self::$page_add_menu[] = array('label' => $label, 'url' => $url, 'icon' => $icon, 'options' => array());
    }

    // One action as a link styled as a button (or as a dropdown item), with its label and URL escaped once
    private function pageActionLink($action, $class)
    {
        $escape = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $attributes = ' class="' . $class . '" href="' . $escape($action['url']) . '"';
        foreach (array('target', 'rel', 'id') as $name) {
            if (!empty($action['options'][$name])) $attributes .= ' ' . $name . '="' . $escape($action['options'][$name]) . '"';
        }
        return '<a' . $attributes . '><i class="bi ' . $escape($action['icon']) . ' me-1" aria-hidden="true"></i>' . $escape($action['label']) . '</a>';
    }

    /*
     * The page's actions for the page header: the outline buttons first, then the red Add button last, as a split
     * button with a dropdown when there are other ways to add. Empty when the page has none.
     */
    public function renderPageActions()
    {
        $outline = 'btn btn-outline-secondary btn-sm';
        $items = array();
        foreach (self::$page_actions as $action) {
            $items[] = $this->pageActionLink($action, $outline);
        }
        if (self::$page_add_action === null) {
            foreach (self::$page_add_menu as $action) {
                $items[] = $this->pageActionLink($action, $outline);
            }
        } elseif (!self::$page_add_menu) {
            $items[] = $this->pageActionLink(self::$page_add_action, 'btn btn-danger btn-sm');
        } else {
            $menu = '';
            foreach (self::$page_add_menu as $action) {
                $menu .= '<li>' . $this->pageActionLink($action, 'dropdown-item') . '</li>';
            }
            $items[] = '<div class="btn-group">' . $this->pageActionLink(self::$page_add_action, 'btn btn-danger btn-sm')
                . '<button type="button" class="btn btn-danger btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">'
                . '<span class="visually-hidden">' . htmlspecialchars(_('More ways to add'), ENT_QUOTES, 'UTF-8') . '</span></button>'
                . '<ul class="dropdown-menu dropdown-menu-end">' . $menu . '</ul></div>';
        }
        if (!$items) return '';
        return '<div class="domainmod-page-actions d-flex flex-wrap align-items-center gap-2" role="group" aria-label="'
            . htmlspecialchars(_('Page actions'), ENT_QUOTES, 'UTF-8') . '">' . implode('', $items) . '</div>';
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
    public function statusBadge($code, $description, $colour, $icon = '')
    {
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        if ($icon != '') {
            // The Domains and SSL lists show a status as just an icon in the colour's deeper (emphasis) shade, which
            // keeps it readable in both colour modes ('muted' is the plain grey used for privacy). The code stays as hidden text, so the lists still sort and search
            // on it.
            $icon_class = $colour == 'muted' ? 'text-body-secondary' : 'text-' . $colour . '-emphasis';
            ob_start(); ?><span class="domainmod-status-icon <?php echo $icon_class; ?>" role="img" aria-label="<?php echo $description; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo $description; ?>" tabindex="0"><i class="bi <?php echo $icon; ?>" aria-hidden="true"></i><span class="visually-hidden"><?php echo $code; ?></span></span><?php
            return ob_get_clean();
        }
        ob_start(); ?><span class="badge text-bg-<?php echo $colour; ?>" role="img" aria-label="<?php echo $description; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo $description; ?>" tabindex="0"><?php echo $code; ?></span><?php
        return ob_get_clean();
    }

    /*
     * A page's introduction (a few sentences explaining the page) in an AdminLTE callout box, kept to the forms' width
     * like every other page introduction (domainmod-intro)
     */
    public function calloutTop()
    {
        ob_start(); ?>
<div class="callout callout-info domainmod-intro"><?php
        return ob_get_clean();
    }

    public function calloutBottom()
    {
        ob_start(); ?>
</div><?php
        return ob_get_clean();
    }

    /*
     * A link to a task that runs in place with htmx: the result replaces $target (with $swap, such as innerHTML or
     * outerHTML), and the spinner with $spinner_id (see htmxSpinner()) shows while it runs. A click while it's running is
     * dropped, not queued. Without JavaScript it's an ordinary link to the same page. $url must already carry the CSRF
     * token.
     */
    public function htmxTaskLink($url, $text, $target, $swap, $spinner_id)
    {
        ob_start(); ?><a href="<?php echo $url; ?>" hx-get="<?php echo $url; ?>" hx-target="<?php echo $target; ?>" hx-swap="<?php echo $swap; ?>" hx-trigger="click queue:none" hx-indicator="#<?php echo $spinner_id; ?>"><?php echo $text; ?></a><?php
        return ob_get_clean();
    }

    /*
     * The spinner for an htmxTaskLink(), hidden until its task runs, with a little space before it. It's separate from
     * the link so it can go where it reads best, such as after the brackets around Run Now.
     */
    public function htmxSpinner($spinner_id)
    {
        ob_start(); ?><span id="<?php echo $spinner_id; ?>" class="spinner-border spinner-border-sm htmx-indicator ms-2" role="status"><span class="visually-hidden"><?php echo _('Working'); ?></span></span><?php
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

    /*
     * A page's delete action in its own "Danger Zone" section at the bottom of the page: a heading, then a box with a
     * soft red tint and a thin red border, with what the delete does on the left and a DELETE button on the right
     * (under the text on phones), which opens the same confirmation modal as deleteButton(). $action and $description
     * are already translated, and $description is already escaped for the page. $heading_level is 2 by default, a
     * section of its own under the page's h1, or 3 where it sits inside an h2 section, and the heading looks the same at
     * either level. The section stops at the same width as the forms (.domainmod-form).
     */
    public function dangerZone($item_type, $item_name, $url, $action, $description, $heading_level = 2)
    {
        // Deleting changes data, so the link carries the session's security token (see DomainMOD\Csrf).
        $csrf = new Csrf();
        $url .= (strpos($url, '?') === false ? '?' : '&') . $csrf->query();

        ob_start(); ?>

        <?php $heading_level = $heading_level === 2 ? 2 : 3; ?>
        <div class="domainmod-danger-zone">
            <h<?php echo $heading_level; ?> class="h3 mt-5"><?php echo _('Danger Zone'); ?></h<?php echo $heading_level; ?>>
            <?php // A soft red tint with a thin red border (Greg's choice, 2026-11-14), in both colour modes ?>
            <div class="bg-danger-subtle border border-danger-subtle rounded-3 p-3">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                    <div>
                        <strong class="d-block"><?php echo $action; ?></strong>
                        <span class="text-body-secondary"><?php echo $description; ?></span>
                    </div>
                    <div class="flex-shrink-0"><?php echo $this->modalButton(Format::toUpper(_('Delete'))); ?></div>
                </div>
            </div>
        </div><?php

        echo ob_get_clean();
        echo $this->modal(_('Delete') . ' ' . $item_type, $item_name, $url, Format::toUpper(_('Cancel')), Format::toUpper(_('Yes, Delete')));
    }

    /*
     * A list's empty state: a large icon, a title, one line of text, and the main action as a button, with an optional
     * secondary link (another way in, or help). It's used when nothing exists yet, when filters or a search hide
     * everything, and the like. $title and $text are already translated, and are trusted HTML, so callers escape
     * anything that isn't theirs ($text may hold a link to the User Guide). The URLs and labels are escaped here, and a
     * URL starting with "/" is taken from the application root (WEB_ROOT). Read-only users can't add or change anything,
     * so an action marked as changing data ($action_changes_data, $secondary_changes_data) is left out for them, while
     * navigation and help links always show. $heading_level is the title's heading (2 under the page's h1, or 3 or 4
     * where the empty state follows an h2 or h3), and the title looks the same at any level. The icon is decorative.
     * Callers leave out an action or link the page already shows above the empty state, so nothing appears twice.
     */
    public function emptyState($icon, $title, $text, $action_url = '', $action_label = '', $secondary_url = '',
                               $secondary_label = '', $heading_level = 2, $action_changes_data = true,
                               $secondary_changes_data = false)
    {
        $read_only = (($_SESSION['s_read_only'] ?? '') == '1');
        $heading_level = max(2, min(6, (int) $heading_level));
        $url = function ($address) {
            if (strpos($address, '/') === 0) $address = WEB_ROOT . $address;
            return htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
        };
        $show_action = $action_url !== '' && $action_label !== '' && !($read_only && $action_changes_data);
        $show_secondary = $secondary_url !== '' && $secondary_label !== '' && !($read_only && $secondary_changes_data);

        ob_start(); ?>

        <div class="domainmod-empty-state">
            <i class="bi <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> fs-2 text-body-secondary" aria-hidden="true"></i>
            <h<?php echo $heading_level; ?> class="h5 mt-2"><?php echo $title; ?></h<?php echo $heading_level; ?>>
            <p class="domainmod-empty-state-text text-body-secondary"><?php echo $text; ?></p><?php
            if ($show_action || $show_secondary) { ?>
            <div class="d-flex flex-wrap align-items-center gap-3"><?php
                if ($show_action) { ?>
                <a class="btn btn-danger" href="<?php echo $url($action_url); ?>"><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></a><?php
                }
                if ($show_secondary) { ?>
                <a href="<?php echo $url($secondary_url); ?>"><?php echo htmlspecialchars($secondary_label, ENT_QUOTES, 'UTF-8'); ?></a><?php
                } ?>
            </div><?php
            } ?>
        </div><?php

        return ob_get_clean();
    }

    /*
     * A multi-step flow's progress (the installer, the Domain Queue's API add, and the Bulk Updater's custom field
     * update): a numbered row of steps, with the steps already done ticked, the current one highlighted, and the ones
     * still to come plain. $steps are the translated step names in order, $current is the current step's number
     * (1-based), and $label is the translated name of the flow for screen readers. The steps aren't links, since moving
     * between them out of order would skip their checks, and screen readers hear each step's position and state.
     */
    // $urls optionally gives each step's address, in the same order as $steps: the steps already done then link back to
    // their page, so a multi-step flow that keeps its answers (such as the installer) can go back to any earlier step.
    // $reached is the furthest step reached so far. After going back, the steps already done after the current one stay
    // ticked and linked, and the furthest step stays numbered and linked, so people can go forward again without redoing
    // them.
    public function stepIndicator($steps, $current, $label, $urls = array(), $reached = 0)
    {
        $steps = array_values($steps);
        $urls = array_values($urls);
        $total = count($steps);
        $current = (int) $current;
        $reached = max((int) $reached, $current);

        ob_start(); ?>

        <nav class="domainmod-steps" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
            <ol><?php
            foreach ($steps as $index => $name) {
                $number = $index + 1;
                if ($number < $current || ($number > $current && $number < $reached)) {
                    $state = 'is-done';
                    $prefix = sprintf(_('Step %1$s of %2$s, done:'), $number, $total);
                } elseif ($number === $current) {
                    $state = 'is-current';
                    $prefix = sprintf(_('Step %1$s of %2$s, current:'), $number, $total);
                } elseif ($number === $reached) {
                    // The furthest step reached, after going back: not done yet, but it can be returned to
                    $state = 'is-reached';
                    $prefix = sprintf(_('Step %1$s of %2$s:'), $number, $total);
                } else {
                    $state = '';
                    $prefix = sprintf(_('Step %1$s of %2$s:'), $number, $total);
                } ?>
                <li class="domainmod-step<?php echo $state !== '' ? ' ' . $state : ''; ?>"<?php if ($state === 'is-current') echo ' aria-current="step"'; ?>><?php
                    $url = (($state === 'is-done' || $state === 'is-reached') && isset($urls[$index]) && $urls[$index] !== '') ? $urls[$index] : '';
                    // A step that links names where it goes in a tooltip, since on narrow screens only the current step shows its name
                    if ($url !== '') { ?><a class="domainmod-step-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php } ?>
                    <span class="domainmod-step-number" aria-hidden="true"><?php if ($state === 'is-done') { ?><i class="bi bi-check-lg"></i><?php } else { echo $number; } ?></span>
                    <span class="visually-hidden"><?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?> </span>
                    <span class="domainmod-step-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span><?php
                    if ($url !== '') { ?></a><?php } ?>
                </li><?php
            } ?>
            </ol>
        </nav><?php

        return ob_get_clean();
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

    /*
     * A box that starts collapsed, opened and closed with the button beside its title. $heading_level sets the title's
     * heading (h2 by default, under the page's h1, or 3 for a box under an h2 section). The button is tied to the box's
     * body (aria-controls), says whether it's open (aria-expanded, kept up to date by the footer script on AdminLTE's
     * expanded and collapsed events), and is named "Show or hide" followed by the box's title, so each box's button
     * says which box it opens. A link after the title (such as Advanced Filtering's reset) isn't part of that name.
     */
    public function expandableBoxTop($title, $url, $url_text, $heading_level = 2)
    {
        $heading_level = max(2, min(6, (int) $heading_level));
        // Ids unique on the page, for the button's name and the body it opens
        self::$expandable_box_count++;
        $box_id = 'domainmod-box-' . self::$expandable_box_count;
        ob_start(); ?>

        <div class="card card-outline card-danger collapsed-card">
            <div class="card-header">
                <h<?php echo $heading_level; ?> class="card-title">
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" aria-controls="<?php echo $box_id; ?>-body" aria-expanded="false" aria-labelledby="<?php echo $box_id; ?>-action <?php echo $box_id; ?>-title" id="<?php echo $box_id; ?>-action-button">
                            <span class="visually-hidden" id="<?php echo $box_id; ?>-action"><?php echo _('Show or hide'); ?></span>
                            <i data-lte-icon="expand" class="bi bi-plus-lg" aria-hidden="true"></i>
                            <i data-lte-icon="collapse" class="bi bi-dash-lg" aria-hidden="true"></i>
                        </button>&nbsp;&nbsp;
                        <span id="<?php echo $box_id; ?>-title"><?php echo $title; ?></span><?php if ($url != '') { ?>&nbsp;&nbsp;[<a href='<?php echo $url; ?>'><?php echo $url_text; ?></a>]<?php } ?>
                    </div>
                </h<?php echo $heading_level; ?>>
                <!-- /.card-tools -->
            </div>
            <!-- /.card-header -->
            <div class="card-body" id="<?php echo $box_id; ?>-body"><?php

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

    // $heading_level is the title's heading: 2 by default, under the page's h1, or 3 for a box inside an h2 section
    public function contentBoxTop($title, $width, $heading_level = 2)
    {
        $heading_level = max(2, min(6, (int) $heading_level));
        ob_start(); ?>

            <div class="col-md-<?php echo $width; ?>">
                <div class="card card-outline card-danger">
                    <?php if ($title != '') { ?>
                        <div class="card-header">
                            <h<?php echo $heading_level; ?> class="card-title"><?php echo $title; ?></h<?php echo $heading_level; ?>>
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
     * One tile on a section's landing page (Reporting, Assets, Administration, and Settings): an outline card with an
     * icon, the page's name as its one link (stretched over the whole card, so the card is clickable), a line saying
     * what's there, and an optional detail line (such as Assets' counts). $url is a path under WEB_ROOT, $icon is a
     * Bootstrap Icons class, $colour is a Bootstrap colour, and $heading is the title's heading level (3 under a
     * group's heading, 2 on a page without groups). The text is escaped here, so callers pass plain translated text.
     */
    public function landingTile($url, $title, $description, $icon, $colour = 'danger', $detail = '', $heading = 3)
    {
        $heading = (int) $heading;
        ob_start(); ?>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-outline card-<?php echo $colour; ?> h-100 mb-0 domainmod-landing-tile">
                <div class="card-body">
                    <i class="bi <?php echo $icon; ?> fs-3 text-<?php echo $colour; ?>" aria-hidden="true"></i>
                    <h<?php echo $heading; ?> class="h6 mt-2 mb-1"><a href="<?php echo WEB_ROOT . $url; ?>" class="stretched-link link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></a></h<?php echo $heading; ?>>
                    <div class="small text-body-secondary"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></div><?php
                    if ($detail != '') { ?>
                        <div class="small fw-semibold mt-1"><?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?></div><?php
                    } ?>
                </div>
            </div>
        </div><?php

        return ob_get_clean();
    }

    /*
     * A compact card on a landing page: a group's title and its links as small buttons in a list. Each $links entry is
     * array(url, short label, full name): the button shows the short label (such as "Category", since the card's title
     * says what it's a list of), and screen readers hear the full name instead (such as "Domain Cost by Category"), so
     * the links still make sense read out on their own. $url is a path under WEB_ROOT, and the text is escaped here.
     */
    public function landingLinks($title, $links)
    {
        ob_start(); ?>

        <div class="col-12 col-lg-6">
            <div class="card card-outline card-secondary h-100 mb-0">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <ul class="list-inline mb-0"><?php
                        foreach ($links as $link) { ?>
                            <li class="list-inline-item mb-2"><a href="<?php echo WEB_ROOT . $link[0]; ?>" class="btn btn-sm btn-outline-secondary"><span aria-hidden="true"><?php echo htmlspecialchars($link[1], ENT_QUOTES, 'UTF-8'); ?></span><span class="visually-hidden"><?php echo htmlspecialchars($link[2], ENT_QUOTES, 'UTF-8'); ?></span></a></li><?php
                        } ?>
                    </ul>
                </div>
            </div>
        </div><?php

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
     * The skip links at the top of the page, drawn here so they're translated. AdminLTE adds its own English ones only
     * when a page has none. $with_navigation adds "Skip to navigation" (to the sidebar menu), which only the main
     * layout has, so the sign-in, bare, and installer layouts leave it out rather than point at nothing.
     */
    public function skipLinks($with_navigation = true)
    {
        $links = '<div class="skip-links"><a href="#main" class="skip-link">' . htmlspecialchars(_('Skip to main content'), ENT_QUOTES, 'UTF-8') . '</a>';
        if ($with_navigation) {
            $links .= '<a href="#navigation" class="skip-link">' . htmlspecialchars(_('Skip to navigation'), ENT_QUOTES, 'UTF-8') . '</a>';
        }
        return $links . '</div>' . "\n";
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

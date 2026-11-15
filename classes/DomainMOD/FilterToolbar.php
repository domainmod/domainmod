<?php
/**
 * /classes/DomainMOD/FilterToolbar.php
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

/*
 * A list's filter toolbar (the Domains list, and the SSL list next): a Filters button with a count of the active
 * filters, one removable chip per active filter, and Clear all, with every filter in a Bootstrap offcanvas side panel.
 * Without JavaScript the Filters button reloads the page with expand=1, and the form is drawn as a normal card instead.
 *
 * Each list adds its filters with addFilter() (a request key, its current value, the value that means "no filter", and
 * the chip's text), and any controls that every link must carry without being chips (the Domains list's Large Mode
 * results per page and sort order) with addCarried(). The class makes every URL from that state with
 * http_build_query(), never carrying paging keys or from_dropdown, so removing a chip starts the list at its first page.
 * Chip text is plain text: names read from the database are decoded once with decode() (they're stored HTML-escaped),
 * and escaped once here when they're printed.
 */
class FilterToolbar
{
    private $panel_id;
    private $base_url;
    private $reset_url;
    private $filters = array();
    private $carried = array();

    // $panel_id is the panel's id (such as domain-filters), $base_url the list's address (filters are added as a query
    // string), and $reset_url the address that clears every filter
    public function __construct($panel_id, $base_url, $reset_url)
    {
        $this->panel_id = $panel_id;
        $this->base_url = $base_url;
        $this->reset_url = $reset_url;
    }

    // Names read from the database are stored HTML-escaped, so they're decoded once before going into a chip or a URL
    public static function decode($text)
    {
        return html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
    }

    // The label for an id filter whose row no longer exists, so an old link still shows why the list is filtered
    public static function notFound()
    {
        return _('(not found)');
    }

    /*
     * Adds an active filter. A value that's empty or equal to $default (compared as strings, so a status of 0 is kept)
     * isn't a filter. $label is the value's plain-text name, and $name the filter's name, shown as "Name: label". With
     * no $name, $label is the chip's whole text (the date range's "Expiring: …").
     */
    public function addFilter($key, $value, $default, $label, $name = null)
    {
        if ($value === null) return;
        $value = (string) $value;
        if ($value === '' || $value === (string) $default) return;

        $this->filters[$key] = array(
            'value' => $value,
            'label' => (string) $label,
            'chip' => $name === null ? (string) $label : sprintf(_('%1$s: %2$s'), $name, $label),
        );
    }

    // A control that every link carries but that's never a chip and never counts as a filter
    public function addCarried($key, $value)
    {
        if ($value === null || (string) $value === '') return;
        $this->carried[$key] = (string) $value;
    }

    public function isActive($key)
    {
        return isset($this->filters[$key]);
    }

    public function activeCount()
    {
        return count($this->filters);
    }

    // The list's address with the current state, leaving out the keys in $without, and adding (or replacing) $extra
    public function url(array $without = array(), array $extra = array())
    {
        $query = array();
        foreach ($this->filters as $key => $filter) {
            if (!in_array($key, $without, true)) $query[$key] = $filter['value'];
        }
        foreach ($this->carried as $key => $value) {
            if (!in_array($key, $without, true)) $query[$key] = $value;
        }
        foreach ($extra as $key => $value) {
            $query[$key] = $value;
        }

        return $this->base_url . ($query ? '?' . http_build_query($query) : '');
    }

    /*
     * The list's address for the current state, always carrying $keyword_key and $range_key, empty when they're off, so
     * whatever reads it (an export, the no-JavaScript pager, or the list's data endpoint) takes them from its own
     * request rather than the session, and another tab's filters can't leak in. $extra is added after them.
     */
    public function stateUrl($keyword_key = 'search_for', $range_key = 'daterange', array $extra = array())
    {
        $blanks = array();
        if (!$this->isActive($keyword_key)) $blanks[$keyword_key] = '';
        if (!$this->isActive($range_key)) $blanks[$range_key] = '';

        return $this->url(array(), array_merge($blanks, $extra));
    }

    // The export address for the current state (see stateUrl())
    public function exportUrl($keyword_key = 'search_for', $range_key = 'daterange')
    {
        return $this->stateUrl($keyword_key, $range_key, array('export_data' => 1));
    }

    /*
     * One extra, selected option for an active filter whose value isn't among a menu's options (each menu's options
     * follow the other filters, and a row may have been deleted), so the next Apply keeps the filter instead of quietly
     * dropping it. $values are the values the menu already shows.
     */
    public function keepOption($key, array $values)
    {
        if (!$this->isActive($key)) return '';

        $value = $this->filters[$key]['value'];
        foreach ($values as $shown) {
            if ((string) $shown === $value) return '';
        }

        return '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" selected>'
            . htmlspecialchars($this->filters[$key]['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }

    /*
     * The toolbar row. The Filters button is a link (the no-JavaScript page's address) that Bootstrap turns into the
     * panel's trigger, so it keeps a link's role and its Enter key rather than claiming a button's Space key it doesn't
     * have. On the no-JavaScript page ($expanded), it's a plain "Hide Filters" link back to the list without expand=1,
     * since the form is already showing as a card.
     */
    public function toolbar($expanded = false)
    {
        $count = $this->activeCount();
        $esc = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };

        ob_start(); ?>

        <div class="domainmod-filter-toolbar d-flex flex-wrap align-items-center gap-2 mb-3"><?php
            if ($expanded) { ?>
            <a class="btn btn-outline-secondary" href="<?php echo $esc($this->url()); ?>"><i class="bi bi-funnel" aria-hidden="true"></i> <?php echo $esc(_('Hide Filters')); ?></a><?php
            } else { ?>
            <a class="btn btn-outline-secondary" href="<?php echo $esc($this->url(array(), array('expand' => 1)) . '#' . $this->panel_id); ?>" data-bs-toggle="offcanvas" data-bs-target="#<?php echo $esc($this->panel_id); ?>" aria-controls="<?php echo $esc($this->panel_id); ?>"><i class="bi bi-funnel" aria-hidden="true"></i> <?php echo $esc(_('Filters')); ?><?php
                if ($count > 0) { ?> <span class="badge rounded-pill text-bg-danger"><span aria-hidden="true"><?php echo number_format($count); ?></span><span class="visually-hidden">(<?php echo $esc(sprintf(ngettext('%s active', '%s active', $count), number_format($count))); ?>)</span></span><?php
                } ?></a><?php
            }
            foreach ($this->filters as $key => $filter) { ?>
            <a class="btn btn-sm rounded-pill domainmod-filter-chip" href="<?php echo $esc($this->url(array($key))); ?>" aria-label="<?php echo $esc(sprintf(_('Remove filter: %s'), $filter['chip'])); ?>"><span class="domainmod-filter-chip-text"><?php echo $esc($filter['chip']); ?></span> <i class="bi bi-x-lg" aria-hidden="true"></i></a><?php
            }
            if ($count > 0) { ?>
            <a class="domainmod-filter-clear" href="<?php echo $esc($this->reset_url); ?>"><?php echo $esc(_('Clear all')); ?></a><?php
            } ?>
        </div><?php

        return ob_get_clean();
    }

    // The start of the panel that holds the list's filter form: an offcanvas, or a card on the no-JavaScript page
    public function panelTop($expanded = false)
    {
        $id = htmlspecialchars($this->panel_id, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars(_('Filters'), ENT_QUOTES, 'UTF-8');

        ob_start();
        if ($expanded) { ?>

        <section class="card mb-3 domainmod-filter-inline" id="<?php echo $id; ?>" aria-labelledby="<?php echo $id; ?>-title">
            <div class="card-body">
                <h2 class="h5" id="<?php echo $id; ?>-title"><?php echo $title; ?></h2><?php
        } else { ?>

        <div class="offcanvas offcanvas-end domainmod-filter-panel" id="<?php echo $id; ?>" tabindex="-1" aria-labelledby="<?php echo $id; ?>-title">
            <div class="offcanvas-header">
                <h2 class="h5 offcanvas-title" id="<?php echo $id; ?>-title"><?php echo $title; ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo htmlspecialchars(_('Close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="offcanvas-body"><?php
        }

        return ob_get_clean();
    }

    public function panelBottom($expanded = false)
    {
        ob_start();
        if ($expanded) { ?>

            </div>
        </section><?php
        } else { ?>

            </div>
        </div><?php
        }

        return ob_get_clean();
    }

} //@formatter:on

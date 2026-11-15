/*
 * /js/charts.js
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

/*
 * Draws the reports' charts (see DomainMOD\Chart). Each chart is a canvas whose data-domainmod-chart attribute holds
 * JSON with its type (doughnut, bar, hbar for a horizontal bar chart, pricebar for a price comparison, or stacked for
 * stacked counts), labels, values, and the user's currency format. The colours come from css/base.css, so the
 * charts match the colour mode, and they're redrawn in the new colours when it changes. Animation is off for users who
 * prefer reduced motion. Loaded by _includes/layout/chart-footer.inc.php, after Chart.js.
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    var root = document.documentElement;
    var reduce_motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var charts = [];

    function colour(name) {
        return getComputedStyle(root).getPropertyValue('--domainmod-chart-' + name).trim();
    }

    function palette() {
        var colours = [];
        for (var i = 1; i <= 9; i++) colours.push(colour(String(i)));
        return colours;
    }

    // An amount the way Currency::format() writes it, such as $1,234.56 or 1,234.56 €
    function money(amount, currency) {
        var number = Number(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        var space = currency.space === '1' ? ' ' : '';
        return currency.order === '1' ? number + space + currency.symbol : currency.symbol + space + number;
    }

    // Each slice or bar's colour: a doughnut's slices in the palette's order (it has at most five), every bar in the
    // first colour, a bar chart's last "Other" bar (the rest of the groups together) in grey, Upcoming Renewals'
    // Overdue bar in red, and a price comparison's cheapest bars in green
    function sliceColours(data) {
        var colours = palette();
        var out = data.values.map(function (value, index) {
            return data.type === 'doughnut' ? colours[index % colours.length] : colours[0];
        });
        if (data.hasOther) out[out.length - 1] = colour('other');
        if (data.type === 'bar' && data.firstIsOverdue) out[0] = colour('2');
        if (data.type === 'pricebar') {
            (data.highlight || []).forEach(function (index) {
                if (index >= 0 && index < out.length) out[index] = colour('3');
            });
        }
        return out;
    }

    // The datasets to draw: one for most charts, and one per series (each in its own colour) for stacked counts
    function datasets(data) {
        if (data.type === 'stacked') {
            return data.series.map(function (item) {
                return {label: item.name, data: item.values, backgroundColor: colour(item.colour), borderWidth: 0};
            });
        }
        return [{
            label: data.title,
            data: data.values,
            backgroundColor: sliceColours(data),
            borderColor: data.type === 'doughnut' ? colour('border') : 'transparent',
            borderWidth: data.type === 'doughnut' ? 2 : 0,
            // A free option still shows a sliver in a price comparison, so its highlight can be seen
            minBarLength: data.type === 'pricebar' ? 4 : 0
        }];
    }

    // A value as the chart shows it: a whole number for counts, and otherwise an amount in the user's currency
    function amount(value, data) {
        return data.format === 'count' ? Number(value).toLocaleString('en-US', {maximumFractionDigits: 0}) : money(value, data.currency);
    }

    // A bar's name on the axis, shortened when it's long (the tooltip shows it in full)
    var MAX_LABEL = 28;
    function shortLabel(label) {
        label = String(label);
        return label.length > MAX_LABEL ? label.slice(0, MAX_LABEL - 1) + '\u2026' : label;
    }

    function options(data) {
        var text = colour('text');
        var grid = colour('grid');
        var tooltip = {
            callbacks: {
                label: function (context) {
                    var horizontal = data.type === 'hbar' || data.type === 'pricebar' || data.type === 'stacked';
                    var value = data.type === 'doughnut' ? context.parsed : (horizontal ? context.parsed.x : context.parsed.y);
                    var prefix = data.type === 'stacked' ? context.dataset.label + ': ' : '';
                    return ' ' + prefix + amount(value, data);
                }
            }
        };
        var common = {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduce_motion.matches ? false : {duration: 400},
            plugins: {tooltip: tooltip}
        };
        if (data.type === 'doughnut') {
            common.plugins.legend = {
                position: window.innerWidth < 576 ? 'bottom' : 'right',
                labels: {color: text}
            };
            return common;
        }
        common.plugins.legend = data.type === 'stacked' ? {position: 'top', labels: {color: text}} : {display: false};
        // A cost report's bar chart, a price comparison, and stacked counts run sideways, one row per group, so long
        // names have room
        if (data.type === 'hbar' || data.type === 'pricebar' || data.type === 'stacked') {
            var stacked = data.type === 'stacked';
            common.indexAxis = 'y';
            common.scales = {
                x: {
                    beginAtZero: true,
                    stacked: stacked,
                    ticks: {
                        color: text,
                        precision: stacked ? 0 : undefined,
                        callback: function (value) {
                            return amount(value, data);
                        }
                    },
                    grid: {color: grid}
                },
                y: {
                    stacked: stacked,
                    ticks: {
                        color: text,
                        autoSkip: false,
                        callback: function (value) {
                            return shortLabel(this.getLabelForValue(value));
                        }
                    },
                    grid: {display: false}
                }
            };
            return common;
        }
        common.scales = {
            x: {ticks: {color: text}, grid: {color: grid}},
            y: {
                beginAtZero: true,
                ticks: {
                    color: text,
                    callback: function (value) {
                        return money(value, data.currency);
                    }
                },
                grid: {color: grid}
            }
        };
        return common;
    }

    function draw(canvas) {
        if (Chart.getChart(canvas)) return; // already drawn (htmx:load also fires for the whole page when it loads)
        var data;
        try {
            data = JSON.parse(canvas.getAttribute('data-domainmod-chart'));
        } catch (error) {
            return;
        }
        if (!data) return;
        var chart = new Chart(canvas, {
            type: data.type === 'doughnut' ? 'doughnut' : 'bar',
            data: {
                labels: data.labels,
                datasets: datasets(data)
            },
            options: options(data)
        });
        // Charts whose canvas an htmx swap took off the page are let go, so a recolour skips them
        charts = charts.filter(function (item) {
            if (document.body.contains(item.chart.canvas)) return true;
            item.chart.destroy();
            return false;
        });
        charts.push({chart: chart, data: data});
    }

    // The new colours (and the motion setting) after the colour mode or reduced motion setting changes
    function recolour() {
        charts.forEach(function (item) {
            item.chart.data.datasets = datasets(item.data);
            item.chart.options = options(item.data);
            item.chart.update('none');
        });
    }

    document.querySelectorAll('canvas[data-domainmod-chart]').forEach(draw);
    // A chart that htmx swaps in (such as a report's comparison) is drawn once it's on the page
    document.addEventListener('htmx:load', function (event) {
        event.detail.elt.querySelectorAll('canvas[data-domainmod-chart]').forEach(draw);
    });

    new MutationObserver(recolour).observe(root, {attributes: true, attributeFilter: ['data-bs-theme']});
    if (reduce_motion.addEventListener) reduce_motion.addEventListener('change', recolour);
}());

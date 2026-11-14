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
 * Draws the reports' charts (see DomainMOD\Chart). Each chart is a canvas with data-domainmod-chart, followed by a JSON
 * block with its type (doughnut, bar, or hbar, a horizontal bar chart), labels, values, and the user's currency format. The colours come from css/base.css, so the
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
    // first colour, a bar chart's last "Other" bar (the rest of the groups together) in grey, and Upcoming Renewals'
    // Overdue bar in red
    function sliceColours(data) {
        var colours = palette();
        var out = data.values.map(function (value, index) {
            return data.type === 'doughnut' ? colours[index % colours.length] : colours[0];
        });
        if (data.hasOther) out[out.length - 1] = colour('other');
        if (data.type === 'bar' && data.firstIsOverdue) out[0] = colour('2');
        return out;
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
                    var value = data.type === 'doughnut' ? context.parsed : (data.type === 'hbar' ? context.parsed.x : context.parsed.y);
                    return ' ' + money(value, data.currency);
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
        common.plugins.legend = {display: false};
        // A cost report's bar chart runs sideways, one row per group, so long names have room
        if (data.type === 'hbar') {
            common.indexAxis = 'y';
            common.scales = {
                x: {
                    beginAtZero: true,
                    ticks: {
                        color: text,
                        callback: function (value) {
                            return money(value, data.currency);
                        }
                    },
                    grid: {color: grid}
                },
                y: {
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
        var block = document.getElementById(canvas.id + '-data');
        if (!block) return;
        var data;
        try {
            data = JSON.parse(block.textContent);
        } catch (error) {
            return;
        }
        var chart = new Chart(canvas, {
            type: data.type === 'hbar' ? 'bar' : data.type,
            data: {
                labels: data.labels,
                datasets: [{
                    label: data.title,
                    data: data.values,
                    backgroundColor: sliceColours(data),
                    borderColor: data.type === 'doughnut' ? colour('border') : 'transparent',
                    borderWidth: data.type === 'doughnut' ? 2 : 0
                }]
            },
            options: options(data)
        });
        charts.push({chart: chart, data: data});
    }

    // The new colours (and the motion setting) after the colour mode or reduced motion setting changes
    function recolour() {
        charts.forEach(function (item) {
            var dataset = item.chart.data.datasets[0];
            dataset.backgroundColor = sliceColours(item.data);
            if (item.data.type === 'doughnut') dataset.borderColor = colour('border');
            item.chart.options = options(item.data);
            item.chart.update('none');
        });
    }

    document.querySelectorAll('canvas[data-domainmod-chart]').forEach(draw);

    new MutationObserver(recolour).observe(root, {attributes: true, attributeFilter: ['data-bs-theme']});
    if (reduce_motion.addEventListener) reduce_motion.addEventListener('change', recolour);
}());

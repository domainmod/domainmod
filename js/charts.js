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
 * stacked counts), labels, values, and the user's currency format. A bar chart with series stacks its amounts. The
 * colours come from css/base.css, so the charts match the colour mode, and they're redrawn in the new colours when it
 * changes. Animation is off for users who prefer reduced motion. Loaded by _includes/layout/chart-footer.inc.php, after
 * Chart.js.
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    var root = document.documentElement;
    // Right-to-left languages (Layout::isRtl() sets the page's dir): legends and tooltips read right to left, bars run
    // from the right, and the value axis sits on the right
    var rtl = root.getAttribute('dir') === 'rtl';
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
    // chart's own colour (data.colour, such as domains for the domain charts) or else the first colour, a bar chart's
    // last "Other" bar (the rest of the groups together) in grey, an Overdue bar hatched in the bars' colour (red
    // without one), and a price comparison's cheapest bars in green
    function sliceColours(data) {
        var colours = palette();
        var base = data.colour && data.type !== 'doughnut' && data.type !== 'pricebar' ? colour(data.colour) : colours[0];
        var out = data.values.map(function (value, index) {
            return data.type === 'doughnut' ? colours[index % colours.length] : base;
        });
        if (data.hasOther) out[out.length - 1] = colour('other');
        if (data.type === 'bar' && data.firstIsOverdue) out[0] = data.colour ? hatch(base) : colour('2');
        if (data.type === 'pricebar') {
            (data.highlight || []).forEach(function (index) {
                if (index >= 0 && index < out.length) out[index] = colour('3');
            });
        }
        return out;
    }

    /*
     * An Overdue bar's fill: diagonal stripes of a colour over a see-through fill of it, so it reads as the same costs,
     * only late. Each colour's pattern is made once (a new colour mode brings new colours, and so new patterns).
     */
    var hatches = {};
    function hatch(hex) {
        if (hatches[hex]) return hatches[hex];
        var tile = document.createElement('canvas');
        tile.width = tile.height = 8;
        var ctx = tile.getContext('2d');
        ctx.fillStyle = hex;
        ctx.globalAlpha = 0.35;
        ctx.fillRect(0, 0, 8, 8);
        ctx.globalAlpha = 1;
        ctx.strokeStyle = hex;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-2, 10);
        ctx.lineTo(10, -2);
        ctx.moveTo(-2, 2);
        ctx.lineTo(2, -2);
        ctx.moveTo(6, 10);
        ctx.lineTo(10, 6);
        ctx.stroke();
        hatches[hex] = ctx.createPattern(tile, 'repeat');
        return hatches[hex];
    }

    /*
     * The datasets to draw: one for most charts, and one per series (each in its own colour) for stacked counts and
     * stacked monthly amounts. A stacked monthly chart's Overdue bar is drawn with each stack hatched in its series'
     * colour (hatch()), with a gap in the card's colour (white in light mode, as between a doughnut's slices) under every
     * stack after the first, so overdue domains, hosting services, and SSL certificates never merge. The legend keeps
     * each series' own solid colour.
     */
    function datasets(data) {
        if (data.type === 'bar' && data.series) {
            return data.series.map(function (item, series_index) {
                var overdue = function (context) {
                    return data.firstIsOverdue && context.type === 'data' && context.dataIndex === 0;
                };
                // The gap is the stack's bottom border, drawn inside the bar, so the stacks keep their heights
                var gap = function (context) {
                    return overdue(context) && series_index > 0;
                };
                return {
                    label: item.name,
                    data: item.values,
                    backgroundColor: function (context) {
                        return overdue(context) ? hatch(colour(item.colour)) : colour(item.colour);
                    },
                    borderColor: function () {
                        return colour('border');
                    },
                    borderWidth: function (context) {
                        return gap(context) ? {top: 0, right: 0, bottom: 2, left: 0} : 0;
                    },
                    borderSkipped: function (context) {
                        return gap(context) ? false : 'start';
                    }
                };
            });
        }
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

    // An amount placed among right-to-left text (an axis label or a tooltip) kept in its own left-to-right order, so its
    // currency symbol and minus sign stay on their configured side, with Unicode's left-to-right isolate marks
    function isolate(text) {
        return rtl ? '\u2066' + text + '\u2069' : text;
    }

    // A value as the chart shows it: a whole number for counts, and otherwise an amount in the user's currency
    function amount(value, data) {
        return data.format === 'count' ? Number(value).toLocaleString('en-US', {maximumFractionDigits: 0}) : money(value, data.currency);
    }

    // A short amount for a bar label that doesn't fit, such as $1.2k or $3.4M, in the user's currency format
    function shortMoney(value, currency) {
        var abs = Math.abs(Number(value));
        var number;
        if (abs >= 1000000) number = (value / 1000000).toLocaleString('en-US', {maximumFractionDigits: 1}) + 'M';
        else if (abs >= 1000) number = (value / 1000).toLocaleString('en-US', {maximumFractionDigits: 1}) + 'k';
        else number = Number(value).toLocaleString('en-US', {maximumFractionDigits: 0});
        var space = currency.space === '1' ? ' ' : '';
        return currency.order === '1' ? number + space + currency.symbol : currency.symbol + space + number;
    }

    /*
     * A top legend gets room below it, so the bar totals and the plot don't crowd the series names. Chart.js has no
     * legend margin option, so the legend's height is grown once it's measured (the plugin runs after the legend's own).
     */
    var legendGap = {
        id: 'domainmodLegendGap',
        beforeInit: function (chart) {
            if (!chart.legend) return;
            var fit = chart.legend.fit;
            chart.legend.fit = function () {
                fit.call(this);
                if (this.options.display !== false && this.options.position === 'top') this.height += 16;
            };
        }
    };

    /*
     * Each month's total above its bar, on the monthly cost charts (Cost by Month, Domain Renewals, and the
     * Dashboard), so the amount can be read without tracing it to the axis. When the full amounts don't fit over
     * their bars, the whole chart uses short amounts (such as $1.2k), then a smaller size, and a label that would still
     * run into another is left off, so labels never overlap. Bars with no total get no label. The labels are
     * only drawn on the canvas, since the table under each chart gives every figure to screen readers.
     */
    var barTotals = {
        id: 'domainmodBarTotals',
        afterDatasetsDraw: function (chart, args, plugin_options) {
            var data = plugin_options.data;
            if (!data) return;
            // A stacked chart's totals are its shown series added up (a series hidden from the legend is left out), and
            // each total sits above the top of its stack. Chart.js stacks amounts above zero and below it separately, so
            // each stack's highest and lowest points are kept too.
            var values = data.values;
            var highs = [];
            var lows = [];
            var meta = chart.getDatasetMeta(0);
            if (data.series) {
                var shown = [];
                chart.data.datasets.forEach(function (dataset, index) {
                    if (chart.isDatasetVisible(index)) shown.push(index);
                });
                if (!shown.length) return;
                meta = chart.getDatasetMeta(shown[0]);
                values = data.labels.map(function (label, index) {
                    highs[index] = lows[index] = 0;
                    return shown.reduce(function (sum, dataset_index) {
                        var amount = Number(chart.data.datasets[dataset_index].data[index] || 0);
                        if (amount > 0) highs[index] += amount;
                        else lows[index] += amount;
                        return sum + amount;
                    }, 0);
                });
            }
            if (!meta || !meta.data.length) return;
            // A bar's position: its own for a plain bar, and its whole stack's for a stacked one, with y at the end its
            // total's label goes by (the top for a total above zero, the bottom for one below) and base at the other end.
            // It's a copy: getProps() can hand back the bar itself, which must keep its own size.
            var y_scale = chart.scales.y;
            function barProps(index) {
                var own = meta.data[index].getProps(['x', 'y', 'width', 'base'], true);
                var props = {x: own.x, y: own.y, width: own.width, base: own.base};
                if (data.series) {
                    var up = Number(values[index]) >= 0;
                    props.y = y_scale.getPixelForValue(up ? highs[index] : lows[index]);
                    props.base = y_scale.getPixelForValue(up ? lows[index] : highs[index]);
                }
                return props;
            }
            var ctx = chart.ctx;
            var area = chart.chartArea;
            var family = Chart.defaults.font.family;
            var drawn = [];
            ctx.save();
            ctx.fillStyle = colour('text');
            // Amounts are drawn left to right in every language, so the symbol stays on its configured side
            ctx.direction = 'ltr';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            // One style for the whole chart, so the labels match: the full amounts if every one fits over its bar,
            // otherwise the short amounts, then the short amounts in a smaller size (on a phone)
            var slot = area.width / meta.data.length;
            var styles = [[money, 11], [shortMoney, 11], [shortMoney, 9]];
            var style = styles[styles.length - 1];
            for (var i = 0; i < styles.length; i++) {
                ctx.font = '600 ' + styles[i][1] + 'px ' + family;
                var widest = 0;
                values.forEach(function (value) {
                    if (Number(value) !== 0) widest = Math.max(widest, ctx.measureText(styles[i][0](value, data.currency)).width);
                });
                if (widest <= slot - 4) {
                    style = styles[i];
                    break;
                }
            }
            ctx.font = '600 ' + style[1] + 'px ' + family;
            meta.data.forEach(function (element, index) {
                var value = Number(values[index]);
                if (!value) return;
                var bar = barProps(index);
                var label = style[0](value, data.currency);
                var half = ctx.measureText(label).width / 2;
                // Above a bar that goes up, and below one that goes down (a month whose credits outweigh its costs)
                var box = {left: bar.x - half, right: bar.x + half};
                if (value > 0) {
                    box.bottom = Math.max(bar.y - 3, area.top + style[1]);
                    box.top = box.bottom - style[1];
                } else {
                    box.top = Math.min(bar.y + 3, area.bottom - style[1]);
                    box.bottom = box.top + style[1];
                }
                // Left off if it would still run into a label already drawn (labels over bars of different heights
                // can sit side by side), into another bar, or past the chart's edge
                if (box.left < area.left - 4 || box.right > area.right + 4) return;
                var clash = drawn.some(function (other) {
                    return box.left < other.right + 3 && box.right > other.left - 3 && box.top < other.bottom && box.bottom > other.top;
                }) || meta.data.some(function (other, other_index) {
                    // Or over a taller neighbour's bar, where it would be hard to read
                    if (other_index === index) return false;
                    var props = barProps(other_index);
                    var bar_top = Math.min(props.y, props.base);
                    var bar_bottom = Math.max(props.y, props.base);
                    return box.left < props.x + props.width / 2 && box.right > props.x - props.width / 2 && box.bottom > bar_top && box.top < bar_bottom;
                });
                if (clash) return;
                ctx.fillText(label, bar.x, box.bottom);
                drawn.push(box);
            });
            ctx.restore();
        }
    };

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
                    var prefix = data.type === 'stacked' || data.series ? context.dataset.label + ': ' : '';
                    return ' ' + prefix + isolate(amount(value, data));
                }
            }
        };
        // A stacked monthly chart's tooltip lists every series in the month, then their total, each with its series'
        // solid colour (an Overdue bar's stripes would make a hard-to-read swatch)
        if (data.type === 'bar' && data.series) {
            tooltip.mode = 'index';
            tooltip.callbacks.labelColor = function (context) {
                var series = data.series[context.datasetIndex];
                var solid = series ? colour(series.colour) : colour('1');
                return {borderColor: solid, backgroundColor: solid};
            };
            tooltip.callbacks.footer = function (items) {
                var total = items.reduce(function (sum, item) {
                    return sum + item.parsed.y;
                }, 0);
                return data.totalLabel + ': ' + isolate(money(total, data.currency));
            };
        }
        // A one-colour bar chart's tooltip swatch is that solid colour, the Overdue bar's too
        if (data.type === 'bar' && !data.series && data.colour) {
            tooltip.callbacks.labelColor = function () {
                var solid = colour(data.colour);
                return {borderColor: solid, backgroundColor: solid};
            };
        }
        if (rtl) {
            tooltip.rtl = true;
            tooltip.textDirection = 'rtl';
        }
        var common = {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduce_motion.matches ? false : {duration: 400},
            plugins: {tooltip: tooltip}
        };
        if (data.type === 'doughnut') {
            common.plugins.legend = {
                // Beside the chart on the reading side's far end (the left in right-to-left languages)
                position: window.innerWidth < 576 ? 'bottom' : (rtl ? 'left' : 'right'),
                rtl: rtl,
                textDirection: rtl ? 'rtl' : 'ltr',
                labels: {color: text}
            };
            return common;
        }
        common.plugins.legend = data.type === 'stacked' || data.series
            ? {position: 'top', rtl: rtl, textDirection: rtl ? 'rtl' : 'ltr', labels: {color: text}}
            : {display: false};
        // A stacked monthly chart's legend keeps each series' own solid colour, which Chart.js would otherwise take from
        // the first bar, the Overdue bar's stripes when there is one, with no border
        if (data.type === 'bar' && data.series) {
            common.plugins.legend.labels.generateLabels = function (chart) {
                var items = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                items.forEach(function (legend_item) {
                    var series = data.series[legend_item.datasetIndex];
                    if (series) legend_item.fillStyle = legend_item.strokeStyle = colour(series.colour);
                    // The Overdue bar's gap is a border, which the legend's boxes don't show
                    legend_item.lineWidth = 0;
                });
                return items;
            };
        }
        // A cost report's bar chart, a price comparison, and stacked counts run sideways, one row per group, so long
        // names have room
        if (data.type === 'hbar' || data.type === 'pricebar' || data.type === 'stacked') {
            var stacked = data.type === 'stacked';
            common.indexAxis = 'y';
            common.scales = {
                x: {
                    beginAtZero: true,
                    // Bars grow from the right in right-to-left languages
                    reverse: rtl,
                    stacked: stacked,
                    ticks: {
                        color: text,
                        precision: stacked ? 0 : undefined,
                        callback: function (value) {
                            return isolate(amount(value, data));
                        }
                    },
                    grid: {color: grid}
                },
                y: {
                    stacked: stacked,
                    // The group names on the right in right-to-left languages
                    position: rtl ? 'right' : 'left',
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
        // The monthly totals above the bars need room over the tallest one
        common.plugins.domainmodBarTotals = {data: data};
        common.layout = {padding: {top: 6}};
        common.scales = {
            // The months run right to left in right-to-left languages, so the earliest is on the right, and the
            // amounts axis moves to the right
            // An Overdue bar's label is red, as the Overdue bar's stripes are in the bars' own colours
            x: {
                reverse: rtl,
                stacked: !!data.series,
                ticks: {
                    color: function (context) {
                        return data.firstIsOverdue && context.index === 0 ? colour('overdue-text') : text;
                    }
                },
                grid: {color: grid}
            },
            y: {
                beginAtZero: true,
                stacked: !!data.series,
                grace: '10%',
                position: rtl ? 'right' : 'left',
                ticks: {
                    color: text,
                    callback: function (value) {
                        return isolate(money(value, data.currency));
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
                // In right-to-left languages each name takes its direction from its own first letter (Unicode's first
                // strong isolate), so a left-to-right name such as .co.uk keeps its order beside right-to-left ones
                labels: rtl ? data.labels.map(function (label) {
                    return '\u2068' + label + '\u2069';
                }) : data.labels,
                datasets: datasets(data)
            },
            options: options(data),
            plugins: data.type === 'doughnut' ? [] : (data.type === 'bar' ? [barTotals, legendGap] : [legendGap])
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

<?php
/**
 * /_includes/layout/date-range-picker-footer.inc.php
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
?>
<?php
// Flatpickr's translation for the user's language, when it has one (DomainMOD's code, such as de_DE.UTF-8, to
// Flatpickr's, such as de). The files are the ones .data/release/vendor-theme.py vendors.
$flatpickr_codes = array('ar_SA' => 'ar', 'bn_BD' => 'bn', 'de_DE' => 'de', 'es_ES' => 'es', 'fa_IR' => 'fa',
    'fr_FR' => 'fr', 'hi_IN' => 'hi', 'id_ID' => 'id', 'it_IT' => 'it', 'ja_JP' => 'ja', 'ko_KR' => 'ko',
    'nl_NL' => 'nl', 'pl_PL' => 'pl', 'pt_BR' => 'pt', 'pt_PT' => 'pt', 'ru_RU' => 'ru', 'tr_TR' => 'tr',
    'vi_VN' => 'vn', 'zh_CN' => 'zh', 'zh_TW' => 'zh-tw');
$flatpickr_code = $flatpickr_codes[substr($_SESSION['s_default_language'] ?? '', 0, 5)] ?? 'default';
?>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/flatpickr/flatpickr.min.js'); ?>"></script>
<?php if ($flatpickr_code !== 'default') { ?>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/flatpickr/l10n/' . $flatpickr_code . '.js'); ?>"></script>
<?php } ?>
<script nonce="<?php echo CURRENT_NONCE; ?>">
    // The field's value is always a whole range, YYYY-MM-DD - YYYY-MM-DD (Date::splitAndCheckRange() reads the first
    // and last ten characters), or empty for no date filter. A range left half-picked when the calendar closes is
    // cleared, and picking the same day twice gives a one-day range.
    document.querySelectorAll('input[name="daterange"]').forEach(function (input) {

        // A finished range is always written here, since Flatpickr writes a one-day range as a single date
        function writeRange(selected_dates, picker) {
            if (selected_dates.length === 2) {
                input.value = picker.formatDate(selected_dates[0], 'Y-m-d') + ' - ' +
                    picker.formatDate(selected_dates[1], 'Y-m-d');
            }
        }

        flatpickr(input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            // Placed next to the field, so it stays with it when AdminLTE's main area scrolls
            static: true,
            allowInput: true,
            locale: Object.assign({}, flatpickr.l10ns['<?php echo $flatpickr_code; ?>'] || flatpickr.l10ns.default,
                {rangeSeparator: ' - '}),
            // Scrolled fully into view when it opens, so Safari doesn't scroll the page under the pointer partway
            // through a click (it scrolls to a day as the day takes focus) and lose the click
            onOpen: function (selected_dates, date_string, picker) {
                picker.calendarContainer.scrollIntoView({block: 'nearest'});
            },
            onChange: function (selected_dates, date_string, picker) {
                writeRange(selected_dates, picker);
            },
            // Closing writes the value again (Flatpickr rewrites it on close), and clears a half-picked range
            onClose: function (selected_dates, date_string, picker) {
                if (selected_dates.length === 2) {
                    writeRange(selected_dates, picker);
                } else {
                    picker.clear();
                }
            },
            onReady: function (selected_dates, date_string, picker) {
                // The Down arrow moves from the field into the open calendar (to the chosen day, then today), where
                // Flatpickr's arrow keys take over, and Escape closes it. Typing a date in the field still works.
                input.addEventListener('keydown', function (event) {
                    if (!picker.isOpen) return;
                    if (event.key === 'Escape') {
                        picker.close();
                        return;
                    }
                    if (event.key !== 'ArrowDown') return;
                    var days = picker.calendarContainer;
                    var day = days.querySelector('.flatpickr-day.selected') ||
                        days.querySelector('.flatpickr-day.today') ||
                        days.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)');
                    if (day) {
                        event.preventDefault();
                        day.focus();
                    }
                });

                // A value already in the field (such as a filter being shown again) gets the same rules: a whole
                // range is written in full, and anything else is cleared
                if (selected_dates.length === 2) {
                    writeRange(selected_dates, picker);
                } else if (input.value !== '') {
                    picker.clear();
                }

                var clear_button = document.createElement('button');
                clear_button.type = 'button';
                clear_button.className = 'btn btn-sm btn-secondary m-2';
                clear_button.textContent = <?php echo json_encode(_('Clear')); ?>;
                // Enter and Space work the button, rather than reaching Flatpickr's own keyboard handling
                clear_button.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') event.stopPropagation();
                });
                clear_button.addEventListener('click', function () {
                    picker.clear();
                    picker.close();
                });
                picker.calendarContainer.appendChild(clear_button);
            }
        });
    });
</script>

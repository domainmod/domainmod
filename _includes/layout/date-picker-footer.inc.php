<?php
/**
 * /_includes/layout/date-picker-footer.inc.php
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
    flatpickr('input[name="datepick"]', {
        dateFormat: 'Y-m-d',
        // Placed next to the field, so it stays with it when AdminLTE's main area scrolls
        static: true,
        allowInput: true,
        minDate: '<?php echo $time->timeBasicPlusYears(-9); ?>',
        maxDate: '<?php echo $time->timeBasicPlusYears(10); ?>',
        locale: flatpickr.l10ns['<?php echo $flatpickr_code; ?>'] || flatpickr.l10ns.default,
        // Scrolled fully into view when it opens, so Safari doesn't scroll the page under the pointer partway through
        // a click (it scrolls to a day as the day takes focus) and lose the click
        onOpen: function (selected_dates, date_string, picker) {
            picker.calendarContainer.scrollIntoView({block: 'nearest'});
        },
        // The Down arrow moves from the field into the open calendar (to the chosen day, then today), where Flatpickr's
        // arrow keys take over, and Escape closes it. Typing a date in the field still works.
        onReady: function (selected_dates, date_string, picker) {
            picker.input.addEventListener('keydown', function (event) {
                if (!picker.isOpen) return;
                if (event.key === 'Escape') {
                    picker.close();
                    return;
                }
                if (event.key !== 'ArrowDown') return;
                var days = picker.calendarContainer;
                var day = days.querySelector('.flatpickr-day.selected') || days.querySelector('.flatpickr-day.today') ||
                    days.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)');
                if (day) {
                    event.preventDefault();
                    day.focus();
                }
            });
        }
    });
</script>

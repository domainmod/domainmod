<?php
/**
 * /classes/DomainMOD/Form.php
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

class Form
{

    public function showFormTop($before)
    {
        ob_start();
            echo $before; ?>
            <form method="post">
                <?php echo $this->showCsrfField(); ?>
                <div class="mb-3"><?php
        return ob_get_clean();
    }

    // The hidden security token field, for forms that don't use showFormTop() (see DomainMOD\Csrf).
    public function showCsrfField()
    {
        $csrf = new Csrf();
        return $csrf->field();
    }

    public function showFormBottom($after)
    {
        ob_start(); ?>
                </div>
            </form><?php
            echo $after;
        return ob_get_clean();
    }

    public function showInputText($name, $text_to_display, $subtext, $value, $maxlength, $is_password, $required, $before, $after)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <input type="<?php if ($is_password == '1') { echo 'password'; } else { echo 'text'; } ?>" autocomplete="<?php if ($is_password == '1') { echo 'off'; } else { echo 'on'; } ?>" class="form-control" placeholder="<?php echo $text_to_display; ?>" name="<?php echo $name; ?>"
                    value="<?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>" maxlength="<?php echo $maxlength; ?>">
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showInputTextarea($name, $text_to_display, $subtext, $value, $required, $before, $after)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <textarea class="form-control" placeholder="<?php echo $text_to_display; ?>" name="<?php
                    echo $name; ?>"><?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showInputHidden($name, $value)
    {
        ob_start(); ?>
            <input type="hidden" class="form-control" name="<?php echo $name; ?>" value="<?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>"><?php
        return ob_get_clean();
    }

    public function showDropdownTop($name, $text_to_display, $subtext, $required, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <select class="form-select" name="<?php echo $name; ?>"><?php
        return ob_get_clean();
    }

    public function showDropdownBottom($after)
    {
        ob_start(); ?>
                </select>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showDropdownOption($value, $text_to_display, $to_compare)
    {
        ob_start(); ?>
            <option value="<?php echo $value ?>"<?php if ($value == $to_compare) echo " selected" ?>><?php echo $text_to_display; ?></option><?php
        return ob_get_clean();
    }

    public function showMultipleSelectTop($name, $text_to_display, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label" for="<?php echo $name; ?>"><?php echo $text_to_display; ?></label>
                <select id="<?php echo $name; ?>" name="<?php echo $name; ?>[]" class="form-select domainmod-multiple-select" multiple="multiple"><?php
        return ob_get_clean();
    }

    public function showMultipleSelectBottom($after)
    {
        ob_start(); ?>
                </select>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showMultipleSelectOption($text_to_display, $value, $selected_flag)
    {
        ob_start();
            if ($selected_flag == '1') { ?>
                <option selected="selected" value="<?php echo $value; ?>"><?php echo $text_to_display; ?></option><?php
            } else { ?>
                <option value="<?php echo $value; ?>"><?php echo $text_to_display; ?></option><?php
            }
        return ob_get_clean();
    }

    public function showCheckbox($name, $value, $text_to_display, $subtext, $to_compare, $before, $after)
    {
        ob_start();
            echo $before; ?>
                <div class="mb-3">
                    <div class="form-check">
                      <input name="<?php echo $name; ?>" class="form-check-input" type="checkbox" id="<?php echo $name; ?>" value="<?php echo $value; ?>"<?php if ($value == $to_compare) echo " checked" ?>>
                      <label for="<?php echo $name; ?>" class="form-check-label"><?php echo $text_to_display; ?></label>
                        <?php if ($subtext != '') echo '<BR>' . $subtext; ?>
                    </div>
                </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showRadioTop($text_to_display, $subtext, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3"><strong><?php echo $text_to_display; ?></strong><BR><?php echo $subtext; ?><?php
        return ob_get_clean();
    }

    public function showRadioBottom($after)
    {
        ob_start(); ?>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showRadioOption($name, $value, $text_to_display, $to_compare, $before, $after)
    {
        ob_start();
            echo $before; ?>
            <div class="form-check form-check-inline">
                <input type="radio" class="form-check-input" id="<?php echo $name . $value; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>"<?php if ($value == $to_compare) echo " checked" ?>>
                <label for="<?php echo $name . $value; ?>" class="form-check-label"><?php echo $text_to_display; ?></label>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showSubmitButton($button_text, $before, $after)
    {
        ob_start();
            $layout = new Layout();
            echo $before;
            echo $layout->showButton('submit', $button_text);
            echo $after;
        return ob_get_clean();
    }

    public function showSwitch($text_to_display, $subtext, $name, $value, $before, $after)
    {
        ob_start();
            echo $before; ?>
            <strong><?php echo $text_to_display; ?></strong><BR>
            <?php if ($subtext != '') echo $subtext . '<BR>'; ?>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="1" aria-label="<?php echo htmlspecialchars(strip_tags($text_to_display), ENT_QUOTES, 'UTF-8'); ?>"<?php if ($value == '1') echo ' checked'; ?>>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

} //@formatter:on

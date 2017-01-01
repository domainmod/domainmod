<?php
/**
 * /classes/DomainMOD/Form.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

class Form
{

    public function showFormTop($before)
    {
        ob_start();
            echo $before; ?>
            <form method="post">
                <div class="form-group"><?php
        return ob_get_clean();
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
            <div class="form-group">
                <label><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span style="font-weight: normal;">' . $subtext . '</span><BR>'; ?></label>
                <input type="<?php if ($is_password == '1') { echo "password"; } else { echo "text"; } ?>" class="form-control" placeholder="<?php echo $text_to_display; ?>" name="<?php echo $name; ?>"
                    value="<?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>" maxlength="<?php echo $maxlength; ?>">
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showInputTextarea($name, $text_to_display, $subtext, $value, $required, $before, $after)
    {
        ob_start();
            echo $before; ?>
            <div class="form-group">
                <label><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span style="font-weight: normal;">' . $subtext . '</span><BR>'; ?></label>
                <textarea class="form-control" placeholder="<?php echo $text_to_display; ?>" name="<?php
                    echo $name; ?>" style="height: 80px;"><?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?></textarea>
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
            <div class="form-group">
                <label class="control-label"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span style="font-weight: normal;">' . $subtext . '</span><BR>'; ?></label>
                <select class="form-control" name="<?php echo $name; ?>"><?php
        return ob_get_clean();
    }

    public function showDropdownTopJump($text_to_display, $subtext, $required, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="form-group">
                <label class="control-label"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span style="font-weight: normal;">' . $subtext . '</span><BR>'; ?></label>
                <select class="form-control" name="jumpMenu" id="jumpMenu" onChange="MM_jumpMenu('parent',this,0)"><?php
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

    public function showDropdownOptionJump($url, $value, $text_to_display, $to_compare)
    {
        ob_start(); ?>
            <option value="<?php echo htmlentities($url, ENT_QUOTES, 'UTF-8'); ?><?php echo htmlentities($value , ENT_QUOTES, 'UTF-8')?>"<?php if ($value == $to_compare) echo " selected" ?>><?php echo htmlentities($text_to_display, ENT_QUOTES, 'UTF-8'); ?></option><?php
        return ob_get_clean();
    }

    public function showMultipleSelectTop($name, $text_to_display, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="form-group">
                <label><?php echo $text_to_display; ?></label>
                <select id="<?php echo $name; ?>" name="<?php echo $name; ?>[]" class="form-control select2"
                    multiple="multiple" data-placeholder="<?php echo $text_to_display; ?>" style="width: 100%;"><?php
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
            <div class="form-group">
                <label>
                    <input type="checkbox" class="form-control square-red" name="<?php echo $name; ?>" value="<?php echo $value; ?>"<?php if ($value == $to_compare) echo " checked" ?>>
                    <span style="font-weight: normal;"><?php echo "&nbsp;&nbsp;&nbsp;" . $text_to_display; ?><?php if ($subtext != '') echo '<BR><BR><span style="font-weight: normal;">' . $subtext . '</span>'; ?></span>
                </label>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    public function showRadioTop($text_to_display, $subtext, $before)
    {
        ob_start();
            echo $before; ?>
            <div class="form-group">
                <label><?php echo $text_to_display; ?><?php if ($subtext != '') echo '<BR><span style="font-weight: normal;">' . $subtext . '</span><BR>'; ?></label><?php
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
            <label>
                <input type="radio" class="form-control square-red" name="<?php echo $name; ?>" value="<?php echo $value; ?>"<?php if ($value == $to_compare) echo " checked" ?>>
                &nbsp;<span style="font-weight: normal;"><?php echo $text_to_display; ?></span>
            </label><?php
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

} //@formatter:on

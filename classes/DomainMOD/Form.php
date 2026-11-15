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
    /*
     * The form mistakes found while checking this request's POST, by field name, so each one can show under its field
     * (see addFieldError()). It only lasts for the request, so nothing carries over a redirect.
     */
    public static $field_errors = array();

    // Whether the first field with a mistake has already been given the focus on this page
    public static $error_focus_used = false;

    /*
     * Records a mistake in a field ($names is one field name, or several when one message covers more than one field,
     * such as two blank passwords), so the field shows it under itself, and adds the message to the page's error message
     * at the top once, as the pages did before. $replace sets the top message to just this one, for the pages that
     * replace it rather than add to it.
     */
    public function addFieldError($names, $message, $replace = false)
    {
        foreach ((array) $names as $name) {
            self::$field_errors[$name][] = $message;
        }

        if ($replace) {
            $_SESSION['s_message_danger'] = $message;
        } else {
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . $message . '<BR>';
        }
    }

    // A field's id, made from its name, for its label's "for" and its error message
    public function fieldId($name)
    {
        return 'domainmod-field-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $name);
    }

    // " is-invalid" when the field has a mistake, for the end of its class attribute, otherwise nothing
    public function errorClass($name)
    {
        return empty(self::$field_errors[$name]) ? '' : ' is-invalid';
    }

    /*
     * The attributes that tell screen readers a field has a mistake and where its message is, and, for the first such
     * field on the page, the focus. Nothing when the field has no mistake.
     */
    public function errorAttributes($name, $id = '')
    {
        if (empty(self::$field_errors[$name])) return '';

        $id = $id != '' ? $id : $this->fieldId($name);
        $attributes = ' aria-invalid="true" aria-describedby="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '-error"';

        if (!self::$error_focus_used) {
            self::$error_focus_used = true;
            $attributes .= ' autofocus';
        }

        return $attributes;
    }

    /*
     * A field's mistakes, shown under it in Bootstrap's invalid style. It's only printed when there's a mistake, so it's
     * always shown (d-block) rather than left to Bootstrap's rule, which only reaches a message that sits right after
     * the field (a date picker or Tom Select wraps the field, so theirs wouldn't show). Nothing when the field has no
     * mistake.
     */
    public function errorFeedback($name, $id = '')
    {
        if (empty(self::$field_errors[$name])) return '';

        $id = $id != '' ? $id : $this->fieldId($name);
        $messages = array_map(function ($message) {
            return htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8', false);
        }, self::$field_errors[$name]);

        return '<div class="invalid-feedback d-block" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '-error">' . implode('<br>', $messages) . '</div>';
    }

    public function showFormTop($before)
    {
        ob_start();
            echo $before; ?>
            <form method="post" class="domainmod-form">
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

    /*
     * A text (or password) field with its label. $autofocus ('1') puts the cursor in it when the page loads, for the
     * first field of a page whose main job is entering something new (such as an Add page). It's left off when the page
     * is showing an error message (including one carried over from a redirect) or the form is shown again after a
     * submit, so the focus doesn't skip past the message. A mistake recorded for the field with addFieldError() shows
     * under it, and the first such field on the page gets the focus instead.
     */
    public function showInputText($name, $text_to_display, $subtext, $value, $maxlength, $is_password, $required, $before, $after, $autofocus = '')
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <input type="<?php if ($is_password == '1') { echo 'password'; } else { echo 'text'; } ?>" autocomplete="<?php if ($is_password == '1') { echo 'off'; } else { echo 'on'; } ?>" class="form-control<?php echo $this->errorClass($name); ?>" placeholder="<?php echo $text_to_display; ?>" name="<?php echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"
                    value="<?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>" maxlength="<?php echo $maxlength; ?>"<?php echo $this->errorAttributes($name); ?><?php if ($autofocus == '1' && !System::$error_shown && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') echo ' autofocus'; ?>>
                <?php echo $this->errorFeedback($name); ?>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    /*
     * A text area with its label. $autofocus ('1') and mistakes recorded with addFieldError() work as in showInputText().
     */
    public function showInputTextarea($name, $text_to_display, $subtext, $value, $required, $before, $after, $autofocus = '')
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <textarea class="form-control<?php echo $this->errorClass($name); ?>" placeholder="<?php echo $text_to_display; ?>" name="<?php
                    echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"<?php echo $this->errorAttributes($name); ?><?php if ($autofocus == '1' && !System::$error_shown && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') echo ' autofocus'; ?>><?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php echo $this->errorFeedback($name); ?>
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

    /*
     * The start of a menu with its label. A mistake recorded for it with addFieldError() marks it, and shows under it
     * when showDropdownBottom() is given the same name. $searchable lets people type to narrow a long menu
     * (js/search-select.js, with Tom Select), and carries the translated message for a search that finds nothing.
     * $hide_label keeps the label for screen readers only, for a menu whose page heading already names it, and adds
     * a little room above the menu in its place.
     */
    public function showDropdownTop($name, $text_to_display, $subtext, $required, $before, $searchable = false, $hide_label = false)
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3<?php echo $hide_label ? ' mt-3' : ''; ?>"><?php // Room under the heading that names the menu, in place of the label ?>
                <label class="form-label<?php echo $hide_label ? ' visually-hidden' : ''; ?>" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <select class="form-select<?php echo $searchable ? ' domainmod-search-select' : ''; ?><?php echo $this->errorClass($name); ?>" name="<?php echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"<?php echo $this->errorAttributes($name); ?><?php if ($searchable) echo ' data-no-results="' . htmlspecialchars(_('No results.'), ENT_QUOTES, 'UTF-8') . '"'; ?>><?php
        return ob_get_clean();
    }

    // The end of a menu. $name, the menu's name, shows its mistake under it.
    public function showDropdownBottom($after, $name = '')
    {
        ob_start(); ?>
                </select>
                <?php if ($name != '') echo $this->errorFeedback($name); ?>
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
                <select id="<?php echo $name; ?>" name="<?php echo $name; ?>[]" class="form-select domainmod-multiple-select<?php echo $this->errorClass($name); ?>" multiple="multiple"<?php echo $this->errorAttributes($name, $name); ?>><?php
        return ob_get_clean();
    }

    // The end of a Tom Select menu. $name, the menu's name, shows its mistake under it.
    public function showMultipleSelectBottom($after, $name = '')
    {
        ob_start(); ?>
                </select>
                <?php if ($name != '') echo $this->errorFeedback($name, $name); ?>
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
                      <input name="<?php echo $name; ?>" class="form-check-input<?php echo $this->errorClass($name); ?>" type="checkbox" id="<?php echo $name; ?>" value="<?php echo $value; ?>"<?php if ($value == $to_compare) echo " checked" ?><?php echo $this->errorAttributes($name, $name); ?>>
                      <label for="<?php echo $name; ?>" class="form-check-label"><?php echo $text_to_display; ?></label>
                        <?php echo $this->errorFeedback($name, $name); ?>
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
                <input class="form-check-input<?php echo $this->errorClass($name); ?>" type="checkbox" role="switch" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="1" aria-label="<?php echo htmlspecialchars(strip_tags($text_to_display), ENT_QUOTES, 'UTF-8'); ?>"<?php if ($value == '1') echo ' checked'; ?><?php echo $this->errorAttributes($name, $name); ?>>
                <?php echo $this->errorFeedback($name, $name); ?>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

} //@formatter:on

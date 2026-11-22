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

    // The fields whose mistakes have been shown under them (see errorFeedback()), so showFormBottom() can show the rest
    public static $field_errors_shown = array();

    /*
     * Records a mistake in a field ($names is one field name, or several when one message covers more than one field,
     * such as two blank passwords), so the field shows it under itself. Each message shows once, under its field, so the
     * page's error message at the top gets one line saying how many fields are marked ("Fix the 3 fields marked
     * below."), kept first and updated as more fields are added. Messages that aren't about a field stay in the top
     * message after it. $replace sets the top message to just the summary, for the pages that replace it rather than
     * add to it.
     */
    public function addFieldError($names, $message, $replace = false)
    {
        foreach ((array) $names as $name) {
            self::$field_errors[$name][] = $message;
        }

        $count = count(self::$field_errors);
        $summary = sprintf(ngettext('Fix the field marked below.', 'Fix the %s fields marked below.', $count), number_format($count)) . '<BR>';

        if ($replace) {
            $_SESSION['s_message_danger'] = $summary;
        } else {
            // The earlier summary comes out wherever it is, and the new one goes first. The last one is kept in the
            // session alongside the message, so one left over from an earlier request comes out too.
            $current = $_SESSION['s_message_danger'] ?? '';
            $previous = $_SESSION['s_field_error_summary'] ?? '';
            if ($previous !== '') $current = str_replace($previous, '', $current);
            $_SESSION['s_message_danger'] = $summary . $current;
        }
        $_SESSION['s_field_error_summary'] = $summary;
    }

    /*
     * A field's dir attribute, so values that are always written left to right (domains, URLs, email and IP
     * addresses, DNS servers, user names and passwords, API keys, ports, and dates) type and read left to right on a
     * right-to-left page. $dir ('ltr', 'rtl', or 'auto') is the caller's choice, and when it's empty the field's name
     * decides, so every form gets it without changes. Nothing is added for other fields, which follow the page.
     */
    public function dirAttribute($name, $dir = '', $is_password = false)
    {
        // A custom field is posted as custom_fields[field_name] (see CustomField::inputName()), and is judged by its
        // field name, as it was when it was posted as new_field_name
        if (preg_match('/^custom_fields\[(.+)\]$/', $name, $parts)) $name = 'new_' . $parts[1];

        if ($dir === '' && ($is_password || preg_match('/(domain|_url$|email|_ip[0-9]*$|^new_ip|rdns|dns[0-9]+$|username|password|api_|account_id|reseller_id|_port$|smtp_server|_tld$|_hash$|^datepick$|^daterange$)/', $name))) {
            $dir = 'ltr';
        }
        return in_array($dir, array('ltr', 'rtl', 'auto'), true) ? ' dir="' . $dir . '"' : '';
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

        self::$field_errors_shown[$name] = true;
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

    /*
     * The end of a form. The top of the page only says how many fields have mistakes (see addFieldError()), so any
     * mistake whose field wasn't drawn with its message (such as a menu left out because it has nothing to choose
     * from) is spelled out here instead, at the bottom of the form.
     */
    public function showFormBottom($after)
    {
        $unshown = array();
        foreach (self::$field_errors as $name => $messages) {
            if (empty(self::$field_errors_shown[$name])) {
                foreach ($messages as $message) {
                    $unshown[] = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8', false);
                }
                // Shown now, so a second form on the page doesn't repeat it
                self::$field_errors_shown[$name] = true;
            }
        }
        $unshown = array_unique($unshown);

        ob_start();
                if ($unshown) { ?>
                <?php // The same slim alert as the page messages (System::slimMessage()), read once by js/messages.js ?>
                <div class="alert alert-danger d-flex align-items-start gap-2 py-2 mt-3 domainmod-message"><i class="bi bi-exclamation-octagon-fill domainmod-message-icon" aria-hidden="true"></i><div class="domainmod-message-text"><?php echo implode('<br>', $unshown); ?></div></div><?php
                } ?>
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
     * under it, and the first such field on the page gets the focus instead. Set $is_password to '1' for a password field
     * marked as a new password, so browsers don't fill in the user's saved DomainMOD sign-in, or to 'current' for the
     * user's current password, which browsers can fill in. An empty $maxlength leaves the length
     * open, and $below is HTML shown under the field, inside its grid item (such as showSavedPassword()'s Remove box).
     */
    public function showInputText($name, $text_to_display, $subtext, $value, $maxlength, $is_password, $required, $before, $after, $autofocus = '', $dir = '', $below = '')
    {
        $is_secret = $is_password == '1' || $is_password === 'current';
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <input type="<?php if ($is_secret) { echo 'password'; } else { echo 'text'; } ?>" autocomplete="<?php if ($is_password === 'current') { echo 'current-password'; } elseif ($is_secret) { echo 'new-password'; } else { echo 'on'; } ?>" class="form-control<?php echo $this->errorClass($name); ?>" placeholder="<?php echo $text_to_display; ?>" name="<?php echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"<?php echo $this->dirAttribute($name, $dir, $is_secret); ?>
                    value="<?php echo htmlentities($value, ENT_QUOTES, 'UTF-8'); ?>" <?php if ((string) $maxlength !== '') echo ' maxlength="' . $maxlength . '"'; ?><?php echo $this->errorAttributes($name); ?><?php if ($autofocus == '1' && !System::$error_shown && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') echo ' autofocus'; ?>>
                <?php echo $this->errorFeedback($name); ?><?php echo $below; ?>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    /*
     * A password field for a secret that's stored, such as the SMTP password or a registrar account's API key. The stored
     * value never goes into the page: the field is always empty. When a value is stored, a note under the label says
     * leaving the field empty keeps it, and a "Remove the saved value" box under the field (named $name . '_remove')
     * blanks it instead. $remove_checked ticks the box again when the form is shown after a failed submit, and $subtext
     * is the field's own help, shown before the note. The page saves what savedSecretToStore() returns.
     */
    public function showSavedPassword($name, $text_to_display, $has_saved_password, $maxlength, $remove_checked = false, $subtext = '')
    {
        $below = '';

        if ($has_saved_password) {

            $keep_note = _('Leave this empty to keep the saved value.');
            $subtext = $subtext !== '' ? $subtext . ' ' . $keep_note : $keep_note;

            $remove_name = $name . '_remove';
            $remove_id = $this->fieldId($remove_name);
            $below = '<div class="form-check mt-2">'
                . '<input class="form-check-input" type="checkbox" name="' . $remove_name . '" id="' . $remove_id . '" value="1"' . ($remove_checked ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="' . $remove_id . '">' . _('Remove the saved value') . '</label>'
                . '</div>';

        }

        return $this->showInputText($name, $text_to_display, $subtext, '', $maxlength, '1', '', '', '', '', '', $below);
    }

    /*
     * What a page saves for a saved-secret field (showSavedPassword()): the typed value when there is one, an empty
     * value when only its Remove box is ticked, or null to leave the saved value as it is (nothing typed, box not
     * ticked). A typed value wins over the box, so ticking it and typing a new value saves the new value.
     */
    public static function savedSecretToStore($typed, $remove)
    {
        if ((string) $typed !== '') return (string) $typed;
        return $remove ? '' : null;
    }

    /*
     * A text area with its label. $autofocus ('1') and mistakes recorded with addFieldError() work as in showInputText().
     * A browser drops one line break straight after <textarea>, so one is written there before the value, and a value
     * that starts with a blank line keeps it when the form is saved again.
     */
    public function showInputTextarea($name, $text_to_display, $subtext, $value, $required, $before, $after, $autofocus = '', $dir = '')
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3">
                <label class="form-label" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <textarea class="form-control<?php echo $this->errorClass($name); ?>" placeholder="<?php echo $text_to_display; ?>" name="<?php
                    echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"<?php echo $this->dirAttribute($name, $dir, false); ?><?php echo $this->errorAttributes($name); ?><?php if ($autofocus == '1' && !System::$error_shown && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') echo ' autofocus'; ?>><?php echo "\n" . htmlentities($value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php echo $this->errorFeedback($name); ?>
            </div><?php
            echo $after;
        return ob_get_clean();
    }

    /*
     * The Bulk Updater's domain picker (js/bulk-domain-picker.js), for the domain list textarea named $name: a search
     * box that finds domains as you type ($search_url, domains/name-search.php) and adds the one picked to the list. The
     * box has no name, so it's never submitted, and the list stays the one field the form sends. It's hidden until the
     * script has set it up, so without JavaScript the page is just the list. The messages the script shows and reads
     * out are written here, translated.
     */
    public function showDomainPicker($name, $label, $help, $search_url)
    {
        $id = $this->fieldId($name) . '-picker';
        $data = array(
            'list' => $this->fieldId($name),
            'search-url' => $search_url,
            // A result as a screen reader reads it: its name, then its status
            'option' => _('%1$s, %2$s'),
            'searching' => _('Searching…'),
            'no-results' => _('No matching domains.'),
            'cut-off' => _('This search matches too many domains to check. Keep typing to narrow it.'),
            'added' => _('Added %s to the list.'),
            'already' => _('%s is already on the list.'),
            'signin' => _('Your session has ended. Please sign in again.'),
            'error' => _("That didn't work. Please reload the page and try again."),
        );
        $attributes = '';
        foreach ($data as $key => $value) {
            $attributes .= ' data-' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        ob_start(); ?>
            <div class="mb-3 domainmod-domain-picker-field" hidden>
                <label class="form-label" for="<?php echo $id; ?>" id="<?php echo $id; ?>-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <div class="form-text mt-0 mb-1" id="<?php echo $id; ?>-help"><?php echo htmlspecialchars($help, ENT_QUOTES, 'UTF-8'); ?></div>
                <select class="form-select domainmod-domain-picker" id="<?php echo $id; ?>" aria-describedby="<?php echo $id; ?>-help"<?php echo $attributes; ?>></select>
                <div class="domainmod-domain-picker-more" id="<?php echo $id; ?>-more" hidden><?php
                    echo htmlspecialchars(_('More domains match. Keep typing to narrow the list.'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div><?php
        return ob_get_clean();
    }

    /*
     * How many domains are on the Bulk Updater's list (the textarea named $name), under it, kept up to date by
     * js/bulk-domain-picker.js as lines are picked, typed, or pasted. One per line that isn't blank, before the server
     * removes duplicates, so it's a guide. Hidden until the script fills it in.
     */
    public function showDomainListCount($name)
    {
        ob_start(); ?>
            <div class="form-text domainmod-domain-list-count" data-list="<?php echo $this->fieldId($name); ?>" hidden><?php
                echo htmlspecialchars(_('Domains on the list:'), ENT_QUOTES, 'UTF-8'); ?> <span class="domainmod-domain-list-number">0</span></div><?php
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
     * a little room above the menu in its place. $data adds data-* attributes to the menu (name => value).
     */
    public function showDropdownTop($name, $text_to_display, $subtext, $required, $before, $searchable = false, $hide_label = false, array $data = array())
    {
        ob_start();
            echo $before; ?>
            <div class="mb-3<?php echo $hide_label ? ' mt-3' : ''; ?>"><?php // Room under the heading that names the menu, in place of the label ?>
                <label class="form-label<?php echo $hide_label ? ' visually-hidden' : ''; ?>" for="<?php echo $this->fieldId($name); ?>"><?php echo $text_to_display; ?><?php if ($required == '1') { ?><?php $layout = new Layout(); echo $layout->highlightText('red', '*'); ?><?php } ?>
                <?php if ($subtext != '') echo '<BR><span class="domainmod-css-normal-font-weight">' . $subtext . '</span><BR>'; ?></label>
                <select class="form-select<?php echo $searchable ? ' domainmod-search-select' : ''; ?><?php echo $this->errorClass($name); ?>" name="<?php echo $name; ?>" id="<?php echo $this->fieldId($name); ?>"<?php echo $this->errorAttributes($name); ?><?php echo $this->dataAttributes($data); ?><?php if ($searchable) echo ' data-no-results="' . htmlspecialchars(_('No results.'), ENT_QUOTES, 'UTF-8') . '"'; ?>><?php
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

    // Each option takes its direction from its own text (dir="auto"), so in a right-to-left language a long
    // left-to-right name, such as a registrar account, is cut at its end in the closed menu rather than its start.
    // $data adds data-* attributes to the option (name => value).
    public function showDropdownOption($value, $text_to_display, $to_compare, array $data = array())
    {
        ob_start(); ?>
            <option value="<?php echo $value ?>" dir="auto"<?php echo $this->dataAttributes($data); ?><?php if ($value == $to_compare) echo " selected" ?>><?php echo $text_to_display; ?></option><?php
        return ob_get_clean();
    }

    // data-* attributes from a name => value list, each value escaped. A name that isn't lowercase letters, digits, and
    // hyphens is left out, so it can't add another attribute.
    protected function dataAttributes(array $data)
    {
        $attributes = '';
        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-z0-9-]+$/D', (string) $key)) continue;
            $attributes .= ' data-' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $attributes;
    }

    /*
     * The domain pages' optional, searchable Hosting Service menu: "None" (0) first, then $services (id => label, from
     * Hosting::serviceMenu()), with $selected chosen. $ip_menu names the domain's IP Address menu on the same form, and
     * $service_ips gives each service's IP address (service id => IP address id, from Hosting::serviceIps()), so
     * js/hosting-service-ip.js can fill in the domain's IP Address from the service chosen. $keep_saved_ip is for a
     * domain that's already saved (Edit Domain): the IP address it was saved with is never replaced, only the placeholder
     * or one the script filled in. A form shown again after a failed save passes no $ip_menu, so nothing is filled in.
     */
    public function showHostingServiceMenu($name, $selected, array $services, $before = '', $after = '', $ip_menu = '', array $service_ips = array(), $keep_saved_ip = false)
    {
        $data = $ip_menu !== '' ? array('ip-menu' => $ip_menu) : array();
        if ($ip_menu !== '' && $keep_saved_ip) $data['ip-keep-saved'] = '1';
        $html = $this->showDropdownTop($name, _('Hosting Service'), '', '', $before, true, false, $data);
        $html .= $this->showDropdownOption(0, _('None'), (int) $selected);
        foreach ($services as $service_id => $label) {
            $html .= $this->showDropdownOption((int) $service_id, $label, (int) $selected,
                isset($service_ips[$service_id]) ? array('ip-id' => (int) $service_ips[$service_id]) : array());
        }
        return $html . $this->showDropdownBottom($after, $name);
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
            <div class="mb-3"><strong><?php echo $text_to_display; ?></strong><BR><?php if ($subtext != '') echo $subtext . '<BR>'; ?><?php
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

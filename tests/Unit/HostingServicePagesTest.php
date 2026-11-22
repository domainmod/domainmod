<?php
/**
 * /tests/Unit/HostingServicePagesTest.php
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
namespace DomainMOD\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The hosting service add and edit pages aren't classes, so these tests read their source: the optional IP address
 * (None, a malformed value refused before the lock, the menu always drawn, and no asset check for None), the Hostname
 * in place of the Server, Username, and Main Domain, the Fee card, and the edit page's Renews On Day choice
 */
class HostingServicePagesTest extends TestCase
{
    private function source($path)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $this->assertNotFalse($source, $path);
        return $source;
    }

    public static function pages()
    {
        return array('add' => array('hosting/add.php'), 'edit' => array('hosting/edit.php'));
    }

    // Username and Main Domain are gone, and the Server is now the Hostname
    #[DataProvider('pages')]
    public function testTheServiceHasAHostnameAndNoUsernameOrMainDomain($path)
    {
        $source = $this->source($path);
        foreach (array('new_username', 'new_server', 'new_domain_id', 'domain_id', '`server`', 'username =', "_('Main Domain')", "_('Server')") as $gone) {
            $this->assertStringNotContainsString($gone, $source, $path . ': ' . $gone);
        }
        // The edit page reads domains only for its Domains card and the delete, by their service
        if ($path === 'hosting/add.php') $this->assertStringNotContainsString('FROM domains', $source);
        $this->assertSame(0, preg_match('/FROM domains\s+WHERE (?!service_id = :hsid)/', $source), $path);
        $this->assertStringContainsString("\$form->showInputText('new_hostname', _('Hostname') . ' (255)', _('Such as web-01.example.com')", $source);
        $this->assertStringContainsString(":new_hostname", $source);
        $this->assertStringContainsString("formSection(_('Where It Runs'), 'where-it-runs'", $source);
        $this->assertStringContainsString("_('The owner comes from the account.')", $source);
    }

    // The IP address is read with -1 for malformed input, refused with a field error before the lock, and checked under
    // the lock only when it isn't None
    #[DataProvider('pages')]
    public function testTheIpAddressIsOptional($path)
    {
        $source = $this->source($path);
        $this->assertStringContainsString("\$new_ip_id = DomainMOD\\Hosting::postedInt(\$_POST['new_ip_id'] ?? null, -1);", $source);
        $condition = strpos($source, '$new_ip_id >= 0 && $new_cat_id !== 0');
        $this->assertNotFalse($condition, $path);
        $this->assertLessThan(strpos($source, 'Hosting::lock($pdo, DomainMOD\\Hosting::PAGE_WAIT)', $condition), $condition);
        $this->assertStringContainsString("if (\$new_ip_id < 0) {\n            \$form->addFieldError('new_ip_id',", $source);
        $this->assertStringContainsString("if (\$new_ip_id === 0) unset(\$asset_checks['new_ip_id']);", $source);
        $this->assertStringNotContainsString("_('Choose the IP Address')", $source);
    }

    // The IP menu, with None first, is always drawn (even with no IP addresses at all), so a service with None saves
    // after the last IP address is deleted
    #[DataProvider('pages')]
    public function testTheIpMenuIsAlwaysDrawnWithNoneFirst($path)
    {
        $source = $this->source($path);
        $query = strpos($source, 'FROM ip_addresses');
        $top = strpos($source, "echo \$form->showDropdownTop('new_ip_id', _('IP Address'), '', '', '');", $query);
        $this->assertNotFalse($top, $path);
        $between = substr($source, $query, $top - $query);
        $this->assertStringNotContainsString('if ($result)', $between, $path);
        $this->assertMatchesRegularExpression("/showDropdownTop\\('new_ip_id'[^\\n]*\\n[^\\n]*TRANSLATORS[^\\n]*\\n\\s*echo \\\$form->showDropdownOption\\('0', _\\('None'\\)/", $source);
    }

    // Adding never fails or hides a service for want of a fee: the add page's Fee card says so before saving
    public function testTheAddPageExplainsTheFeeBeforeSaving()
    {
        $source = $this->source('hosting/add.php');
        $this->assertStringContainsString("formSection(_('Fee'), 'fee'", $source);
        $this->assertStringContainsString('the service is saved without a cost until you add one.', $source);
        $this->assertStringContainsString('->addHostingFeeUrl($fee_link)', $source);
    }

    // The edit page offers "Same as the Renewal Date" (0) as the add page does, accepts it, carries the choice the form
    // opened with in a hidden field (posted back unchanged after a failed Save), and saves through
    // Hosting::renewalDayToSave()
    public function testTheEditPageRenewsOnDayMatchesTheAddPage()
    {
        $source = $this->source('hosting/edit.php');
        $this->assertStringContainsString("echo \$form->showDropdownOption('0', _('Same as the Renewal Date'), \$new_renewal_day);", $source);
        $this->assertStringContainsString('$day_valid = $new_renewal_day >= 0 && $new_renewal_day <= 31;', $source);
        $this->assertStringContainsString("\$renewal_day_opened = DomainMOD\\Hosting::postedInt(\$_POST['renewal_day_opened'] ?? null, -1);", $source);
        $this->assertStringContainsString("echo \$form->showInputHidden('renewal_day_opened', (string) \$renewal_day_opened);", $source);
        $this->assertStringContainsString('DomainMOD\\Hosting::renewalDayToSave((string) $stored_record->expiry_date,', $source);
        // Only a form opened fresh works the choice out from the stored row, so a failed Save keeps the posted one
        $get = strpos($source, "if (\$_SERVER['REQUEST_METHOD'] != 'POST') {\n\n        \$new_name = \$saved_record->name;");
        $this->assertNotFalse($get);
        $this->assertGreaterThan($get, strpos($source, '$renewal_day_opened = $new_renewal_day;'));
        // Set only from the post and, on a form opened fresh, from the stored row
        $this->assertSame(2, substr_count($source, '$renewal_day_opened = '));
        $this->assertStringContainsString("_('If a shorter month moves the renewal date earlier, the next renewal goes back to the day chosen here.')", $source);
        $this->assertStringContainsString("_('If a shorter month moves the renewal date earlier, the next renewal goes back to the day chosen here.')", $this->source('hosting/add.php'));
    }

    // The Change History card sits in the same width wrapper as the cards above it
    public function testTheEditPageHistoryCardKeepsTheFormsWidth()
    {
        $source = $this->source('hosting/edit.php');
        $this->assertStringContainsString("echo '<div class=\"domainmod-form\">' . DomainMOD\\ChangeHistoryListColumns::editSection(\$pdo, 'hosting', \$hsid) . '</div>';", $source);
    }

    /*
     * The edit page's Domains card: the domains on the service, read-only and outside the forms (after the main form,
     * which holds the Fee card), sorted by name with the first 100 shown, each linked with its expiry date and status,
     * the total in the heading, and past 100, a link to all of them (every status) in the Domains list
     */
    public function testTheEditPageListsTheServicesDomains()
    {
        $source = $this->source('hosting/edit.php');
        $card = strpos($source, "formSection(sprintf(_('Domains (%s)'), number_format(\$service_domain_count)), 'domains'");
        $this->assertNotFalse($card);
        $this->assertGreaterThan(strpos($source, "formSection(_('Fee'), 'fee'"), $card);
        $this->assertGreaterThan(strpos($source, "echo \$form->showSubmitButton(_('Save'), '', '');\n    echo \$form->showFormBottom('');"), $card);
        $this->assertLessThan(strpos($source, 'echo $renew_card(false);'), $card);
        $this->assertStringContainsString("\$domains_card_limit = 100;", $source);
        $this->assertStringContainsString("WHERE service_id = :hsid\n            ORDER BY domain ASC, id ASC\n            LIMIT \" . \$domains_card_limit", $source);
        $this->assertStringContainsString('<a href="../domains/edit.php?did=<?php echo (int) $service_domain->id; ?>">', $source);
        $this->assertStringContainsString('$layout->ltr($service_domain->expiry_date)', $source);
        $this->assertStringContainsString("if (\$service_domain_count > \$domains_card_limit) {", $source);
        $this->assertStringContainsString("'<p class=\"domainmod-span-all mb-0\"><a href=\"../domains/index.php?hsid=' . (int) \$hsid . '&amp;is_active=ALL\">'", $source);
        $this->assertStringContainsString("_('Open these domains in the Domains list')", $source);
        $this->assertStringContainsString("_(\"No domains use this service. Choose it on a domain's edit page, or use the Bulk Updater's Change Hosting Service.\")", $source);
    }

    /*
     * The service delete keeps its domains: under the hosting lock and in its transaction, the custom field list is
     * read in share mode, then the domains are locked, recorded, and unlinked (HostingServiceDelete::unlinkDomains()),
     * then the service row is locked and deleted, all inside the deadlock retry, with the count returned by the attempt
     * (so a retry never counts twice) and an ngettext message for the domains kept
     */
    public function testTheDeleteKeepsTheDomainsInTheChangeHistorysLockOrder()
    {
        $source = $this->source('hosting/edit.php');
        $start = strpos($source, 'if ($hosting_available && $del === 1) {');
        $this->assertNotFalse($start);
        $delete = substr($source, $start, strpos($source, '// The service as it\'s saved', $start) - $start);

        $order = array('Hosting::lock($pdo, DomainMOD\\Hosting::PAGE_WAIT)', '$service_delete->withDeadlockRetry(function ()',
            '$pdo->beginTransaction();', '$lock_custom_fields();', 'if (!$find_service(false)) {',
            '$domains_kept = $service_delete->unlinkDomains($hsid, $history_op);', '$delete_record = $find_service(true);',
            "\$history_op->deleting('hosting', array(\$hsid));", 'DELETE FROM hosting_services', 'DELETE FROM hosting_service_field_data',
            '$pdo->commit();', "return array('name' => \$delete_record->name, 'domains_kept' => \$domains_kept);");
        $at = 0;
        foreach ($order as $step) {
            $found = strpos($delete, $step, $at);
            $this->assertNotFalse($found, $step);
            $at = $found + 1;
        }
        $this->assertStringNotContainsString('UPDATE domains', $delete);
        $this->assertStringContainsString("ngettext('%s domain was kept without a hosting service.',\n                '%s domains were kept without a hosting service.', \$deleted['domains_kept'])", $delete);
        $this->assertStringContainsString("number_format(\$deleted['domains_kept'])", $delete);
        $this->assertStringContainsString("_('Any linked domains are kept without a hosting service.')", $source);
    }

    /*
     * The Add Service form starts on the default hosting account when it still exists, whichever provider it's at, then
     * the first account at the default provider, then none
     */
    public function testTheAddPageStartsOnTheDefaultHostingAccount()
    {
        $source = $this->source('hosting/add.php');
        $loop = "        foreach (\$provider_accounts as \$row) {

            if (\$default_account !== 0 && (int) \$row->id === \$default_account) {
                \$account_to_compare = \$default_account;
                break;
            }
            if (\$account_to_compare === 0 && (int) \$row->hosting_id === \$default_host) \$account_to_compare = (int) \$row->id;

        }";
        $this->assertStringContainsString($loop, $source);
        $this->assertStringNotContainsString('if ((int) $row->hosting_id !== $default_host) continue;', $source);
    }

    /*
     * Mark Renewed is the page's one renew action (no "Renew One Term"): read-only users are refused before
     * HostingRenewal::markRenewed() runs, the bootstrap checks the form's security token (every signed-in POST, see
     * _includes/debug.inc.php, which the page loads), and a renewal refreshes the missing fee warning
     */
    public function testMarkRenewedIsCheckedAndRecorded()
    {
        $source = $this->source('hosting/edit.php');
        $start = strpos($source, "if (\$hosting_available && \$_SERVER['REQUEST_METHOD'] == 'POST' && (\$_POST['renew_hosting'] ?? '') === '1') {");
        $this->assertNotFalse($start);
        $handler = substr($source, $start, strpos($source, "header('Location: ' . \$renew_location);", $start) - $start);

        $read_only = strpos($handler, "\$system->readOnlyCheck(\$_SERVER['HTTP_REFERER'] ?? '');");
        $this->assertNotFalse($read_only);
        $this->assertGreaterThan($read_only, strpos($handler, "(new DomainMOD\\HostingRenewal(\$pdo))->markRenewed(\$hsid, \$form_revision, \$form_insert_time, \$_SESSION['s_user_id']);"));
        $this->assertGreaterThan(strpos($handler, "if (\$renewal['status'] === 'renewed') {"), strpos($handler, '$refresh_missing_fees();'));
        foreach (array("'busy'", "'gone'", "'term'", "'date'") as $status) {
            $this->assertStringContainsString("\$renewal['status'] === " . $status, $handler);
        }
        $this->assertStringContainsString('$_SESSION[\'s_message_danger\'] .= $stale_message', $handler);
        // The write itself (lock, transaction, history) is HostingRenewal's, tested in HostingRenewalTest
        $this->assertStringNotContainsString('UPDATE hosting_services', $handler);
        $this->assertStringContainsString("require_once DIR_INC . '/debug.inc.php';", $source);
        $this->assertStringContainsString("\$csrf->check(", $this->source('_includes/debug.inc.php'));

        $this->assertStringContainsString("\$form->showSubmitButton(_('Mark Renewed'), '', '')", $source);
        $this->assertStringNotContainsString('Renew One Term', $source);
        $this->assertSame(1, substr_count($source, "showInputHidden('renew_hosting', '1')"));
    }

    /*
     * In the Confirm renewal state the renew card is drawn above the main form (which is drawn first, so its field errors
     * stay with its fields), titled Confirm renewal, with the help line, whose sentence about the button is there only
     * when the button is. Otherwise it's at the bottom, titled Renew, for users who aren't read-only.
     */
    public function testTheRenewCardMovesToTheTopToConfirmRenewal()
    {
        $source = $this->source('hosting/edit.php');
        $this->assertStringContainsString("\$renewal_state = DomainMOD\\Hosting::renewalState(\$saved_record->active, \$saved_record->autorenew,\n        \$saved_record->expiry_date, \$saved_record->billing_term, DomainMOD\\Hosting::hostingDate(\$pdo));", $source);
        $this->assertStringContainsString("\$layout->formSection(\$confirm ? _('Confirm renewal') : _('Renew'), 'renew', \$renew_body);", $source);
        $this->assertStringContainsString("_('Auto-renew is on, so the host should have renewed this service on %s.')", $source);
        $this->assertStringContainsString("if (\$can_renew) \$help[] = _(\"Once you've checked that it did, choose Mark Renewed.\");", $source);
        $this->assertStringContainsString('$can_renew = !$read_only && $renew_preview !== \'\';', $source);
        $this->assertStringContainsString("_(\"If it's still unconfirmed on %s, it shows as overdue.\")", $source);
        $this->assertStringContainsString('DomainMOD\\Hosting::CONFIRM_DAYS + 1', $source);
        $this->assertStringContainsString('<div class="domainmod-span-all callout callout-warning mb-0">', $source);
        // The card can't move a date past 9999-12-31, and says so instead of the sentence and the button
        $this->assertStringContainsString("_(\"The renewal date can't be moved forward one term until the hosting service has a valid billing term and renewal date.\")", $source);

        $main = strpos($source, "    ob_start();\n    echo \$form->showFormTop('');");
        $this->assertNotFalse($main);
        $top = strpos($source, "if (\$renewal_state === DomainMOD\\Hosting::STATE_CONFIRM) echo \$renew_card(true);\n    echo \$main_form;");
        $this->assertGreaterThan($main, $top);
        $this->assertGreaterThan(strpos($source, '$main_form = ob_get_clean();'), $top);
        $this->assertStringContainsString("if (\$renewal_state !== DomainMOD\\Hosting::STATE_CONFIRM && !\$read_only) echo \$renew_card(false);", $source);
    }

    // The Auto-Renewal switch's help is drawn whatever the switch's value, on both pages, and says how long a service
    // waits for its renewal to be confirmed
    #[DataProvider('pages')]
    public function testTheAutoRenewHelpIsAlwaysDrawn($path)
    {
        $source = $this->source($path);
        $this->assertStringContainsString("\$autorenew_help = sprintf(ngettext('With auto-renew on, an active service whose renewal date has passed waits %s day for you to confirm the renewal before it shows as overdue.', 'With auto-renew on, an active service whose renewal date has passed waits %s days for you to confirm the renewal before it shows as overdue.', DomainMOD\\Hosting::CONFIRM_DAYS), DomainMOD\\Hosting::CONFIRM_DAYS);", $source);
        $this->assertStringContainsString("echo \$form->showSwitch(_('Auto-Renewal') . '?', \$autorenew_help, 'new_autorenew',", $source);
        $this->assertStringNotContainsString('moves forward automatically', $source);
    }

    // The messages follow the SSL pages' form
    public function testTheMessagesNameTheHostingService()
    {
        $add = $this->source('hosting/add.php');
        $edit = $this->source('hosting/edit.php');
        $this->assertStringContainsString("_('Hosting Service %s added')", $add);
        $this->assertStringContainsString("_('Hosting Service %s updated')", $edit);
        $this->assertStringContainsString("_('Hosting Service %s deleted')", $edit);
        $this->assertStringContainsString("_('Hosting Service %1\$s marked renewed. Its renewal date is now %2\$s.')", $edit);
        $this->assertStringContainsString("_('This hosting service was changed since you opened it. Reload to see the changes.')", $edit);
        foreach (array($add, $edit) as $source) {
            $this->assertDoesNotMatchRegularExpression('/[Hh]osting [Aa]ccount %|Web Hosting|[Pp]lan [Tt]ype/', $source);
        }
    }

    // The list keeps the table search on every page: the pager for when JavaScript is off carries the search the page
    // accepted
    public function testTheListKeepsTheTableSearch()
    {
        $list = $this->source('hosting/index.php');
        $this->assertStringContainsString("\$pager_url = \$list_state_url . (\$accepted_search ? (strpos(\$list_state_url, '?') === false ? '?' : '&')\n            . http_build_query(\$accepted_search) : '');", $list);
        $this->assertStringContainsString('echo $layout->listPager($filtered_rows, $list_start, $list_length, $pager_url,', $list);
    }

    /*
     * The list has no line counting the cancelled services the default status hides (the Status filter shows them), and
     * the no-fee line, when there is one, sits a blank line below the totals
     */
    public function testTheListHasNoHiddenCancelledLine()
    {
        $list = $this->source('hosting/index.php');
        foreach (array('cancelled_line', 'cancelled_count', 'hiddenLine', 'domainmod-list-hidden', "_('Show them')") as $text) {
            $this->assertStringNotContainsString($text, $list);
        }
        $this->assertStringNotContainsString('hiddenCancelled', $this->source('hosting/list-data.php'));
        $this->assertStringNotContainsString('hiddenLine', $this->source('js/server-list.js'));
        $this->assertStringNotContainsString('domainmod-list-hidden', $this->source('css/datatables/hosting-main.css'));
        foreach (array('cancelledCount', 'cancelledText', 'showCancelledUrl') as $method) {
            $this->assertFalse(method_exists(\DomainMOD\HostingList::class, $method), $method);
        }

        // The blank line is drawn only inside the no-fee block, so there's no extra gap without it
        $block = substr($list, strpos($list, 'if ($no_fee_count > 0) {'));
        $block = substr($block, 0, strpos($block, "\n    } ?>"));
        $this->assertStringContainsString('<BR><span class="domainmod-list-no-fee">', $block);
        $this->assertSame(1, substr_count($list, 'domainmod-list-no-fee'));
    }

    // The list's scroll box is boxed in all round with the column dividers' line, and the last row's own line goes
    public function testTheListScrollBoxIsBoxedIn()
    {
        $css = $this->source('css/datatables/hosting-main.css');
        $this->assertStringContainsString(".domainmod-list-scroll:has(> #hosting-main) {\n    border: 1px solid var(--bs-border-color-translucent);\n}", $css);
        $this->assertStringContainsString(".domainmod-list-scroll:has(> #hosting-main) > #hosting-main > tbody > tr:last-child > td {\n    border-block-end: 0;\n}", $css);
    }
} //@formatter:on

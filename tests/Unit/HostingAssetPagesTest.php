<?php
/**
 * /tests/Unit/HostingAssetPagesTest.php
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
 * The Hosting asset pages and fee maintenance aren't classes, so these tests read their source: every write that
 * touches hosting services or fees checks that the hosting tables exist, takes the hosting lock before its transaction
 * and lets go of it in a finally, works out the missing fees warning again, and matches fees by provider, service type,
 * and billing term together. The asset lists' counts, the fee help, the Hosting column choices, and the sidebar are
 * read the same way.
 */
class HostingAssetPagesTest extends TestCase
{
    private function source($path)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $this->assertNotFalse($source, $path);
        return $source;
    }

    // Each page that changes hosting services or fees (or whether any service is missing a fee)
    public static function writers()
    {
        return array(
            'add fee' => array('assets/add/hosting-fee.php'),
            'edit fee' => array('assets/edit/hosting-fee.php'),
            'zero out' => array('assets/zero-out-hosting-fees.php'),
            'hosting account' => array('assets/edit/hosting-provider-account.php'),
            'service type' => array('assets/edit/hosting-type.php'),
            'hosting provider' => array('assets/edit/host.php'),
        );
    }

    #[DataProvider('writers')]
    public function testEveryWriterHoldsTheHostingLockAroundItsTransaction($path)
    {
        $source = $this->source($path);
        $this->assertStringContainsString('Hosting::isAvailable(', $source);
        $lock = strpos($source, 'Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)');
        $this->assertNotFalse($lock, $path);
        $begin = strpos($source, '$pdo->beginTransaction();', $lock);
        $this->assertNotFalse($begin, $path);
        // the lock is let go of in a finally, after the commit or rollback
        $finally = strpos($source, '} finally {', $begin);
        $this->assertNotFalse($finally, $path);
        $this->assertStringContainsString('Hosting::unlock($pdo);', substr($source, $finally, 300), $path);
        // and the missing fees warning is worked out again
        $this->assertStringContainsString("\$_SESSION['s_missing_hosting_fees'] = \$system->checkForRows(\$sql);", $source, $path);
        $this->assertStringContainsString("missingFees('hosting_services')", $source, $path);
    }

    // The fee pages and the fee recalculation match a service's fee by all three keys
    public static function feeMatchers()
    {
        return array(
            'add fee' => array('assets/add/hosting-fee.php'),
            'edit fee' => array('assets/edit/hosting-fee.php'),
            'zero out' => array('assets/zero-out-hosting-fees.php'),
            'maintenance' => array('classes/DomainMOD/Maintenance.php'),
        );
    }

    #[DataProvider('feeMatchers')]
    public function testFeesAreMatchedByProviderServiceTypeAndBillingTerm($path)
    {
        $source = $this->source($path);
        $this->assertMatchesRegularExpression('/hosting_id = :\w+\s+AND (?:hs\.)?type_id = :\w+\s+AND (?:hs\.)?billing_term = :\w+/', $source, $path);
    }

    public function testTheAccountMoveLooksUpEachFeeAgain()
    {
        $source = $this->source('assets/edit/hosting-provider-account.php');
        $this->assertStringContainsString('hf.type_id = hs.type_id', $source);
        $this->assertStringContainsString('hf.billing_term = hs.billing_term', $source);
        $this->assertStringContainsString('hs.fee_id = COALESCE(hf.id, 0)', $source);
        $this->assertStringContainsString('hs.revision = hs.revision + 1', $source);
        // the services are locked before the account's own row
        $services = strpos($source, 'WHERE account_id = :account_id');
        $own_row = strpos($source, "FROM hosting_provider_accounts\n                        WHERE id = :record_id\n                        FOR UPDATE");
        $this->assertNotFalse($own_row);
        $this->assertLessThan($own_row, $services);
    }

    public function testTheFeeDeleteCountsItsServicesWithALockingReadFirst()
    {
        $source = $this->source('assets/edit/hosting-fee.php');
        $count = strpos($source, 'FROM hosting_services FORCE INDEX (fee_id)');
        $delete = strpos($source, 'DELETE FROM hosting_fees');
        $this->assertNotFalse($count);
        $this->assertLessThan($delete, $count);
        $this->assertStringContainsString('FOR UPDATE', substr($source, $count, 120));
        $this->assertStringContainsString('HostingFee::deleteRefusal(', substr($source, $count, $delete - $count));
    }

    public function testRatesAreAddedOnlyAfterTheLockIsLetGoOf()
    {
        foreach (array('assets/add/hosting-fee.php', 'assets/edit/hosting-fee.php', 'assets/zero-out-hosting-fees.php') as $path) {
            $source = $this->source($path);
            $this->assertStringNotContainsString('updateRates(', $source, $path);
            $unlock = strrpos($source, 'Hosting::unlock($pdo);');
            $rates = strpos($source, '$conversion->ensureRates(');
            $this->assertNotFalse($rates, $path);
            $this->assertLessThan($rates, $unlock, $path);
            $this->assertStringContainsString('} catch (Throwable $e) {', substr($source, $rates, 200), $path);
        }
    }

    public function testTheCleanupRunsTheHostingFeeStepOnItsOwn()
    {
        $source = $this->source('classes/DomainMOD/Maintenance.php');
        $cleanup = substr($source, strpos($source, 'public function performCleanup('), 6000);
        $this->assertSame(1, substr_count($cleanup, '$this->updateHostingFees(Hosting::TASK_WAIT'));
        // after the domain and SSL fee steps have committed, never inside their transaction
        $this->assertLessThan(strpos($cleanup, '$this->updateHostingFees('), strpos($cleanup, '$pdo->commit();'));
        $this->assertStringNotContainsString("deleteUnusedFees('hosting_fees'", $source);
        $this->assertStringContainsString("'hosting_warning' => \$this->hosting_fee_warning", $cleanup);
    }

    // The Hosting Providers list counts each provider's accounts and its services that aren't Cancelled, and a provider
    // with an account counts as in use, on the list and on the Assets page's tile
    public function testTheProvidersListCountsAccountsAndServices()
    {
        $source = $this->source('assets/hosting.php');
        $this->assertStringContainsString("FROM hosting_provider_accounts\n    WHERE hosting_id = :hosting_id", $source);
        $this->assertStringContainsString("FROM hosting_services\n    WHERE hosting_id = :hosting_id\n      AND active NOT IN ('0')", $source);
        $this->assertStringContainsString("<th><?php echo _('Accounts'); ?></th>", $source);
        $this->assertStringContainsString("<th><?php echo _('Services'); ?></th>", $source);
        $this->assertStringContainsString('hosting-accounts.php?whid=', $source);
        $this->assertSame(2, substr_count($source, '$total_hosting_provider_accounts >= 1 || $total_hosting_services >= 1'));

        $landing = $this->source('assets/index.php');
        $this->assertStringContainsString('return " WHERE EXISTS (SELECT 1 FROM hosting_provider_accounts AS hpa WHERE hpa.hosting_id = $id)
             OR EXISTS (SELECT 1 FROM hosting_services AS hs WHERE hs.hosting_id = $id AND hs.active NOT IN (\'0\'))";', $landing);
    }

    /*
     * Domains no longer name a hosting provider once hosting is available, so the providers' domain count and the
     * provider delete's domain check are there only while domains.hosting_id is (Hosting::legacyDomainColumn()), each
     * marked as the old-names guard allows (HostingNamesTest). The legacy count is plain text, since the Domains list no longer filters by provider.
     */
    public function testTheProvidersDomainCountsAreOnlyBeforeTheUpdate()
    {
        $marker = '// Legacy: domains.hosting_id before 4.27.0';

        $list = $this->source('assets/hosting.php');
        $this->assertStringContainsString("\$stmt_domain_count = !DomainMOD\\Hosting::legacyDomainColumn(\$pdo) ? null : \$pdo->prepare( " . $marker, $list);
        $this->assertStringNotContainsString('domains/index.php?whid=', $list);
        $this->assertStringContainsString("<td><?php echo \$total_domains >= 1 ? number_format(\$total_domains) : '-'; ?></td>", $list);
        $this->assertSame(1, substr_count($list, "<th><?php echo _('Domains'); ?></th>"));
        $this->assertStringContainsString("if (\$hosting_available) {\n        \$row_contents[] = _('Hosting Accounts');\n        \$row_contents[] = _('Hosting Services');\n    } else {\n        \$row_contents[] = _('Domains');", $list);

        $delete = $this->source('assets/edit/host.php');
        $this->assertSame(1, substr_count($delete, 'FROM domains'));
        $this->assertStringContainsString("if (DomainMOD\\Hosting::legacyDomainColumn(\$pdo)) {\n\n        \$stmt = \$pdo->prepare( " . $marker, $delete);
        $this->assertStringContainsString("_('This removes %s and its fees. It can only be deleted when no hosting services or hosting accounts use it. It can\\'t be undone.')", $delete);

        $landing = $this->source('assets/index.php');
        $this->assertStringContainsString("    if (\$legacy_domain_column) {\n        return \" WHERE EXISTS (SELECT 1 FROM domains AS d\" " . $marker, $landing);
    }

    // The account list's Services count leaves out Cancelled services, as the Hosting Services list's default status
    // does, and its link opens that list filtered by the account with no status, so the numbers match
    public function testTheAccountsListCountsWhatItsLinkOpens()
    {
        $source = $this->source('assets/hosting-accounts.php');
        $this->assertStringContainsString("WHERE account_id = :account_id\n      AND active NOT IN ('0')", $source);
        $this->assertSame(1, preg_match('#href="\.\./hosting/index\.php\?([^"]*)"#', $source, $match));
        $this->assertStringContainsString('hpaid=', $match[1]);
        $this->assertStringNotContainsString('is_active', $match[1]);
        $this->assertStringNotContainsString(' ha.', $source);
    }

    // The fee forms explain the Misc Fee and the Initial Fee
    public function testTheFeeFormsExplainTheMiscAndInitialFees()
    {
        foreach (array('assets/add/hosting-fee.php', 'assets/edit/hosting-fee.php') as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString("_('Initial Fee'), _('For your records. It isn\\'t counted in costs.')", $source, $path);
            $this->assertStringContainsString("_('Misc Fee'), _('Added to every renewal, such as a control panel license.')", $source, $path);
        }
    }

    // A service has no Main Domain any more, so no page reads or offers the dropped display_hosting_domain setting, and
    // the Hosting column choices use the list's short titles
    public function testTheHostingColumnChoicesHaveNoDomain()
    {
        foreach (array('settings/display/index.php', 'classes/DomainMOD/Login.php', 'checks.php', 'admin/users/add.php') as $path) {
            $this->assertStringNotContainsString('display_hosting_domain', $this->source($path), $path);
        }
        $display = $this->source('settings/display/index.php');
        $this->assertSame(8, preg_match_all("/showMultipleSelectOption\(_\('([^']+)'\), '\w+', \\\$new_display_hosting/", $display, $matches));
        $this->assertSame(array('Renewal Date', 'Fee', 'Provider', 'Account', 'Type', 'IP Address', 'Category', 'Owner'), $matches[1]);
    }

    // The sidebar's Hosting items are short, each with its full name for screen readers
    public function testTheSidebarItemsHaveFullNamesForScreenReaders()
    {
        $menu = $this->source('_includes/layout/menu-main.inc.php');
        foreach (array('Providers' => 'Hosting Providers', 'Accounts' => 'Hosting Accounts', 'Types' => 'Hosting Service Types',
                     'Fees' => 'Hosting Fees') as $short => $full) {
            $this->assertStringContainsString("<span aria-hidden=\"true\"><?php echo _('" . $short . "') ?></span>\n                            <span class=\"visually-hidden\"><?php echo _('" . $full . "') ?></span>", $menu, $short);
        }
        $this->assertStringContainsString("<li class=\"nav-header\"><?php echo _('Hosting') ?></li>", $menu);
        $this->assertStringNotContainsString('Web Hosting', $menu);
    }


    /*
     * Each sentence is one translatable message: the zero-out sentence keeps its link inside it (as placeholders), and
     * the fees page's Missing Fees sentence is chosen by the count itself, since it doesn't show the count (a plural form
     * follows a shown number, so ngettext() could pick the one-fee sentence for 21 fees in some languages)
     */
    public function testTheMissingFeeSentencesTranslateWhole()
    {
        $maintenance = $this->source('_includes/layout/table-maintenance.inc.php');
        $this->assertStringContainsString('_("If you don\'t care about tracking Hosting Service fees, %sclick here to set all missing fees to 0%s.")', $maintenance);
        $this->assertStringNotContainsString("_('If you don\\'t care about tracking Hosting Service fees, ')", $maintenance);

        $fees = $this->source('assets/hosting-fees.php');
        $this->assertStringContainsString("echo sprintf(count(\$missing) === 1\n        ? _('Some of your hosting services with %s have no fee yet. The combination of service type and billing term below needs one, so add this fee as soon as possible.')\n        : _('Some of your hosting services with %s have no fee yet. Each combination of service type and billing term below needs one, so add these fees as soon as possible.'),", $fees);
        $this->assertStringNotContainsString('ngettext(\'Some of your hosting services', $fees);
    }
} //@formatter:on

<?php
/**
 * /tests/Unit/SslRefreshTest.php
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

use DomainMOD\Sanitize;
use DomainMOD\SslLookup;
use DomainMOD\SslQueue;
use DomainMOD\SslRefresh;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The SSL certificate edit page's Refresh rules that don't need the database: the status rule, the throttle messages,
 * which host a name means, and the change lines (with the issuer compared in its stored form). The object is built
 * without its constructor, with only the helpers these methods use.
 */
class SslRefreshTest extends TestCase
{

    private function refresh()
    {
        return Instance::withoutConstructor(SslRefresh::class, array(
            'lookup' => Instance::withoutConstructor(SslLookup::class),
            'queue' => Instance::withoutConstructor(SslQueue::class, array('sanitize' => new Sanitize()))));
    }

    private function details(array $values = array())
    {
        return $values + array('host' => 'www.example.com', 'fingerprint' => str_repeat('a', 64), 'issuer' => 'Example CA',
            'names' => array('www.example.com'), 'valid_from' => 0, 'valid_to' => 0, 'expiry_date' => '2027-01-31',
            'trusted' => true, 'reason' => '');
    }

    private function current($expiry_date = '2027-01-31', $active = 1)
    {
        return (object) array('expiry_date' => $expiry_date, 'active' => $active);
    }

    public function testExpiredBecomesActiveWithACertificateWithinItsDates()
    {
        $this->assertSame(1, SslRefresh::newStatus(0, 'ok'));
        $this->assertSame(1, SslRefresh::newStatus('0', 'untrusted'));
        $this->assertSame(1, SslRefresh::newStatus(1, 'ok'));
    }

    public function testActiveBecomesExpiredWithAnExpiredCertificate()
    {
        $this->assertSame(0, SslRefresh::newStatus(1, 'expired'));
        $this->assertSame(0, SslRefresh::newStatus(0, 'expired'));
    }

    public function testPendingStatusesAreLeftAlone()
    {
        foreach (array(3, 4, 5) as $pending) {
            foreach (array('ok', 'untrusted', 'expired') as $code) {
                $this->assertSame($pending, SslRefresh::newStatus($pending, $code));
            }
        }
    }

    public function testTheSameCertificateWaitsThirtySeconds()
    {
        $now = 1000000;

        $this->assertSame('Please wait 20 seconds before refreshing this SSL certificate again.',
            $this->refresh()->waitMessage(7, array(7 => $now - 10), $now - 10, $now));
        $this->assertSame('Please wait 1 second before refreshing this SSL certificate again.',
            $this->refresh()->waitMessage(7, array(7 => $now - 29), $now - 29, $now));
        $this->assertSame('', $this->refresh()->waitMessage(7, array(7 => $now - 30), $now - 30, $now));
    }

    public function testAnyOtherCertificateWaitsFiveSeconds()
    {
        $now = 1000000;

        $this->assertSame('Please wait 3 seconds before refreshing another SSL certificate.',
            $this->refresh()->waitMessage(8, array(7 => $now - 2), $now - 2, $now));
        $this->assertSame('Please wait 1 second before refreshing another SSL certificate.',
            $this->refresh()->waitMessage(8, array(7 => $now - 4), $now - 4, $now));
        $this->assertSame('', $this->refresh()->waitMessage(8, array(7 => $now - 5), $now - 5, $now));
        $this->assertSame('', $this->refresh()->waitMessage(8, array(), 0, $now));
    }

    public function testAHostnameAtOrUnderTheDomainIsLookedUpInItsStoredForm()
    {
        $this->assertSame(array('ok', 'www.example.com'), $this->refresh()->hostCheck('www.example.com.', 'example.com'));
        $this->assertSame(array('ok', 'shop.example.com'), $this->refresh()->hostCheck('Shop.Example.COM', 'example.com'));
        $this->assertSame(array('ok', 'example.com'), $this->refresh()->hostCheck('example.com', 'example.com'));
        $this->assertSame(array('ok', 'www.münchen.de'), $this->refresh()->hostCheck('www.xn--mnchen-3ya.de', 'münchen.de'));
    }

    public function testALabelOrANameOutsideTheDomainIsRefused()
    {
        $this->assertSame(array('label', ''), $this->refresh()->hostCheck('2027 wildcard renewal', 'example.com'));
        $this->assertSame(array('label', ''), $this->refresh()->hostCheck('www', 'example.com'));
        $this->assertSame(array('outside', ''), $this->refresh()->hostCheck('www.example.net', 'example.com'));
        $this->assertSame(array('outside', ''), $this->refresh()->hostCheck('myexample.com', 'example.com'));
    }

    public function testACertificateWithNoDomainAcceptsAnyHostname()
    {
        $this->assertSame(array('ok', 'www.example.com'), $this->refresh()->hostCheck('www.example.com.', ''));
        $this->assertSame(array('ok', 'status.example.org'), $this->refresh()->hostCheck('Status.Example.ORG', ''));
        $this->assertSame(array('ok', 'shop.münchen.de'), $this->refresh()->hostCheck('shop.xn--mnchen-3ya.de', ''));
        // A name that isn't a hostname is still refused
        $this->assertSame(array('label', ''), $this->refresh()->hostCheck('2027 wildcard renewal', ''));
        $this->assertSame(array('label', ''), $this->refresh()->hostCheck('www', ''));
    }

    public function testTheRefusalsNameTheHostnameRule()
    {
        $this->assertSame("Refresh needs this certificate's name to be the hostname it's installed on, such as www.example.com.",
            $this->refresh()->refusalMessage('label', 'Office & Shop', 'example.com'));
        $this->assertSame('This certificate\'s name, <bdi dir="ltr">www.example.net</bdi>, isn\'t part of its domain, <bdi dir="ltr">example.com</bdi>. Link the certificate to the right domain, set its Domain to None, or change its name. Then refresh the certificate.',
            $this->refresh()->refusalMessage('outside', 'www.example.net', 'example.com'));
        $this->assertStringContainsString('<bdi dir="ltr">a&amp;b.example.net</bdi>',
            $this->refresh()->refusalMessage('outside', 'a&b.example.net', 'example.com'));
    }

    public function testANewExpiryDateIsAChange()
    {
        $compared = $this->refresh()->compare($this->current('2026-12-01'), 'ok', $this->details(), 'Example CA');

        $this->assertSame('2027-01-31', $compared['expiry_date']);
        $this->assertSame(array('Expiry date changed from 2026-12-01 to 2027-01-31.'), $compared['changes']);
        $this->assertSame(array('Expiry date changed from 2026-12-01 to 2027-01-31.'), $compared['note_changes']);
    }

    public function testNothingNewGivesNoChanges()
    {
        $compared = $this->refresh()->compare($this->current(), 'ok', $this->details(), 'Example CA');

        $this->assertSame(array(), $compared['changes']);
        $this->assertSame(array(), $compared['note_changes']);
        $this->assertSame(1, $compared['active']);
    }

    public function testAStatusChangeIsListed()
    {
        $compared = $this->refresh()->compare($this->current('2027-01-31', 0), 'untrusted', $this->details(), 'Example CA');
        $this->assertSame(1, $compared['active']);
        $this->assertSame(array('Status changed from Expired to Active.'), $compared['changes']);
        $this->assertSame(array('Status changed from Expired to Active.'), $compared['note_changes']);

        $compared = $this->refresh()->compare($this->current('2025-01-31', 1), 'expired', $this->details(array('expiry_date' => '2025-01-31')), 'Example CA');
        $this->assertSame(0, $compared['active']);
        $this->assertSame(array('Status changed from Active to Expired.'), $compared['changes']);

        $compared = $this->refresh()->compare($this->current('2025-01-31', 3), 'expired', $this->details(array('expiry_date' => '2025-01-31')), 'Example CA');
        $this->assertSame(3, $compared['active']);
        $this->assertSame(array(), $compared['changes']);
    }

    public function testTheIssuerIsComparedInItsStoredForm()
    {
        $details = $this->details(array('issuer' => 'Example & Sons'));

        $this->assertSame(array(), $this->refresh()->compare($this->current(), 'ok', $details, 'Example &amp; Sons')['changes']);
        $this->assertSame(array('The certificate is now issued by Example &amp; Sons.'),
            $this->refresh()->compare($this->current(), 'ok', $details, 'Example CA')['changes']);
        $this->assertSame(array('The certificate is now issued by Example & Sons.'),
            $this->refresh()->compare($this->current(), 'ok', $details, 'Example CA')['note_changes']);
    }

    public function testTheFirstRefreshRecordsTheIssuerWithoutCallingItAChange()
    {
        $compared = $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => 'Other CA')), null);

        $this->assertSame(array(), $compared['changes']);
        $this->assertSame('Other CA', $compared['issuer']);
    }

    public function testAnEmptyEarlierIssuerIsARealValue()
    {
        $this->assertSame(array('The certificate is now issued by Other CA.'),
            $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => 'Other CA')), '')['changes']);
        $this->assertSame(array(), $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => '')), '')['changes']);
    }

    public function testAnIssuerThatIsOnlyWhitespaceNoLongerNamesIt()
    {
        $compared = $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => " \t  ")), 'Example CA');

        $this->assertSame(array('The certificate no longer names its issuer.'), $compared['changes']);
        $this->assertSame(array('The certificate no longer names its issuer.'), $compared['note_changes']);
        $this->assertSame('', $compared['issuer']);
        $this->assertStringNotContainsString('issued by .', implode(' ', $compared['changes']));
    }

    public function testTheIssuerIsEscapedAndKeepsOneFullStop()
    {
        $this->assertSame(array('The certificate is now issued by &lt;b&gt;CA&lt;/b&gt;.'),
            $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => '<b>CA</b>')), 'Example CA')['changes']);
        $this->assertSame(array('The certificate is now issued by Entrust, Inc.'),
            $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => 'Entrust, Inc.')), 'Example CA')['changes']);
        $this->assertSame(array('The certificate is now issued by ..'),
            $this->refresh()->compare($this->current(), 'ok', $this->details(array('issuer' => '.')), 'Example CA')['changes']);
    }

    public function testEveryChangeIsListedTogether()
    {
        $compared = $this->refresh()->compare($this->current('2026-01-31', 0), 'ok', $this->details(array('issuer' => 'Other CA')), 'Example CA');

        $this->assertSame(array('Expiry date changed from 2026-01-31 to 2027-01-31.', 'Status changed from Expired to Active.',
            'The certificate is now issued by Other CA.'), $compared['changes']);
    }

    public function testTheTrustWarningOnlyShowsWhenItApplies()
    {
        $this->assertSame(array('This certificate is self-signed.'), $this->refresh()->trustNotes('untrusted', $this->details(array('reason' => 'self_signed'))));
        $this->assertSame(array("This certificate isn't trusted."), $this->refresh()->trustNotes('untrusted', $this->details(array('reason' => 'not_trusted'))));
        $this->assertSame(array('This certificate has expired.'), $this->refresh()->trustNotes('expired', $this->details(array('reason' => 'expired'))));
        $this->assertSame(array(), $this->refresh()->trustNotes('ok', $this->details()));
    }

    public function testAFailedLookupSaysWhy()
    {
        $this->assertSame("This hostname doesn't resolve.", $this->refresh()->failureMessage('no_dns'));
        $this->assertSame('The lookup had to wait for another connection to the same address. Please try again shortly.', $this->refresh()->failureMessage('deferred'));
        $this->assertSame('The lookup failed.', $this->refresh()->failureMessage('something_new'));
    }

} //@formatter:on

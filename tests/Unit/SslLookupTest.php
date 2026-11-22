<?php
/**
 * /tests/Unit/SslLookupTest.php
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

use DomainMOD\SslLookup;
use DomainMOD\Tests\Support\TestCertificates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * SslLookup without the network: cleaning pasted hosts, name matching, reading certificates made in the test (the DNS
 * names come from the DER, the same path as production), the date checks with the time passed in, the trust check
 * against a test CA, and the address checks through the $test_resolve hook.
 */
class SslLookupTest extends TestCase
{
    private $temp_files = array();

    protected function tearDown(): void
    {
        SslLookup::$test_resolve = null;
        SslLookup::$test_ca_file = null;
        SslLookup::$test_clock = null;
        SslLookup::$test_target = null;
        foreach ($this->temp_files as $file) @unlink($file);
    }

    public static function hostLines()
    {
        return array(
            'hostname' => array('www.example.com', 'www.example.com'),
            'upper case' => array('WWW.Example.COM', 'www.example.com'),
            'https URL with a path' => array('https://www.example.com/path?q=1#top', 'www.example.com'),
            'https URL with :443' => array('https://www.example.com:443/path', 'www.example.com'),
            'scheme in capitals' => array('HTTPS://example.com/', 'example.com'),
            'trailing dot' => array('example.com.', 'example.com'),
            'Unicode' => array('münchen.de', 'münchen.de'),
            'punycode' => array('xn--mnchen-3ya.de', 'münchen.de'),
            'surrounding spaces' => array("  example.com\t", 'example.com'),
            'port 0443' => array('https://example.com:0443/path', ''),
            'port 443.5' => array('https://example.com:443.5/', ''),
            'other port' => array('example.com:8443', ''),
            'empty port' => array('https://example.com:/', ''),
            'http' => array('http://example.com/', ''),
            'ftp' => array('ftp://example.com/', ''),
            'mailto' => array('mailto:someone@example.com', ''),
            'user details' => array('https://user@example.com/', ''),
            'wildcard' => array('*.example.com', ''),
            'IPv4' => array('127.0.0.1', ''),
            'IPv4 with a port' => array('https://10.0.0.1:443/', ''),
            'full-width IPv4' => array('１２７.０.０.１', ''),
            'IPv6' => array('https://[2001:db8::1]/', ''),
            'bare IPv6' => array('::1', ''),
            'single label' => array('localhost', ''),
            'single label with a dot' => array('localhost.', ''),
            'single label URL' => array('https://localhost./', ''),
            'numeric TLD' => array('example.123', ''),
            'space inside' => array('exa mple.com', ''),
            'underscore' => array('my_host.example.com', ''),
            'bare host with a path' => array('example.com/typo', ''),
            'bare host with a query' => array('example.com?x', ''),
            'NUL before the port' => array("https://example.com\0:443/", ''),
            'NUL at the start' => array("\0example.com", ''),
            'invalid UTF-8' => array("exa\xffmple.com", ''),
            'empty' => array('   ', ''));
    }

    #[DataProvider('hostLines')]
    public function testCleanHost($line, $expected)
    {
        $this->assertSame($expected, (new SslLookup())->cleanHost($line));
    }

    public function testNameMatching()
    {
        $lookup = new SslLookup();

        $this->assertTrue($lookup->nameMatches('www.example.com', 'WWW.Example.com'));
        $this->assertTrue($lookup->nameMatches('a.example.com', '*.example.com'));
        $this->assertFalse($lookup->nameMatches('a.b.example.com', '*.example.com'));
        $this->assertFalse($lookup->nameMatches('example.com', '*.example.com'));
        $this->assertFalse($lookup->nameMatches('example.com', '*.com'));
        $this->assertFalse($lookup->nameMatches('a.example.com', 'a*.example.com'));
        $this->assertFalse($lookup->nameMatches('www.example.com', 'example.com'));
        $this->assertFalse($lookup->nameMatches('example.com', ' example.com '));
        $this->assertFalse($lookup->nameMatches('example.com', 'example.com.'));
    }

    public function testCommonNamesOnlyCountWithoutDnsNames()
    {
        $lookup = new SslLookup();

        $this->assertTrue($lookup->hostMatches('example.com', array(), array('example.com')));
        $this->assertFalse($lookup->hostMatches('example.com', array('shop.example.com'), array('example.com')));
        $this->assertFalse($lookup->hostMatches('example.com', null, array('example.com')));
        $this->assertTrue($lookup->hostMatches('b.example.com', array(), array('a.example.com', 'b.example.com')));
    }

    public function testDnsNamesComeFromTheDerNotThePrintableString()
    {
        $made = TestCertificates::make(array('cn' => 'example.com', 'san' => array('DNS.1 = shop.example.com',
            'URI.1 = https://a.example/x, DNS:other.example')));
        $lookup = new SslLookup();
        $parsed = openssl_x509_parse($made['cert']);
        $now = time();

        $this->assertSame(array(true, array('shop.example.com')), $lookup->dnsNames($made['pem']));
        $this->assertSame('within_dates', $lookup->readCertificate($made['pem'], $parsed, 'shop.example.com', $now));
        $this->assertSame('name_mismatch', $lookup->readCertificate($made['pem'], $parsed, 'other.example', $now));
        $this->assertSame('name_mismatch', $lookup->readCertificate($made['pem'], $parsed, 'example.com', $now));
    }

    public function testAMalformedDnsNameFailsTheWholeRead()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('DNS.1 = exa mple.com')));
        $lookup = new SslLookup();

        $this->assertSame(array(false, array()), $lookup->dnsNames($made['pem']));
        $this->assertSame('name_mismatch', $lookup->readCertificate($made['pem'], openssl_x509_parse($made['cert']), 'www.example.com', time()));
    }

    public function testHandBuiltSubjectAltNames()
    {
        $lookup = new SslLookup();

        $this->assertSame(array(true, array('www.example.com', 'shop.example.com')),
            $lookup->dnsNames($this->pemWithNames(array('www.example.com', 'shop.example.com'))));

        // a later name ending in a newline fails the whole read, even after a good one
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com', "shop.example.com\n"))));
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com', ''))));

        // GeneralNames must hold at least one name
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array())));

        // a URI entry is skipped, but a form GeneralName doesn't allow (a constructed [2]) fails the read
        $this->assertSame(array(true, array('www.example.com')), $lookup->dnsNames($this->pemWithNames(array('www.example.com'), $this->der(0x86, 'https://a.example/'))));
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com'), $this->der(0xa2, $this->der(0x16, 'shop.example.com')))));

        // an allowed form with a payload of the wrong shape fails the read too
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com'), $this->der(0x87, "\x01"))));
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com'), $this->der(0xa4, ''))));
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array('www.example.com'), $this->der(0x86, "https://a.example/\n"))));

        // a length in the long form where the short form fits isn't DER
        $long_form = $this->pemWithNames(array(), "\x82\x81\x0f" . 'www.example.com');
        $this->assertSame(array(false, array()), $lookup->dnsNames($long_form));

        // a subjectAltName with no DNS names never lets the common name decide, whatever its other entries hold
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array(), $this->der(0x87, "\x5d\xb8\xd7\x0e"))));
        $this->assertSame(array(false, array()), $lookup->dnsNames($this->pemWithNames(array(), $this->der(0xa4, $this->der(0x05, '')))));
    }

    public function testASubjectAltNameWithoutDnsNamesDoesntUseTheCommonName()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('IP.1 = 93.184.215.14')));

        $this->assertSame('name_mismatch', (new SslLookup())->readCertificate($made['pem'], openssl_x509_parse($made['cert']), 'www.example.com', time()));
    }

    public function testACertificateWithoutDnsNamesUsesItsCommonName()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com'));
        $lookup = new SslLookup();

        $this->assertSame(array(true, array()), $lookup->dnsNames($made['pem']));
        $this->assertSame('within_dates', $lookup->readCertificate($made['pem'], openssl_x509_parse($made['cert']), 'www.example.com', time()));
    }

    public function testADamagedPemNeverFallsBackToTheCommonName()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com'));
        $lookup = new SslLookup();
        $parsed = openssl_x509_parse($made['cert']);

        $this->assertSame('within_dates', $lookup->readCertificate($made['pem'], $parsed, 'www.example.com', time()));

        $damaged = str_replace('-----END CERTIFICATE-----', '', $made['pem']);
        $this->assertSame('name_mismatch', $lookup->readCertificate($damaged, $parsed, 'www.example.com', time()));

        $bad_base64 = preg_replace('/^(-----BEGIN CERTIFICATE-----\s*)(.)/', '$1!', $made['pem']);
        $this->assertSame('name_mismatch', $lookup->readCertificate($bad_base64, $parsed, 'www.example.com', time()));

        $truncated = "-----BEGIN CERTIFICATE-----\n" . base64_encode(substr(base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $made['pem'])), 0, 40)) . "\n-----END CERTIFICATE-----\n";
        $this->assertSame(array(false, array()), $lookup->dnsNames($truncated));
    }

    public function testTheDateChecks()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('DNS.1 = www.example.com'), 'days' => 10));
        $lookup = new SslLookup();
        $parsed = openssl_x509_parse($made['cert']);
        list(, $names) = $lookup->dnsNames($made['pem']);

        $this->assertSame('expired', $lookup->classify($parsed, $names, 'www.example.com', $parsed['validTo_time_t'] + 1));
        $this->assertSame('not_yet_valid', $lookup->classify($parsed, $names, 'www.example.com', $parsed['validFrom_time_t'] - 60));
        $this->assertSame('within_dates', $lookup->classify($parsed, $names, 'www.example.com', $parsed['validFrom_time_t'] + 60));

        // an expired certificate for another name is a name mismatch, never expired
        $this->assertSame('name_mismatch', $lookup->classify($parsed, $names, 'shop.example.com', $parsed['validTo_time_t'] + 1));
    }

    public function testDatesTheDatabaseCantHoldAreABadCertificate()
    {
        $made = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('DNS.1 = www.example.com')));
        $lookup = new SslLookup();
        $parsed = openssl_x509_parse($made['cert']);
        list(, $names) = $lookup->dnsNames($made['pem']);

        // 0500-01-01 00:00:00 UTC, written out since gmmktime() can't give it on 32-bit PHP (this literal is a float
        // there). 32-bit PHP reads the date's text, 64-bit PHP its time.
        $parsed['validFrom_time_t'] = -46388678400;
        $parsed['validFrom'] = '05000101000000Z';

        $this->assertSame('bad_certificate', $lookup->classify($parsed, $names, 'www.example.com', time()));
        $this->assertSame('name_mismatch', $lookup->classify($parsed, $names, 'shop.example.com', time()));

        // a start after the expiry, at fixed times (32-bit PHP reads the text, 64-bit PHP the times)
        $backwards = openssl_x509_parse($made['cert']);
        $backwards['validTo_time_t'] = 1797850791;
        $backwards['validTo'] = '261221105951Z';
        $backwards['validFrom_time_t'] = 1797850791 + 86400;
        $backwards['validFrom'] = '261222105951Z';
        $this->assertSame('bad_certificate', $lookup->classify($backwards, $names, 'www.example.com', 1797850791 + 3600));

        // PHP gives -1 for a date it can't convert (and on 32-bit PHP, the date's own text is unreadable too)
        $unconverted = openssl_x509_parse($made['cert']);
        $unconverted['validFrom_time_t'] = -1;
        $unconverted['validFrom'] = 'not a date';
        $this->assertSame('bad_certificate', $lookup->classify($unconverted, $names, 'www.example.com', time()));

        $unconverted = openssl_x509_parse($made['cert']);
        $unconverted['validTo_time_t'] = -1;
        $unconverted['validTo'] = 'not a date';
        $this->assertSame('bad_certificate', $lookup->classify($unconverted, $names, 'www.example.com', time()));
    }

    // 32-bit PHP (the armhf Docker image) gives -1, or a time cut to 32 bits, for a certificate date past 2038, so the
    // date comes from its text
    public function testACertificateThatEndsAfter2038IsWithinItsDates()
    {
        $made = TestCertificates::make(array('cn' => 'self.example.com', 'san' => array('DNS.1 = self.example.com'), 'days' => 9000));
        $lookup = new SslLookup();
        $parsed = openssl_x509_parse($made['cert']);
        list(, $names) = $lookup->dnsNames($made['pem']);
        $valid_to = $lookup->asn1Time($parsed['validTo']);

        $this->assertGreaterThan(2147483647, $valid_to);
        $this->assertSame('within_dates', $lookup->classify($parsed, $names, 'self.example.com', time()));

        list($code, $details) = $lookup->resultFromCertificate($made['cert'], array($made['cert']), 'self.example.com', time());
        $this->assertSame('untrusted', $code);
        $this->assertGreaterThan(2147483647, $details['valid_to']);
        if (PHP_INT_SIZE >= 8) {
            // 64-bit PHP's own time, which a time zone's daylight saving gap can move, so it's the expected date there
            $this->assertSame(gmdate('Y-m-d', $parsed['validTo_time_t']), $details['expiry_date']);
        } else {
            // UTCTime (YYMMDDHHMMSSZ) until 2049, GeneralizedTime (YYYYMMDDHHMMSSZ) from 2050
            $text = $parsed['validTo'];
            $year = strlen($text) === 13 ? '20' . substr($text, 0, 2) : substr($text, 0, 4);
            $this->assertSame($year . '-' . substr($text, -11, 2) . '-' . substr($text, -9, 2), $details['expiry_date']);
        }
    }

    // 32-bit PHP gives -1, or the time cut to 32 bits where the C library's time is 64-bit, for a date past 2038
    public function testOnlyThirtyTwoBitPhpReadsTheDateText()
    {
        $lookup = new SslLookup();
        foreach (array(-1, -1722108505) as $php_time) {
            $parsed = array('validTo_time_t' => $php_time, 'validTo' => '20510713105951Z');
            $this->assertSame(PHP_INT_SIZE >= 8 ? $php_time : 2572858791, $lookup->certificateTime($parsed, 'validTo'));
        }

        // Text it can't read leaves PHP's own value
        $parsed = array('validTo_time_t' => 1797850791, 'validTo' => '261221105951.5Z');
        $this->assertSame(1797850791, $lookup->certificateTime($parsed, 'validTo'));
        $this->assertNull($lookup->certificateTime(array(), 'validTo'));
    }

    public static function asn1Times()
    {
        // The literals past 2^31 are floats on 32-bit PHP, as asn1Time() gives there
        return array(
            'UTCTime in the 2000s' => array('491231235959Z', 2524607999),
            'UTCTime in the 1900s' => array('500101000000Z', -631152000),
            'GeneralizedTime past 2038' => array('20510713105951Z', 2572858791),
            'the start of Unix time' => array('19700101000000Z', 0),
            'the latest stored time' => array('99991231235959Z', 253402300799),
            'before the earliest stored time' => array('05000101000000Z', -46388678400),
            'a day that doesn\'t exist' => array('20510230000000Z', false),
            'an hour that doesn\'t exist' => array('20510713245951Z', false),
            'a digit short' => array('2051071310595Z', false),
            'an offset instead of Z' => array('20510713105951+0000', false),
            'not text' => array(array('20510713105951Z'), false));
    }

    #[DataProvider('asn1Times')]
    public function testAsn1TimesAreReadWithoutA32BitInt($text, $expected)
    {
        $this->assertSame($expected, (new SslLookup())->asn1Time($text));
    }

    public function testUtcDateTakesATimePastThe32BitIntRange()
    {
        $this->assertSame('9999-12-31 23:59:59', SslLookup::utcDate('Y-m-d H:i:s', 253402300799.0));
        $this->assertSame('2051-07-13', SslLookup::utcDate('Y-m-d', 2572858791.0));
        $this->assertSame('1970-01-01 00:00:00', SslLookup::utcDate('Y-m-d H:i:s', 0));
        $this->assertSame(gmdate('Y-m-d H:i:s', 1797850791), SslLookup::utcDate('Y-m-d H:i:s', 1797850791));
    }

    public function testASelfSignedCertificateIsUntrusted()
    {
        $made = TestCertificates::make(array('cn' => 'self.example.com', 'o' => 'Self', 'san' => array('DNS.1 = self.example.com')));
        $lookup = new SslLookup();

        list($code, $details) = $lookup->resultFromCertificate($made['cert'], array($made['cert']), 'self.example.com', time());

        $this->assertSame('untrusted', $code);
        $this->assertSame('self_signed', $details['reason']);
        $this->assertFalse($details['trusted']);
        $this->assertSame('Self', $details['issuer']);
        $this->assertSame(array('self.example.com'), $details['names']);
        $this->assertSame(64, strlen($details['fingerprint']));
        $this->assertSame(gmdate('Y-m-d', $details['valid_to']), $details['expiry_date']);
    }

    public function testTheTrustCheckUsesTheIntermediatesTheSiteSent()
    {
        $root = TestCertificates::make(array('cn' => 'Test Root', 'o' => 'Test Root CA', 'ca' => true));
        $intermediate = TestCertificates::make(array('cn' => 'Test Intermediate', 'o' => 'Test Intermediate CA', 'ca' => true, 'signer' => $root));
        $leaf = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('DNS.1 = www.example.com'), 'signer' => $intermediate));
        SslLookup::$test_ca_file = $this->caFile($root['pem']);
        $lookup = new SslLookup();

        list($code, $details) = $lookup->resultFromCertificate($leaf['cert'], array($leaf['cert'], $intermediate['cert']), 'www.example.com', time());
        $this->assertSame('ok', $code);
        $this->assertTrue($details['trusted']);
        $this->assertSame('Test Intermediate CA', $details['issuer']);

        // without the intermediate, the chain can't be built
        list($code, $details) = $lookup->resultFromCertificate($leaf['cert'], array($leaf['cert']), 'www.example.com', time());
        $this->assertSame('untrusted', $code);
        $this->assertSame('not_trusted', $details['reason']);
    }

    public function testAnExpiredCertificateIsntTrustChecked()
    {
        $root = TestCertificates::make(array('cn' => 'Test Root', 'ca' => true));
        $leaf = TestCertificates::make(array('cn' => 'www.example.com', 'san' => array('DNS.1 = www.example.com'), 'signer' => $root, 'days' => 1));
        SslLookup::$test_ca_file = $this->caFile($root['pem']);
        $lookup = new class extends SslLookup {
            public $trust_checks = 0;
            public function checkTrust($cert, $chain)
            {
                $this->trust_checks++;
                return parent::checkTrust($cert, $chain);
            }
        };
        $parsed = openssl_x509_parse($leaf['cert']);

        list($code, $details) = $lookup->resultFromCertificate($leaf['cert'], array(), 'www.example.com', $parsed['validTo_time_t'] + 1);

        $this->assertSame(0, $lookup->trust_checks);
        $this->assertSame('expired', $code);
        $this->assertFalse($details['trusted']);
        $this->assertSame('expired', $details['reason']);
    }

    public function testANameMismatchGivesNoCertificateDetails()
    {
        $made = TestCertificates::make(array('cn' => 'other.example.org', 'san' => array('DNS.1 = other.example.org')));

        list($code, $details) = (new SslLookup())->resultFromCertificate($made['cert'], array(), 'www.example.com', time());

        $this->assertSame('name_mismatch', $code);
        $this->assertSame('', $details['fingerprint']);
        $this->assertSame('', $details['expiry_date']);
    }

    public function testTheIssuerName()
    {
        $lookup = new SslLookup();

        $this->assertSame("Let's Encrypt", $lookup->issuerName(array('issuer' => array('O' => "Let's Encrypt", 'CN' => 'R11'))));
        $this->assertSame('R11', $lookup->issuerName(array('issuer' => array('O' => " \u{2066} ", 'CN' => 'R11'))));
        $this->assertSame('First', $lookup->issuerName(array('issuer' => array('O' => array('', 'First', 'Second')))));
        $this->assertSame('', $lookup->issuerName(array('issuer' => array('O' => array(1, 2)))));
        $this->assertSame('', $lookup->issuerName(array()));
        $this->assertSame('=Example', $lookup->issuerName(array('issuer' => array('O' => "\u{2066}=Exa\u{202E}mple\u{1F600}"))));
        $this->assertSame('Example CA', $lookup->issuerName(array('issuer' => array('O' => "  Example \t\n CA "))));
    }

    public function testTheAddressChecks()
    {
        $lookup = new SslLookup();
        $deadline = microtime(true) + 30;

        foreach (array('10.0.0.1', '127.0.0.1', '::1', 'fec0::1', '64:ff9b:1::1', '2001::1', '2001:0:4136:e378::1',
                     '2002:7f00:1::1', '::ffff:192.168.1.1') as $address) {

            SslLookup::$test_resolve = function () use ($address) { return array('93.184.215.14', $address); };
            $this->assertSame(array('not_public', ''), $lookup->resolve('www.example.com', $deadline), $address);

        }

        SslLookup::$test_resolve = function () { return array(); };
        $this->assertSame(array('no_dns', ''), $lookup->resolve('www.example.com', $deadline));

        SslLookup::$test_resolve = function () { return array('93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c'); };
        $this->assertSame(array('ok', '93.184.215.14'), $lookup->resolve('www.example.com', $deadline));
    }

    public function testMappedAddressesSharePacing()
    {
        $lookup = new SslLookup();

        $this->assertSame('93.184.215.14', $lookup->pacingKey('::ffff:93.184.215.14'));
        $this->assertSame('93.184.215.14', $lookup->pacingKey('93.184.215.14'));
        $this->assertSame('2606:2800::1', $lookup->pacingKey('2606:2800:0:0::1'));
    }

    public function testASlowResolverIsATimeout()
    {
        $now = 1000.0;
        SslLookup::$test_clock = function () use (&$now) { return $now; };
        SslLookup::$test_resolve = function () use (&$now) { $now += 20; return array(); };

        $this->assertSame(array('timeout', ''), (new SslLookup())->resolve('www.example.com', 1010.0));
    }

    public function testAnAddressThatIsntPublicIsNeverConnectedTo()
    {
        $targets = 0;
        SslLookup::$test_resolve = function () { return array('93.184.215.14', '10.0.0.1'); };
        SslLookup::$test_target = function () use (&$targets) { $targets++; return '127.0.0.1:9'; };

        $this->assertSame('not_public', (new SslLookup())->fetch('www.example.com', microtime(true) + 30)[0]);
        $this->assertSame(0, $targets);
    }

    public function testIpAddressesAreRefusedBeforeResolving()
    {
        SslLookup::$test_resolve = function () { throw new \RuntimeException('resolved'); };

        $this->assertSame('not_public', (new SslLookup())->fetchHost('127.0.0.1', microtime(true) + 30)[0]);
    }

    public function testConnectAndHandshakeFailures()
    {
        $lookup = new SslLookup();
        $deadline = microtime(true) + 30;

        $this->assertSame('timeout', $lookup->connectFailure(110, 0.2, 6, $deadline));
        $this->assertSame('timeout', $lookup->connectFailure(111, 6, 6, $deadline));
        $this->assertSame('no_connect', $lookup->connectFailure(111, 0.01, 6, $deadline));
        $this->assertSame('timeout', $lookup->connectFailure(111, 0.01, 6, microtime(true) - 1));
        $this->assertSame('timeout', $lookup->handshakeFailure(6, 6, $deadline));
        $this->assertSame('no_tls', $lookup->handshakeFailure(0.1, 6, $deadline));
        $this->assertSame('no_tls', $lookup->handshakeFailure(0.1, 6, $deadline, 'stream_socket_enable_crypto(): SSL operation failed with code 1.'));

        // a reset during the handshake is retried, however quickly it came, and the English wordings are matched even
        // without the sockets extension's system wording
        $no_sockets = new class extends SslLookup {
            protected function systemResetText()
            {
                return '';
            }
        };
        $this->assertSame('timeout', $no_sockets->handshakeFailure(0.1, 6, $deadline, 'stream_socket_enable_crypto(): SSL: Connection reset by peer'));
        $this->assertSame('timeout', $no_sockets->handshakeFailure(0.1, 6, $deadline, 'stream_socket_enable_crypto(): SSL: An existing connection was forcibly closed by the remote host.'));
        $this->assertFalse($lookup->isReset('stream_socket_enable_crypto(): SSL: Broken pipe'));
        $this->assertFalse($lookup->isReset(''));

        // in another language, the system's own wording for a reset is matched instead
        $french = new class extends SslLookup {
            protected function systemResetText()
            {
                return 'Connexion réinitialisée par le correspondant';
            }
        };
        $this->assertTrue($french->isReset('stream_socket_enable_crypto(): SSL: Connexion réinitialisée par le correspondant'));
        $this->assertFalse($french->isReset('stream_socket_enable_crypto(): SSL: Relais brisé (pipe)'));
    }

    // A PEM whose DER holds only what dnsNames() reads: a tbsCertificate with a subjectAltName of these DNS names
    private function pemWithNames(array $names, $extra = '')
    {
        $general_names = $extra;
        foreach ($names as $name) $general_names .= $this->der(0x82, $name);

        $extension = $this->der(0x30, $this->der(0x06, "\x55\x1d\x11") . $this->der(0x04, $this->der(0x30, $general_names)));
        $tbs = $this->der(0x30, $this->der(0x02, "\x01") . $this->der(0xa3, $this->der(0x30, $extension)));

        return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($this->der(0x30, $tbs)), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    private function der($tag, $content)
    {
        $length = strlen($content);
        $encoded = ($length < 0x80) ? chr($length) : (($length < 0x100) ? "\x81" . chr($length) : "\x82" . pack('n', $length));
        return chr($tag) . $encoded . $content;
    }

    private function caFile($pem)
    {
        $file = tempnam(sys_get_temp_dir(), 'domainmod-test-ca-');
        $this->assertIsString($file);
        $this->temp_files[] = $file;
        $this->assertSame(strlen($pem), file_put_contents($file, $pem), "Couldn't write the test CA file");
        return $file;
    }

} //@formatter:on

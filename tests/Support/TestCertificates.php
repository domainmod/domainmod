<?php
/**
 * /tests/Support/TestCertificates.php
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
namespace DomainMOD\Tests\Support;

/*
 * Certificates made on the spot for the SSL lookup tests, so no network is used. Each one is signed now (OpenSSL always
 * starts a certificate's validity at signing time). The OpenSSL settings come from a small file written for each
 * certificate, so the tests don't depend on the server's openssl.cnf.
 */
class TestCertificates
{

    /*
     * Returns array('cert' => OpenSSLCertificate, 'pem' => certificate PEM, 'key' => OpenSSLAsymmetricKey, 'key_pem' =>
     * private key PEM). Options:
     * - cn: the subject's common name.
     * - o: the subject's organisation, if any.
     * - san: the subjectAltName entries as config lines (such as array('DNS.1 = shop.example.com')), or none.
     * - ca: true for a CA certificate.
     * - signer: array('cert' => ..., 'key' => ...) of the issuing CA, or none for a self-signed certificate.
     * - days: how long it's valid.
     */
    public static function make(array $options)
    {
        $config = tempnam(sys_get_temp_dir(), 'domainmod-test-cnf-');
        if (!is_string($config) || $config === '') throw new \RuntimeException("Couldn't make a temporary file for a test certificate");
        // PHP 8.2 checks a key size before it looks at the key type, so even an EC key needs one (from default_bits
        // here)
        $lines = array('[req]', 'default_bits = 2048', 'distinguished_name = req_dn', '[req_dn]', '[v3_ext]');

        if (!empty($options['ca'])) {
            $lines[] = 'basicConstraints = critical, CA:TRUE';
            $lines[] = 'keyUsage = critical, keyCertSign, cRLSign';
        } else {
            $lines[] = 'basicConstraints = CA:FALSE';
            $lines[] = 'extendedKeyUsage = serverAuth';
        }

        if (!empty($options['san'])) {
            $lines[] = 'subjectAltName = @alt_names';
            $lines[] = '[alt_names]';
            foreach ($options['san'] as $entry) $lines[] = $entry;
        }

        try {
            return self::sign($config, $lines, $options);
        } finally {
            @unlink($config);
        }
    }

    private static function sign($config, array $lines, array $options)
    {
        $contents = implode("\n", $lines) . "\n";
        if (file_put_contents($config, $contents) !== strlen($contents)) throw new \RuntimeException("Couldn't write the test certificate's settings");
        $settings = array('config' => $config, 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_ext',
            'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1');

        $key = openssl_pkey_new($settings);
        if ($key === false) throw new \RuntimeException("Couldn't make a test key");
        $subject = array('commonName' => $options['cn'] ?? 'example.com');
        if (isset($options['o'])) $subject['organizationName'] = $options['o'];

        $csr = openssl_csr_new($subject, $key, $settings);
        if ($csr === false) throw new \RuntimeException("Couldn't make a test certificate request");
        $signer_cert = isset($options['signer']) ? $options['signer']['cert'] : null;
        $signer_key = isset($options['signer']) ? $options['signer']['key'] : $key;
        $cert = openssl_csr_sign($csr, $signer_cert, $signer_key, (int) ($options['days'] ?? 30), $settings, random_int(1, PHP_INT_MAX));

        $pem = '';
        $key_pem = '';
        if ($cert === false || !openssl_x509_export($cert, $pem)
            || !openssl_pkey_export($key, $key_pem, null, $settings)) {
            throw new \RuntimeException("Couldn't make a test certificate");
        }

        return array('cert' => $cert, 'pem' => $pem, 'key' => $key, 'key_pem' => $key_pem);
    }

} //@formatter:on

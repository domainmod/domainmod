<?php
/**
 * /tests/tls-check.php
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
/*
 * The SSL lookup against local servers, through the real connection, TLS handshake, SNI, and trust check, with no
 * network (the unit test runner runs it after PHPUnit). From the repository root: php tests/tls-check.php
 *
 * A TLS server picks its certificate by SNI: the test hostname's leaf (signed by a test intermediate it sends along,
 * signed by a test root only the check trusts) when that name is sent, and a default certificate for another name
 * otherwise. Other servers stall the handshake, reset the connection during it, or answer in plain text, and a closed
 * port refuses the connection. The $test_resolve hook gives each hostname a public address (a loopback address would be
 * refused before the override), and $test_target then points that address at the local server, after the address checks
 * have run.
 *
 * Exit 0 when every case gives the expected result, 1 when a case doesn't, and 2 when the check couldn't run (a local
 * server didn't start).
 */
//@formatter:off

// The tests run from the command line only, so a web request to this file (on an install made with git clone) does
// nothing.
if (PHP_SAPI !== 'cli') exit;

require_once __DIR__ . '/bootstrap.php';

use DomainMOD\SslLookup;
use DomainMOD\Tests\Support\TestCertificates;

// A local server, run as a separate process: prints its address, then serves until it's stopped
if (($argv[1] ?? '') === '--serve') {

    $mode = $argv[2] ?? '';
    $ssl_options = array();

    if ($mode === 'tls') {

        $ssl_options = array('local_cert' => $argv[3], 'SNI_server_certs' => array($argv[4] => $argv[5]),
            'verify_peer' => false);

    }

    $server = stream_socket_server('tcp://127.0.0.1:0', $error_number, $error_message,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create(array('ssl' => $ssl_options)));
    if ($server === false) exit(2);

    fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
    fflush(STDOUT);

    $held = array();

    while (true) {

        $connection = @stream_socket_accept($server, 60);
        if ($connection === false) continue;

        if ($mode === 'tls') {

            @stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
            fclose($connection);

        } elseif ($mode === 'stall') {

            $held[] = $connection;

        } elseif ($mode === 'reset') {

            // closing with the client's hello still unread makes the system reset the connection, so this waits up to
            // 10 seconds for the hello to arrive, then peeks at it, which leaves it unread (a client that closed
            // without sending anything gets a plain close, which the check reports as a failure)
            $read = array($connection);
            $write = $except = null;
            if (stream_select($read, $write, $except, 10) === 1) stream_socket_recvfrom($connection, 1, STREAM_PEEK);
            fclose($connection);

        } else {

            fwrite($connection, "HTTP/1.0 400 Bad Request\r\n\r\n");
            fclose($connection);

        }

    }

}

$temp_files = array();
$processes = array();

// The local servers are stopped and the test files deleted however the script ends
register_shutdown_function(function () {
    global $temp_files, $processes;
    foreach ($processes as $process) {
        if (is_resource($process)) proc_terminate($process);
    }
    foreach ($temp_files as $file) @unlink($file);
});

function tempFile($contents)
{
    global $temp_files;
    $file = tempnam(sys_get_temp_dir(), 'domainmod-tls-check-');
    if (!is_string($file) || $file === '') throw new \RuntimeException("Couldn't make a temporary file");
    $temp_files[] = $file;
    if (file_put_contents($file, $contents) !== strlen($contents)) throw new \RuntimeException("Couldn't write a temporary file");
    return $file;
}

// Starts a local server and returns array(process, address)
function startServer(array $arguments)
{
    global $processes;
    $process = proc_open(array_merge(array(PHP_BINARY, __FILE__, '--serve'), $arguments),
        array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) return array(null, '');
    $processes[] = $process;

    $read = array($pipes[1]);
    $write = null;
    $except = null;
    $address = (stream_select($read, $write, $except, 10) > 0) ? trim((string) fgets($pipes[1])) : '';

    return array($process, $address);
}

try {

    $root = TestCertificates::make(array('cn' => 'TLS Check Root', 'o' => 'TLS Check Root CA', 'ca' => true));
    $intermediate = TestCertificates::make(array('cn' => 'TLS Check Intermediate', 'o' => 'TLS Check CA', 'ca' => true,
        'signer' => $root));
    $leaf = TestCertificates::make(array('cn' => 'tls-check.example.com', 'san' => array('DNS.1 = tls-check.example.com'),
        'signer' => $intermediate));
    $default = TestCertificates::make(array('cn' => 'default.example.org', 'san' => array('DNS.1 = default.example.org')));

    $leaf_file = tempFile($leaf['pem'] . $intermediate['pem'] . $leaf['key_pem']);
    $default_file = tempFile($default['pem'] . $default['key_pem']);
    SslLookup::$test_ca_file = tempFile($root['pem']);

} catch (\Throwable $e) {

    echo "Not run: the test certificates couldn't be made (" . $e->getMessage() . ")\n";
    exit(2);

}

list(, $tls_address) = startServer(array('tls', $default_file, 'tls-check.example.com', $leaf_file));
list(, $stall_address) = startServer(array('stall'));
list(, $plain_address) = startServer(array('plain'));
list(, $reset_address) = startServer(array('reset'));

// A port with nothing listening on it
$closed = @stream_socket_server('tcp://127.0.0.1:0');
$closed_address = '';
if ($closed !== false) {
    $closed_address = (string) stream_socket_get_name($closed, false);
    fclose($closed);
}

// Each case: hostname => array(public address, local server, expected code)
$cases = array(
    'tls-check.example.com' => array('93.184.215.1', $tls_address, 'ok'),
    'default.example.org' => array('93.184.215.7', $tls_address, 'untrusted'),
    'other.example.com' => array('93.184.215.2', $tls_address, 'name_mismatch'),
    'closed.example.com' => array('93.184.215.3', $closed_address, 'no_connect'),
    'silent.example.com' => array('93.184.215.4', $closed_address, 'timeout'),
    'stall.example.com' => array('93.184.215.5', $stall_address, 'timeout'),
    'plain.example.com' => array('93.184.215.6', $plain_address, 'no_tls'),
    'reset.example.com' => array('93.184.215.8', $reset_address, 'timeout'));

SslLookup::$test_resolve = function ($ascii_host) use ($cases) {
    return isset($cases[$ascii_host]) ? array($cases[$ascii_host][0]) : array();
};
SslLookup::$test_target = function ($address, $ascii_host) use ($cases) {
    return $cases[$ascii_host][1] ?? '';
};

$failed = 0;

if ($tls_address === '' || $stall_address === '' || $plain_address === '' || $reset_address === '' || $closed_address === '') {

    echo "Not run: a local server didn't start\n";
    $failed = 2;

} else {

    $lookup = new SslLookup();

    foreach ($cases as $host => $case) {

        // the never-answered connection can't be staged without a network, so the connect stage gets the failure a
        // timed-out connection gives, and the real classifier turns it into timeout
        SslLookup::$test_connect_timeout = ($host === 'silent.example.com') ? true : null;

        $started = microtime(true);
        list($code, $details) = $lookup->fetch($host, microtime(true) + 12);
        $seconds = microtime(true) - $started;

        $problem = ($code !== $case[2]) ? 'got ' . $code : '';

        // every case ends within its 12-second deadline (with a second to spare for scheduling)
        if ($problem === '' && $seconds > 13) $problem = sprintf('took %.1fs, past the deadline', $seconds);

        // only the reset server's timeout is marked as a reset (the mark lets fetch() still try the www. name)
        if ($problem === '' && $code === 'timeout' && !empty($details['reset']) !== ($host === 'reset.example.com')) {
            $problem = empty($details['reset']) ? 'not marked as a reset' : 'marked as a reset';
        }

        // the test hostname gets its trusted leaf, and any other name the self-signed default
        if ($problem === '' && $code === 'ok') {

            if ($details['trusted'] !== true) {
                $problem = 'not trusted';
            } elseif ($details['names'] !== array($host)) {
                $problem = 'names ' . implode(', ', $details['names']);
            } elseif ($details['issuer'] !== 'TLS Check CA') {
                $problem = 'issuer ' . $details['issuer'];
            }

        } elseif ($problem === '' && $code === 'untrusted') {

            if ($details['reason'] !== 'self_signed') {
                $problem = 'reason ' . $details['reason'];
            } elseif ($details['names'] !== array($host)) {
                $problem = 'names ' . implode(', ', $details['names']);
            }

        }

        printf("  %s %s: expected %s, %s (%.1fs)\n", $problem === '' ? 'pass' : 'FAIL', $host, $case[2],
            $problem === '' ? 'got ' . $code : $problem, $seconds);
        if ($problem !== '') $failed = 1;

    }

}

echo $failed ? "TLS check: failed\n" : "TLS check: passed\n";
exit($failed);
//@formatter:on

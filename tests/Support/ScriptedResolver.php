<?php
/**
 * /tests/Support/ScriptedResolver.php
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

use DomainMOD\LiveDns;

/*
 * A stand-in for the server's resolver in LiveDns tests, with its own clock. Each answer is scripted by absolute name
 * and type ('example.com.|NS'), as records (an array, as dns_get_record() returns) or false (a resolver failure), with
 * the seconds the query takes. A name and type with no script answers with no records in $default_seconds. Every
 * query is logged in $calls, so a test can check which were asked and in what order.
 */
class ScriptedResolver
{
    public $now = 1000.0;
    public $default_seconds = 0.1;
    public $answers = array();
    public $calls = array();

    public function install()
    {
        LiveDns::$test_clock = function () {
            return $this->now;
        };
        LiveDns::$test_lookup = function ($name, $type) {
            $key = $name . '|' . $type;
            $this->calls[] = $key;
            $answer = $this->answers[$key] ?? array(array(), $this->default_seconds);
            $this->now += $answer[1];
            return $answer[0];
        };
        return $this;
    }

    public static function uninstall()
    {
        LiveDns::$test_clock = null;
        LiveDns::$test_lookup = null;
    }

    // Scripts one answer
    public function answer($name, $type, $records, $seconds = null)
    {
        $this->answers[$name . '|' . $type] = array($records, $seconds ?? $this->default_seconds);
        return $this;
    }

    // Scripts every type of one name with the same failure or delay
    public function all($name, $records, $seconds)
    {
        foreach (array('NS', 'A', 'AAAA', 'MX') as $type) $this->answer($name, $type, $records, $seconds);
        return $this;
    }

    public static function ns($host, array $targets)
    {
        $records = array();
        foreach ($targets as $target) $records[] = array('host' => $host, 'class' => 'IN', 'ttl' => 300, 'type' => 'NS', 'target' => $target);
        return $records;
    }

    public static function a($host, array $addresses)
    {
        $records = array();
        foreach ($addresses as $address) $records[] = array('host' => $host, 'class' => 'IN', 'ttl' => 300, 'type' => 'A', 'ip' => $address);
        return $records;
    }

    public static function aaaa($host, array $addresses)
    {
        $records = array();
        foreach ($addresses as $address) $records[] = array('host' => $host, 'class' => 'IN', 'ttl' => 300, 'type' => 'AAAA', 'ipv6' => $address);
        return $records;
    }

    public static function mx($host, array $entries)
    {
        $records = array();
        foreach ($entries as $priority => $target) $records[] = array('host' => $host, 'class' => 'IN', 'ttl' => 300, 'type' => 'MX', 'pri' => $priority, 'target' => $target);
        return $records;
    }

} //@formatter:on

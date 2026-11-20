<?php
/**
 * /tests/Support/ScriptedDynadot.php
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

use DomainMOD\Dynadot;

/*
 * Dynadot with no network: transfer() answers from a script of responses in order (array($http_code, $body), or a
 * callable given the URL), records each URL and its headers, and the clock is a number the test controls, which
 * sleeping and each transfer move on. restCall(), send(), and the deadline checks run for real.
 */
class ScriptedDynadot extends Dynadot
{
    public $responses = array();
    public $urls = array();
    public $headers = array();
    public $clock = 1000000.0;
    public $transfer_seconds = 0.0;
    public $slept = 0.0;
    public $deadlines = array(); // the HttpClient deadline in force during each transfer
    public $max_execution_time = 0;
    public $request_started = 1000000.0; // stands in for REQUEST_TIME_FLOAT

    // A fixture from tests/Fixtures/Dynadot as array($http_code, $body)
    public static function fixture($name)
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/Fixtures/Dynadot/' . $name . '.json'), true);
        return array($fixture['http_code'], json_encode($fixture['body']));
    }

    protected function request($url, $headers, $deadline, $paced)
    {
        $this->headers[] = $headers;
        return parent::request($url, $headers, $deadline, $paced);
    }

    protected function transfer($handle)
    {
        $url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        $this->urls[] = $url;
        $this->deadlines[] = \DomainMOD\HttpClient::getDeadline();
        $this->clock += $this->transfer_seconds;

        if (!$this->responses) throw new \RuntimeException('No scripted response left for ' . $url);
        $response = array_shift($this->responses);
        return is_callable($response) ? $response($url) : $response;
    }

    // The api3 call for an account with no API Secret, answered from the same script
    public function apiCall($full_url)
    {
        $this->urls[] = $full_url;
        if (!$this->responses) throw new \RuntimeException('No scripted response left for ' . $full_url);
        $response = array_shift($this->responses);
        $response = is_callable($response) ? $response($full_url) : $response;
        return $response[1];
    }

    protected function maxExecutionTime()
    {
        return $this->max_execution_time;
    }

    protected function requestStarted()
    {
        return $this->request_started;
    }

    protected function now()
    {
        return $this->clock;
    }

    protected function sleepFor($seconds)
    {
        $this->slept += $seconds;
        $this->clock += $seconds;
    }

} //@formatter:on

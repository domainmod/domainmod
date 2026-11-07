<?php
/**
 * /classes/DomainMOD/Cloudflare.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2025 Greg Chetcuti <greg@greg.ca>
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

class Cloudflare
{
    public $deeb;
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.cloudflare');
        $this->deeb = Database::getInstance();
    }

    public function getApiUrl($command, $domain)
    {
        $base_url = 'https://api.cloudflare.com/client/v4/';
        if ($command == 'domainlist') {
            $full_url = $base_url . 'zones?status=active';
        } elseif ($command == 'info') {
            $full_url = $base_url . 'registrar/domains/' . $domain;
        } else {
            return array(_('Unable to build API URL'), '');
        }

        return $full_url;
    }

    public function apiCall($full_url, $api_token)
    {
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json'));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getDomainList($api_token)
    {
        $domain_list = array();
        $domain_count = 0;
        $per_page = 50;
        $page = 1;
        $total_pages = 1;
        $max_retries = 5;
        $base_url = 'https://api.cloudflare.com/client/v4/zones?status=active';

        do {
            $url = $base_url . '&per_page=' . $per_page . '&page=' . $page;
            $retries = 0;
            while (true) {
                $handle = curl_init($url);
                curl_setopt($handle, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . $api_token,
                    'Content-Type: application/json'
                ));
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($handle, CURLOPT_HEADER, true);
                $response = curl_exec($handle);
                $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
                $header = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
                $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
                curl_close($handle);

                // Read the Retry-After header if present
                $retry_after = 0;
                if (preg_match('/Retry-After: (\d+)/i', $header, $matches)) {
                    $retry_after = (int)$matches[1];
                }

                if ($http_code == 429) {
                    // Rate limit exceeded, wait and retry
                    $wait = $retry_after > 0 ? $retry_after : 10;
                    sleep($wait);
                    $retries++;
                    if ($retries >= $max_retries) {
                        $log_message = 'Cloudflare API rate limit exceeded, max retries reached.';
                        $log_extra = array('API Token' => $this->format->obfusc($api_token), 'URL' => $url);
                        $this->log->error($log_message, $log_extra);
                        break;
                    }
                    continue;
                } elseif ($http_code >= 500) {
                    // Server error, wait and retry
                    sleep(5);
                    $retries++;
                    if ($retries >= $max_retries) {
                        $log_message = 'Cloudflare API server error, max retries reached.';
                        $log_extra = array('API Token' => $this->format->obfusc($api_token), 'URL' => $url);
                        $this->log->error($log_message, $log_extra);
                        break;
                    }
                    continue;
                }
                // Successful or other error, exit loop
                break;
            }

            $array_results = $this->convertToArray($body);
            if (isset($array_results['result'][0]['name'])) {
                foreach ($array_results['result'] as $domain) {
                    $domain_list[] = $domain['name'];
                    $domain_count++;
                }
            } else {
                $log_message = 'Unable to get domain list';
                $log_extra = array('API Token' => $this->format->obfusc($api_token), 'URL' => $url, 'Response' => $body);
                $this->log->error($log_message, $log_extra);
                break;
            }

            // Pagination information
            if (isset($array_results['result_info']['total_pages'])) {
                $total_pages = (int)$array_results['result_info']['total_pages'];
            } else {
                $total_pages = 1;
            }

            $page++;
            // Add a short delay after each request (for rate limiting)
            usleep(250000); // 0.25 seconds

        } while ($page <= $total_pages);

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_token, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl('info', $domain);
        $api_results = $this->apiCall($api_url, $api_token);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results['result']['name'])) {
            // get expiration date
            $expiration_date = substr($array_results['result']['expires_at'], 0, 10);
            // get privacy status
            $privacy_result = (string) $array_results['result']['privacy'];
            $privacy_status = $this->processPrivacy($privacy_result);
            // get auto renewal status
            $autorenewal_result = (string) $array_results['result']['auto_renew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);
            // get DNS servers
            $dns_result = $array_results['result']['name_servers'];
            $dns_servers = $this->processDns($dns_result);
        } else {
            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'API Token' => $this->format->obfusc($api_token));
            $this->log->error($log_message, $log_extra);
        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, true);
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty($dns_result)) {
            $dns_servers = array_filter($dns_result);
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == '1') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == '1') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on

<?php
/**
 * /classes/DomainMOD/DomainAdd.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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

class DomainAdd
{
    public $assets;
    public $conversion;
    public $deeb;
    public $domain;
    public $log;
    public $maint;
    public $system;
    public $time;

    public function __construct()
    {
        $this->assets = new Assets();
        $this->conversion = new Conversion();
        $this->deeb = Database::getInstance();
        $this->domain = new Domain();
        $this->log = new Log('class.domainadd');
        $this->maint = new Maintenance();
        $this->system = new System();
        $this->time = new Time();
    }

    public function addDomainFull($user_id, $creation_type, $domain, $expiry, $function, $auto_renewal, $privacy,
                                  $status, $registration_fee, $renewal_fee, $transfer_fee, $privacy_fee, $misc_fee,
                                  $fee_currency, $domain_notes, $category, $stakeholder, $category_notes,
                                  $hosting_provider, $hosting_provider_url, $hosting_provider_notes, $registrar,
                                  $registrar_url, $registrar_notes, $registrar_account_owner,
                                  $registrar_account_owner_notes, $registrar_account_email_address,
                                  $registrar_account_username, $registrar_account_password, $registrar_account_id,
                                  $registrar_reseller, $registrar_reseller_id, $registrar_account_notes, $api_app_name,
                                  $api_key, $api_secret, $api_ip_address_name, $api_ip_address, $api_ip_address_rdns,
                                  $api_ip_address_notes, $domain_ip_address_name, $domain_ip_address,
                                  $domain_ip_address_rdns, $domain_ip_address_notes, $dns_server_name, $dns_server_1,
                                  $dns_server_2, $dns_server_3, $dns_server_4, $dns_server_5, $dns_server_6,
                                  $dns_server_7, $dns_server_8, $dns_server_9, $dns_server_10, $dns_notes, $dns_ip_1,
                                  $dns_ip_2, $dns_ip_3, $dns_ip_4, $dns_ip_5, $dns_ip_6, $dns_ip_7, $dns_ip_8,
                                  $dns_ip_9, $dns_ip_10)
    {

        $default_dns_1 = 'ns1.fake-dns.ns1';
        $default_dns_2 = 'ns2.fake-dns.ns2';
        $default_ip_address = '111.222.333.444';
        $default_name = '';
        $default_rdns = 'one.two.three.four';
        $default_text = '';
        $default_username = '';
        $todays_date = $this->time->timeBasic();

        if ($creation_type == 'CSV Importer') {

            $default_dns_1 = 'ns1.domainmod-csvimport.ns1';
            $default_dns_2 = 'ns2.domainmod-csvimport.ns2';
            $default_name = 'CSV Importer';
            $default_text = 'Created by CSV Importer';
            $default_username = 'csv-importer';

        }

        if ($expiry == '') $expiry = $this->time->timeBasic();
        if ($auto_renewal == '1' || strtoupper($auto_renewal) == 'YES') {
            $auto_renewal = 1;
        } else {
            $auto_renewal = 0;
        }
        if ($privacy == '1' || strtoupper($privacy) == 'YES') {
            $privacy = 1;
        } else {
            $privacy = 0;
        }
        $status_upper = strtoupper($status);
        if ($status_upper == 'EXPIRED' || $status == '0') {
            $status = 0;
        } elseif ($status_upper == 'PENDING (TRANSFER)' || $status_upper == 'PENDING TRANSFER' || $status == '2') {
            $status = 2;
        } elseif ($status_upper == 'PENDING (RENEWAL)' || $status_upper == 'PENDING RENEWAL' || $status == '3') {
            $status = 3;
        } elseif ($status_upper == 'PENDING (OTHER)' || $status_upper == 'PENDING OTHER' || $status == '4') {
            $status = 4;
        } elseif ($status_upper == 'PENDING (REGISTRATION)' || $status_upper == 'PENDING REGISTRATION' || $status == '5') {
            $status = 5;
        } elseif ($status_upper == 'SOLD' || $status == '10') {
            $status = 10;
        } else { // Default to Active
            $status = 1;
        }
        if ($domain_notes == '') $domain_notes = $todays_date . ' - ' . $default_text;
        if ($category == '') $category = $default_text;
        if ($category_notes == '') $category_notes = $todays_date . ' - ' . $default_text;
        if ($stakeholder == '')  $stakeholder = $default_name;
        if ($hosting_provider == '')  $hosting_provider = $default_text;
        if ($hosting_provider_notes == '') $hosting_provider_notes = $todays_date . ' - ' . $default_text;
        if ($domain_ip_address_name == '') $domain_ip_address_name = $default_text;
        if ($domain_ip_address == '') $domain_ip_address = $default_ip_address;
        if ($domain_ip_address_rdns == '') $domain_ip_address_rdns = $default_rdns;
        if ($domain_ip_address_notes == '') $domain_ip_address_notes = $todays_date . ' - ' . $default_text;
        if ($registrar == '')  $registrar = $default_text;
        if ($registrar_notes == '') $registrar_notes = $todays_date . ' - ' . $default_text;
        if ($registrar_account_owner == '')  $registrar_account_owner = $default_name;
        if ($registrar_account_owner_notes == '') $registrar_account_owner_notes = $todays_date . ' - ' . $default_text;
        if ($registrar_account_username == '')  $registrar_account_username = $default_username;
        if ($registrar_reseller == '1' || strtoupper($registrar_reseller) == 'YES') {
            $registrar_reseller = 1;
        } else {
            $registrar_reseller = 0;
        }
        if ($registrar_account_notes == '') $registrar_account_notes = $todays_date . ' - ' . $default_text;
        if ($api_ip_address_name == '') $api_ip_address_name = $default_text;
        if ($api_ip_address == '') $api_ip_address = $default_ip_address;
        if ($api_ip_address_rdns == '') $api_ip_address_rdns = $default_rdns;
        if ($api_ip_address_notes == '') $api_ip_address_notes = $todays_date . ' - ' . $default_text;
        if ($dns_server_name == '') $dns_server_name = $default_text;
        if ($registration_fee == '') $registration_fee = 0;
        if ($renewal_fee == '') $renewal_fee = 0;
        if ($transfer_fee == '') $transfer_fee = 0;
        if ($privacy_fee == '') $privacy_fee = 0;
        if ($misc_fee == '') $misc_fee = 0;
        if ($fee_currency == '') $fee_currency = 'USD';
        $dns_servers_raw = array($dns_server_1, $dns_server_2, $dns_server_3, $dns_server_4, $dns_server_5, $dns_server_6,
            $dns_server_7, $dns_server_8, $dns_server_9, $dns_server_10);
        $dns_servers_clean = array_filter($dns_servers_raw);
        $dns_servers = array();
        if (!empty($dns_servers_clean)) {
            $dns_servers = $dns_servers_clean;
        } else {
            $dns_servers[0] = $default_dns_1;
            $dns_servers[1] = $default_dns_2;
        }
        if ($dns_notes == '') $dns_notes = $todays_date . ' - ' . $default_text;
        if ($dns_ip_1 == '') $dns_ip_1 = $default_ip_address;
        if ($dns_ip_2 == '') $dns_ip_2 = $default_ip_address;
        if ($dns_ip_3 == '') $dns_ip_3 = $default_ip_address;
        if ($dns_ip_4 == '') $dns_ip_4 = $default_ip_address;
        if ($dns_ip_5 == '') $dns_ip_5 = $default_ip_address;
        if ($dns_ip_6 == '') $dns_ip_6 = $default_ip_address;
        if ($dns_ip_7 == '') $dns_ip_7 = $default_ip_address;
        if ($dns_ip_8 == '') $dns_ip_8 = $default_ip_address;
        if ($dns_ip_9 == '') $dns_ip_9 = $default_ip_address;
        if ($dns_ip_10 == '') $dns_ip_10 = $default_ip_address;

        $creation_type_id = $this->system->getCreationTypeId($creation_type);

        // Check to see if the domain already exists
        if ($this->domain->checkDomainExistence($domain) == true) {

            // return sprintf(_('This domain is already in %s'), SOFTWARE_TITLE . '<BR>');
            return false;

        }

        // Check to see if the category already exists
        $result = $this->assets->checkForCatByName($category);

        if ($result == true) {

            // If the category exists, get the category ID
            $cat_id = $this->assets->getCatId($category);

        } else {

            // If the category doesn't exist, create it and get the category ID
            $cat_id = $this->assets->createCategory($category, $stakeholder, $category_notes, $creation_type_id,
                $user_id);

        }

        // Check to see if the hosting provider already exists
        $result = $this->assets->checkForHostByName($hosting_provider);

        if ($result == true) {

            // If the hosting provider exists, get the hosting provider ID
            $host_id = $this->assets->getHostId($hosting_provider);

        } else {

            // If the hosting provider doesn't exist, create it and get the hosting provider ID
            $host_id = $this->assets->createHost($hosting_provider, $hosting_provider_url, $hosting_provider_notes,
                $creation_type_id, $user_id);

        }

        // Check to see if the Domain IP address already exists
        $result = $this->assets->checkForIpAddress($domain_ip_address);

        if ($result == true) {

            // If the Domain IP address exists, get the Domain IP address ID
            $domain_ip_id = $this->assets->getIpAddressId($domain_ip_address);

        } else {

            // If the Domain IP address doesn't exist, create it and get the Domain IP address ID
            $domain_ip_id = $this->assets->createIpAddress($domain_ip_address_name, $domain_ip_address,
                $domain_ip_address_rdns, $domain_ip_address_notes, $creation_type_id, $user_id);

        }

        // Check to see if the registrar already exists
        $result = $this->assets->checkForRegistrarByName($registrar);

        if ($result == true) {

            // If the registrar exists, get the registrar ID
            $registrar_id = $this->assets->getRegistrarId($registrar);

        } else {

            // If the registrar doesn't exist, create it and get the registrar ID
            $registrar_id = $this->assets->createRegistrar($registrar, $registrar_url, $registrar_notes,
                $creation_type_id, $user_id);

        }

        // Check to see if the registrar account owner already exists
        $result = $this->assets->checkForOwner($registrar_account_owner);

        if ($result == true) {

            // If the registrar account owner exists, get the owner ID
            $registrar_account_owner_id = $this->assets->getOwnerId($registrar_account_owner);

        } else {

            // If the registrar account owner doesn't exist, create it and get the registrar account owner ID
            $registrar_account_owner_id = $this->assets->createOwner($registrar_account_owner,
                $registrar_account_owner_notes, $creation_type_id, $user_id);

        }

        // Check to see if the API IP address already exists
        $result = $this->assets->checkForIpAddress($api_ip_address);

        if ($result == true) {

            // If the API IP address exists, get the API IP address ID
            $api_ip_id = $this->assets->getIpAddressId($api_ip_address);

        } else {

            // If the API IP address doesn't exist, create it and get the API IP address ID
            $api_ip_id = $this->assets->createIpAddress($api_ip_address_name, $api_ip_address, $api_ip_address_rdns,
                $api_ip_address_notes, $creation_type_id, $user_id);

        }

        // Check to see if the registrar account already exists
        $result = $this->assets->checkForRegistrarAccount($registrar_id, $registrar_account_username);

        if ($result == true) {

            // If the registrar account exists, get the registrar account ID
            $account_id = $this->assets->getRegistrarAccountId($registrar_id, $registrar_account_username);

        } else {

            // If the registrar account doesn't exist, create it and get the registrar account ID
            $account_id = $this->assets->createRegistrarAccount($registrar_account_owner_id, $registrar_id,
                $registrar_account_email_address, $registrar_account_username, $registrar_account_password,
                $registrar_account_id, $registrar_reseller, $registrar_reseller_id, $api_app_name, $api_key,
                $api_secret, $api_ip_id, $registrar_account_notes, $creation_type_id, $user_id);

        }

        // Check to see if the DNS profile already exists
        $result = $this->assets->checkForDnsProfile($dns_servers);

        if ($result !== false) {

            $dns_id = $result;

        } else {

            // If the registrar account doesn't exist, create it and get the registrar account ID
            $dns_id = $this->assets->createDnsProfile($dns_server_name, $dns_servers, $dns_ip_1, $dns_ip_2, $dns_ip_3,
                $dns_ip_4, $dns_ip_5, $dns_ip_6, $dns_ip_7, $dns_ip_8, $dns_ip_9, $dns_ip_10, $dns_notes,
                $creation_type_id, $user_id);

        }

        $tld = $this->domain->getTld($domain);

        list($fee_id, $total_cost) = $this->domain->getFeeIdAndTotalCost($privacy, $tld, $registrar_id);

        // Check to see if the fee already exists
        $result = $this->assets->checkForFee($registrar_id, $tld);

        if ($result == true) {

            // If the fee exists, get the fee ID
            $fee_id = $this->assets->getFeeId($registrar_id, $tld);

        } else {

            // If the fee doesn't exist, create it and get the fee ID
            $fee_id = $this->assets->createFee($registrar_id, $tld, $registration_fee, $renewal_fee, $transfer_fee,
                $privacy_fee, $misc_fee, $fee_currency);

        }

        // Check to see if the currency conversion already exists
        $result = $this->conversion->checkForConvRate($user_id, $fee_currency);

        if ($result == false) {

            // If the currency conversion doesn't exist, create it
            $void = $this->assets->createCurrencyConversion($fee_currency, $user_id);

        }

        $void = $this->domain->addDomain($registrar_account_owner_id, $registrar_id, $account_id, $domain, $tld,
            $expiry, $cat_id, $dns_id, $domain_ip_id, $host_id, $fee_id, $total_cost, $function, $domain_notes,
            $auto_renewal, $privacy, $creation_type_id, $user_id, $status);

        return true;

    }

} //@formatter:on

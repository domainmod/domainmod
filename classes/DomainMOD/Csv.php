<?php
/**
 * /classes/DomainMOD/Csv.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

class Csv
{
    public $assets;
    public $conversion;
    public $deeb;
    public $domainadd;
    public $log;
    public $maint;
    public $time;
    public $user;

    public function __construct()
    {
        $this->assets = new Assets();
        $this->conversion = new Conversion();
        $this->deeb = Database::getInstance();
        $this->domainadd = new DomainAdd();
        $this->log = new Log('class.csv');
        $this->maint = new Maintenance();
        $this->time = new Time();
        $this->user = new User();
    }

    public function parse($filename, $user_id)
    {
        $this->deeb->cnxx->beginTransaction();

        $user_currency = $this->user->getDefaultCurrency($user_id);

        $handle = fopen($filename, "r");
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {

            if ($count !== 0) {

                $creation_type = 'CSV Importer';

                $domain = $row[0];
                $expiry = $row[1];
                $function = $row[2];
                $auto_renewal = $row[3];
                $privacy = $row[4];
                $status = $row[5];
                $registration_fee = $row[6];
                $renewal_fee = $row[7];
                $transfer_fee = $row[8];
                $privacy_fee = $row[9];
                $misc_fee = $row[10];
                $fee_currency = $row[11];
                $domain_notes = $row[12];
                $category = $row[13];
                $stakeholder = $row[14];
                $category_notes = $row[15];
                $hosting_provider = $row[16];
                $hosting_provider_url = $row[17];
                $hosting_provider_notes = $row[18];
                $registrar = $row[19];
                $registrar_url = $row[20];
                $registrar_notes = $row[21];
                $registrar_account_owner = $row[22];
                $registrar_account_owner_notes = $row[23];
                $registrar_account_email_address = $row[24];
                $registrar_username = $row[25];
                $registrar_password = $row[26];
                $registrar_account_id = $row[27];
                $registrar_reseller = $row[28];
                $registrar_reseller_id = $row[29];
                $registrar_account_notes = $row[30];
                $api_app_name = $row[31];
                $api_key = $row[32];
                $api_secret = $row[33];
                $api_ip_address_name = $row[34];
                $api_ip_address = $row[35];
                $api_ip_address_rdns = $row[36];
                $api_ip_address_notes = $row[37];
                $domain_ip_address_name = $row[38];
                $domain_ip_address = $row[39];
                $domain_ip_address_rdns = $row[40];
                $domain_ip_address_notes = $row[41];
                $dns_server_name = $row[42];
                $dns_server_1 = $row[43];
                $dns_server_2 = $row[44];
                $dns_server_3 = $row[45];
                $dns_server_4 = $row[46];
                $dns_server_5 = $row[47];
                $dns_server_6 = $row[48];
                $dns_server_7 = $row[49];
                $dns_server_8 = $row[50];
                $dns_server_9 = $row[51];
                $dns_server_10 = $row[52];
                $dns_notes = $row[53];
                $dns_ip_1 = $row[54];
                $dns_ip_2 = $row[55];
                $dns_ip_3 = $row[56];
                $dns_ip_4 = $row[57];
                $dns_ip_5 = $row[58];
                $dns_ip_6 = $row[59];
                $dns_ip_7 = $row[60];
                $dns_ip_8 = $row[61];
                $dns_ip_9 = $row[62];
                $dns_ip_10 = $row[63];

                echo $this->domainadd->addDomainFull($user_id, $creation_type, $domain, $expiry, $function,
                    $auto_renewal, $privacy, $status, $registration_fee, $renewal_fee, $transfer_fee, $privacy_fee,
                    $misc_fee, $fee_currency, $domain_notes, $category, $stakeholder, $category_notes,
                    $hosting_provider, $hosting_provider_url, $hosting_provider_notes, $registrar, $registrar_url,
                    $registrar_notes, $registrar_account_owner, $registrar_account_owner_notes,
                    $registrar_account_email_address, $registrar_username, $registrar_password, $registrar_account_id,
                    $registrar_reseller, $registrar_reseller_id, $registrar_account_notes, $api_app_name, $api_key,
                    $api_secret, $api_ip_address_name, $api_ip_address, $api_ip_address_rdns, $api_ip_address_notes,
                    $domain_ip_address_name, $domain_ip_address, $domain_ip_address_rdns, $domain_ip_address_notes,
                    $dns_server_name, $dns_server_1, $dns_server_2, $dns_server_3, $dns_server_4, $dns_server_5,
                    $dns_server_6, $dns_server_7, $dns_server_8, $dns_server_9, $dns_server_10, $dns_notes, $dns_ip_1,
                    $dns_ip_2, $dns_ip_3, $dns_ip_4, $dns_ip_5, $dns_ip_6, $dns_ip_7, $dns_ip_8, $dns_ip_9, $dns_ip_10);

            }

            $count++;

        }

        $this->maint->updateDomainFees();

        $this->conversion->updateRates($user_currency, $user_id);

        $this->maint->updateSegments();

        $_SESSION['s_has_registrar'] = 1;
        $_SESSION['s_has_registrar_account'] = 1;
        $_SESSION['s_has_domain'] = 1;

        if ($this->deeb->cnxx->InTransaction()) $this->deeb->cnxx->commit();

    }

}

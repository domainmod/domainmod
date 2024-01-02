<?php
/**
 * /install/currency/index.php
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
?>
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.currency.inc.php';

$system->loginCheck();
$system->installCheck();

$_SESSION['s_installation_currency'] = $_SESSION['s_installation_currency'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $_SESSION['s_installation_currency'] = $sanitize->text($_POST['new_currency']);

    header("Location: ../timezone/");
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition text-sm">
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php
echo $form->showFormTop('');

echo $form->showDropdownTop('new_currency', '', '', '', '');
echo $form->showDropdownOption('AFN', 'Afghanistan Afghani', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ALL', 'Albania Lek', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('DZD', 'Algerian Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AOA', 'Angolan Kwanza', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ARS', 'Argentina Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AMD', 'Armenian Dram', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AWG', 'Aruba Guilder', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AUD', 'Australia Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AZN', 'Azerbaijan New Manat', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BSD', 'Bahamas Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BHD', 'Bahraini Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BDT', 'Bangladeshi Taka', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BBD', 'Barbados Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LSL', 'Basotho Loti', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BYR', 'Belarus Ruble', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BZD', 'Belize Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BMD', 'Bermuda Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BTN', 'Bhutanese Ngultrum', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BOB', 'Bolivia Boliviano', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BAM', 'Bosnia and Herzegovina Convertible Marka', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BWP', 'Botswana Pula', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BRL', 'Brazil Real', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BND', 'Brunei Darussalam Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BGN', 'Bulgaria Lev', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MMK', 'Burmese Kyat', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('BIF', 'Burundian Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KHR', 'Cambodia Riel', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CAD', 'Canada Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CVE', 'Cape Verdean Escudo', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KYD', 'Cayman Islands Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('XAF', 'Central African CFA Franc BEAC', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('XOF', 'CFA Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('XPF', 'CFP Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CLP', 'Chile Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CNY', 'China Yuan Renminbi', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('COP', 'Colombia Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KMF', 'Comoran Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CDF', 'Congolese Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CRC', 'Costa Rica Colon', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('HRK', 'Croatia Kuna', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CUP', 'Cuba Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CUC', 'Cuban Convertible Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CZK', 'Czech Republic Koruna', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('DKK', 'Denmark Krone', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('DJF', 'Djiboutian Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('DOP', 'Dominican Republic Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('XCD', 'East Caribbean Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('EGP', 'Egypt Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SVC', 'El Salvador Colon', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('AED', 'Emirati Dirham', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('EEK', 'Estonia Kroon', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ETB', 'Ethiopian Birr', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('EUR', 'Euro Member Countries', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('FKP', 'Falkland Islands (Malvinas) Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('FJD', 'Fiji Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GMD', 'Gambian Dalasi', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GEL', 'Georgian Lari', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GHC', 'Ghana Cedis', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GHS', 'Ghanaian Cedi', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GIP', 'Gibraltar Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GTQ', 'Guatemala Quetzal', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GGP', 'Guernsey Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GNF', 'Guinean Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GYD', 'Guyana Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('HTG', 'Haitian Gourde', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('HNL', 'Honduras Lempira', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('HKD', 'Hong Kong Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('HUF', 'Hungary Forint', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ISK', 'Iceland Krona', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('INR', 'India Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('IDR', 'Indonesia Rupiah', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('IRR', 'Iran Rial', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('IQD', 'Iraqi Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('IMP', 'Isle of Man Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ILS', 'Israel Shekel', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('JMD', 'Jamaica Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('JPY', 'Japan Yen', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('JEP', 'Jersey Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('JOD', 'Jordanian Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KZT', 'Kazakhstan Tenge', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KES', 'Kenyan Shilling', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KPW', 'Korea (North) Won', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KRW', 'Korea (South) Won', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KWD', 'Kuwaiti Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('KGS', 'Kyrgyzstan Som', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LAK', 'Laos Kip', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LVL', 'Latvia Lat', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LBP', 'Lebanon Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LRD', 'Liberia Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LYD', 'Libyan Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LTL', 'Lithuania Litas', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MOP', 'Macau Pataca', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MKD', 'Macedonia Denar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MGA', 'Malagasy Ariary', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MWK', 'Malawian Kwacha', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('RM', 'Malaysia Ringgit', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MYR', 'Malaysian Ringgit', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MVR', 'Maldivian Rufiyaa', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MRO', 'Mauritanian Ouguiya', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MUR', 'Mauritius Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MXN', 'Mexico Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MDL', 'Moldovan Leu', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MNT', 'Mongolia Tughrik', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MAD', 'Moroccan Dirham', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('MZN', 'Mozambique Metical', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NAD', 'Namibia Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NPR', 'Nepal Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ANG', 'Netherlands Antilles Guilder', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NZD', 'New Zealand Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('VUV', 'Ni-Vanuatu Vatu', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NIO', 'Nicaragua Cordoba', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NGN', 'Nigeria Naira', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('NOK', 'Norway Krone', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('OMR', 'Oman Rial', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PKR', 'Pakistan Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PAB', 'Panama Balboa', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PGK', 'Papua New Guinean Kina', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PYG', 'Paraguay Guarani', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PEN', 'Peru Nuevo Sol', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PHP', 'Philippines Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('PLN', 'Poland Zloty', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('QAR', 'Qatar Riyal', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('RON', 'Romania New Leu', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('RUB', 'Russia Ruble', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('RWF', 'Rwandan Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SHP', 'Saint Helena Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('WST', 'Samoan Tala', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('STD', 'Sao Tomean Dobra', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SAR', 'Saudi Arabia Riyal', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SPL', 'Seborgan Luigino', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('RSD', 'Serbia Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SCR', 'Seychelles Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SLL', 'Sierra Leonean Leone', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SGD', 'Singapore Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SBD', 'Solomon Islands Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SOS', 'Somalia Shilling', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ZAR', 'South Africa Rand', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('LKR', 'Sri Lanka Rupee', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SDG', 'Sudanese Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SRD', 'Suriname Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SZL', 'Swazi Lilangeni', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SEK', 'Sweden Krona', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('CHF', 'Switzerland Franc', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('SYP', 'Syria Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TWD', 'Taiwan New Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TJS', 'Tajikistani Somoni', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TZS', 'Tanzanian Shilling', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('THB', 'Thailand Baht', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TOP', 'Tongan Pa\'anga', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TTD', 'Trinidad and Tobago Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TND', 'Tunisian Dinar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TRY', 'Turkey Lira', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TMT', 'Turkmenistani Manat', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('TVD', 'Tuvalu Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('UGX', 'Ugandan Shilling', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('UAH', 'Ukraine Hryvna', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('GBP', 'United Kingdom Pound', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('USD', 'United States Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('UYU', 'Uruguay Peso', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('UZS', 'Uzbekistan Som', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('VEF', 'Venezuela Bolivar', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('VND', 'Viet Nam Dong', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('YER', 'Yemen Rial', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ZMK', 'Zambian Kwacha', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ZMW', 'Zambian Kwacha', $_SESSION['s_installation_currency']);
echo $form->showDropdownOption('ZWD', 'Zimbabwe Dollar', $_SESSION['s_installation_currency']);
echo $form->showDropdownBottom('');
?>
<BR>
<a href="../requirements/"><?php echo $layout->showButton('button', _('Go Back')); ?></a>
<?php
echo $form->showSubmitButton(_('Next Step'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>

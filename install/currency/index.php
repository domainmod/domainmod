<?php
/**
 * /install/currency/index.php
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

// The currency to pre-select: the one already chosen in this installation, or a guess from the browser's region (see
// DomainMOD\BrowserLocale), or US Dollars when there's no telling
if ($_SESSION['s_installation_currency'] !== '') {
    $selected_currency = $_SESSION['s_installation_currency'];
} else {
    $browser_locale = new DomainMOD\BrowserLocale();
    $selected_currency = $browser_locale->guessCurrency('USD');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The installer runs while signed out, so it checks the security token itself (see DomainMOD\Csrf).
    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

    $_SESSION['s_installation_currency'] = $sanitize->text($_POST['new_currency']);

    header("Location: ../timezone/");
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('install'); ?>
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php
echo $form->showFormTop('');

echo $form->showDropdownTop('new_currency', _('Currency'), '', '', '', true, true);
echo $form->showDropdownOption('AFN', 'Afghanistan Afghani', $selected_currency);
echo $form->showDropdownOption('ALL', 'Albania Lek', $selected_currency);
echo $form->showDropdownOption('DZD', 'Algerian Dinar', $selected_currency);
echo $form->showDropdownOption('AOA', 'Angolan Kwanza', $selected_currency);
echo $form->showDropdownOption('ARS', 'Argentina Peso', $selected_currency);
echo $form->showDropdownOption('AMD', 'Armenian Dram', $selected_currency);
echo $form->showDropdownOption('AWG', 'Aruba Guilder', $selected_currency);
echo $form->showDropdownOption('AUD', 'Australia Dollar', $selected_currency);
echo $form->showDropdownOption('AZN', 'Azerbaijan New Manat', $selected_currency);
echo $form->showDropdownOption('BSD', 'Bahamas Dollar', $selected_currency);
echo $form->showDropdownOption('BHD', 'Bahraini Dinar', $selected_currency);
echo $form->showDropdownOption('BDT', 'Bangladeshi Taka', $selected_currency);
echo $form->showDropdownOption('BBD', 'Barbados Dollar', $selected_currency);
echo $form->showDropdownOption('LSL', 'Basotho Loti', $selected_currency);
echo $form->showDropdownOption('BYR', 'Belarus Ruble', $selected_currency);
echo $form->showDropdownOption('BZD', 'Belize Dollar', $selected_currency);
echo $form->showDropdownOption('BMD', 'Bermuda Dollar', $selected_currency);
echo $form->showDropdownOption('BTN', 'Bhutanese Ngultrum', $selected_currency);
echo $form->showDropdownOption('BOB', 'Bolivia Boliviano', $selected_currency);
echo $form->showDropdownOption('BAM', 'Bosnia and Herzegovina Convertible Marka', $selected_currency);
echo $form->showDropdownOption('BWP', 'Botswana Pula', $selected_currency);
echo $form->showDropdownOption('BRL', 'Brazil Real', $selected_currency);
echo $form->showDropdownOption('BND', 'Brunei Darussalam Dollar', $selected_currency);
echo $form->showDropdownOption('BGN', 'Bulgaria Lev', $selected_currency);
echo $form->showDropdownOption('MMK', 'Burmese Kyat', $selected_currency);
echo $form->showDropdownOption('BIF', 'Burundian Franc', $selected_currency);
echo $form->showDropdownOption('KHR', 'Cambodia Riel', $selected_currency);
echo $form->showDropdownOption('CAD', 'Canada Dollar', $selected_currency);
echo $form->showDropdownOption('CVE', 'Cape Verdean Escudo', $selected_currency);
echo $form->showDropdownOption('KYD', 'Cayman Islands Dollar', $selected_currency);
echo $form->showDropdownOption('XAF', 'Central African CFA Franc BEAC', $selected_currency);
echo $form->showDropdownOption('XOF', 'CFA Franc', $selected_currency);
echo $form->showDropdownOption('XPF', 'CFP Franc', $selected_currency);
echo $form->showDropdownOption('CLP', 'Chile Peso', $selected_currency);
echo $form->showDropdownOption('CNY', 'China Yuan Renminbi', $selected_currency);
echo $form->showDropdownOption('COP', 'Colombia Peso', $selected_currency);
echo $form->showDropdownOption('KMF', 'Comoran Franc', $selected_currency);
echo $form->showDropdownOption('CDF', 'Congolese Franc', $selected_currency);
echo $form->showDropdownOption('CRC', 'Costa Rica Colon', $selected_currency);
echo $form->showDropdownOption('HRK', 'Croatia Kuna', $selected_currency);
echo $form->showDropdownOption('CUP', 'Cuba Peso', $selected_currency);
echo $form->showDropdownOption('CUC', 'Cuban Convertible Peso', $selected_currency);
echo $form->showDropdownOption('CZK', 'Czech Republic Koruna', $selected_currency);
echo $form->showDropdownOption('DKK', 'Denmark Krone', $selected_currency);
echo $form->showDropdownOption('DJF', 'Djiboutian Franc', $selected_currency);
echo $form->showDropdownOption('DOP', 'Dominican Republic Peso', $selected_currency);
echo $form->showDropdownOption('XCD', 'East Caribbean Dollar', $selected_currency);
echo $form->showDropdownOption('EGP', 'Egypt Pound', $selected_currency);
echo $form->showDropdownOption('SVC', 'El Salvador Colon', $selected_currency);
echo $form->showDropdownOption('AED', 'Emirati Dirham', $selected_currency);
echo $form->showDropdownOption('EEK', 'Estonia Kroon', $selected_currency);
echo $form->showDropdownOption('ETB', 'Ethiopian Birr', $selected_currency);
echo $form->showDropdownOption('EUR', 'Euro Member Countries', $selected_currency);
echo $form->showDropdownOption('FKP', 'Falkland Islands (Malvinas) Pound', $selected_currency);
echo $form->showDropdownOption('FJD', 'Fiji Dollar', $selected_currency);
echo $form->showDropdownOption('GMD', 'Gambian Dalasi', $selected_currency);
echo $form->showDropdownOption('GEL', 'Georgian Lari', $selected_currency);
echo $form->showDropdownOption('GHC', 'Ghana Cedis', $selected_currency);
echo $form->showDropdownOption('GHS', 'Ghanaian Cedi', $selected_currency);
echo $form->showDropdownOption('GIP', 'Gibraltar Pound', $selected_currency);
echo $form->showDropdownOption('GTQ', 'Guatemala Quetzal', $selected_currency);
echo $form->showDropdownOption('GGP', 'Guernsey Pound', $selected_currency);
echo $form->showDropdownOption('GNF', 'Guinean Franc', $selected_currency);
echo $form->showDropdownOption('GYD', 'Guyana Dollar', $selected_currency);
echo $form->showDropdownOption('HTG', 'Haitian Gourde', $selected_currency);
echo $form->showDropdownOption('HNL', 'Honduras Lempira', $selected_currency);
echo $form->showDropdownOption('HKD', 'Hong Kong Dollar', $selected_currency);
echo $form->showDropdownOption('HUF', 'Hungary Forint', $selected_currency);
echo $form->showDropdownOption('ISK', 'Iceland Krona', $selected_currency);
echo $form->showDropdownOption('INR', 'India Rupee', $selected_currency);
echo $form->showDropdownOption('IDR', 'Indonesia Rupiah', $selected_currency);
echo $form->showDropdownOption('IRR', 'Iran Rial', $selected_currency);
echo $form->showDropdownOption('IQD', 'Iraqi Dinar', $selected_currency);
echo $form->showDropdownOption('IMP', 'Isle of Man Pound', $selected_currency);
echo $form->showDropdownOption('ILS', 'Israel Shekel', $selected_currency);
echo $form->showDropdownOption('JMD', 'Jamaica Dollar', $selected_currency);
echo $form->showDropdownOption('JPY', 'Japan Yen', $selected_currency);
echo $form->showDropdownOption('JEP', 'Jersey Pound', $selected_currency);
echo $form->showDropdownOption('JOD', 'Jordanian Dinar', $selected_currency);
echo $form->showDropdownOption('KZT', 'Kazakhstan Tenge', $selected_currency);
echo $form->showDropdownOption('KES', 'Kenyan Shilling', $selected_currency);
echo $form->showDropdownOption('KPW', 'Korea (North) Won', $selected_currency);
echo $form->showDropdownOption('KRW', 'Korea (South) Won', $selected_currency);
echo $form->showDropdownOption('KWD', 'Kuwaiti Dinar', $selected_currency);
echo $form->showDropdownOption('KGS', 'Kyrgyzstan Som', $selected_currency);
echo $form->showDropdownOption('LAK', 'Laos Kip', $selected_currency);
echo $form->showDropdownOption('LVL', 'Latvia Lat', $selected_currency);
echo $form->showDropdownOption('LBP', 'Lebanon Pound', $selected_currency);
echo $form->showDropdownOption('LRD', 'Liberia Dollar', $selected_currency);
echo $form->showDropdownOption('LYD', 'Libyan Dinar', $selected_currency);
echo $form->showDropdownOption('LTL', 'Lithuania Litas', $selected_currency);
echo $form->showDropdownOption('MOP', 'Macau Pataca', $selected_currency);
echo $form->showDropdownOption('MKD', 'Macedonia Denar', $selected_currency);
echo $form->showDropdownOption('MGA', 'Malagasy Ariary', $selected_currency);
echo $form->showDropdownOption('MWK', 'Malawian Kwacha', $selected_currency);
echo $form->showDropdownOption('RM', 'Malaysia Ringgit', $selected_currency);
echo $form->showDropdownOption('MYR', 'Malaysian Ringgit', $selected_currency);
echo $form->showDropdownOption('MVR', 'Maldivian Rufiyaa', $selected_currency);
echo $form->showDropdownOption('MRO', 'Mauritanian Ouguiya', $selected_currency);
echo $form->showDropdownOption('MUR', 'Mauritius Rupee', $selected_currency);
echo $form->showDropdownOption('MXN', 'Mexico Peso', $selected_currency);
echo $form->showDropdownOption('MDL', 'Moldovan Leu', $selected_currency);
echo $form->showDropdownOption('MNT', 'Mongolia Tughrik', $selected_currency);
echo $form->showDropdownOption('MAD', 'Moroccan Dirham', $selected_currency);
echo $form->showDropdownOption('MZN', 'Mozambique Metical', $selected_currency);
echo $form->showDropdownOption('NAD', 'Namibia Dollar', $selected_currency);
echo $form->showDropdownOption('NPR', 'Nepal Rupee', $selected_currency);
echo $form->showDropdownOption('ANG', 'Netherlands Antilles Guilder', $selected_currency);
echo $form->showDropdownOption('NZD', 'New Zealand Dollar', $selected_currency);
echo $form->showDropdownOption('VUV', 'Ni-Vanuatu Vatu', $selected_currency);
echo $form->showDropdownOption('NIO', 'Nicaragua Cordoba', $selected_currency);
echo $form->showDropdownOption('NGN', 'Nigeria Naira', $selected_currency);
echo $form->showDropdownOption('NOK', 'Norway Krone', $selected_currency);
echo $form->showDropdownOption('OMR', 'Oman Rial', $selected_currency);
echo $form->showDropdownOption('PKR', 'Pakistan Rupee', $selected_currency);
echo $form->showDropdownOption('PAB', 'Panama Balboa', $selected_currency);
echo $form->showDropdownOption('PGK', 'Papua New Guinean Kina', $selected_currency);
echo $form->showDropdownOption('PYG', 'Paraguay Guarani', $selected_currency);
echo $form->showDropdownOption('PEN', 'Peru Nuevo Sol', $selected_currency);
echo $form->showDropdownOption('PHP', 'Philippines Peso', $selected_currency);
echo $form->showDropdownOption('PLN', 'Poland Zloty', $selected_currency);
echo $form->showDropdownOption('QAR', 'Qatar Riyal', $selected_currency);
echo $form->showDropdownOption('RON', 'Romania New Leu', $selected_currency);
echo $form->showDropdownOption('RUB', 'Russia Ruble', $selected_currency);
echo $form->showDropdownOption('RWF', 'Rwandan Franc', $selected_currency);
echo $form->showDropdownOption('SHP', 'Saint Helena Pound', $selected_currency);
echo $form->showDropdownOption('WST', 'Samoan Tala', $selected_currency);
echo $form->showDropdownOption('STD', 'Sao Tomean Dobra', $selected_currency);
echo $form->showDropdownOption('SAR', 'Saudi Arabia Riyal', $selected_currency);
echo $form->showDropdownOption('SPL', 'Seborgan Luigino', $selected_currency);
echo $form->showDropdownOption('RSD', 'Serbia Dinar', $selected_currency);
echo $form->showDropdownOption('SCR', 'Seychelles Rupee', $selected_currency);
echo $form->showDropdownOption('SLL', 'Sierra Leonean Leone', $selected_currency);
echo $form->showDropdownOption('SGD', 'Singapore Dollar', $selected_currency);
echo $form->showDropdownOption('SBD', 'Solomon Islands Dollar', $selected_currency);
echo $form->showDropdownOption('SOS', 'Somalia Shilling', $selected_currency);
echo $form->showDropdownOption('ZAR', 'South Africa Rand', $selected_currency);
echo $form->showDropdownOption('LKR', 'Sri Lanka Rupee', $selected_currency);
echo $form->showDropdownOption('SDG', 'Sudanese Pound', $selected_currency);
echo $form->showDropdownOption('SRD', 'Suriname Dollar', $selected_currency);
echo $form->showDropdownOption('SZL', 'Swazi Lilangeni', $selected_currency);
echo $form->showDropdownOption('SEK', 'Sweden Krona', $selected_currency);
echo $form->showDropdownOption('CHF', 'Switzerland Franc', $selected_currency);
echo $form->showDropdownOption('SYP', 'Syria Pound', $selected_currency);
echo $form->showDropdownOption('TWD', 'Taiwan New Dollar', $selected_currency);
echo $form->showDropdownOption('TJS', 'Tajikistani Somoni', $selected_currency);
echo $form->showDropdownOption('TZS', 'Tanzanian Shilling', $selected_currency);
echo $form->showDropdownOption('THB', 'Thailand Baht', $selected_currency);
echo $form->showDropdownOption('TOP', 'Tongan Pa\'anga', $selected_currency);
echo $form->showDropdownOption('TTD', 'Trinidad and Tobago Dollar', $selected_currency);
echo $form->showDropdownOption('TND', 'Tunisian Dinar', $selected_currency);
echo $form->showDropdownOption('TRY', 'Turkey Lira', $selected_currency);
echo $form->showDropdownOption('TMT', 'Turkmenistani Manat', $selected_currency);
echo $form->showDropdownOption('TVD', 'Tuvalu Dollar', $selected_currency);
echo $form->showDropdownOption('UGX', 'Ugandan Shilling', $selected_currency);
echo $form->showDropdownOption('UAH', 'Ukraine Hryvna', $selected_currency);
echo $form->showDropdownOption('GBP', 'United Kingdom Pound', $selected_currency);
echo $form->showDropdownOption('USD', 'United States Dollar', $selected_currency);
echo $form->showDropdownOption('UYU', 'Uruguay Peso', $selected_currency);
echo $form->showDropdownOption('UZS', 'Uzbekistan Som', $selected_currency);
echo $form->showDropdownOption('VEF', 'Venezuela Bolivar', $selected_currency);
echo $form->showDropdownOption('VND', 'Viet Nam Dong', $selected_currency);
echo $form->showDropdownOption('YER', 'Yemen Rial', $selected_currency);
echo $form->showDropdownOption('ZMK', 'Zambian Kwacha', $selected_currency);
echo $form->showDropdownOption('ZMW', 'Zambian Kwacha', $selected_currency);
echo $form->showDropdownOption('ZWD', 'Zimbabwe Dollar', $selected_currency);
echo $form->showDropdownBottom('');
$install_back_url = WEB_ROOT . '/install/requirements/';
$install_next_label = sprintf(_('Next: %s'), _('Time Zone'));
require DIR_INC . '/layout/install-buttons.inc.php';
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>

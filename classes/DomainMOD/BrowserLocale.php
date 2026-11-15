<?php
/**
 * /classes/DomainMOD/BrowserLocale.php
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
namespace DomainMOD;

/*
 * Guesses a new install's settings from what the browser sends, for the installer to pre-select. Every guess is only a
 * starting point: a choice already saved in the session always wins, and the person can change any of them.
 */
class BrowserLocale
{
    public $languages;

    // The language a bare language tag (no region) points to, where DomainMOD has more than one for that language
    const LANGUAGE_DEFAULTS = array('en' => 'en_US', 'pt' => 'pt_PT', 'zh' => 'zh_CN');

    // English-speaking regions closer to Canadian English (colour, centre) than to American English
    const ENGLISH_CA_REGIONS = array('CA', 'GB', 'IE', 'AU', 'NZ', 'ZA', 'IN', 'SG', 'MT');

    // Chinese regions that use Traditional characters
    const CHINESE_TRADITIONAL = array('TW', 'HK', 'MO');

    // The region a bare language tag most likely means, for the currency guess (only where it's clear)
    const LANGUAGE_REGIONS = array('bg' => 'BG', 'bn' => 'BD', 'cs' => 'CZ', 'da' => 'DK', 'de' => 'DE', 'el' => 'GR',
        'es' => 'ES', 'et' => 'EE', 'fa' => 'IR', 'fi' => 'FI', 'fr' => 'FR', 'he' => 'IL', 'hi' => 'IN', 'hr' => 'HR',
        'hu' => 'HU', 'id' => 'ID', 'is' => 'IS', 'it' => 'IT', 'ja' => 'JP', 'ko' => 'KR', 'lt' => 'LT', 'lv' => 'LV',
        'mr' => 'IN', 'ms' => 'MY', 'nb' => 'NO', 'nl' => 'NL', 'nn' => 'NO', 'no' => 'NO', 'pl' => 'PL', 'pt' => 'PT',
        'ro' => 'RO', 'ru' => 'RU', 'sk' => 'SK', 'sl' => 'SI', 'sv' => 'SE', 'ta' => 'IN', 'te' => 'IN', 'th' => 'TH',
        'tr' => 'TR', 'uk' => 'UA', 'ur' => 'PK', 'vi' => 'VN', 'zh' => 'CN');

    // Each region's currency, limited to the codes the installer's currency list offers
    const REGION_CURRENCIES = array('AD' => 'EUR', 'AE' => 'AED', 'AR' => 'ARS', 'AT' => 'EUR', 'AU' => 'AUD',
        'BD' => 'BDT', 'BE' => 'EUR', 'BG' => 'EUR', 'BR' => 'BRL', 'CA' => 'CAD', 'CH' => 'CHF', 'CL' => 'CLP',
        'CN' => 'CNY', 'CO' => 'COP', 'CY' => 'EUR', 'CZ' => 'CZK', 'DE' => 'EUR', 'DK' => 'DKK', 'EE' => 'EUR',
        'EG' => 'EGP', 'ES' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'GB' => 'GBP', 'GR' => 'EUR', 'HK' => 'HKD',
        'HR' => 'EUR', 'HU' => 'HUF', 'ID' => 'IDR', 'IE' => 'EUR', 'IL' => 'ILS', 'IN' => 'INR', 'IR' => 'IRR',
        'IS' => 'ISK', 'IT' => 'EUR', 'JP' => 'JPY', 'KR' => 'KRW', 'LI' => 'CHF', 'LK' => 'LKR', 'LT' => 'EUR',
        'LU' => 'EUR', 'LV' => 'EUR', 'MC' => 'EUR', 'ME' => 'EUR', 'MT' => 'EUR', 'MX' => 'MXN', 'MY' => 'MYR',
        'NG' => 'NGN', 'NL' => 'EUR', 'NO' => 'NOK', 'NZ' => 'NZD', 'PE' => 'PEN', 'PH' => 'PHP', 'PK' => 'PKR',
        'PL' => 'PLN', 'PT' => 'EUR', 'RO' => 'RON', 'RU' => 'RUB', 'SA' => 'SAR', 'SE' => 'SEK', 'SG' => 'SGD',
        'SI' => 'EUR', 'SK' => 'EUR', 'SM' => 'EUR', 'TH' => 'THB', 'TR' => 'TRY', 'TW' => 'TWD', 'UA' => 'UAH',
        'US' => 'USD', 'VA' => 'EUR', 'VN' => 'VND', 'ZA' => 'ZAR');

    public function __construct($accept_language = null)
    {
        if ($accept_language === null) $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $this->languages = $this->parse((string) $accept_language);
    }

    // The browser's languages, most preferred first, as arrays of language, script, and region (such as fr, '', CA)
    public function parse($accept_language)
    {
        $found = array();
        foreach (explode(',', substr($accept_language, 0, 500)) as $position => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(str_replace('_', '-', trim($pieces[0])));
            if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{1,8})*$/', $tag)) continue;
            // A weight is q=0 to q=1 with up to three decimals, and an entry with a malformed one is skipped
            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $parameter) {
                if (!preg_match('/^\s*q\s*=/i', $parameter)) continue;
                if (!preg_match('/^\s*q\s*=\s*(0(\.[0-9]{0,3})?|1(\.0{0,3})?)\s*$/i', $parameter, $match)) continue 2;
                $quality = (float) $match[1];
            }
            if ($quality <= 0) continue;
            $subtags = explode('-', $tag);
            $script = '';
            $region = '';
            foreach (array_slice($subtags, 1) as $subtag) {
                // An extension or private use part (such as -u-co-phonebk) starts with a single letter, and nothing
                // after it is a script or region
                if (strlen($subtag) === 1) break;
                if ($script === '' && $region === '' && strlen($subtag) === 4) $script = strtoupper($subtag);
                elseif ($region === '' && preg_match('/^([a-z]{2}|[0-9]{3})$/', $subtag)) $region = strtoupper($subtag);
            }
            $found[] = array('quality' => $quality, 'position' => $position, 'language' => $subtags[0],
                'script' => $script, 'region' => $region);
        }
        usort($found, function ($a, $b) {
            return $b['quality'] <=> $a['quality'] ?: $a['position'] <=> $b['position'];
        });
        return $found;
    }

    // The closest of $available (locale names such as fr_FR.UTF-8) to the browser's languages, or '' for none. For
    // each language in order of preference, an exact language and region match wins, then the best match for the
    // language alone.
    public function guessLanguage($available)
    {
        $by_name = array();
        foreach ($available as $locale) {
            $by_name[strtolower(strtok($locale, '.'))] = $locale;
        }
        foreach ($this->languages as $language) {
            $code = $language['language'];
            // A script named outright (such as zh-Hant-CN) decides between the Chinese locales before the region does
            if ($code === 'zh' && in_array($language['script'], array('HANS', 'HANT'), true)) {
                $choice = $language['script'] === 'HANT' ? 'zh_tw' : 'zh_cn';
                if (isset($by_name[$choice])) return $by_name[$choice];
            }
            if ($language['region'] !== '' && isset($by_name[$code . '_' . strtolower($language['region'])])) {
                return $by_name[$code . '_' . strtolower($language['region'])];
            }
            $choice = $this->languageOnly($language);
            if ($choice !== '' && isset($by_name[strtolower($choice)])) return $by_name[strtolower($choice)];
            foreach ($by_name as $name => $locale) {
                if (strtok($name, '_') === $code) return $locale;
            }
        }
        return '';
    }

    // The locale a language without an exact region match points to, such as en-GB to en_CA and zh-HK to zh_TW
    private function languageOnly($language)
    {
        if ($language['language'] === 'en') {
            return in_array($language['region'], self::ENGLISH_CA_REGIONS, true) ? 'en_CA' : 'en_US';
        }
        if ($language['language'] === 'zh') {
            if ($language['script'] === 'HANT') return 'zh_TW';
            if ($language['script'] === 'HANS') return 'zh_CN';
            return in_array($language['region'], self::CHINESE_TRADITIONAL, true) ? 'zh_TW' : 'zh_CN';
        }
        if ($language['language'] === 'pt' && $language['region'] === 'BR') return 'pt_BR';
        return self::LANGUAGE_DEFAULTS[$language['language']] ?? '';
    }

    // The browser's region (such as CA), from the most preferred language that names one, or from a language whose
    // region is clear (such as ja), or '' when there's no telling
    public function guessRegion()
    {
        foreach ($this->languages as $language) {
            if (preg_match('/^[A-Z]{2}$/', $language['region'])) return $language['region'];
        }
        // A language with a wider region (such as es-419, Latin America) says the region isn't the language's own
        foreach ($this->languages as $language) {
            if ($language['region'] === '' && isset(self::LANGUAGE_REGIONS[$language['language']])) {
                return self::LANGUAGE_REGIONS[$language['language']];
            }
        }
        return '';
    }

    // The currency for the browser's region, or $fallback when the region is unknown or has no listed currency
    public function guessCurrency($fallback = 'USD')
    {
        return self::REGION_CURRENCIES[$this->guessRegion()] ?? $fallback;
    }

} //@formatter:on

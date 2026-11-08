<?php
/**
 * /classes/DomainMOD/RegistrarResponse.php
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
 * Safe parsing helpers for registrar API responses, used when reading domain lists for the Domain Queue. They never
 * emit PHP warnings on an empty, malformed, or unexpected response, so a failed call can be told apart from an
 * account with no domains.
 */
class RegistrarResponse
{

    // An XML body as an array, in the same shape as the registrar classes' convertToArray() (wrapped in array(...)),
    // or null if the body is empty or isn't XML (e.g. an HTML error page)
    public static function parseXml($body)
    {
        if (!is_string($body) || trim($body) === '') return null;
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) return null;
        $decoded = json_decode(json_encode(array($xml)), true);
        return is_array($decoded) ? $decoded : null;
    }

    // A JSON body as an array, or null if the body is empty or doesn't decode to an array or object
    public static function parseJson($body)
    {
        if (!is_string($body) || trim($body) === '') return null;
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    // Whether a JSON body is a list ([...]). json_decode() turns both {} and [] into an empty array, so the decoded
    // value alone can't tell an empty list from an unexpected object
    public static function isJsonList($body)
    {
        return is_string($body) && str_starts_with(ltrim($body), '[') && is_array(json_decode($body, true));
    }

    // Whether the value at a path in a JSON body is a JSON list ([...]), not an object ({...}) or anything else
    public static function isJsonListAt($body, ...$keys)
    {
        if (!is_string($body) || trim($body) === '') return false;
        $data = json_decode($body);
        foreach ($keys as $key) {
            if (!is_object($data) || !property_exists($data, $key)) return false;
            $data = $data->$key;
        }
        return is_array($data);
    }

    // Whether every XML container along a path to a list is a real container (an array, which an empty element also
    // converts to) up to where the path stops: an account with no domains may leave the list out, but text where a
    // container should be (e.g. <domains>x</domains>) means the response isn't a domain list
    public static function isXmlContainer($data, ...$keys)
    {
        foreach ($keys as $key) {
            if (!is_array($data)) return false;
            if (!array_key_exists($key, $data)) return true;
            $data = $data[$key];
        }
        return is_array($data);
    }

    // A nested value, or null if any key along the way is missing
    public static function get($data, ...$keys)
    {
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) return null;
            $data = $data[$key];
        }
        return $data;
    }

    // Records as a list. XML converted to an array gives a single record (or a single string) instead of a list of
    // one, and an absent list gives null
    public static function toList($value)
    {
        if ($value === null || $value === '' || $value === array()) return array();
        if (is_array($value) && array_is_list($value)) return $value;
        return array($value);
    }

    // Records from XML converted to an array, as a list. Like toList(), except that an empty array is one empty
    // record (an empty element such as <domain/>), not zero records: an absent list (null) is the only empty one
    public static function toXmlList($value)
    {
        if ($value === null) return array();
        if ($value === array()) return array(array());
        return self::toList($value);
    }

    // A string value, or '' for anything else (an empty XML element converts to an empty array)
    public static function text($value)
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

} //@formatter:on

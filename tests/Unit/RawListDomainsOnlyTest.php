<?php
/**
 * /tests/Unit/RawListDomainsOnlyTest.php
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
namespace DomainMOD\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Only the Domains list has a Raw List. The list pages and raw.php aren't classes, so these tests read their source:
 * the SSL Certificates and Hosting Services lists have no Raw List action and save no Raw List, raw.php reads only the
 * Domains list and shows a message for any other list's link (such as a bookmark of the SSL Certificates list's Raw
 * List from 4.26.0), and the Domains list keeps its Raw List. The Hosting Services list's Add button says what it adds.
 */
class RawListDomainsOnlyTest extends TestCase
{
    private function source($path)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $this->assertNotFalse($source, $path);
        return $source;
    }

    public static function otherLists()
    {
        return array('ssl certificates' => array('ssl/index.php'), 'hosting services' => array('hosting/index.php'));
    }

    // No Raw List action, link, or saved list on the SSL Certificates and Hosting Services lists
    #[DataProvider('otherLists')]
    public function testTheOtherListsHaveNoRawList($path)
    {
        $source = $this->source($path);
        foreach (array("_('Raw List')", 'raw.php', 'list-raw', 's_raw_list', 'savedListKey', 'dt_type') as $gone) {
            $this->assertStringNotContainsString($gone, $source, $path . ': ' . $gone);
        }
        // Export still follows the table's search box
        $this->assertMatchesRegularExpression("/'searchLinks' => array\\(\\s*'[a-z]+-list-export' =>/", $source);
    }

    // raw.php reads only the Domains list, and any other list's link gets a message before anything is read
    public function testRawListServesOnlyTheDomainsList()
    {
        $source = $this->source('raw.php');
        foreach (array("'ssl-certs'", "'hosting'", 'SslList', 'HostingList', 'ssl_cert_fields', 'hosting_service_fields',
            'new $list_class', 'Hosting::') as $gone) {
            $this->assertStringNotContainsString($gone, $source, $gone);
        }
        // One title, the Domains list's
        $this->assertSame(1, substr_count($source, "(Raw List)')"));
        $this->assertStringContainsString("\$page_title = _('Domains (Raw List)');", $source);
        // The currency rates are only made sure of for the Domains list, after the type is known
        $rates = strpos($source, "if (\$raw_list_domains) {\n    (new DomainMOD\\Conversion())->ensureRates(");
        $this->assertNotFalse($rates);
        $this->assertSame(1, substr_count($source, '->ensureRates('));
        $this->assertGreaterThan(strpos($source, '$raw_list_domains = '), $rates);
        // The message comes first, before the list's own messages or any read
        $refused = strpos($source, "if (!\$raw_list_domains) {\n\n    echo htmlspecialchars(_('Only the Domains list has a Raw List. Please open it from there.'), ENT_QUOTES, 'UTF-8') . \"<BR>\";\n\n} elseif (\$raw_list_message !== '') {");
        $this->assertNotFalse($refused);
        $this->assertGreaterThan($refused, strpos($source, '$list_query = new DomainMOD\DomainList('));
        $this->assertGreaterThan($refused, strpos($source, '->beginSnapshot()'));
        $this->assertSame(2, substr_count($source, 'new DomainMOD\DomainList('));
        $this->assertStringContainsString("DomainMOD\\CustomField::readFilterFields(\$pdo, 'domain_fields')", $source);
        $this->assertStringContainsString("new DomainMOD\\CustomFieldFilter(\$list_fields, 'dfd')", $source);
    }

    public static function requests()
    {
        $link = array('dt_list' => 'abc123abc123', 'dt_cfk' => 'def');
        return array(
            // The Domains list's own links, and an old bookmark of it, still read the list
            'a domains link' => array(array('dt_type' => 'domains') + $link, 'domains', true),
            'a domains link after another tab saved an older list' => array(array('dt_type' => 'domains') + $link, 'ssl-certs', true),
            'a domains link with a search' => array(array('dt_type' => 'domains', 'dt_search' => 'x', 'dt_layout' => 'l') + $link, 'domains', true),
            'an old domains bookmark' => array(array(), 'domains', true),
            // The SSL Certificates list's Raw List links from 4.26.0, and any other list's
            'an ssl certificates link' => array(array('dt_type' => 'ssl-certs') + $link, 'ssl-certs', false),
            'an ssl certificates link with domains saved' => array(array('dt_type' => 'ssl-certs') + $link, 'domains', false),
            'a hosting services link' => array(array('dt_type' => 'hosting') + $link, 'hosting', false),
            'a type without the rest of the link' => array(array('dt_type' => 'ssl-certs'), 'domains', false),
            // A Domains type that doesn't name its list reads only a saved Domains list, as an old bookmark does
            'a domains type alone with domains saved' => array(array('dt_type' => 'domains'), 'domains', true),
            'a domains type alone with ssl certificates saved' => array(array('dt_type' => 'domains'), 'ssl-certs', false),
            'a domains type with a list for a list' => array(array('dt_type' => 'domains', 'dt_list' => array('x')), 'hosting', false),
            // An old bookmark when the saved list isn't the Domains list, or nothing is saved
            'an old bookmark with ssl certificates saved' => array(array(), 'ssl-certs', false),
            'an old bookmark with hosting services saved' => array(array(), 'hosting', false),
            'an old bookmark with nothing saved' => array(array(), '', false),
            // A link that names its list without dt_type=domains
            'a link without a type' => array($link, 'domains', false),
            'a link with an empty type' => array(array('dt_type' => '') + $link, 'domains', false),
            'a link with a list for a type' => array(array('dt_type' => array('domains')) + $link, 'domains', false),
            'a type that is a list' => array(array('dt_type' => array('domains')), 'domains', false),
        );
    }

    // Which requests read the Domains list: the type a link names, never the saved list's when it names one, and the
    // saved list's type for a plain link (an old bookmark)
    #[DataProvider('requests')]
    public function testOnlyDomainsRequestsReadTheList($get, $saved_type, $domains)
    {
        $source = $this->source('raw.php');
        $this->assertSame(1, preg_match('/^\$raw_list_link = .*?^\$raw_list_domains = .*?;$/ms', $source, $match));
        $saved_get = $_GET;
        $saved_session = $_SESSION ?? null;
        $_GET = $get;
        $_SESSION = array('s_raw_list_type' => $saved_type);
        set_error_handler(function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $this->assertSame($domains, eval($match[0] . "\nreturn \$raw_list_domains;"));
        } finally {
            restore_error_handler();
            $_GET = $saved_get;
            if ($saved_session === null) unset($_SESSION); else $_SESSION = $saved_session;
        }
    }

    // The Domains list keeps its Raw List, saved for raw.php and linked by type
    public function testTheDomainsListKeepsItsRawList()
    {
        $source = $this->source('domains/index.php');
        $this->assertStringContainsString("\$_SESSION['s_raw_list_type'] = 'domains';", $source);
        $this->assertStringContainsString("'dt_type' => 'domains'", $source);
        $this->assertStringContainsString("\$layout->addPageAction(_('Raw List'), \$raw_list_url, 'bi-list-ul', array('target' => '_blank', 'id' => 'domain-list-raw'));", $source);
        $this->assertStringContainsString("'domain-list-raw' => array('dt_layout' => \$list_columns->layoutKey()),", $source);
    }

    // The Hosting Services list's Add buttons all say Add Hosting Service
    public function testTheHostingListsAddButtonsSayAddHostingService()
    {
        $source = $this->source('hosting/index.php');
        $this->assertStringNotContainsString("_('Add Service')", $source);
        $this->assertSame(2, substr_count($source, "\$layout->setPageAddAction(_('Add Hosting Service'), 'add.php');"));
        $this->assertStringContainsString("'/hosting/add.php', _('Add Hosting Service'),", $source);
    }

} //@formatter:on

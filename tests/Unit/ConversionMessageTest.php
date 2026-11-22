<?php
/**
 * /tests/Unit/ConversionMessageTest.php
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
 * A conversion rate update whose lookups failed is shown as a warning, not a success: the pages that show
 * Conversion::updateRates()' message pick the message type from Conversion::$skipped, and the Task Scheduler's Run Now
 * adds up every user's skipped rates, so one failure among many still warns (found at 4.27.0's [K], where Run Now said
 * "Conversion Rates Updated" and the failure showed as a green success).
 */
class ConversionMessageTest extends TestCase
{

    public static function pages()
    {
        return array('maintenance' => array('maintenance/update-conversions.php'), 'profile' => array('settings/profile/index.php'));
    }

    #[DataProvider('pages')]
    public function testThePageShowsAFailedUpdateAsAWarning($page)
    {
        $code = file_get_contents(dirname(__DIR__, 2) . '/' . $page);
        $this->assertStringContainsString("\$message_type = \$conversion->skipped > 0 ? 's_message_warning' : 's_message_success';", $code);
        $this->assertStringContainsString("\$_SESSION[\$message_type] = (\$_SESSION[\$message_type] ?? '') . \$result_message;", $code);
        $this->assertStringNotContainsString("\$_SESSION['s_message_success'] .= \$conversion->updateRates(", $code);
        $this->assertStringNotContainsString("\$_SESSION['s_message_success'] .= \$result_message", $code);
    }

    public function testRunNowWarnsWhenAnyUsersLookupFailed()
    {
        $code = file_get_contents(dirname(__DIR__, 2) . '/admin/scheduler/run.php');
        $this->assertStringContainsString('$conversion_skipped += $conversion->skipped;', $code);
        $this->assertMatchesRegularExpression('/if \(\(\$conversion_skipped \?\? 0\) > 0\) \{\s+\$_SESSION\[\'s_message_warning\'\]/', $code);
    }

} //@formatter:on

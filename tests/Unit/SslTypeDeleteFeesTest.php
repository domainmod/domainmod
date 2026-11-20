<?php
/**
 * /tests/Unit/SslTypeDeleteFeesTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * Deleting an SSL type only checked for certificates, so a fee entered for a type with no certificates stayed in
 * ssl_fees, out of sight, since the provider's fee list only shows fees for types that exist. The type's fees are now
 * deleted with it, in one transaction (as deleting an SSL provider deletes its fees), and the Danger Zone says so.
 * These tests read the page source, since the page isn't a class.
 */
class SslTypeDeleteFeesTest extends TestCase
{
    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/edit/ssl-type.php');
        $this->assertNotFalse($source);
        return $source;
    }

    // The delete step, from its certificate check to the end of the file's PHP
    private function deleteStep()
    {
        $source = $this->source();
        $start = strpos($source, 'if ($del === 1) {');
        $this->assertNotFalse($start);
        return substr($source, $start, strpos($source, '?>', $start) - $start);
    }

    public function testTheTypesFeesAreDeletedBeforeTheTypeInOneTransaction()
    {
        $step = $this->deleteStep();

        $begin = strpos($step, '$pdo->beginTransaction();');
        $fees = strpos($step, "DELETE FROM ssl_fees\n                WHERE type_id = :ssltid");
        $type = strpos($step, "DELETE FROM ssl_cert_types\n                WHERE id = :ssltid");
        $commit = strpos($step, '$pdo->commit();');

        $this->assertNotFalse($begin);
        $this->assertNotFalse($fees);
        $this->assertNotFalse($type);
        $this->assertNotFalse($commit);
        $this->assertTrue($begin < $fees && $fees < $type && $type < $commit);
        // Each delete is run, not just prepared
        $this->assertStringContainsString('$stmt->execute();', substr($step, $fees, $type - $fees));
        $this->assertStringContainsString('$stmt->execute();', substr($step, $type, $commit - $type));
        $this->assertStringContainsString('$pdo->rollback();', $step);
    }

    public function testATypeWithCertificatesIsStillKept()
    {
        $step = $this->deleteStep();
        $check = strpos($step, "FROM ssl_certs\n        WHERE type_id = :ssltid");
        $this->assertNotFalse($check);
        $this->assertLessThan(strpos($step, 'DELETE FROM ssl_fees'), $check);
        $this->assertStringContainsString("_('This Type has SSL Certificates associated with it and cannot be deleted.')", $step);
    }

    public function testTheDangerZoneSaysTheFeesGoToo()
    {
        $this->assertStringContainsString("_('This removes %s and its fees. It can only be deleted when no SSL certificates use it. It can\\'t be undone.')", $this->source());
    }

} //@formatter:on

<?php
/**
 * /_includes/updates/4.06.00-current.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
<?php //@formatter:off

// upgrade database from 4.06.00 to 4.06.01
if ($current_db_version === '4.06.00') {

    $old_version = '4.06.00';
    $new_version = '4.06.01';

    try {

        $pdo->beginTransaction();
        $upgrade->database($new_version);
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.06.01 to 4.07.00
if ($current_db_version === '4.06.01') {

    $old_version = '4.06.01';
    $new_version = '4.07.00';

    try {

        $pdo->beginTransaction();
        $upgrade->database($new_version);
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

//@formatter:on

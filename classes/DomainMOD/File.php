<?php
/**
 * /classes/DomainMOD/File.php
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

class File
{

    public function openFile($base_filename, $append_data)
    {
        $full_filename = DIR_TEMP . '/' . $base_filename . '_' . $append_data . '.csv';
        $open_file = fopen($full_filename, 'w');
        return array($full_filename, $open_file);
    }

    public function writeRow($open_file, $row_contents)
    {
        fputcsv($open_file, $row_contents);
    }

    public function writeBlankRow($open_file)
    {
        $blank_line = array('');
        fputcsv($open_file, $blank_line);
    }

    public function closeFile($open_file)
    {
        fclose($open_file);
    }

}

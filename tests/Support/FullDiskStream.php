<?php
/**
 * /tests/Support/FullDiskStream.php
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
namespace DomainMOD\Tests\Support;

/*
 * A stream wrapper over a string in memory ($contents) that reads, seeks, truncates, and locks, but whose writes always
 * fail, as on a full disk, for testing that a failed write doesn't lose what was there
 */
class FullDiskStream
{
    public static $contents = '';
    public $context;
    protected $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $data = (string) substr(self::$contents, $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$contents);
    }

    public function stream_seek($offset, $whence)
    {
        if ($whence !== SEEK_SET || $offset < 0) return false;
        $this->position = $offset;
        return true;
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_truncate($size)
    {
        self::$contents = str_pad(substr(self::$contents, 0, $size), $size, "\0");
        return true;
    }

    public function stream_write($data)
    {
        return false;
    }

    public function stream_lock($operation)
    {
        return true;
    }

    public function stream_flush()
    {
        return true;
    }

    public function stream_close()
    {
    }

} //@formatter:on

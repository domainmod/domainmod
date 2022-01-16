<?php
/**
 * /classes/DomainMOD/Segment.php
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

class Segment
{
    public $deeb;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.segment');
    }

    public function trimLength($input_segment, $max_length)
    {
        if (strlen($input_segment) > $max_length) {

            $output_segment = substr($input_segment, 0, $max_length);
            $pos = strrpos($output_segment, ", ");

            if ($pos === false) {

                return substr($output_segment, 0, $max_length) . "...";

            }

            return substr($output_segment, 0, $pos) . "...";

        } else {

            return $input_segment;

        }
    }

    public function getSegment($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `segment`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getName($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment name';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getNumberOfDomains($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT number_of_domains
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Number of Domains for Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on

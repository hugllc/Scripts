<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @subpackage Test
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
 */
        require_once(dirname(__FILE__).'/../head.inc.php');
	
	if (empty($argv[1])) {
		die("DeviceID must be specified!\r\n");	
	}

        for ($i = 0; $i < count($newArgv); $i++) {
            switch($newArgv[$i]) {
                // Gateway IP address
                case "-D":
                    $i++;
                    $forceStart = $newArgv[$i];
                    break;
            }
        }


	$Info = $endpoint->getDevice($DeviceID, "ID");

	$query = "SELECT * FROM ".$endpoint->raw_history_table." WHERE ";
	$query .= " DeviceKey=".$Info["DeviceKey"];
	if (!is_null($pktCommand)) $query .= " AND sendCommand='".$pktCommand."' ";
	if (!is_null($forceStart)) $query .= " AND Date < '".$forceStart."' ";
	$query .= " AND Status='GOOD' ";
	$query .= " ORDER BY Date DESC ";
	$query .= " LIMIT 0, 2 ";

	$rHist = $endpoint->db->getArray($query);
        $rHist = array_reverse($rHist);

	$packet = $endpoint->InterpSensors($Info, $rHist);
	$endpoint->modifyUnits($packet, $Info, 2, $Info['params']['dType'], $Info['params']['Units']);
	var_dump($packet);
?>

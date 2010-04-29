<?php
/**
 * Checks the interpretation of packets.
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
require_once dirname(__FILE__).'/../../head.inc.php';

$rCount = 2;
for ($i = 0; $i < count($newArgv); $i++) {
    switch($newArgv[$i]) {
    // Gateway IP address
    case "-C":
        $i++;
        $rCount = $newArgv[$i];
        break;
    case "-D":
        $i++;
        $forceStart = $newArgv[$i];
        break;
    case "-f":
        $i++;
        $csvFile = $newArgv[$i];
        break;
    case "-n":
        $i++;
        $inputNum = (int)$newArgv[$i];
        break;
    }
}

if (empty($csvFile)) {
    if (empty($argv[1])) {
        die("DeviceID must be specified!\r\n");
    }
    $endpoint =& HUGnetDriver::getInstance($hugnet_config);
    $history =& $endpoint->getHistoryInstance(array("Type" => "raw"));
    $Info    = $endpoint->getDevice($DeviceID, "ID");


    $query .= " DeviceKey = ?";
    $data[] = $Info["DeviceKey"];
    if (!is_null($pktCommand)) {
        $query .= " AND sendCommand= ? ";
        $data[] = $pktCommand;
    }
    if (!is_null($forceStart)) {
        $query .= " AND Date < ? ";
        $data[] = $forceStart;
    }
    $query  .= " AND Status='GOOD' ";
    $orderby = " ORDER BY Date DESC ";
    var_dump($query);
    $rHist = $history->getWhere($query, $data, $rCount, 0, $orderby);
} else {
    $endpoint =& HUGnetDriver::getInstance(array());
    $history =& $endpoint->getHistoryInstance(array("Type" => "raw"));
    $history->createTable();
    $f = file($csvFile);
    $rHist = $history->fromCSV($f);
    $Info = $endpoint->driverInfo($rHist[0]);
}
$packet = $endpoint->InterpSensors($Info, $rHist);
$endpoint->modifyUnits($packet, $Info, 2, $Info['params']['dType'],
                       $Info['params']['Units']);
var_dump($Info);
if (is_null($inputNum)) {
    var_dump($packet);
} else {
    print "Input: ".$inputNum."\n";
    print "Type: 0x".dechex($Info["Types"][$inputNum])."\n";
    print "Data Type: ".$Info["dType"][$inputNum]."\n";
    print "Unit: ".$Info["Units"][$inputNum]."\n";
    $fields = array(
        "Raw" => "raw",
        "Data" => "data",
    );
    foreach ($fields as $name => $f) {
        print $name.":  ";
        foreach ($packet as $p) {
            print $p[$f][$inputNum]."\t";
        }
        print "\n";
    }
}
?>

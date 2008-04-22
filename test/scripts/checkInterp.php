<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007 Hunt Utilities Group, LLC
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
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
require_once(dirname(__FILE__).'/../../head.inc.php');

if (empty($argv[1])) {
    die("DeviceID must be specified!\r\n");    
}

$rCount = 2;
for ($i = 0; $i < count($newArgv); $i++) {
    switch($newArgv[$i]) {
        // Gateway IP address
        case "-D":
            $i++;
            $forceStart = $newArgv[$i];
            break;
        case "-C":
            $i++;
            $rCount = $newArgv[$i];
            break;
    }
}
$endpoint =& HUGnetDriver::getInstance($hugnet_config);

$Info = $endpoint->getDevice($DeviceID, "ID");
$history =& $endpoint->getHistoryInstance(array("Type" => "raw"));

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
$query .= " AND Status='GOOD' ";
$orderby = " ORDER BY Date DESC ";
var_dump($query);
$rHist = $history->getWhere($query, $data, $rCount, 0, $orderby);


$packet = $endpoint->InterpSensors($Info, $rHist);
$endpoint->modifyUnits($packet, $Info, 2, $Info['params']['dType'], $Info['params']['Units']);

var_dump($packet);
?>

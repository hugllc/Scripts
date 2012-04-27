<?php
/**
 * Converts old raw history into new raw history
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
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
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
$pktData = "";
require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/tables/GenericTable.php';

$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($hugnet_config["verbose"]+1);

$device = new DeviceContainer(array("group" => $group));
$gatewayKey = $config->script_gateway;

$where = "1";
$data = array();
$devs = $device->selectIDs(
    $where,
    $data
);
shuffle($devs);
// Go through the devices
foreach ($devs as $key) {
    $device->clearData();
    $device->getRow($key);

    $useTime = gmmktime(0, 0, 0, 02, 14, 11);
    $checkTime = gmmktime(0, 0, 0, 02, 15, 11);
    //print $remoteDevice->DeviceID."\n";
    if ($device->gateway()) {
        // Don't want to update gateways
        continue;
    } else if (($device->id < 0xFD0000)
        && ($device->params->DriverInfo["LastHistory"] >= $checkTime)
    ) {
        print $device->DeviceID."\n";
        $device->params->DriverInfo["LastHistory"] = $useTime;
        $device->params->DriverInfo["LastHistoryTry"] = $useTime;
        $device->params->DriverInfo["LastAverage15MIN"] = $useTime;
        $device->params->DriverInfo["LastAverage15MINTry"] = $useTime;
        $device->params->DriverInfo["LastAverageHOURLY"] = $useTime;
        $device->params->DriverInfo["LastAverageHOURLYTry"] = $useTime;
        unset($device->params->DriverInfo["LastAverageDailyTry"]);
        unset($device->params->DriverInfo["LastAverageDaily"]);
        unset($device->params->DriverInfo["LastAverage15MinTry"]);
        unset($device->params->DriverInfo["LastAverage15Min"]);
        $device->updateRow();
    }
};
// State we are looking for firmware
DeviceContainer::vprint(
    "Finished",
    HUGnetClass::VPRINT_NORMAL
);

?>

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

$remoteDevice = new DeviceContainer(array("group" => "old"));
$device = new DeviceContainer();
$gatewayKey = $config->script_gateway;
// State we are looking for firmware
DeviceContainer::vprint(
    "Upgrading the database from the old system. "
    ." This should only run once.",
    HUGnetClass::VPRINT_NORMAL
);
if (strtolower($gatewayKey) === "all") {
    $where = "";
    $data = array();
} else {
    $where = "GatewayKey = ?";
    $data = array($gatewayKey);
}

// Get the devices
$remoteDevice->selectInto(
    $where,
    $data
);
// Go through the devices
do {
    //print $remoteDevice->DeviceID."\n";
    if ($remoteDevice->gateway()) {
        // Don't want to update gateways
        continue;
    } else if ($remoteDevice->id < 0xFD0000) {
        $device->clearData();
        $device->getRow(hexdec($remoteDevice->DeviceID));
        if ($device->isEmpty()) {
            $device->fromArray($remoteDevice->toDB());
            // Replace the row
            if ($device->insertRow(false)) {
                // State we are looking for firmware
                DeviceContainer::vprint(
                    "Upgraded DeviceID ".$device->DeviceID,
                    HUGnetClass::VPRINT_NORMAL
                );
            }
        }
    }
} while ($remoteDevice->nextInto());
// State we are looking for firmware
DeviceContainer::vprint(
    "Finished",
    HUGnetClass::VPRINT_NORMAL
);

?>

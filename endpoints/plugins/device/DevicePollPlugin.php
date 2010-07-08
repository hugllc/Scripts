<?php
/**
 * This is the default endpoint driver and the base for all other
 * endpoint drivers.
 *
 * PHP Version 5
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007-2010 Hunt Utilities Group, LLC
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA  02110-1301, USA.
 * </pre>
 *
 * @category   Processes
 * @package    HUGnetLib
 * @subpackage Processes
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
/** This is for the base class */
require_once HUGNET_INCLUDE_PATH."/base/DeviceProcessPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/containers/ConfigContainer.php";
require_once HUGNET_INCLUDE_PATH."/containers/PacketContainer.php";
require_once HUGNET_INCLUDE_PATH."/interfaces/PacketConsumerInterface.php";

/**
 * This class has functions that relate to the manipulation of elements
 * of the devInfo array.
 *
 * @category   Processes
 * @package    HUGnetLib
 * @subpackage Processes
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DevicePollPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DevicePoll",
        "Type" => "deviceProcess",
        "Class" => "DevicePollPlugin",
        "Priority" => 50,
    );
    /**
    * This function sets up the driver object, and the database object.  The
    * database object is taken from the driver object.
    *
    * @param mixed         $config The configuration array
    * @param DeviceProcess &$obj   The controller object
    *
    * @return null
    */
    public function __construct($config, DeviceProcess &$obj)
    {
        parent::__construct($config, $obj);
        $this->enable = $this->control->myConfig->poll["enable"];
        if (!$this->enable) {
            return;
        }
        $this->unsolicited = new DeviceContainer();
        $this->gatewayKey = $this->control->myConfig->script_gateway;
        // State we are here
        self::vprint(
            "Registed class ".self::$registerPlugin["Class"],
            HUGnetClass::VPRINT_NORMAL
        );
    }

    /**
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function main(DeviceContainer &$dev)
    {
        if ($dev->isEmpty() || !$this->ready($dev)) {
            return; // Can't do anything with an empty device
        }
        // Be verbose ;)
        self::vprint(
            "Polling ".$dev->DeviceID." LastPoll: ".
            date(
                "Y-m-d H:i:s", $dev->params->DriverInfo["LastPoll"]
            ),
            HUGnetClass::VPRINT_NORMAL
        );
        // Read the setup
        if (!$dev->readData()) {
            $this->_checkFail($dev);
        }
    }
    /**
    * This function should be used to wait between config attempts
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return int The number of packets routed
    */
    private function _checkFail(DeviceContainer &$dev)
    {
        // Print out the failure if verbose
        self::vprint(
            "Failed. Failures: ".$dev->params->DriverInfo["PollFail"]
            ." LastPoll try: "
            .date("Y-m-d H:i:s", $dev->params->DriverInfo["LastPollTry"]),
            HUGnetClass::VPRINT_NORMAL
        );
        // Log an error for every 10 failures
        if ((($dev->params->DriverInfo["PollFail"] % 10) == 0)
            && ($dev->params->DriverInfo["PollFail"] > 0)
        ) {
            $this->logError(
                "NOPOLL",
                $dev->DeviceID.": has failed "
                .$dev->params->DriverInfo["PollFail"]." polls",
                ErrorTable::SEVERITY_WARNING,
                "DeviceConfig::config"
            );
        }
    }
    /**
    * This deals with Unsolicited Packets
    *
    * @param PacketContainer &$pkt The packet that is to us
    *
    * @return string
    */
    public function packetConsumer(PacketContainer &$pkt)
    {
        if (!$this->enable) {
            return;
        }
        // Be verbose
        self::vprint(
            "Got Unsolicited Packet from: ".$pkt->From." Type: ".$pkt->Type,
            HUGnetClass::VPRINT_NORMAL
        );
        // Set up our DeviceContainer
        $this->unsolicited->clearData();
        // Find the device if it is there
        $this->unsolicited->selectInto("DeviceID = ?", array($pkt->From));

        if (!$this->unsolicited->isEmpty()) {
            // If it is not empty, reset the LastConfig.  This causes it to actually
            // try to get the config.
            $this->unsolicited->readDataTimeReset();
            // Set our gateway key
            $this->unsolicited->GatewayKey = $this->gatewayKey;
            // Set the device active
            $this->unsolicited->Active = 1;
            // Update the row
            $this->unsolicited->updateRow();
        }
    }
    /**
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready(DeviceContainer &$dev)
    {
        return $dev->readDataTime()
            && $this->enable;
    }
}
?>

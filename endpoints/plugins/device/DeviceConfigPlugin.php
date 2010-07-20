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
class DeviceConfigPlugin extends DeviceProcessPluginBase
    implements PacketConsumerInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DeviceConfig",
        "Type" => "deviceProcess",
        "Class" => "DeviceConfigPlugin",
        "Priority" => 10,
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
        $this->enable = $this->control->myConfig->config["enable"];
        $this->deactivate = $this->control->myConfig->config["deactivate"];
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
            "Checking ".$dev->DeviceID." Last Config: ".
            date(
                "Y-m-d H:i:s",
                $dev->params->DriverInfo["LastConfig"]
            ),
            HUGnetClass::VPRINT_NORMAL
        );
        $lastConfig = $dev->params->DriverInfo["LastConfig"];
        // Read the setup
        if (!$dev->readSetup()) {
            $this->_checkFail($dev);
            return false;
        } else {
            // Print out the failure if verbose
            self::vprint(
                "Success.  LastConfig set to: "
                .date("Y-m-d H:i:s", $dev->params->DriverInfo["LastConfig"])
                ." Interval: "
                .round(
                    (($dev->params->DriverInfo["LastConfig"] - $lastConfig)/60), 2
                ),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        return true;
    }
    /**
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function pre(DeviceContainer &$dev)
    {
        if ($dev->isEmpty() || !$dev->controller()) {
            return; // Looking for only controller boards here
        }
        // Be verbose ;)
        self::vprint(
            "Checking Controller ".$dev->DeviceID." LastConfig: ".
            date(
                "Y-m-d H:i:s",
                $dev->params->DriverInfo["LastConfig"]
            ),
            HUGnetClass::VPRINT_NORMAL
        );
        // Read the setup
        if (!$dev->readSetup()) {
            $this->_checkFail($dev);
            return false;
        }
        $dev->readSetup();
        return true;
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
            "Failed. Failures: ".$dev->params->DriverInfo["ConfigFail"]
            ." LastConfig try: "
            .date("Y-m-d H:i:s", $dev->params->DriverInfo["LastConfigTry"]),
            HUGnetClass::VPRINT_NORMAL
        );
        // Log an error for every 10 failures
        if ((($dev->params->DriverInfo["ConfigFail"] % 10) == 0)
            && ($dev->params->DriverInfo["ConfigFail"] > 0)
        ) {
            $this->logError(
                "NOCONFIG",
                $dev->DeviceID.": has failed "
                .$dev->params->DriverInfo["ConfigFail"]." configs",
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
        // Check if it is empty
        $empty = $this->unsolicited->isEmpty();
        // Set our gateway key
        $this->unsolicited->GatewayKey = $this->gatewayKey;
        // Set the last contact date
        $this->unsolicited->params->LastContact = time();

        if (!$empty) {
            // If it is not empty, reset the LastConfig.  This causes it to actually
            // try to get the config.
            $this->unsolicited->readSetupTimeReset();
            // Increment the unsolicited count
            $this->unsolicited->params->ProcessInfo["unsolicited"][$pkt->Command]++;
            // Update the row
            $this->unsolicited->updateRow();

        } else {
            // Reset the last config times.  This will cause a setup immediately
            $this->unsolicited->readSetupTimeReset();
            // This is a brand new device.  Set the DeviceID
            $this->unsolicited->id = hexdec($pkt->From);
            $this->unsolicited->DeviceID = $pkt->From;
            // Insert this row
            $this->unsolicited->insertRow();
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
        return $dev->readSetupTime() && $this->enable && !$dev->lostContact();
    }


}
?>

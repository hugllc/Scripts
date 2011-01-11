<?php
/**
 * This is the default endpoint driver and the base for all other
 * endpoint drivers.
 *
 * PHP Version 5
 * <pre>
 * HUGnetLib is a library of HUGnet code
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA  02110-1301, USA.
 * </pre>
 *
 * @category   Processes
 * @package    HUGnetLib
 * @subpackage Processes
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
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
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DevicePingPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DevicePing",
        "Type" => "deviceProcess",
        "Class" => "DevicePingPlugin",
        "Priority" => 0,
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
        if ($dev->isEmpty() || !$this->ready($dev) || ($dev->Active != 1)) {
            return; // Can't do anything with an empty device
        }
        // Be verbose ;)
        self::vprint(
            "Pinging ".$dev->DeviceID." Last Contact: ".
            date(
                "Y-m-d H:i:s", $dev->params->LastContact
            ),
            HUGnetClass::VPRINT_NORMAL
        );
        // Ping the device
        $pkt = new PacketContainer(array(
            "To" => $dev->DeviceID,
            "Retries" => 1,
        ));
        $ret = $pkt->ping("", true);
        if (!$ret) {
            $this->_checkFail($dev);
        } else {
            $dev->params->LastContact = time();
            // Print out the success if verbose
            self::vprint(
                "Success.  Last Contact set to: "
                .date("Y-m-d H:i:s", $dev->params->LastContact),
                HUGnetClass::VPRINT_NORMAL
            );

        }
        $dev->params->DriverInfo["LastPingTry"] = time();
        return $ret;
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
        $dev->params->DriverInfo["PingFail"]++;
        $dev->params->DriverInfo["LastPingTry"] = time();
        // Print out the failure if verbose
        self::vprint(
            "Failed. Failures: ".$dev->params->DriverInfo["PingFail"]
            ." Last Ping try: "
            .date("Y-m-d H:i:s", $dev->params->DriverInfo["LastPingTry"]),
            HUGnetClass::VPRINT_NORMAL
        );
        // Log an error for every 10 failures
        if ((($dev->params->DriverInfo["PingFail"] % 10) == 0)
            && ($dev->params->DriverInfo["PingFail"] > 0)
        ) {
            $this->logError(
                "NOPING",
                $dev->DeviceID.": has failed "
                .$dev->params->DriverInfo["PingFail"]." pings",
                ErrorTable::SEVERITY_WARNING,
                "DeviceConfig::config"
            );
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
        return $dev->lostContact()
            && ($dev->params->DriverInfo["LastPingTry"] < (time() - 3600 * 2));
    }
}
?>

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
    /** @var This is our configuration */
    protected $defConf = array(
        "enabled"   => true,
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
            "Polling ".$dev->DeviceID." Last Poll: ".
            date(
                "Y-m-d H:i:s", $dev->params->DriverInfo["LastPoll"]
            ),
            HUGnetClass::VPRINT_NORMAL
        );
        $lastPoll = $dev->params->DriverInfo["LastPoll"];
        $ret      = $dev->readData();
        // Read the setup
        if (!$ret) {
            $this->_checkFail($dev);
        } else {
            // Print out the failure if verbose
            self::vprint(
                "Success.  LastPoll set to: "
                .date("Y-m-d H:i:s", $dev->params->DriverInfo["LastPoll"])
                ." Interval: "
                .round(
                    (($dev->params->DriverInfo["LastPoll"] - $lastPoll)/60), 2
                )."/".$dev->PollInterval,
                HUGnetClass::VPRINT_NORMAL
            );
        }
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
    * This function does the stuff in the class.
    *
    * @param DeviceContainer &$dev The device to check
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready(DeviceContainer &$dev)
    {
        return $dev->readDataTime() && $this->enable && !$dev->lostContact();
    }
}
?>

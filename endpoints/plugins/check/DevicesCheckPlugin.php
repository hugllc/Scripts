<?php
/**
 * Classes for dealing with devices
 *
 * PHP Version 5
 *
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */
/** Stuff we need */
require_once HUGNET_INCLUDE_PATH."/base/PeriodicPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/tables/ErrorTable.php";
/**
 * Base class for all other classes
 *
 * This class uses the {@link http://www.php.net/pdo PDO} extension to php.
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DevicesCheckPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DevicesCheckPlugin",
        "Type" => "periodic",
        "Class" => "DevicesCheckPlugin",
    );
    /** @var This is the array of devices in our gateway */
    protected $errors = null;
    /** @var This is the array of devices in our gateway */
    protected $error = null;
    /** @var This is the array of devices in our gateway */
    private $_subject = null;
    /** @var This is the array of devices in our gateway */
    protected $last = null;
    /** @var This is our configuration */
    protected $defConf = array(
        "enable"   => true,
    );
    /**
    * This function sets up the driver object, and the database object.  The
    * database object is taken from the driver object.
    *
    * @param mixed           $config The configuration array
    * @param PeriodicPlugins &$obj   The controller object
    *
    * @return null
    */
    public function __construct($config, PeriodicPlugins &$obj)
    {
        parent::__construct($config, $obj);
        if (!$this->enable) {
            return;
        }
        $this->device = new DeviceContainer();
        $this->devicesHistory = new DevicesHistoryTable();
        $this->gatewayKey = $this->control->myConfig->script_gateway;
        // State we are here
        self::vprint(
            "Registed class ".self::$registerPlugin["Class"],
            HUGnetClass::VPRINT_NORMAL
        );
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @return bool True if ready to return, false otherwise
    */
    public function main()
    {
        $this->last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        // State we are looking for errors
        self::vprint(
            "Checking for bad device and deviceHistory records.  Last Check: "
            .date("Y-m-d H:i:s", $this->last),
            HUGnetClass::VPRINT_NORMAL
        );
        $where = 1;
        $data = array();
        if ($this->gatewayKey != "all") {
            $where .= " AND GatewayKey = ?";
            $data[] = $this->gatewayKey;
        }
        $devs = $this->device->selectIDs(
            $where,
            $data
        );
        shuffle($devs);
        // Go through the devices
        foreach ($devs as $key) {
            $this->checkDevicesTable($key);
            $this->checkDevicesHistoryTable($key);
        }
        $this->last = time();
        return true;
    }
    /**
    * Check the devices table
    * 
    * @param int $id The device id to use
    *
    * @return bool True if ready to return, false otherwise
    */
    protected function checkDevicesTable($id)
    {
        $this->device->clearData();
        $this->device->getRow($id);
        if ($this->device->params->LastContact < (time() - 3600)) {

            if (((empty($this->device->HWPartNum)
                && empty($this->device->FWPartNum)
                && empty($this->device->FWVersion)))
            ) {
                self::vprint(
                    "Device ".$this->device->DeviceID." removed as a bad record",
                    HUGnetClass::VPRINT_NORMAL
                );
                $this->logError(
                    -15,
                    "Device ".$this->device->DeviceID." removed as a bad record",
                    ErrorTable::SEVERITY_ERROR,
                    __METHOD__
                );
                $this->device->deleteRow();
            } else if ($this->device->gateway()) {
                // If it is a gateway script just set it inactive.
                $this->device->Active = 0;
                $this->device->updateRow(array("Active"));
            }
        }
    }
    /**
    * Check the devicesHistory table
    * 
    * @param int $id The device id to use
    *
    * @return bool True if ready to return, false otherwise
    */
    protected function checkDevicesHistoryTable($id)
    {
        $this->devicesHistory->clearData();
        $this->devicesHistory->selectInto("`id` = ?", array($id));
        do {
            if (!$this->devicesHistory->checkRecord()) {
                $this->device->clearData();
                $this->device->getRow($id);
                $date = $this->devicesHistory->SaveDate;
                $this->device->params->DriverInfo["LastHistory"] = $date;
                $this->device->params->DriverInfo["LastAverage15MIN"] = $date;
                $this->device->params->DriverInfo["LastAverageHOURLY"] = $date;
                $this->device->params->DriverInfo["LastAverageDAILY"] = $date;
                $this->device->params->DriverInfo["LastAverageWEEKLY"] = $date;
                $this->device->params->DriverInfo["LastAverageMONTHLY"] = $date;
                $this->device->params->DriverInfo["LastAverageYEARLY"] = $date;
                $this->device->updateRow(array("params"));
                
                $this->devicesHistory->deleteRow();
            }
        } while ($this->devicesHistory->nextInto());
    }

    /**
    * This function checks to see if it is ready to run again
    *
    * Check every 10 minutes
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        return (time() >= ($this->last + 600)) && $this->enable;
    }
}


?>

<?php
/**
 * Classes for dealing with devices
 *
 * PHP Version 5
 *
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 *
 */
/**
 * Base class for all other classes
 *
 * This class uses the {@link http://www.php.net/pdo PDO} extension to php.
 *
 * @category   Base
 * @package    HUGnetLib
 * @subpackage Plugins
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class DevicesTableSyncPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DevicesTableSync",
        "Type" => "periodic",
        "Class" => "DevicesTableSyncPlugin",
    );
    /** @var This is when we were created */
    protected $firmware = 0;
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
        $this->remoteDevice = new DeviceContainer(array("group" => "remote"));
        $this->device = new DeviceContainer();
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
        // State we are looking for firmware
        self::vprint(
            "Synchronizing remote and local devices",
            HUGnetClass::VPRINT_NORMAL
        );
        $remote = $this->remoteDevice->select(1);
        foreach (array_keys((array)$remote) as $k) {
            $this->device->clearData();
            $ret = $this->device->selectOneInto(
                "`DeviceID` = ?",
                array($remote[$k]->DeviceID)
            );
            if ($this->device->gateway()) {
                // Don't want to update gateways
                continue;
            } else if ($ret) {
                $this->_updateLocal($this->device, $remote[$k]);
                $this->_updateRemote($this->device, $remote[$k]);
            } else if ($remote[$k]->GatewayKey == $this->gatewayKey) {
                DevicesTable::insertDeviceID(
                    $remote[$k]->toDB()
                );
            }
        }
        $this->last = time();
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @param DeviceContainer &$dev    The local device
    * @param DeviceContainer &$remote The remove device
    *
    * @return bool True if ready to return, false otherwise
    */
    private function _updateLocal(DeviceContainer &$dev, DeviceContainer &$remote)
    {
        $columns = array(
            "DeviceName"     => "DeviceName",
            "DeviceJob"      => "DeviceJob",
            "DeviceLocation" => "DeviceLocation",
            "PollInterval"   => "PollInterval",
            "ActiveSensors"  => "ActiveSensors",
            "params"         => "params",
            "sensors"        => "sensors",
            "RawSetup"       => "RawSetup",
        );
        foreach ($columns as $c => $col) {
            if (empty($dev->$col)) {
                $dev->$col = $remote->$col;
            } else {
                unset($columns[$c]);
            }
        }
        if (!empty($columns)) {
            $dev->params->DriverInfo["lastSync"] = time();
            $dev->updateRow($columns);
        }

    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @param DeviceContainer &$dev    The local device
    * @param DeviceContainer &$remote The remove device
    *
    * @return bool True if ready to return, false otherwise
    */
    private function _updateRemote(DeviceContainer &$dev, DeviceContainer &$remote)
    {
        $columns = array(
            "LastPoll"        => "LastPoll",
            "LastConfig"      => "LastConfig",
            "LastHistory"     => "LastHistory",
            "LastAnalysis"    => "LastAnalysis",
            "FWVersion"       => "FWVersion",
            "FWPartNum"       => "FWPartNum",
            "HWPartNum"       => "HWPartNum",
            "SerialNum"       => "SerialNum",
            "Active"          => "Active",
            "RawSetup"        => "RawSetup",
            "GatewayKey"      => "GatewayKey",
            "ControllerKey"   => "ControllerKey",
            "ControllerIndex" => "ControllerIndex",
            "Driver"          => "Driver",
            "DeviceGroup"     => "DeviceGroup",
            "GatewayKey"      => "GatewayKey",
        );
        foreach ($columns as $c => $col) {
            $remote->$col = $dev->$col;
        }
        $remote->params->DriverInfo["lastSync"] = time();
        $remote->updateRow($columns);
    }
    /**
    * This function checks to see if it is ready to run again
    *
    * The default is to run every 10 minutes
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        // Run every 24 hours
        return (time() >= ($this->last + 600));
    }

}


?>

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
    /** @var These are the keys to copy over */
    protected $remoteCopy = array(
        "driverInfo" => array(
            "LastConfig", "LastPoll", "LastContact"
        ),
        "keys" => array(
            "RawSetup", "ControllerKey", "ControllerIndex", "Driver",
            "FWVersion", "FWPartNum", "HWPartNum", "GatewayKey"
        ),
    );
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
        $this->enable &= $this->control->myConfig->servers->available("remote");
        if (!$this->enable) {
            return;
        }
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
        $this->localToRemote();
        $this->remoteToLocal();
        $this->last = time();
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @return bool True if ready to return, false otherwise
    */
    public function localToRemote()
    {
        // State we are looking for firmware
        self::vprint(
            "Synchronizing local -> remote",
            HUGnetClass::VPRINT_NORMAL
        );
        // Get the devices
        $devs = $this->device->selectIDs(
            "GatewayKey = ?",
            array($this->gatewayKey)
        );
        shuffle($devs);
        // Go through the devices
        foreach ($devs as $key) {
            $this->device->getRow($key);
            if ($this->device->gateway()) {
                // Don't want to update gateways
                continue;
            } else if ($this->device->id < 0xFD0000) {
                $ret = $this->remoteDevice->getRow($key);
                if ($ret) {
                    foreach ($this->remoteCopy["keys"] as $key) {
                        $this->remoteDevice->$key = $this->device->$key;
                    }
                    foreach ($this->remoteCopy["driverInfo"] as $key) {
                        $this->remoteDevice->params->DriverInfo[$key]
                            = $this->device->DriverInfo[$key];
                    }
                    $rows = array_merge(
                        $this->remoteCopy["keys"],
                        array("params")
                    );
                    $this->remoteDevice->updateRow($rows);
                } else {
                    $this->remoteDevice->fromArray($this->device->toDB());
                    // Insert a new row since we didn't find one.
                    $this->remoteDevice->insertRow(false);
                }
            }
        }
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @return bool True if ready to return, false otherwise
    */
    public function remoteToLocal()
    {
        // State we are looking for firmware
        self::vprint(
            "Synchronizing remote -> local",
            HUGnetClass::VPRINT_NORMAL
        );
        // Get the devices
        $remoteDevs = $this->remoteDevice->selectIDs(
            "GatewayKey = ?",
            array($this->gatewayKey)
        );
        // Get the devices
        $devs = $this->device->selectIDs(
            "GatewayKey = ?",
            array($this->gatewayKey)
        );
        // Go through the devices
        foreach ($remoteDevs as $key) {
            if (array_search($key, $devs) === false) {
                $this->remoteDevice->getRow($key);
                if ($this->remoteDevice->gateway()) {
                    // Don't want to update gateways
                    continue;
                } else if ($this->remoteDevice->id < 0xFD0000) {
                    $this->device->fromArray($this->remoteDevice->toDB());
                    // Insert a row only if there is nothing here.
                    $this->device->insertRow(false);
                }
            }
        }
    }

}


?>

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
        //$this->enable &= $this->control->myConfig->servers->available("remote");
        if (!$this->enable) {
            return;
        }
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
        if (!$this->control->myConfig->servers->available("remote")) {
            self::vprint(
                "Remote database not available",
                HUGnetClass::VPRINT_NORMAL
            );
            return;
        }
        if (!is_object($this->remoteDevice)) {
            $this->remoteDevice = new DeviceContainer(array("group" => "remote"));
        }
        // Get the devices (no gateways)
        $devs = $this->device->selectIDs(
            "GatewayKey = ? AND id < ?",
            array($this->gatewayKey, 0xFD0000)
        );
        // Get the devices (no gateways)
        $remoteDevs = $this->remoteDevice->selectIDs(
            "GatewayKey = ? AND id < ?",
            array($this->gatewayKey, 0xFD0000)
        );
        $this->syncDevs(array_intersect($devs, $remoteDevs));
        $this->newLocalDevs(array_diff($remoteDevs, $devs));
        $this->newRemoteDevs(array_diff($devs, $remoteDevs));
        $this->last = time();
    }
    /**
    * This function synchronizes the devices between local and remote
    *
    * @param array $devs An array of devices to sync
    *
    * @return null
    */
    public function syncDevs($devs)
    {
        if (!empty($devs)) {
            self::vprint(
                "Synchronizing ".count($devs)." devices",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        foreach ($devs as $id) {
            $this->device->clearData();
            $this->device->getRow($id);
            $this->remoteDevice->clearData();
            $this->remoteDevice->getRow($id);
            if (empty($this->remoteDevice->HWPartNum)
                && empty($this->device->HWPartNum)
            ) {
                continue;
            }

            $locked = $this->control->myDevice->getMyDevLock($this->device);
            $this->localToRemote($this->device, $this->remoteDevice, $locked);
            $this->remoteToLocal($this->device, $this->remoteDevice, $locked);
            $this->device->updateRow();
            $this->remoteDevice->updateRow();
        }
    }
    /**
    * This function synchronizes the devices between local and remote
    *
    * @param array $devs An array of devices to sync
    *
    * @return null
    */
    public function newLocalDevs($devs)
    {
        // Go through the devices
        foreach ($devs as $key) {
            $this->remoteDevice->clearData();
            $this->remoteDevice->getRow($key);
            if (empty($this->remoteDevice->HWPartNum)) {
                unset($devs[$key]);
                continue;
            }
            $this->device->clearData();
            $this->device->fromArray($this->remoteDevice->toDB());
            $this->device->insertRow(false);
        }
        if (!empty($devs)) {
            self::vprint(
                "Downloaded ".count($devs)." new local devices",
                HUGnetClass::VPRINT_NORMAL
            );
        }

    }
    /**
    * This function synchronizes the devices between local and remote
    *
    * @param array $devs An array of devices to sync
    *
    * @return null
    */
    public function newRemoteDevs($devs)
    {
        // Go through the devices
        foreach ($devs as $key) {
            $this->device->clearData();
            $this->device->getRow($key);
            if (empty($this->device->HWPartNum)) {
                unset($devs[$key]);
                continue;
            }
            $this->remoteDevice->clearData();
            $this->remoteDevice->fromArray($this->device->toDB());
            $this->remoteDevice->insertRow(false);
        }
        if (!empty($devs)) {
            self::vprint(
                "Uploaded ".count($devs)." new remote devices",
                HUGnetClass::VPRINT_NORMAL
            );
        }
    }

    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @param object &$local  The local object to do
    * @param object &$remote The remote object to use
    * @param bool   $lock    Whether or not I have a lock
    *
    * @return bool True if ready to return, false otherwise
    */
    public function localToRemote(&$local, &$remote, $lock)
    {
        if ($lock) {
            $from = &$local;
            $to = &$remote;
        } else {
            $to = &$local;
            $from = &$remote;
        }
        foreach ($this->remoteCopy["keys"] as $key) {
            $to->$key = $from->$key;
        }
        $di = &$from->params->DriverInfo;
        $rdi = &$to->params->DriverInfo;
        foreach ($this->remoteCopy["driverInfo"] as $key) {
            // Copy only if the date is greater
            if ($from->params->DriverInfo[$key] > $to->params->DriverInfo[$key]) {
                $to->params->DriverInfo[$key] = $from->params->DriverInfo[$key];
            }
        }
    }
    /**
    * This function checks to see if any new firmware has been uploaded
    *
    * @param object &$local  The local object to do
    * @param object &$remote The remote object to use
    * @param bool   $lock    Whether or not I have a lock
    *
    * @return bool True if ready to return, false otherwise
    */
    public function remoteToLocal(&$local, &$remote, $lock)
    {
        if ($remote->params->LastModified > $local->params->LastModified) {
            $local->sensors->fromArray(
                $remote->sensors->toArray(true)
            );
            $keys = array(
                "DeviceName", "DeviceLocation", "DeviceJob",
                "ActiveSensors", "Active", "PollInterval"
            );
            foreach ($keys as $k) {
                $local->$k = $remote->$k;
            }
            $local->params->LastModified = $remote->params->LastModified;
            $local->params->LastModifiedBy = $remote->params->LastModifiedBy;
        }
    }
    /**
    * This function checks to see if it is ready to run again
    *
    * The default is to run every 24 hours.
    *
    * @return bool True if ready to return, false otherwise
    */
    public function ready()
    {
        // Run every 10 minutes
        return $this->enable && (time() >= ($this->last + 600));
    }

}


?>

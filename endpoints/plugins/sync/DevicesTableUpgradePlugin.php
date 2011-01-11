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
class DevicesTableUpgradePlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DevicesTableUpgrade",
        "Type" => "periodic",
        "Class" => "DevicesTableUpgradePlugin",
    );
    /** @var This is our configuration */
    protected $defConf = array(
        "enable"   => false,
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
        $old = $this->control->myConfig->servers->available("old");
        $this->enable = $this->enable && $old;
        if (!$this->enable) {
            return;
        }
        $this->remoteDevice = new DeviceContainer(array("group" => "old"));
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
            "Upgrading the database from the old system. "
            ." This should only run once.",
            HUGnetClass::VPRINT_NORMAL
        );
        // Get the devices
        $this->remoteDevice->group = "old";
        $this->remoteDevice->selectInto(
            "GatewayKey = ?",
            array($this->gatewayKey)
        );
        // Go through the devices
        do {
            print $this->remoteDevice->DeviceID."\n";
            if ($this->remoteDevice->gateway()) {
                // Don't want to update gateways
                continue;
            } else if ($this->device->id < 0xFD0000) {
                $this->device->fromArray($this->remoteDevice->toDB());
                // Replace the row
                $this->device->insertRow(true);
            }
        } while ($this->remoteDevice->nextInto());
        // This should only run once.
        $this->control->myDevice->params->ProcessInfo[__CLASS__] = time();
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
        return empty($this->control->myDevice->params->ProcessInfo[__CLASS__])
            && $this->enable;
    }

}

?>

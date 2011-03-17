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
require_once HUGNET_INCLUDE_PATH."/tables/DataCollectorsTable.php";
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
class RemoteRegisterPlugin extends PeriodicPluginBase
    implements PeriodicPluginInterface
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "RemoteRegisterPlugin",
        "Type" => "periodic",
        "Class" => "RemoteRegisterPlugin",
    );
    /** @var This is the array of devices in our gateway */
    protected $errors = null;
    /** @var This is the array of devices in our gateway */
    protected $error = null;
    /** @var This is the array of devices in our gateway */
    private $_subject = null;
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
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        // State we are looking for errors
        self::vprint(
            "Registering with remote host.  Last Registration: "
            .date("Y-m-d H:i:s", $last),
            HUGnetClass::VPRINT_NORMAL
        );
        $dc = new DataCollectorsTable(array("group" => "remote"));
        $dc->fromDeviceContainer($this->control->myDevice);
        $uname = posix_uname();
        $dc->name = trim($uname['nodename']);
        $ret = $dc->registerMe();
        if ($ret) {
            $last = time();
        }
        return $ret;
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
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        return (time() >= ($last + 600)) && $this->enable;
    }
}


?>

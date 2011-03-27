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
require_once HUGNET_INCLUDE_PATH."/base/DeviceProcessPluginBase.php";
require_once HUGNET_INCLUDE_PATH."/tables/RawHistoryOldTable.php";
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
class DeviceRegenAnalysisPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "DeviceRegenAnalysis",
        "Type" => "analysisPeriodic",
        "Class" => "DeviceRegenAnalysisPlugin",
        "Priority" => 8,  // This runs it before the history crunching
    );
    /** @var This is our configuration */
    protected $defConf = array(
        "enable"   => false,
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
        $info = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        $di = &$dev->params->DriverInfo;
        if ($dev->DeviceID == $info["current"]) {
            if ($di["LastHistory"] > ($di["LastPoll"] - 3600)) {
                $info["current"] = "";
                $info["done"][$dev->DeviceID] = true;
            }
        } else if (empty($info["current"])
            && !isset($info["done"][$dev->DeviceID])
        ) {
            $info["current"] = $dev->DeviceID;
            // Reset all of the last dates so it totally rebuilds the history.
            unset($di["LastHistory"]);
            unset($di["LastHistoryTry"]);
            unset($di["LastAverage15MIN"]);
            unset($di["LastAverage15MINTry"]);
            unset($di["LastAverageHOURLY"]);
            unset($di["LastAverageHOURLYTry"]);
            unset($di["LastAverageDAILY"]);
            unset($di["LastAverageDAILYTry"]);
            unset($di["LastAverageWEEKLY"]);
            unset($di["LastAverageWEEKLYTry"]);
            unset($di["LastAverageMONTHLY"]);
            unset($di["LastAverageMONTHLYTry"]);
            unset($di["LastAverageYEARLY"]);
            unset($di["LastAverageYEARLYTry"]);
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
    public function ready(DeviceContainer &$dev)
    {
        return $this->enable;
    }

}


?>

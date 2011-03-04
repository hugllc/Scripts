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
class AverageDailyAnalysisPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "AverageDailyAnalysis",
        "Type" => "analysis",
        "Class" => "AverageDailyAnalysisPlugin",
        "Priority" => 22,
    );
    /** @var This is when we were created */
    protected $firmware = 0;
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
        $hist = &$dev->historyFactory($data, false);
        // We don't want more than 100 records at a time;
        if (empty($this->conf["maxRecords"])) {
            $hist->sqlLimit = 100;
        } else {
            $hist->sqlLimit = $this->conf["maxRecords"];
        }
        $hist->sqlOrderBy = "Date asc";

        $avg = &$dev->historyFactory($data, false);

        $last = &$dev->params->DriverInfo["LastAverageDAILY"];
        $dev->params->DriverInfo["LastAverageDAILYTry"] = time();
        $lastHourly = &$dev->params->DriverInfo["LastAverageHOURLY"];
        $ret = $hist->getPeriod(
            (int)$last, $lastHourly, $dev->id, AverageTableBase::AVERAGE_HOURLY
        );

        $bad = 0;
        $local = 0;
        if ($ret) {
            // Go through the records
            while ($avg->calcAverage($hist, AverageTableBase::AVERAGE_DAILY)) {
                if ($avg->insertRow(true)) {
                    $now = $avg->Date;
                    $local++;
                    $lastTry = time();
                } else {
                    $bad++;
                }
            }
        }
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                $dev->DeviceID." - ".
                "Failed to insert $bad DAILY average records",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                $dev->DeviceID." - ".
                "Inserted $local DAILY average records ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if (!empty($now)) {
            $last = (int)$now;
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
        $last = &$dev->params->DriverInfo["LastAverageDAILYTry"];
        // Run when enabled, and at most every 15 minutes.
        return $this->enable
            && ((time() - $last) > 3600);
    }

}


?>

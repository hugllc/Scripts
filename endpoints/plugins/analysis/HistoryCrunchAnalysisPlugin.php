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
 * @copyright  2007-2010 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:HUGnetLib
 */
class HistoryCrunchAnalysisPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "HistoryCrunchAnalysis",
        "Type" => "analysis",
        "Class" => "HistoryCrunchAnalysisPlugin",
        "Priority" => 10,
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
        $this->raw = new RawHistoryTable();
        // We don't want more than 10 records at a time;
        if (empty($this->conf["maxRecords"])) {
            $this->raw->sqlLimit = 100;
        } else {
            $this->raw->sqlLimit = $this->conf["maxRecords"];
        }
        $this->raw->sqlOrderBy = "Date asc";
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
        $last = &$dev->params->DriverInfo["LastHistory"];
        // Get the devices
        $ret = $this->raw->getPeriod((int)$last, time(), $dev->id, "id");
        $bad = 0;
        $local = 0;
        if ($ret) {
            // Go through the records
            do {
                $now = $this->raw->Date;
                $id = $this->raw->id;
                $hist = &$this->raw->toHistoryTable($prev);
                if ($hist->insertRow(true)) {
                    $local++;
                } else {
                    $bad++;
                }
                $prev = $this->raw->raw;
            } while ($this->raw->nextInto());
        }
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                $dev->DeviceID." - ".
                "Found $bad bad raw history records",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                $dev->DeviceID." - ".
                "Decoded $local raw history records ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if (!empty($now)) {
            $last = (int)$now+1;
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
        return $this->enable;
    }

}


?>

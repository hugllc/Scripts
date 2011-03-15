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
class OldRawAnalysisPlugin extends DeviceProcessPluginBase
{
    /** @var This is to register the class */
    public static $registerPlugin = array(
        "Name" => "OldRawAnalysis",
        "Type" => "analysisPeriodic",
        "Class" => "OldRawAnalysisPlugin",
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
        $this->enable &= $this->control->myConfig->servers->available("old");
        if (!$this->enable) {
            return;
        }
        if (empty($this->conf["maxRecords"])) {
            $maxRec = 1000;
        } else {
            $maxRec = $this->conf["maxRecords"];
        }
        $this->raw = new RawHistoryTable();
        // We don't want more than 10 records at a time;
        $this->raw->sqlLimit = $maxRec;
        $this->raw->sqlOrderBy = "Date asc";
        $this->oldRaw = new RawHistoryOldTable();
        $this->oldRaw->sqlOrderBy = "Date asc";
        $this->oldRaw->sqlLimit = $maxRec;
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
        $last = &$this->control->myDevice->params->ProcessInfo[__CLASS__];
        if (empty($last)) {
            $last = $this->conf["startTime"];
            self::vprint(
                "Starting at ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        $old = $last;
        $startTime = time();
        $ret = $this->oldRaw->selectInto(
            "Date >= ?",
            array(date("Y-m-d H:i:s", (int)$last))
        );
        $count = 0;
        $bad = 0;
        $failed = 0;
        $startTime = time();
        while ($ret) {
            $raw = $this->oldRaw->toRaw($this->group);
            if (is_object($raw) && ($raw->id < 0x500)) {
                $ins = $raw->insertRow((bool)$this->conf["force"]);
                if ($ins) {
                    $count++;
                    if ($this->conf["dots"] && (($count % 100) == 0)) {
                        print ".";
                    }
                } else {
                    $failed++;
                }
            } else {
                $bad++;
            }
            //$now = $this->raw->Date;
            if (!empty($raw->Date)) {
                $last = (int)$raw->Date;
            }
            $ret = $this->oldRaw->nextInto();
        }
        if ($this->conf["dots"]) {
            print "\r";
        }
        if ($count > 0) {
            // State we did some uploading
            self::vprint(
                "Moved $count good raw history records ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last)." in "
                .(time() - $startTime)." s",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                "Found $bad bad raw history records ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($failed > 0) {
            // State we did some uploading
            self::vprint(
                "$failed raw history records failed to insert ".
                date("Y-m-d H:i:s", $old)." - ".date("Y-m-d H:i:s", $last),
                HUGnetClass::VPRINT_NORMAL
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
        return $this->enable;
    }
    /**
    * Method to set the id
    *
    * @param int $DeviceKey The devicekey to check
    *
    * @return null
    */
    private function _getID($DeviceKey)
    {
        return $this->_devices[$DeviceKey];
    }

}


?>

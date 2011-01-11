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
require_once HUGNET_INCLUDE_PATH."/tables/GenericTable.php";
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
        $this->enable = $this->control->myConfig->servers->available("old");
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
        $this->oldRaw = new GenericTable(array("group" => "old"));
        $this->oldRaw->forceTable("history_raw");
        $this->oldRaw->sqlOrderBy = "Date desc";
        $this->oldRaw->sqlLimit = $maxRec;
        $this->pkt = new PacketContainer();
        $this->oldDev = new GenericTable(array("group" => "old"));
        $this->oldDev->sqlID = "DeviceKey";
        $this->oldDev->forceTable("devices");
        $this->myDev = new DeviceContainer();
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
            $last = time();
        }
        
        //$startTime = time();
        $ret = $this->oldRaw->selectInto(
            "Date <= ?",
            array(date("Y-m-d H:i:s", (int)$last))
        );
        /*
        // State we did some uploading
        self::vprint(
            "Retrieved raw history in ".(time() - $startTime)." s",
            HUGnetClass::VPRINT_NORMAL
        );
        */
        $count = 0;
        $bad = 0;
        $local = 0;
        $failed  = 0;
        $startTime = time();
        while ($ret) {
            $this->raw->clearData();
            $this->myDev->clearData();
            $this->myDev->fromSetupString($this->oldRaw->RawSetup);
            $time = $this->oldRaw->unixDate($this->oldRaw->Date, "UTC");
            $this->pkt->clearData();
            $this->pkt->fromArray(
                array(
                    "To" =>  $this->myDev->DeviceID,
                    "Command" => $this->oldRaw->sendCommand,
                    "Time" => $time - $this->oldRaw->ReplyTime,
                    "Date" => $time - $this->oldRaw->ReplyTime,
                    "Reply" => new PacketContainer(
                        array(
                        "From" => $this->myDev->DeviceID,
                        "Command" => PacketContainer::COMMAND_REPLY,
                        "Data" => $this->oldRaw->RawData,
                        "Length" => strlen($this->oldRaw->RawData)/2,
                        "Time" => $time,
                        "Date" => $time,
                        )
                    ),
                )
            );
            $this->raw->fromArray(
                array(
                    "id" => hexdec($this->myDev->id),
                    "Date" => $this->oldRaw->unixDate($this->oldRaw->Date, "UTC"),
                    "packet" => $this->pkt,
                    "device" => $this->myDev,
                    "command" => $this->oldRaw->sendCommand,
                    "dataIndex" => $this->myDev->dataIndex($this->oldRaw->RawData),
                )
            );
            $ins = $this->raw->insert();
            if ($ins) {
                $hist =& $this->raw->toHistoryTable($prev);
                $count++;
                if ($this->conf["dots"] && (($count % 10) == 0)) {
                    print ".";
                }
                if ($hist->insertRow(true)) {
                    $local++;
                } else {
                    $bad++;
                }
                $prev = $this->raw->raw;
            } else {
                $failed++;
            }
            $now = $this->raw->Date;
            $ret = $this->oldRaw->nextInto();
        }
        $this->raw->insertEnd();
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                "Moved $count good raw history records ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now)." in "
                .(time() - $startTime)." s",
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($bad > 0) {
            // State we did some uploading
            self::vprint(
                "Found $bad bad raw history records ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($failed > 0) {
            // State we did some uploading
            self::vprint(
                "$failed raw history records failed to insert ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if ($local > 0) {
            // State we did some uploading
            self::vprint(
                "Decoded $local raw history records ".
                date("Y-m-d H:i:s", $last)." - ".date("Y-m-d H:i:s", $now),
                HUGnetClass::VPRINT_NORMAL
            );
        }
        if (!empty($now)) {
            $last = (int)$now-1;
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

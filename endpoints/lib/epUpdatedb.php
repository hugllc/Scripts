<?php
/**
 * Keeps the local SQLite database in sync with the remote database
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
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
 * @category   Test
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/** This is our base class */
require_once "endpointBase.php";
/** For retrieving packet logs */
require_once HUGNET_INCLUDE_PATH.'/database/Plog.php';
/** For process information and control */
require_once HUGNET_INCLUDE_PATH.'/database/Process.php';
/** For process statistics */
require_once HUGNET_INCLUDE_PATH.'/database/ProcStats.php';

/**
 * This class interacts with the final database.  It has the following functions:
 * - Get device and other information from the database
 * - Send pollings and packet logs up to the final database
 *
 * @category   Test
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
class EpUpdatedb extends EndpointBase
{
    /** @var array The array of device information */
    var $ep = array();
    /** @var bool/int Verbosity level.  False or 0 for none */
    var $verbose = false;

    /**
    * Constructor
    *
    * @param array $config Configuration
    *
    * @return null
    */
    function __construct($config = array())
    {
        $this->verbose     = (int) $config["verbose"];
        $this->endpoint    =& HUGnetDriver::getInstance($config);
        $config["partNum"] = UPDATEDB_PARTNUMBER;
        $this->config      = $config;

        print "Creating remote plog...\n";
        unset($config["table"]);
        $this->plogRemote =& HUGnetDB::getInstance("Plog", $config);

        $config["table"] = "PacketSend";
        $this->psend     =& HUGnetDB::getInstance("Plog", $config);
        print("Creating Gateway Cache...\n");
        unset($config["table"]);
        $this->gateway =& HUGnetDB::getInstance("Gateway", $config);
        $this->gateway->createCache(HUGNET_LOCAL_DATABASE);
        $this->gateway->getAll();

        print("Creating Device Cache...\n");
        unset($config["table"]);
        $this->device =& HUGnetDB::getInstance("Device", $config);
        $this->device->createCache(HUGNET_LOCAL_DATABASE);
        $this->device->getAll();

        print("Creating Firmware Cache...\n");
        unset($config["table"]);
        $this->firmware =& HUGnetDB::getInstance("Firmware", $config);
        $this->firmware->createCache(HUGNET_LOCAL_DATABASE);
        $this->firmware->getAll();

        unset($config["table"]);
        $this->rawHistory = & HUGnetDB::getInstance("RawHistory", $config);

        // This is the local stuff.
        unset($config["servers"]);
        unset($config["table"]);
        print "Creating local plog...\n";
        $this->plog =& HUGnetDB::getInstance("Plog", $config);
        $this->plog->createTable();

        unset($config["table"]);
        $this->uproc =& HUGnetDB::getInstance("Process", $config);
        $this->uproc->createTable();

        unset($config["table"]);
        $this->stats =& HUGnetDB::getInstance("ProcStats", $config);
        $this->stats->createTable();
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);
        $this->stats->setStat('HWPartNum', UPDATEDB_PARTNUMBER);
        $this->stats->setStat('FWPartNum', UPDATEDB_PARTNUMBER);


        parent::__construct($config);

    }

    /**
     * The main loop
     *
     * @return null
     */
    function updateCache()
    {
        print "Updating Caches... ";
        print " Firmware ";
        $this->firmware->getAll();
        print " Gateway ";
        $this->gateway->getAll();
        print "\n";
    }

    /**
     * The main loop
     *
     * @return null
     */
    function main()
    {
        $this->uproc->register();

        while ($GLOBALS["exit"] !== true) {
            declare(ticks = 1);
            if ($this->errors[HUGNETDB_META_ERROR_SERVER_GONE] > 0) {
                die("Lost my database connection\n");
            } else if (!$this->checkErrors(10)) {
                die ("Too many errors\n");
            }
            if ($lastminute != date("i")) {
                $this->setupMyInfo();
                $this->getOtherPriorities();
                $lastminute = date("i");
            }

            $this->updateCache();
            $this->getAllDevices();
            if (count($this->ep) == 0) {
                die("No devices found");
            }
            if ($this->verbose) {
                print "[".$this->uproc->me["PID"]."] Starting database update...\n";
            }
            // This section does the packetlog
            $this->updatedb();

            //        $lplog->reset();
            $this->wait();

            // Check the PHP log to make sure it isn't too big.
            clearstatcache();
            if (file_exists("/var/log/php.log")) {
                if (filesize("/var/log/php.log") > (1024*1024)) {
                    $fd = fopen("/var/log/php.log", "w");
                    @fclose($fd);
                }
            }
        }
        $this->uproc->unregister();

    }
    /**
    * Check to see if any error is higher than the value given
    *
    * @param int $value The value
    *
    * @return bool
    */
    function checkErrors ($value)
    {
        if (!is_array($this->errors)) {
            return true;
        }
        foreach ($this->errors as $e) {
            if ($e > $value) {
                return false;
            }
        }
        return true;
    }

    /**
    * Gets all devices from the remote database and stores them in the
    * local database.
    *
    * @return bool
    */
    function getAllDevices()
    {
        // Regenerate our endpoint information
        if (((time() - $this->lastdev) > 120) || (count($this->ep) < 1)) {
            $this->lastdev = time();

            print "Getting endpoints:  ";

            $res = $this->device->getAll();
            if (is_array($res) && (count($res) > 0)) {
                print "found ".count($res)."";
                $this->stats->setStat('Devices', count($res));
                $this->oldep = $this->ep;
                $this->ep    = array();
                foreach ($res as $key => $val) {
                    $dev             = $this->endpoint->DriverInfo($val);
                    $dev['params']   = device::decodeParams($dev['params']);
                    $val['DeviceID'] = trim(strtoupper($val['DeviceID']));

                    $this->ep[$val['DeviceID']] = $dev;
                }
            } else {
                die("Gaahhh...  Can't get devices!\n");
            }
            print "\n";
        }
        return $this->ep;
    }

    /**
    * Waits for a time when there is nothing to do.  This is so we don't eat
    * all of the processing time.
    *
    * @return bool
    */
    function wait()
    {
        if ($this->verbose) {
            print  "[".$this->uproc->me["PID"]."] Pausing...\n";
        }
        sleep(2);
        $this->lastminute = date("i");

    }

    /**
    * Update the remote database from the local one.
    *
    * @return bool
    */
    function updatedb()
    {
        $res = $this->plog->getWhere("Checked > 10", array(), 50);
        if ($this->verbose) {
            print "[".$this->uproc->me["PID"]."] Found ".count($res)." Packets\n";
        }
        if (!is_array($res)) {
            return;
        }
        foreach ($res as $packet) {
            $this->stats->incStat("Packets");

            print "[".$this->uproc->me["PID"]."]";
            print " ".$packet["PacketFrom"];
            print " ".$packet["sendCommand"];
            if (empty($packet["PacketFrom"])) {
                $packet["Type"] = BAD;
            }
            if ($this->endpoint->packet->isGateway($packet["PacketFrom"])) {
                $packet["Type"] = BAD;
            }
            $DeviceID = trim(strtoupper($packet['PacketFrom']));
            if (is_array($this->ep[$DeviceID])) {
                $packet = array_merge($this->ep[$DeviceID], $packet);
            }
            $packet["remove"] == false;
            $this->_updatedbUnsolicited($packet);
            $this->_updatedbReply($packet);
            $this->_updatedbConfig($packet);
            $this->_updatedbPoll($packet);
            $this->_updatedbUnknown($packet);
            $this->_updatedbRemove($packet);
            print "\r\n";

        }
    }

    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbUnsolicited(&$packet)
    {
        if ($packet['Type'] == 'UNSOLICITED') {
            $packet["Checked"] = true;
            $this->stats->incStat("Unsolicited");
            $pkt    = $this->plog->packetLogSetup($packet, $packet);
            $return = $this->plogRemote->add($pkt);
            if ($return) {
                print " - Inserted ".$packet['sendCommand']."";
                $packet["remove"] = true;
            } else {
                $this->updatedbError($packet, "Failed", "Unsolicited Failed");
            }
        }
    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbReply(&$packet)
    {
        if ($packet['Type'] == 'REPLY') {
            $packet["Checked"] = true;
            $this->stats->incStat("Reply");
            if ($return) {
                print " - Inserted into PacketSend ".$packet['sendCommand']."";
                $packet["remove"] = true;
            } else {
                print " - Failed ";
                $this->stats->incStat("Reply Failed");
            }
        }
    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbConfig(&$packet)
    {
        if ($packet['Type'] == 'CONFIG') {
            $packet["Checked"] = true;
            $this->stats->incStat("Config");
            $return = $this->plogRemote->add($packet);
            if ($return) {
                print " - Moved ";
                $packet["remove"]               = true;
                $this->errors["updatedbConfig"] = 0;
            } else {
                print " - Move Failed ";
                $this->updatedbError($packet, "Update Failed", "Config Failed");
                $this->errors["updatedbConfig"]++;
            }
            if ($this->endpoint->UpdateDevice(array($packet))) {
                $this->stats->incStat("Device Updated");
                print " - Updated ";
            } else {
                print " - Update Failed ";
            }
        }
    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbPoll(&$packet)
    {
        if ($packet['Type'] == 'POLL') {
            $packet["Checked"] = true;
            $this->stats->incStat("Poll");
            print " ".$packet['Driver']." ";
            $packet = $this->endpoint->InterpSensors($packet, array($packet));
            $packet = $packet[0];
            print " ".$packet["Date"];
            print " - decoded ".$packet['sendCommand']." ";
            if ($this->_updatedbPollDuplicate($packet)) {
                print "Duplicate";
                $packet["remove"] = true;
            } else {

                if ($this->_updatedbPollRawHistory(&$packet)) {
                    $packet["remove"] = true;
                    $this->_updatedbPollHistory($packet);
                }
            }
        }
    }
    /**
    * Checks if this is a duplicate or not.
    *
    * @param array &$packet the packet array
    *
    * @return bool
    */
    private function _updatedbPollHistory(&$packet)
    {
        $set  = array(
            "DeviceKey"  => $packet["DeviceKey"],
            "LastPoll"   => $packet["Date"],
            "GatewayKey" => $packet['GatewayKey'],
        );
        $hist = $this->endpoint->saveSensorData($packet, array($packet));
        if ($hist) {
            $set["LastHistory"] = $packet["Date"];
            print " - ".$packet["Driver"]." history ";
            $this->errors["updatedbHist"] = 0;
        } else {
            print " - History Failed";
            $this->errors["updatedbHist"]++;
            return false;
        }
        $ret = $this->endpoint->device->update($set);
        if ($ret === true) {
            print " - Last Poll ";
            return true;
        } else {
            print " - Last Poll Failed ";
            return false;
        }

    }
    /**
    * Checks if this is a duplicate or not.
    *
    * @param array &$packet the packet array
    *
    * @return bool
    */
    private function _updatedbPollRawHistory(&$packet)
    {
        $ret = $this->rawHistory->add($packet);
        if ($ret) {
            $info = array();
            print " - raw history ";
            $this->errors["updatedbRaw"] = 0;
            return true;
        } else {
            $this->updatedbError($packet, "Raw History Failed", "Poll Failed");
            $this->errors["updatedbRaw"]++;
            return false;
        }

    }
    /**
    * Checks if this is a duplicate or not.
    *
    * @param array &$packet the packet array
    *
    * @return bool
    */
    private function _updatedbPollDuplicate(&$packet)
    {
        if (isset($packet['DataIndex'])) {
            $data  = array(
                $packet["DeviceKey"],
                $packet["sendCommand"],
                $packet["Date"]
            );
            $query = " DeviceKey= ? " .
                     " AND " .
                     " sendCommand= ? ".
                     " AND " .
                     " Date= ? " .
                     " ORDER BY 'Date' desc " .
                     " LIMIT 0, 1 ";
            $check = $this->rawHistory->getWhere($query, $data);
            if (is_array($check)) {
                $check = $this->endpoint->InterpSensors($packet, $check);
                if ($check[0]['DataIndex'] == $packet['DataIndex']) {
                    return true;
                }
            }
        }
        return false;

    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbUnknown(&$packet)
    {
        if ($packet['Checked'] !== true) {
            print " - Unknown ";
            $this->stats->incStat("Unknown");
            $packet["remove"] = true;
        }
    }
    /**
    * Gets error info
    *
    * @return int error number
    */
    function getDbError()
    {
        if (is_object($this->endpoint->db)) {
            return $this->endpoint->db->errorInfo();
        } else {
            return false;
        }
    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array  &$packet the packet array
    * @param string $msg     Generic message to print if specific
    *                            failures can't be found
    * @param string $stat    Generic stat to increment if specific
    *                            failures can't be found
    *
    * @return null
    */
    function updatedbError(&$packet, $msg, $stat)
    {
        $this->errors[$this->rawHistory->metaError]++;
        if ($this->rawHistory->metaError == HUGNETDB_META_ERROR_DUPLICATE) {
            print " Duplicate ".$packet['Date']." ";
            $packet["remove"] = true;
        } else {
            print " - ".$msg;
            $this->stats->incStat($stat);
        }
    }
    /**
    * Function to deal with unsolicited packets
    *
    * @param array &$packet the packet array
    *
    * @return null
    */
    private function _updatedbRemove(&$packet)
    {
        if ($packet["remove"]) {
            if ($this->plog->remove($packet["id"])) {
                print " - local deleted";
            } else {
                print " - Delete Failed";
            }
        }
    }
}
?>

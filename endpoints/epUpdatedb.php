<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007 Hunt Utilities Group, LLC
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
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/** For retrieving packet logs */
require_once(HUGNET_INCLUDE_PATH.'/database/plog.php');
/** For process information and control */
require_once(HUGNET_INCLUDE_PATH.'/database/process.php');
/** For process statistics */
require_once(HUGNET_INCLUDE_PATH.'/database/procstats.php');

/**
 * This class interacts with the final database.  It has the following functions:
 * - Get device and other information from the database
 * - Send pollings and packet logs up to the final database
 *
 * @category   Test
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
class epUpdatedb
{
    /** @var array The array of device information */
    var $ep = array();
    /** @var int To keep track if the minute has changed */
    var $lastminute = 0;
    /** @var array Gateway information */
    var $gw = array(0 => array());
    var $doPoll = false;
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $verbose = false;

    /**
     * Constructor
     *
     * @param object $endpoint an endpoint object
      */
    function __construct(&$endpoint, $verbose=false) {
        $this->verbose = (bool) $verbose;
        $this->db = &$endpoint->db;
        $this->endpoint = &$endpoint;
        print "Creating plog...\n";
        $this->plog = new plog();
        $this->plog->verbose($this->verbose);
        $this->plog->createTable();

        print "Creating remote plog...\n";
        $this->plogRemote = new plog($this->db);
        $this->plogRemote->verbose($this->verbose);
        $this->psend = new DbBase($this->db, "PacketSend");
        $this->psend->verbose($this->verbose);
//        $this->psend->createPacketLog("PacketSend");
        $this->uproc = new process();
        $this->uproc->createTable();
        
        $this->stats = new ProcStats();
        $this->stats->createTable();
        $this->stats->clearStats();
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);

        print("Creating Gateway Cache...\n");
        $this->gateway = new gateway($this->db);
        $this->gateway->verbose($this->verbose); 
        $this->gateway->createCache(HUGNET_LOCAL_DATABASE);
        $this->gateway->getAll();

        print("Creating Device Cache...\n");
        $this->device = new device($this->db);
        $this->device->verbose($this->verbose); 
        $this->device->createCache(HUGNET_LOCAL_DATABASE);
        $this->device->getAll();

        print("Creating Firmware Cache...\n");
        $this->firmware = new firmware($this->db);
        $this->firmware->verbose($this->verbose); 
        $this->firmware->createCache(HUGNET_LOCAL_DATABASE);
        $this->firmware->getAll();

        $this->rawHistory = new DbBase($this->db, "history_raw", "HistoryRawKey", $this->verbose);

     }

    /**
     * The main loop
     *
     * @param array $serv The database server info.  Should contain keys 'dsn', 'User', and 'Password'
     *
     * @return void
     */ 
    function main($serv) 
    {
        $this->uproc->register();
        
        while(1) {
            if ($this->errors[DBBASE_META_ERROR_SERVER_GONE] > 0) {
                do {
                    print "Trying to reconnect to the database ";
                    $this->db = DbBase::createPDO($serv["dsn"], $serv["User"], $serv["Password"]);
                    if ($this->db === false) {
                        $this->updatedbError($emptyVar, "Failed", "dbReconnectFail");
                        print " - Sleeping";
                        sleep(60);
                    } else {
                        $this->updatedbError($emptyVar, "Succeeded", "dbReconnect");
                    }
                    print "\n";
                } while ($this->db === false);
                $this->errors[DBBASE_META_ERROR_SERVER_GONE] = 0;
            }
    
            $this->getAllDevices();
    
            if ($this->verbose) print "[".$this->uproc->me["PID"]."] Starting database update...\n";
    //        $this->uproc->FastCheckin();
    
            // This section does the packetlog
            $this->updatedb();
            $this->getPacketSend();
    
            //        $lplog->reset();
            $this->wait();
    
            // Check the PHP log to make sure it isn't too big.
            clearstatcache();
            if (file_exists("/var/log/php.log")) {
                if (filesize("/var/log/php.log") > (1024*1024)) {
                    $fd = fopen("/var/log/php.log","w");
                    @fclose($fd);
                }
            }
        }
        $this->uproc->unregister();
    
    }
    /**
     * Gets all devices from the remote database and stores them in the
     * local database.
      */
    function getAllDevices() {
        // Regenerate our endpoint information
        if (((time() - $this->lastdev) > 120) || (count($this->ep) < 1)) {
            $this->lastdev = time();

            print "Getting endpoints:  ";

            $res = $this->device->getAll();
            if (is_array($res) && (count($res) > 0)) {
                print "found ".count($res)."";
                $this->oldep = $this->ep;
                $this->ep = array();
                foreach ($res as $key => $val) {
                    $dev = $this->endpoint->DriverInfo($val);
                    $dev['params'] = device::decodeParams($dev['params']);
                    $val['DeviceID'] = trim(strtoupper($val['DeviceID']));
                    $this->ep[$val['DeviceID']] = $dev;                
                }
            }
            print "\n";
        }
        return $this->ep;    
    }

    /**
     * Looks for packets to be sent out on the HUGnet network.
      */
    function getPacketSend() {
        $res = $this->psend->getWhere("Checked = 0");
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Checking for Outgoing Packets\n";
        if (is_array($res) && (count($res) > 0)) {
            foreach ($res as $packet) {
                unset($packet['Checked']);
                $found = false;
                if (isset($this->ep[$packet['DeviceID']])) {
                    $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                    $found = true;
                } else {
            
                    foreach ($this->ep as $key => $val) {
                        if (trim(strtoupper($val['DeviceID'])) == trim(strtoupper($packet['PacketTo']))) {
                            $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                            $found = true;
                            break;
                        }
                    }
                }
                if ($found) {
                    print "[".$this->uproc->me["PID"]."] ".$packet["PacketTo"]." -> ".$packet["sendCommand"]." -> ";                        

                    $this->getPacketSave($packet);
                    
                    print "\n";
                }
            }
        }
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Done\n";
       
    }
    /**
     * Saves the packet in the local database and marks it as saved in the remote database.
     *
     * @param array $packet the packet array
     */    
    private function getPacketSave(&$packet) {
        if ($this->psend->add($packet)) {
            print " Saved ";
            $where = " (GatewayKey = '".$packet['GatewayKey']."'".
                     " AND ".
                     " Date = '".$packet['Date']."'".
                     " AND ".
                     " Command = '".$packet['Command']."'".
                     " AND ".
                     " sendCommand = '".$packet['sendCommand']."'".
                     " AND ".
                     " PacketFrom = '".$packet['PacketFrom']."'".
                     " AND ".
                     " PacketTo = '".$packet['PacketTo']."') ";
            
            $res = $this->endpoint->db->AutoExecute("PacketSend", array("Checked" => 1), 'UPDATE', $where);
            if ($res) {
                print " Updated ";
                return true;
            }
        } 

        print " Failed ";
        return false;

    }
    /**
     * Waits for a time when there is nothing to do.  This is so we don't eat
     * all of the processing time.
      */
    function wait() {
        if ($this->verbose) print  "[".$this->uproc->me["PID"]."] Pausing...\n";
//        $cnt = 0;
//        while((date("i") == $this->lastminute) && ($cnt++ < 2)) {
            sleep(2);
//        }
//        sleep(10);
        $this->lastminute = date(i);

    }

    /**
     * Update the remote database from the local one.
      */
    function updatedb() {
        $res = $this->plog->getAll(50);
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Found ".count($res)." Packets\n";
        if (!is_array($res)) return;
        foreach ($res as $packet) {
            $this->stats->incStat("Packets");                    

            print "[".$this->uproc->me["PID"]."] ".$packet["PacketFrom"]." ".$packet["sendCommand"];

            $DeviceID = trim(strtoupper($packet['PacketFrom']));       
            if (is_array($this->ep[$DeviceID])) {
                $packet = array_merge($this->ep[$DeviceID], $packet);
            }
            $packet["remove"] == false;
            $this->updatedbUnsolicited($packet);
            $this->updatedbReply($packet);
            $this->updatedbConfig($packet);
            $this->updatedbPoll($packet);
            $this->updatedbUnknown($packet);
            $this->updatedbRemove($packet);
            print "\r\n";

        }
    }
    
    /**
     * Function to deal with unsolicited packets
     *
     * @param array $packet the packet array
     */ 
    private function updatedbUnsolicited(&$packet) {
        if ($packet['Type'] == 'UNSOLICITED') {
            $packet["Checked"] = true;
            $this->stats->incStat("Unsolicited");                    
            $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
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
     * @param array $packet the packet array
     */ 
    private function updatedbReply(&$packet) {
        if ($packet['Type'] == 'REPLY') {
            $packet["Checked"] = true;
            $this->stats->incStat("Reply");
            $return = $this->endpoint->db->AutoExecute("PacketSend", $packet, 'INSERT');
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
     * @param array $packet the packet array
     */ 
    private function updatedbConfig(&$packet) {
        if ($packet['Type'] == 'CONFIG') {
            $packet["Checked"] = true;
            $this->stats->incStat("Config");
            $return = $this->plogRemote->add($packet);
            if ($return) {
                print " - Moved ";
                $packet["remove"] = true;
            } else {
                print " - Move Failed ";
                $this->updatedbError($packet, "Update Failed", "Config Failed");
            }
            if ($this->endpoint->UpdateDevice(array($packet))) {
                $this->stats->incStat("Device Updated");
                print " - Updated ";                    
            } else {
                print " - Update Failed ";

//                        $return = $this->endpoint->db->AutoExecute($this->endpoint->device_table, $packet, 'INSERT');
            }
        }
    }
    /**
     * Function to deal with unsolicited packets
     *
     * @param array $packet the packet array
     */ 
    private function updatedbPoll(&$packet) {
        if ($packet['Type'] == 'POLL') {
            $packet["Checked"] = true;
            $this->stats->incStat("Poll");
            print " ".$packet['Driver']." ";
            $packet = $this->endpoint->InterpSensors($packet, array($packet));
            $packet = $packet[0];
            print " ".$packet["Date"]; 
            print " - decoded ".$packet['sendCommand']." ";
            if ($this->updatedbPollDuplicate($packet)) {
                print "Duplicate";
                $packet["remove"] = true;
            } else {

                if ($this->updatedbPollRawHistory(&$packet)) {
                    $packet["remove"] = true;
                    $this->updatedbPollHistory($packet);
                }
            }
        }
    }
    /**
     * Checks if this is a duplicate or not.
     *
     * @param array $packet the packet array
     */ 
    private function updatedbPollHistory(&$packet) 
    {
        $set = array(
            "LastPoll" => $packet["Date"],
            "GatewayKey" => $packet['GatewayKey'],
        );

        $hist = $this->endpoint->saveSensorData($packet, array($packet));
        if ($hist) {
            $set["LastHistory"] = $packet["Date"];
            print " - ".$packet["Driver"]." history ";
        } else {
            print " - History Failed";
//            if ($testMode) var_dump($this->);
        }
        $ret = $this->endpoint->device->update($packet['DeviceKey'], $set);
        if ($ret) {
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
     * @param array $packet the packet array
     */ 
    private function updatedbPollRawHistory(&$packet) 
    {
        $ret = $this->rawHistory->add($packet);
        if ($ret) {
            $info = array();
            print " - raw history ";
            return true;
        } else {
            $this->updatedbError($packet, "Raw History Failed", "Poll Failed");
            return false;
        }
        
    }
    /**
     * Checks if this is a duplicate or not.
     *
     * @param array $packet the packet array
     *
     * @return bool
     */ 
    private function updatedbPollDuplicate(&$packet) 
    {
        if (isset($packet['DataIndex'])) {
            $data = array($packet["DeviceKey"], $packet["sendCommand"], $packet["Date"]);
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
     * @param array $packet the packet array
     */ 
    private function updatedbUnknown(&$packet) {
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
            return FALSE;
        }
    }
    /**
     * Function to deal with unsolicited packets
     *
     * @param array $packet the packet array
     * @param string $msg Generic message to print if specific failures can't be found
     * @param string $stat Generic stat to increment if specific failures can't be found
     */ 
    function updatedbError(&$packet, $msg, $stat) 
    {
        $this->errors[$this->rawHistory->metaError]++;
        if ($this->rawHistory->metaError == DBBASE_META_ERROR_DUPLICATE) {
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
     * @param array $packet the packet array
     */ 
    private function updatedbRemove(&$packet) {
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

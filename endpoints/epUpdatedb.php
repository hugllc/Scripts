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
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Scripts
 * @subpackage UpdateDB
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id: updatedb.php 375 2007-10-16 18:55:27Z prices $    
 *
 */
/** For retrieving packet logs */
require_once(HUGNET_INCLUDE_PATH.'/plog.php');
/** For process information and control */
require_once(HUGNET_INCLUDE_PATH.'/process.php');

/**
 * This class interacts with the final database.  It has the following functions:
 * - Get device and other information from the database
 * - Send pollings and packet logs up to the final database
 *
 */
class epUpdatedb {
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
    function __construct(&$endpoint) {
        $this->endpoint = &$endpoint;
        $this->plog = new plog();
        $this->plog->createPacketLog();
        $this->psend = new plog("PacketSend");
//        $this->psend->createPacketLog("PacketSend");
        $this->uproc = new process();
        $this->uproc->clearStats();
        $this->uproc->setStat('start', time());
        $this->uproc->setStat('PID', $this->uproc->me['PID']);
        $this->devices = new deviceCache();

     }

    /**
     * Gets all devices from the remote database and stores them in the
     * local database.
      */
    function getAllDevices() {
        // Regenerate our endpoint information
        if (((time() - $this->lastdev) > 120) || (count($this->ep) < 1)) {
            $this->lastdev = time();

            print "Getting endpoints\n";


            $query = "SELECT * FROM ".$this->endpoint->device_table;
            $res = $this->endpoint->db->getArray($query);
            if (is_array($res) && (count($res) > 0)) {
                $this->oldep = $this->ep;
                $this->ep = array();
                foreach ($res as $key => $val) {
                    $dev = $this->endpoint->DriverInfo($val);
                    $dev['params'] = device::decodeParams($dev['params']);
//                    if (isset($this->oldep[$key])) $dev = array_merge($this->oldep[$key], $dev);
                    $val['DeviceID'] = trim(strtoupper($val['DeviceID']));
                    $this->ep[$val['DeviceID']] = $dev;                
                    $res = $this->devices->add($dev);
                }
            }
        }
        return $this->ep;    
    }

    /**
     * Looks for packets to be sent out on the HUGnet network.
      */
    function getPacketSend() {
        $query = "SELECT * FROM PacketSend WHERE Checked = 0";
        $res = $this->endpoint->db->getArray($query);
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
            $this->uproc->incStat("Packets");                    

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
            $packed["Checked"] = true;
            $this->uproc->incStat("Unsolicited");                    
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
            $packed["Checked"] = true;
            $this->uproc->incStat("Reply");
            $return = $this->endpoint->db->AutoExecute("PacketSend", $packet, 'INSERT');
            if ($return) {
                print " - Inserted into PacketSend ".$packet['sendCommand']."";                    
                $packet["remove"] = true;
            } else {
                print " - Failed ";
                $this->uproc->incStat("Reply Failed");
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
            $packed["Checked"] = true;
            $this->uproc->incStat("Config");
            $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
            
            if ($return) {
                print " - Moved ";
                $packet["remove"] = true;

            } else {
                $this->updatedbError($packet, "Update Failed", "Config Failed");
            }
            if ($this->endpoint->UpdateDevice(array($packet))) {
                $this->uproc->incStat("Device Updated");
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
            $packed["Checked"] = true;
            $this->uproc->incStat("Poll");
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
    private function updatedbPollHistory(&$packet) {
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
            if ($testMode) print $this->endpoint->db->MetaErrorMsg();
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
    private function updatedbPollRawHistory(&$packet) {
                $ret = $this->endpoint->db->AutoExecute($this->endpoint->raw_history_table, $packet, 'INSERT');
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
     */ 
    private function updatedbPollDuplicate(&$packet) {
        if (isset($packet['DataIndex'])) {

            $query = " SELECT * FROM ".$this->endpoint->raw_history_table.
                     " WHERE " .
                     " DeviceKey=".$packet['DeviceKey'] .
                     " AND " .
                     " sendCommand='".$packet['sendCommand']."'" .
                     " AND " .
                     " Date='".$packet["Date"]."' " . 
                     " ORDER BY 'Date' desc " .
                     " LIMIT 0, 1 ";
            $check = $this->endpoint->db->getArray($query);
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
            $this->uproc->incStat("Unknown");
            $packet["remove"] = true;
        }
    }
    /**
     * Function to deal with unsolicited packets
     *
     * @param array $packet the packet array
     * @param string $msg Generic message to print if specific failures can't be found
     * @param string $stat Generic stat to increment if specific failures can't be found
     */ 
    private function updatedbError(&$packet, $msg, $stat) {
        $error = $this->endpoint->db->MetaError();
        if ($error == DB_ERROR_ALREADY_EXISTS) {
            print " Duplicate ".$packet['Date']." ";
            $packet["remove"] = true;                                            
        } else {
            print " - ".$msg;
           $this->uproc->incStat($stat);
        }
    }
    /**
     * Function to deal with unsolicited packets
     *
     * @param array $packet the packet array
     */ 
    private function updatedbRemove(&$packet) {
        if ($packet["remove"]) {
            if ($this->plog->remove($packet)) {
                print " - local deleted";
            } else {
                print " - Delete Failed";
            }
        }
    }
}
?>

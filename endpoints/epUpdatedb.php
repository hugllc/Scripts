<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @subpackage UpdateDB
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id: updatedb.php 375 2007-10-16 18:55:27Z prices $    
 *
 */

    require_once(HUGNET_INCLUDE_PATH.'/plog.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.php');


class epUpdatedb {

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    var $doPoll = FALSE;
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $verbose = FALSE;

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
        		foreach($res as $key => $val) {
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

    function getPacketSend() {
    	$query = "SELECT * FROM PacketSend WHERE Checked = 0";
        $res = $this->endpoint->db->getArray($query);
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Checking for Outgoing Packets\n";
    	if (is_array($res) && (count($res) > 0)) {
            foreach($res as $packet) {
                unset($packet['Checked']);
                $found = FALSE;
                if (isset($this->ep[$packet['DeviceID']])) {
                    $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                    $found = TRUE;
                } else {
            
                    foreach($this->ep as $key => $val) {
                        if (trim(strtoupper($val['DeviceID'])) == trim(strtoupper($packet['PacketTo']))) {
                            $packet['DeviceKey'] = $this->ep[$packet['DeviceID']]['DeviceKey'];
                            $found = TRUE;
                            break;
                        }
                    }
                }
                if ($found) {
                    print "[".$this->uproc->me["PID"]."] ".$packet["PacketTo"]." -> ".$packet["sendCommand"]." -> ";                        
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
                        }
    //                    print $this->endpoint->db->ErrorMsg();
                    } else {
                        print " Failed ";
                    }
                    print "\n";
                }
            }
        }
        if ($this->verbose) print "[".$this->uproc->me["PID"]."] Done\n";
       
    }

    function wait() {
    	if ($this->verbose) print  "[".$this->uproc->me["PID"]."] Pausing...\n";
//        $cnt = 0;
//		while((date("i") == $this->lastminute) && ($cnt++ < 2)) {
		    sleep(2);
//		}
//        sleep(10);
        $this->lastminute = date(i);

    }

    function updatedb() {
    	$res = $this->plog->getAll(50);
		if ($this->verbose) print "[".$this->uproc->me["PID"]."] Found ".count($res)." Packets\n";
        if (!is_array($res)) return;
		foreach($res as $packet) {
            $this->uproc->incStat("Packets");					

			print "[".$this->uproc->me["PID"]."] ".$packet["PacketFrom"]." ".$packet["sendCommand"];

            $DeviceID = trim(strtoupper($packet['PacketFrom']));       
            if (is_array($this->ep[$DeviceID])) {
                $packet = array_merge($this->ep[$DeviceID], $packet);
            }
			$remove = FALSE;

			switch($packet['Type']) {
				case 'UNSOLICITED':
                    $this->uproc->incStat("Unsolicited");					
				    $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
					if ($return) {
						print " - Inserted ".$packet['sendCommand']."";					
						$remove = TRUE;
					} else {
                        $error = $this->endpoint->db->MetaError();
                        if ($error == DB_ERROR_ALREADY_EXISTS) {
							print " Duplicate ".$packet['Date']." ";
							$remove = TRUE;											
						} else {
                            $this->uproc->incStat("Unsolicited Failed");					
							print " - Failed ";
						}
					}
					break;
				case 'REPLY':
                    $this->uproc->incStat("Reply");
				    $return = $this->endpoint->db->AutoExecute("PacketSend", $packet, 'INSERT');
					if ($return) {
						print " - Inserted into PacketSend ".$packet['sendCommand']."";					
						$remove = TRUE;
					} else {
						print " - Failed ";
                        $this->uproc->incStat("Reply Failed");
					}
					break;
				case "CONFIG":
                    $this->uproc->incStat("Config");
				    $return = $this->endpoint->db->AutoExecute("PacketLog", $packet, 'INSERT');
                    
					if ($return) {
						print " - Moved ";
						$remove = TRUE;
	
					} else {
                        $error = $this->endpoint->db->MetaError();
                        if ($error == DB_ERROR_ALREADY_EXISTS) {
							print " Duplicate ".$packet['Date']." ";
							$remove = TRUE;											
						} else {
							print " - Failed ";
                           $this->uproc->incStat("Config Failed");
						}
					}
					if ($this->endpoint->UpdateDevice(array($packet))) {
                        $this->uproc->incStat("Device Updated");
						print " - Updated ";					
						$refreshdev = TRUE;
					} else {
						print " - Update Failed ";

//	    			    $return = $this->endpoint->db->AutoExecute($this->endpoint->device_table, $packet, 'INSERT');
					}
					break;
				case 'POLL':
                    $this->uproc->incStat("Poll");
                    print " ".$packet['Driver']." ";
					$packet = $this->endpoint->InterpSensors($packet, array($packet));
					$packet = $packet[0];
					print " ".$packet["Date"]; 
					print " - decoded ".$packet['sendCommand']." ";
					$duplicate = FALSE;
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
								$duplicate = TRUE;
							}
						}
					}
					if ($duplicate === FALSE) {
						$return = $this->endpoint->db->AutoExecute($this->endpoint->raw_history_table, $packet, 'INSERT');

						if ($return) {
							$info = array();
							print " - raw history ";
			
                            $set = " LastPoll = '".$packet["Date"]."' " .
                                   ", GatewayKey = '".$packet['GatewayKey']."' ";

							$hist = $this->endpoint->saveSensorData($packet, array($packet));
							if ($hist) {
								$set .= ", LastHistory = '".$packet["Date"]."' ";
								print " - ".$packet["Driver"]." history ";
							} else {
								print " - History Failed";
								if ($testMode) {
								    if ($this->endpoint->db->MetaError() != 0) {
    								    print $this->endpoint->db->MetaErrorMsg();
    								}
								}
							}

                            $query = " UPDATE ".$this->endpoint->device_table.
                                     " SET " . $set .
                                     " WHERE " .
                                     " DeviceKey=".$packet['DeviceKey'];

							if ($this->endpoint->db->Execute($query)) {
								print " - Last Poll ";
							} else {
								print " - Last Poll Failed ";
							}
							$remove = TRUE;
						} else {
                            $error = $this->endpoint->db->MetaError();
                            if ($error == DB_ERROR_ALREADY_EXISTS) {
								print " Duplicate ".$packet['Date']." ";
								$remove = TRUE;											
							} else {
								print " - Raw History Failed ";
                                $this->uproc->incStat("Poll Failed");
							}
						}

					} else {
   						print "Duplicate";
  						$remove = TRUE;
					}
					break;
				default:
                    $this->uproc->incStat("Unknown");
					$remove = TRUE;
					break;
				
			}
			if ($remove) {
				if ($this->plog->remove($packet)) {
					print " - local deleted";
				} else {
    				print " - Delete Failed";
				}
			}
			print "\r\n";
		}

    }
}
?>

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
 *   @subpackage Poll
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
 */

    define("POLL_VERSION", "0.2.10");
    define("POLL_PARTNUMBER", "0039260150");  //0039-26-01-P
    define("POLL_SVN", '$Id$');

	$GatewayKey = FALSE;
    $testMode = FALSE;

	require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/process.inc.php');

    print 'poll.php Version '.POLL_VERSION.'  $Id$'."\n";
	print "Starting...\n";

    define("CONTROLLER_CHECK", 10);

//	$proc = new process(NULL, "", "NORMAL", FALSE);
//	if ($proc->Register() === FALSE) {
//		die ("Already Running\r\n");	
//	}

	if (($GatewayKey == FALSE) | ($GatewayKey == 0)) die("You must supply a gateway key\n");
	
    $endpoint->packet->_getAll = TRUE;

//	$plog = new MDB_QueryWrapper($prefs['servers'], "HUGnetLocal", array('table' => "PacketLog", "dbWrite" => true));
    
	//Only try twice per server.
	$endpoint->socket->Retries = 2;
	$endpoint->socket->PacketTimeout = 6;


    $poll = new ep_poll($endpoint, $testMode);
    $poll->uproc->register();

//    $poll->test = $testMode;
//    $poll->getGateways($GatewayKey);
    if (isset($GatewayIP)) {
        $gw = array(
            'GatewayIP' => $GatewayIP,
            'GatewayPort' => $GatewayPort,
            'GatewayName' => $GatewayIP,
            'GatewayKey' => $GatewayKey,
        );
/*       
        $TGatewayKey = $GatewayKey;
        $query = "SELECT * FROM gateways ".
                 "WHERE GatewayKey=".$gw['MasterGatewayKey'];
        $tgw = $endpoint->db->getArray($query);
                if ($tgw[0]["BackupKey"] != 0) {
                        $gw['MasterGatewayKey'] = $tgw[0]["BackupKey"];
                }
        } while ($tgw[0]["BackupKey"] != 0);
*/
        $poll->forceGateways($gw);
		print "Using Gateway ".$gw["GatewayIP"].":".$gw["GatewayPort"]."\n";
    } else {
        die("Gateway key must be supplied (-g)\r\n");
    }
    $poll->powerup();
    $poll->packet->packetSetCallBack('checkPacket', $poll);

	while (1) {

        print "Using: ".$endpoint->packet->SN." Priority: ".$poll->Priority."\r\n";
        $poll->checkOtherGW();
        $poll->getAllDevices();
        $poll->controllerCheck();
        $poll->poll();	
        
        $poll->wait();
	}
    $poll->uproc->unregister();

	print "Finished\n";
/**
 *	@endcond
 */

class ep_poll {

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    var $doPoll = FALSE;
    var $doCCheck = FALSE;
    var $doConfig = FALSE;
    var $doUnsolicited = FALSE;
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = FALSE;
    var $failureLimit = 5;
    var $Priority = 0;
    var $lastContactTime = 0; //!< Last time an endpoint was contacted.
    var $lastContactAttempt = 0; //!< Last time an endpoint was contacted.
    var $critTime = 60;
    
    function ep_poll(&$endpoint, $test=FALSE) {
        $this->test = (bool) $test;
    	$this->cutoffdate = date("Y-m-d H:i:s", (time() - (86400 * $this->cutoffdays)));
        $this->endpoint = &$endpoint;
        $this->plog = new plog();
        $this->plog->createPacketLog();
        $this->psend = new plog("PacketSend");
        $this->packet = &$this->endpoint->packet;
//        $this->getGateways($GatewayKey);
//        $this->randomizegwTimeout();
        $this->setPriority();
        $this->uproc = new process();
        $this->uproc->clearStats();        
        $this->uproc->setStat('start', time());
        $this->uproc->setStat('PID', $this->uproc->me['PID']);
        $this->uproc->setStat('Priority', $this->Priority);
        $this->devices = new deviceCache();
        $this->lastContactTime = time();
        $this->lastContactAttempt = time();

     }
    
    function setPriority() {
        if ($this->test) {
            $this->Priority = 0xFF;
        } else {
            $this->Priority = mt_rand(1, 0xFF);
        }
    }

    function getGateways($Key) {
        $query = "SELECT * FROM ".$this->endpoint->gateway->table.
                 " WHERE ".
                 $this->endpoint->gateway->id." = ".$Key.
                 " OR ".
                 " BackupKey = ".$Key.
                 " ORDER BY BackupKey asc ";
                 ;
    	$res = $this->endpoint->db->getArray($query);
    	$this->GatewayKey = $Key;

    	if (!is_array($res)) return FALSE;

        $this->gw = $res;

    }

    function powerup() {
        if ($this->gw[0] !== array()) {
            // Send a powerup packet.
            $pkt = array(
                'to' => $this->endpoint->packet->unsolicitedID,
                'command' => PACKET_COMMAND_POWERUP,
            );
            $this->endpoint->packet->sendPacket($this->gw[0], array($pkt), FALSE);
        }
        $this->lastminute = date('i');
        $this->setPollWait();
    }
    
    function forceGateways($gw) {
        $this->gw = array(0 => $gw);
        $this->GatewayKey = $gw['GatewayKey'];
        $this->uproc->setStat('GatewayKey', $this->GatewayKey);
        $this->uproc->setStat('GatewayIP', $gw['GatewayIP']);
        $this->uproc->setStat('GatewayPort', $gw['GatewayPort']);
    }

    function devGateway($key) {
        if (is_array($this->ep[$key])) {
    		if (is_array($this->gw[$this->_devInfo[$key]["gwIndex"]])) {
    			$this->ep[$key] = array_merge($this->ep[$key], $this->gw[$this->_devInfo[$key]["gwIndex"]]);
    	    } else if (is_array($this->gw[0])){
    			$this->ep[$key] = array_merge($this->ep[$key], $this->gw[0]);
    			$this->_devInfo[$key]["gwIndex"] = 0;        		    
            } else {
                // Leave it as is.  We don't know what gateway this is.
            }
        }
    }
    /**
    	@brief Figures out the next time we should poll    
    	@param $time
    	@param $Interval
    	@param $failures
    	@return The time of the next poll
    */
    function GetNextPoll($key, $time=NULL) {
//        $time = time();
        if ($this->ep[$key]["PollInterval"] <= 0) return;

        if (!isset($this->_devInfo[$key]["gwIndex"])) $this->_devInfo[$key]["gwIndex"] = 0;

        if ($time == NULL) {
            $lastpoll1 = strtotime($this->ep[$key]["LastPoll"]);
            $lastpoll2 = strtotime($this->_devInfo[$key]["LastPoll"]);
            if ($lastpoll1 > $lastpoll2) {
                $time = $lastpoll1;
            } else {
                $time = $lastpoll2;            
            }
        }
        $Interval = $this->ep[$key]["PollInterval"];
    	if ($this->_devInfo[$key]['failures'] > 0) {
    		$Interval = ($this->_devInfo[$key]['failures'] / $this->failureLimit) * $Interval; 
        }
   		if ($Interval > 240) $Interval = 240;

        $Interval = (int) $Interval;

    	$sec = 0; //date("s", $time);
    	$min = date("i", $time) + $Interval;
    	$hour = date("H", $time);
    	$mon = date("m", $time);
    	$day = date("d", $time);
    	$year = date("Y", $time);
    
        $newtime = mktime($hour, $min, $sec, $mon, $day, $year);
        if ($this->_devInfo[$key]["PollTime"] < $newtime) {
            $this->_devInfo[$key]["PollTime"] = $newtime;
        }
    }
    
    
    function getAllDevices() {

		// Regenerate our endpoint information
		if (((time() - $this->lastdev) > 60) || (count($this->ep) < 1)) {
			$this->lastdev = time();

			print "Getting endpoints for Gateway #".$this->gw[0]["GatewayKey"]."\n";


        	$query = "SELECT * FROM ".$this->endpoint->device_table.
        	         " WHERE ".
        	         "GatewayKey=".$this->gw[0]["GatewayKey"];
            $res = $this->devices->query($query);
        	if (!is_array($res) || (count($res) == 0)) {
        	    $this->uproc->incStat("Device Cache Failed");
                $res = $this->endpoint->db->getArray($query);
            }
        	if (is_array($res) && (count($res) > 0)) {
                $this->ep = array();
        		foreach($res as $key => $val) {
                    if ($val['DeviceID'] !== "000000") {
                        $dev = $this->endpoint->DriverInfo($val);
            			$this->ep[$val['DeviceID']] = $dev;
            			$this->devGateway($key);
                        if ($this->endpoint->device->isController($dev)) {
                            $this->_devInfo[$key]['GetConfig'] = TRUE;
               		    }
               			if (!isset($this->_devInfo[$key]["GetConfig"])) $this->_devInfo[$key]["GetConfig"] = FALSE;
            	    }
        		}
            }
        }
        return $this->ep;    
    }
    
    

    function controllerCheck() {
        if (!$this->doCCheck) return;

        print "Checking Controllers...\n";
        foreach ($this->ep as $key => $dev)
		{
            if (method_exists($this->endpoint->drivers[$dev['Driver']], "checkProgram")) {
                if (($this->_devInfo[$key]["LastCheck"] + $this->ccTimeout) < time()) {
                    $this->checkDev($key);
                    $this->_devInfo[$key]['getConfig'] = TRUE;
        		}
		    }
		}
    }
    
    function wait() {
        $this->uproc->setStat("doPoll", $this->doPoll);
        $this->uproc->setStat("doCCheck", $this->doCCheck);
        $this->uproc->setStat("doConfig", $this->doConfig);
        $this->uproc->setStat("doUnsolicited", $this->doUnsolicited);
        $this->uproc->setStat("Gateways", base64_encode(serialize($this->otherGW)));
        $this->uproc->setStat("PacketSN", substr($this->packet->SN, 0, 6));

        if (($this->lastContactAttempt - $this->lastContactTime) > (30 * 60)) {
            $this->criticalError("Last Poll at ".date("Y-m-d H:i:s", $this->lastContantTime));
        }
        
		do {
            $this->getConfig();
            $packet = $this->checkPacketQ();
            if ($packet === FALSE) $packet = $this->endpoint->packet->monitor($this->gw[0], 1);
		} while(date("i") == $this->lastminute);
        $this->lastminute = date("i");
        
        print "Checking... ".date("Y-m-d H:i:s")."\n";
    }

    function qPacket($gw, $to, $command, $data="", $timeout=0) {
  
        $pkt = $this->packet->buildPacket($to, $command, $data);
        $pkt['Timeout'] = $timeout;
        $pkt['gw'] = $gw;
        $this->packetQ[] = $pkt;
        return $ret;
    }
    
    function setPollWait() {
        if ($this->test) return;
        $this->otherGW["Wait"] = array(
            'LastConfig' => date("Y-m-d H:i:s"),
            'RemoveTime' => time(),
        );
    }

    function findDev($DeviceID) {
        if (isset($this->ep[$DeviceID])) {
            return $DeviceID;
        }
        foreach($this->ep as $key => $ep) {
            if (trim(strtoupper($ep["DeviceID"])) == trim(strtoupper($DeviceID))) {
                return $key;
            }
        }
        return FALSE;
    }

    function checkPacket($pkt, $Type = "UNKNOWN") {
        if (is_array($pkt)) {
            if ($pkt['Unsolicited']) {
                if ($this->doUnsolicited) {
                    $this->uproc->incStat("Unsolicited");
                    $Type = "UNSOLICITED";
                    switch(trim(strtoupper($pkt['Command']))) {
                        case PACKET_COMMAND_POWERUP:
                        case PACKET_COMMAND_RECONFIG:
                            if (!$pkt['isGateway']) {
//                                $found = FALSE;
 //                               foreach($this->ep as $key => $ep) {
//                                    if (trim(strtoupper($ep["DeviceID"])) == trim(strtoupper($pkt['From']))) {
                                        //$this->ep[$key]['LastConfig'] = date("Y-m-d H:i:s", 0);
                                $pkt['DeviceID'] = trim(strtoupper($pkt['From']));
                                if (isset($this->ep[$pkt['DeviceID']])) {
                                        $this->_devInfo[$pkt['DeviceID']]["failures"] = 0;
                                        unset($this->_devInfo[$pkt['DeviceID']]['failedcCheck']);
                                        unset($this->_devInfo[$pkt['DeviceID']]['failedCheck']);
                                        unset($this->_devInfo[$pkt['DeviceID']]['nextCheck']);
                                        $this->getNextPoll($pkt['DeviceID']);                                        
                                        $pkt['DeviceKey'] = $this->ep[$pkt['DeviceID']]['DeviceKey'];
                                        $this->_devInfo[$pkt['DeviceID']]['GetConfig'] = TRUE;
                                } else {
                                    $this->ep[$pkt['From']] = $pkt;
                                    $this->ep[$pkt['From']]['DeviceID'] = $pkt['From'];
                                    $this->_devInfo[$pkt['From']]['GetConfig'] = TRUE;
                                    $this->ep[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                                    $this->_devInfo[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                                }
                            }
                            break;
                    }
                    $lpkt = $this->endpoint->PacketLog($pkt, $this->gw[0], $Type);
                    $this->plog->add($lpkt);
                }
            } else if ($pkt['toMe']) {
                $this->uproc->incStat("To Me");
                switch(trim(strtoupper($pkt['sendCommand']))) {
                    case PACKET_COMMAND_GETSETUP:
                        $this->interpConfig(array($pkt));
                        break;
                    default:    // We didn't send a packet out.
                        switch(trim(strtoupper($pkt['Command']))) {
                            case PACKET_COMMAND_GETSETUP:
                                $ret = $this->packet->sendReply($pkt, $pkt['From'], $this->getMyConfig());
                                break;
                            default:
                                break;
                        }
                        break;
                    
                }            
            

            } else {
                $lpkt = $this->endpoint->PacketLog($pkt, $dev, $Type);
                $this->plog->add($lpkt);
            }
            if ($pkt['isGateway']) {
                if (!isset($this->otherGW[$pkt['From']])) {
                    $pkt['DeviceID'] = $pkt['From'];
                    $this->otherGW[$pkt['From']] = array('DeviceID' => $pkt['From'], 'RemoveTime' => (time() + $this->gwRemove));
                    $ret = $this->qPacket($pkt, $pkt['From'], PACKET_COMMAND_GETSETUP, 30);
                }
            }

        }
    }

    function poll() {
        if ($this->doPoll !== TRUE) {
            print "Skipping the poll.\r\n";
            return;
        }

        if ($this->lastPoll == date("i")) return;
        $this->lastPoll = date("i");

//        shuffle($this->ep);

        $epkeys = array_keys($this->ep);
        shuffle($epkeys);
//    	foreach($this->ep as $key => $dev) {
    	foreach($epkeys as $key) {
            $dev = $this->ep[$key];
			if ($dev["PollInterval"] > 0) {
                if (empty($this->_devInfo[$key]["PollTime"])) $this->GetNextPoll($key);

				if ($this->_devInfo[$key]["PollTime"] <= time()) { 
                    $this->lastContactAttempt = time();
                    $this->uproc->incStat("Polls");
					print $dev["DeviceID"]." (".$dev["Driver"].") -> ".date("Y-m-d H:i:s", $this->_devInfo[$key]["PollTime"])." <-> ".date("Y-m-d H:i:s");
                    $this->devGateway($key);
					// print  " [".$dev["GatewayName"]."] ->";                    
					$sensorRead = $this->endpoint->ReadSensors($dev);
					$gotReply = FALSE;
					if (is_array($sensorRead) && (count($sensorRead) > 0)) {
						foreach($sensorRead as $sensors) {
                            if ($sensors['Reply'] == TRUE) {
								$gotReply = TRUE;
    							if (is_array($sensors) && (count($sensors) > 0) && isset($sensors['RawData'])) {
    								$sensors['DeviceKey'] == $dev['DeviceKey'];
    								$this->_devInfo[$key]["failures"] = 0;
    								if (!isset($sensors['DataIndex']) || ($this->_devInfo[$key]['DataIndex'] != $sensors["DataIndex"])) {
    									$sensors = $this->endpoint->PacketLog($sensors, $dev, "POLL");
    									if ($this->plog->add($sensors)) {
    										print " Success (".number_format($sensors["ReplyTime"], 2).")";
    										$this->_devInfo[$key]['DataIndex'] = $sensors["DataIndex"];
    										$this->ep[$key]['LastPoll'] = $sensors['Date'];
    										$this->_devInfo[$key]['LastPoll'] = $sensors['Date'];
    										$this->GetNextPoll($key);
    										$this->lastContactTime = time(); // Reset the last contact time
    									} else {
    										$DevCount++;
    										print " Failed to store data \r\n"; //(".$history->Errno."): ".$history->Error;
                                            $this->uproc->incStat("Poll Store Failed");
    										//print strip_tags(get_stuff($history));							
    									}
    								} else {
                                        $this->uproc->incStat("Poll Data Index Ident");
    									print " Data Index (".$sensors['DataIndex'].") Identical (".number_format($sensors["ReplyTime"], 2).")";
    								}
    							}
    						}
						}
					}
					if ($gotReply === FALSE) {
   						$this->_devInfo[$key]["failures"]++;
   						if ($this->_devInfo[$key]["failures"] > $this->failureLimit) {
     						$this->GetNextPoll($key, time());
   						}
//   						$this->GetNextPoll($key, time());
 						// This will make the controller board find it if it doesn't see it yet
                        $this->uproc->incStat("Find Device");
						$ping = $this->endpoint->packet->ping($dev, TRUE);
						$DevCount++;
						print " No data returned (".$this->_devInfo[$key]["failures"].")";
						$this->_devInfo[$key]['gwIndex']++;
						$this->ep[] = array_shift($this->ep);
					} else {
                       $this->uproc->incStat("Poll Success");					
					}
					print "\n";
				}
			}
			if ($this->lastminute != date("i")) break;
		}

    }

    function checkPacketQ() {
        if (count($this->packetQ) > 0) {
            list($key, $q) = each($this->packetQ);
            $gw = (isset($q['gw'])) ? $q['gw'] : $this->gw[0];
            $packet = $this->endpoint->packet->sendPacket($gw, array($q));
            if (is_array($packet)) {
                foreach($packet as $pkt) {
                    $this->checkPacket($pkt);
                }
            }
            unset($this->packetQ[$key]);
            return $packet;
        } else if ($p = $this->psend->getOne()) {
            $pk = array(
                'to' => $p['PacketTo'],
                'command' => $p['sendCommand'],
                'data' => $p['RawData'],
            );
            print $p['PacketTo']." -> Sending user Packet -> ".$p['sendCommand']." -> ";
            $packet = $this->endpoint->packet->sendPacket($this->gw[0], array($pk));
            $this->uproc->incStat("Sent User Packet");
            if (is_array($packet)) {
                foreach($packet as $pkt) {
                    $lpkt = $this->endpoint->PacketLog($pkt, $p, "REPLY");
                    $lpkt['Checked'] = 2;
                    $lpkt['id'] = $p['id'];
                    $lpkt['PacketTo'] = $p['PacketTo'];
                    $lpkt['DeviceKey'] = $p['DeviceKey'];
                    $lpkt['GatewayKey'] = $p['GatewayKey'];
                    $this->plog->add($lpkt);
                }
                print "Success";
                $this->uproc->incStat("Sent User Success");
            } else {
                print "Failed";
            }
            $this->psend->remove($p);
            print "\n";
            return $packet;
        } else {
            return FALSE;
        }
    }

    function checkDev($key) {
        if (!is_array($this->ep[$key])) return;
        if (empty($key)) return;
        
        $this->lastContactAttempt = time();
        $dev = $this->ep[$key];
        if (is_array($this->_devInfo[$key])) {
            $dev = array_merge($dev, $this->_devInfo[$key]);
        }
        
		print "Checking ".$dev["DeviceID"]." ";
        $this->uproc->incStat("Device Checked");
		$pkt = $this->endpoint->ReadConfig($dev);
        $gotConfig = FALSE;
		if ($pkt !== FALSE) {
            $newConfig = $this->interpConfig($pkt);
			foreach($pkt as $p) {
				if ($p !== FALSE) {
				    if ($p["Reply"]) {
    					if (!isset($p['DeviceKey'])) $p['DeviceKey'] = $dev['DeviceKey'];
                        if (empty($dev['GatewayKey'])) $dev['GatewayKey'] = $this->GatewayKey;
   	        					$logpkt = $this->endpoint->PacketLog($p, $dev, "CONFIG");
    					if ($this->plog->add($logpkt)) {
    						print " Done (".number_format($p["ReplyTime"], 2).")";
    						$gotConfig = TRUE;
                            $this->ep[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["GetConfig"] = FALSE;
                            $this->_devInfo[$key]['LastCheck'] = time();
                            unset($this->_devInfo[$key]['failedCheck']);
                            $this->lastContactTime = time();
    					} else {
//    					    print "Error: ".$this->plog->_sqlite->lastError();
    					}
    				}
				}
			}
            if ($gotConfig) {
    			// If it is a controller board check the program
                $this->uproc->incStat("Device Checked Success");
    			if (method_exists($this->endpoint->drivers[$newConfig['Driver']], "checkProgram")) {
                    $this->uproc->incStat("Check Program");
    			    print " Checking Program ";
                    $ret = $this->endpoint->drivers[$dev['Driver']]->checkProgram($newConfig, $pkt, TRUE);
                    if ($ret) {
                        $this->uproc->incStat("Check Program Success");
                        print " Done ";
                    } else {
                        print " Failed ";
                    }
                }
   			} else {
   				print " Failed ";
   				$this->_devInfo[$key]['nextCheck'] = time()+120;
                print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);
//       						$this->_devInfo[$key]["gwIndex"]++;
   		    }

		} else {
			print " Nothing Returned ";
 			$ping = $this->endpoint->packet->ping($dev, TRUE);
            $this->_devInfo[$key]['nextCheck'] = time()+300;
            print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);

		}
		print "\r\n";
    
    }

    function getConfig() {
        if ($this->doConfig !== TRUE) return;
        
        foreach($this->ep as $key => $dev) {
            if (empty($dev['DeviceID'])) {
                unset($this->ep[$key]);
            }
			if ((date("s") > 55) || (date("i") != $this->lastminute)) break;

            if (!isset($this->_devInfo[$key]['nextCheck']) || ($this->_devInfo[$key]['nextCheck'] < time())) {
                if (((strtotime($this->_devInfo[$key]["LastConfig"]) + $this->configInterval) < time()) || $this->_devInfo[$key]['GetConfig']) {
  				    if ($this->_devInfo[$key]['failedCheck'] != date("i")) {
    
                        $this->checkDev($key);
        	    	}
                }
            }
		}

    }

    function checkOtherGW() {

        $doPoll = TRUE;
        $doConfig = TRUE;
        $doCCheck = TRUE;
        $doUnsolicited = TRUE;
        $maxPriority = 0;
        foreach($this->otherGW as $key => $gw) {
            if ($this->otherGW[$key]['failedCheckGW'] != date("i")) {
                $expired = FALSE;
                if ($gw['RemoveTime'] < time()) {
                    unset($this->otherGW[$key]);
                    print "Removed ".$gw['DeviceID'];
                } else if ($key == "Wait") {
                    print "Waiting before Polling... ".date("Y-m-d H:i:s", $gw['RemoveTime']);
                    $doPoll = FALSE;
                    $doConfig = FALSE;
                    $doCCheck = FALSE;
                    $doUnsolicited = FALSE;
                    $maxPriority = 0xFFFF;  // Real priorities should never be this high.
                } else {
                    print "Checking in with Gateway ".$gw['DeviceID']; 
                    $pkt = $this->packet->buildPacket($gw['DeviceID'], PACKET_COMMAND_GETSETUP);
                    $ret = $this->packet->sendPacket($gw, array($pkt), TRUE, 2);
                    if (is_array($ret)) {
                        foreach($ret as $p) {
                            $this->CheckPacket($p);
                        }
                        print " Done ";
       				    unset($this->otherGW[$key]['failedCheckGW']);
        
                    } else {
                        print " Failed ";
           				$this->otherGW[$key]['failedCheckGW'] = date("i");
                        if ($gw['ConfigExpire'] < time()) {
                            $this->otherGW[$key]['doPoll'] = FALSE;
                            $this->otherGW[$key]['doConfig'] = FALSE;
                            $this->otherGW[$key]['doCCheck'] = FALSE;
                            $this->otherGW[$key]['doUnsolicited'] = FALSE;
                            $expired = TRUE;
                            print " Expired ";
                        } else {
                            print date("Y-m-d H:i:s", $gw['ConfigExpire']);
                        }
        
                    }
                    print " P:".$this->otherGW[$key]['Priority'];
                    if ($this->otherGW[$key]['Priority'] == $this->Priority) {
                        $this->setPriority();
                    }
                    if (($maxPriority < $this->otherGW[$key]['Priority']) && !$expired)  {
                        $maxPriority = $this->otherGW[$key]['Priority'];
                    }

                    if ($this->otherGW[$key]['doPoll']) {
                        $doPoll = FALSE;
                        print " Polling";
                    }            
                    if ($this->otherGW[$key]['doConfig']) {
                        $doConfig = FALSE;
                        print " Config";
                    }            
                    if ($this->otherGW[$key]['doCCheck']) {
                        $doCCheck = FALSE;
                        print " CCheck";
                    }            
                    if ($this->otherGW[$key]['doUnsolicited']) {
                        $doUnsolicited = FALSE;
                        print " Unsolicited";
                    }            
        
                }
                print "\r\n";
            }
        }
        if ($maxPriority < $this->Priority) {
            $doPoll = TRUE;
            $doConfig = TRUE;
            $doCCheck = TRUE;
            $doUnsolicited = TRUE;
        } else {
            $doPoll = FALSE;
            $doConfig = FALSE;
            $doCCheck = FALSE;
            $doUnsolicited = FALSE;        
        }
        $this->doPoll = $doPoll;
        $this->doConfig = $doConfig;
        $this->doCCheck = $doCCheck;
        $this->doUnsolicited = $doUnsolicited;
    }

    function randomizegwTimeout() {
        return (time() + mt_rand(120, 420));
    }

    function getMyConfig() {

        $string = $this->packet->hexify($this->packet->SN, 10);

        $string .= POLL_PARTNUMBER . POLL_PARTNUMBER;
        $ver = explode(".", POLL_VERSION);
        for($i = 0; $i < 3; $i++) $string .= $this->packet->hexify($ver[$i], 2);
        $string .= "FFFFFF";

        $stuff = 0;
        if ($this->doPoll) $stuff |= 0x01;
        if ($this->doConfig) $stuff |= 0x02;
        if ($this->doCCheck) $stuff |= 0x04;
        if ($this->doUnsolicited) $stuff |= 0x08;
        $string .= $this->packet->hexify($this->Priority, 2);
        $string .= $this->packet->hexify($stuff, 2);
        $string .= $this->packet->hexify($this->gw[0]['GatewayKey'], 4);
        $string .= $this->packet->hexifyStr(trim($_SERVER['HOST']), 60);
        $myIP =`/sbin/ifconfig|grep Bcast|/bin/cut -f2 -d:|/bin/cut -f1 -d' '`;

        $myIP = explode(".", $myIP);
        for($i = 0; $i < 4; $i++) {
            $string .= $this->packet->hexify($myIP[$i], 2);
        }
        return $string;
    }

    function interpConfig($pkt) {
        if (!is_array($pkt)) return;

        $newConfig = $this->endpoint->InterpConfig($pkt);

        if ($pkt['isGateway']) {

			$newConfig["FWVersion"] = 	trim(strtoupper(hexdec(substr($pkt["RawData"], ENDPOINT_FWV_START, 2)).".".
													hexdec(substr($pkt["RawData"], ENDPOINT_FWV_START+2, 2)).".".
													hexdec(substr($pkt["RawData"], ENDPOINT_FWV_START+4, 2))));


            $newConfig["Priority"] = 	hexdec(trim(strtoupper(substr($pkt["RawData"], ENDPOINT_BOREDOM, 2))));
            $newConfig["Jobs"] = 	hexdec(trim(strtoupper(substr($pkt["RawData"], ENDPOINT_BOREDOM+2, 2))));
            $newConfig["myGatewayKey"] = 	hexdec(trim(strtoupper(substr($pkt["RawData"], ENDPOINT_BOREDOM+4, 4))));
            $newConfig["NodeName"] = 	$this->packet->deHexify(trim(strtoupper(substr($pkt["RawData"], ENDPOINT_BOREDOM+8, 60))));
			$newConfig["NodeIP"] = 	trim(strtoupper(hexdec(substr($pkt["RawData"], ENDPOINT_BOREDOM+68, 2)).".".
													hexdec(substr($pkt["RawData"], ENDPOINT_BOREDOM+70, 2)).".".
													hexdec(substr($pkt["RawData"], ENDPOINT_BOREDOM+72, 2)).".".
													hexdec(substr($pkt["RawData"], ENDPOINT_BOREDOM+74, 2))));

            
            $newConfig['doPoll'] = (bool) ($newConfig['Jobs'] & 0x01);
            $newConfig['doConfig'] = (bool) ($newConfig['Jobs'] & 0x02);
            $newConfig['doCCheck'] = (bool) ($newConfig['Jobs'] & 0x04);
            $newConfig['doUnsolicited'] = (bool) ($newConfig['Jobs'] & 0x08);

            $newConfig['RemoveTime'] = time() + $this->gwRemove;
            $newConfig['ConfigExpire'] = $this->randomizegwTimeout();
            $newConfig['RemoveTimeDate'] = date("Y-m-d H:i:s", $newConfig['RemoveTime']);
            $newConfig['ConfigExpireDate'] = date("Y-m-d H:i:s", $newConfig['ConfigExpire']);
            
            $send = array("FWVersion", "Priority", "myGatewayKey", "NodeName", "NodeIP",
                          "doPoll", "doConfig", "doCCheck", "doUnsolicited", 'ConfigExpire');
            foreach($send as $var) {
                $this->sendGW[$pkt['From']][$var] = $newConfig[$var];
            }            
            
            
            if (!is_array($this->otherGW[$pkt['From']])) {
                $this->otherGW[$pkt['From']] = $newConfig;
            } else {
                $this->otherGW[$pkt['From']] = array_merge($this->otherGW[$pkt['From']], $newConfig);
            }
        } else if ($newConfig['sendCommand'] == PACKET_COMMAND_GETSETUP) {
            if (!is_null($newConfig['DeviceKey'])) {
                $devKey = $newConfig['DeviceKey'];
            } else {
                $devKey = $this->findDev($pkt['From']);
            }
            if ($devkey === FALSE) {
                $devKey = $newConfig['DeviceID'];
            }
            if (is_array($this->ep[$devKey])) $this->ep[$devKey] = array_merge($this->ep[$devKey], $newConfig);
            $this->GetNextPoll($devKey);
            $this->_devInfo[$devKey]['GetConfig'] = FALSE;
        }
        
        return $newConfig;
    }
    
    function criticalFailure($reason) {
        $last = (int) $this->uproc->getStat("LastCriticalError", $this->uproc->me['Program']);
        
        if ((time() - $last) > ($this->critTime * 60)) { 
            $to = "hugnet@hugllc.com";
            $from = "".$this->uproc->me['Host']."<noreply@hugllc.com>";
            $subject = "HUGnet Critical Failure!";
            $message = $reason;
            mail ($to, $subject, $message);
        }
        $this->uproc->setStat("LastCriticalError", time());

    }

}

?>


?>

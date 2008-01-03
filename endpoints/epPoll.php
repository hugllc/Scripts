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
 * @category   Scripts
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
    require_once(HUGNET_INCLUDE_PATH.'/database/plog.php');
    require_once(HUGNET_INCLUDE_PATH.'/database/process.php');
    require_once(HUGNET_INCLUDE_PATH.'/database/procstats.php');

/**
 * Class for polling endpoints
 *
 * @category   Test
 * @package    Scripts
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */ 
class epPoll
{

    var $ep = array();
    var $lastminute = 0;
    var $gw = array(0 => array());
    var $doPoll = false;
    var $doCCheck = false;
    var $doConfig = false;
    var $doUnsolicited = false;
    
    var $configInterval = 43200; //!< Number of seconds between config attempts.
    var $otherGW = array();
    var $packetQ = array();
    var $gwTimeout = 120;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $gwRemove = 600;
    var $ccTimeout = 600;    //!< Minutes. How long we keep a non responding gateway on our list.
    var $cutoffdays = 14;
    var $test = false;
    var $failureLimit = 5;
    var $Priority = 0;
    var $lastContactTime = 0; //!< Last time an endpoint was contacted.
    var $lastContactAttempt = 0; //!< Last time an endpoint was contacted.
    var $critTime = 60;

    /**
     *
     */    
    function __construct(&$endpoint, $gateway=null, $verbose=false, $test=false) 
    {
        $this->uproc = new process();
        $this->uproc->createTable();
        
        $this->stats = new ProcStats();
        $this->stats->createTable();
        $this->stats->clearStats();        
        $this->stats->setStat('start', time());
        $this->stats->setStat('PID', $this->uproc->me['PID']);
        $this->stats->setStat('Priority', $this->myInfo['Priority']);


        $this->test = (bool) $test;
        $this->verbose = (bool) $verbose;
        $this->cutoffdate = date("Y-m-d H:i:s", (time() - (86400 * $this->cutoffdays)));
        $this->endpoint = &$endpoint;
        $this->endpoint->packet->getAll(true);
        do {
            $this->plog = new plog();
            $this->plog->createTable();
            if ($this->plog->criticalError !== false) {
                $this->criticalFailure($this->plog->criticalError);
                print "Local Database not available.  Waiting 5 minutes\n";
                sleep(300);
            }
        } while ($this->plog->criticalError !== false);
        
//        $this->plog->createPacketLog();
        $file = HUGNET_LOCAL_DATABASE;
        $this->psend = new plog($file, "PacketSend");
        $this->psend->createTable("PacketSend");
        $this->psend->verbose($this->verbose);
        $this->packet = &$this->endpoint->packet;
        $this->setPriority();
        $this->device = new device($file);
        $this->device->verbose($this->verbose);
        $this->lastContactTime = time();
        $this->lastContactAttempt = time();
        $this->gateway = new gateway($file);
        $this->gateway->verbose($this->verbose);

        if (!is_null($gateway)) {
            $this->forceGateways($gateway);
        }

        $this->setupMyInfo();
     }
    
    /**
     * Main routine for polling endpoints
     * This routine will
     */    
    function main($while=1) {
        $this->powerup();
        $this->packet->packetSetCallBack('checkPacket', $poll);
    
        do {
            print "Using: ".$this->myInfo['DeviceID']." Priority: ".$this->myInfo["Priority"]."\r\n";
            $this->checkOtherGW();
            $this->getAllDevices();
            $this->controllerCheck();
            $this->poll();    
            
            $this->wait();
        } while ($while);
    
    }
    /**
     * Sets the priority we run at.
      */
    function setPriority() {
        if ($this->test) {
            $this->myInfo['Priority'] = 0xFF;
        } else {
            $this->myInfo['Priority'] = mt_rand(1, 0xFF);
        }
    }

    /**
     * Gets all of the gateways with $Key as their key or backup key
      */
    function getGateways($Key) {
        $res = $this->gateway->get($Key);
        $this->GatewayKey = $Key;

        if (!is_array($res)) return false;

        $this->gw = $res;

    }
    /**
     *  Sets everything up when we start
      */
    function powerup() {
        if ($this->gw[0] !== array()) {
            // Send a powerup packet.
            $pkt = array(
                'to' => $this->endpoint->packet->unsolicitedID,
                'command' => PACKET_COMMAND_POWERUP,
           );
            $this->endpoint->packet->sendPacket($this->gw[0], array($pkt), false);
        }
        $this->lastminute = date('i');
        $this->setPollWait();
    }

    /**
     *  Force the gateway to be a specific one.  The array given
     *  must have the following keys:
     *  - GatewayKey is the database key for the gateway
     *  - GatewayIP is the IP address to contact the endpoints through
     *     defaults to 127.0.0.2 if not given
     *  - GatewayPort is the TCP port number to use to contact the gateways
     *     defaults to 2000 if not given
     *  - GatewayName is the name of the gateway (Optional)
     *
     * @param array $gw The gateway array
      */
    function forceGateways($gw) {
        if (empty($gw['GatewayIP'])) $gw['GatewayIP'] = '127.0.0.1';
        if (empty($gw['GatewayPort'])) $gw['GatewayPort'] = '2000';
        $this->gw = array(0 => $gw);
        $this->GatewayKey = $gw['GatewayKey'];
        $this->stats->setStat('GatewayKey', $this->GatewayKey);
        $this->stats->setStat('GatewayIP', $gw['GatewayIP']);
        $this->stats->setStat('GatewayPort', $gw['GatewayPort']);
        $this->packet->connect($this->gw[0]);
    }

    function devGateway($key) {
        if (is_array($this->ep[$key])) {
            if (is_array($this->gw[$this->_devInfo[$key]["gwIndex"]])) {
                $this->ep[$key] = array_merge($this->ep[$key], $this->gw[$this->_devInfo[$key]["gwIndex"]]);
            } else if (is_array($this->gw[0])) {
                $this->ep[$key] = array_merge($this->ep[$key], $this->gw[0]);
                $this->_devInfo[$key]["gwIndex"] = 0;                    
            } else {
                // Leave it as is.  We don't know what gateway this is.
            }
        }
    }
    /**
     * Figures out the next time we should poll    
     * @param $time
     * @param $Interval
     * @param $failures
     * @return The time of the next poll
     */
    function GetNextPoll($key, $time=null) {
//        $time = time();
        if ($this->ep[$key]["PollInterval"] <= 0) return;

        if (!isset($this->_devInfo[$key]["gwIndex"])) $this->_devInfo[$key]["gwIndex"] = 0;

        if ($time == null) {
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


            $query = "GatewayKey= ? ";
            $res = $this->device->getWhere($query, array($this->gw[0]["GatewayKey"]));

            if (!is_array($res) || (count($res) == 0)) {
                $this->stats->incStat("Device Cache Failed");
                print "Didn't find any devices.\n";
//                $res = $this->endpoint->db->getArray($query);
            }
            if (is_array($res) && (count($res) > 0)) {
                $this->ep = array();
                foreach ($res as $key => $val) {
                    if ($val['DeviceID'] !== "000000") {
                        $dev = $this->endpoint->DriverInfo($val);
                        $this->ep[$val['DeviceID']] = $dev;
                        $this->devGateway($key);
                        if ($this->endpoint->isController($dev)) {
                            $this->_devInfo[$key]['GetConfig'] = true;
                           }
                           if (!isset($this->_devInfo[$key]["GetConfig"])) $this->_devInfo[$key]["GetConfig"] = false;
                    }
                }
            }
        }
        return $this->ep;    
    }
    
    

    function controllerCheck() {
        if (!$this->myInfo['doCCheck']) return;

        print "Checking Controllers...\n";
        foreach ($this->ep as $key => $dev)
        {
            if (method_exists($this->endpoint->drivers[$dev['Driver']], "checkProgram")) {
                if (($this->_devInfo[$key]["LastCheck"] + $this->ccTimeout) < time()) {
                    $this->checkDev($key);
                    $this->_devInfo[$key]['getConfig'] = true;
                }
            }
        }
    }
    
    function wait() {
        $this->setupMyInfo();
        $this->stats->setStat("doPoll", $this->myInfo['doPoll']);
        $this->stats->setStat("doCCheck", $this->myInfo['doCCheck']);
        $this->stats->setStat("doConfig", $this->myInfo['doConfig']);
        $this->stats->setStat("doUnsolicited", $this->myInfo['doUnsolicited']);
        $this->stats->setStat("Gateways", base64_encode(serialize($this->otherGW)));
        $this->stats->setStat("PacketSN", substr($this->DeviceID, 0, 6));

        if (($this->lastContactAttempt - $this->lastContactTime) > (30 * 60)) {
            $this->criticalFailure("Last Poll at ".date("Y-m-d H:i:s", $this->lastContantTime));
        }
        
        do {
            $this->getConfig();
            $packet = $this->checkPacketQ();
            if ($packet === false) $packet = $this->endpoint->packet->monitor($this->gw[0], 1);
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
        $wait = mt_rand(90, 300);
        $this->otherGW["Wait"] = array(
            'LastConfig' => date("Y-m-d H:i:s"),
            'RemoveTime' => time() + $wait,
        );
        print "Waiting $wait seconds before polling\n";
    }

    function findDev($DeviceID) {
        if (isset($this->ep[$DeviceID])) {
            return $DeviceID;
        }
        foreach ($this->ep as $key => $ep) {
            if (trim(strtoupper($ep["DeviceID"])) == trim(strtoupper($DeviceID))) {
                return $key;
            }
        }
        return false;
    }

    function checkPacket($pkt, $Type = "UNKNOWN") {
        if (is_array($pkt)) {
            if ($pkt['Unsolicited']) {
                if ($this->myInfo['doUnsolicited']) {
                    $this->stats->incStat("Unsolicited");
                    $Type = "UNSOLICITED";
                    switch(trim(strtoupper($pkt['Command']))) {
                        case PACKET_COMMAND_POWERUP:
                        case PACKET_COMMAND_RECONFIG:
                            if (!$pkt['isGateway']) {
//                                $found = false;
 //                               foreach ($this->ep as $key => $ep) {
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
                                        $this->_devInfo[$pkt['DeviceID']]['GetConfig'] = true;
                                } else {
                                    $this->ep[$pkt['From']] = $pkt;
                                    $this->ep[$pkt['From']]['DeviceID'] = $pkt['From'];
                                    $this->_devInfo[$pkt['From']]['GetConfig'] = true;
                                    $this->ep[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                                    $this->_devInfo[$pkt['From']]['LastConfig'] = date("Y-m-d H:i:s", (time() - (86400*2)));
                                }
                            }
                            break;
                    }
                    $lpkt = plog::packetLogSetup($pkt, $this->gw[0], $Type);
                    $this->plog->add($lpkt);
                }
            } else if ($pkt['toMe']) {
                $this->stats->incStat("To Me");
                switch(trim(strtoupper($pkt['sendCommand']))) {
                    case PACKET_COMMAND_GETSETUP:
                        $this->interpConfig(array($pkt));
                        break;
                    default:    // We didn't send a packet out.
                        switch(trim(strtoupper($pkt['Command']))) {
                            case PACKET_COMMAND_GETSETUP:
                                $ret = $this->packet->sendReply($pkt, $pkt['From'], e00392601::getConfigStr($this->myInfo));
                                break;
                            default:
                                break;
                        }
                        break;
                    
                }            
            

            } else {
                $lpkt = plog::packetLogSetup($pkt, $dev, $Type);
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

    function poll() 
    {
        if ($this->myInfo['doPoll'] !== true) {
            print "Skipping the poll.\r\n";
            return;
        }

        if ($this->lastPoll == date("i")) return;
        $this->lastPoll = date("i");

//        shuffle($this->ep);

        $epkeys = array_keys($this->ep);
        shuffle($epkeys);
//        foreach ($this->ep as $key => $dev) {
        foreach ($epkeys as $key) {
            $dev = $this->ep[$key];
            if ($dev["PollInterval"] > 0) {
                if (empty($this->_devInfo[$key]["PollTime"])) $this->GetNextPoll($key);

                if ($this->_devInfo[$key]["PollTime"] <= time()) { 
                    $this->lastContactAttempt = time();
                    $this->stats->incStat("Polls");
                    print $dev["DeviceID"]." (".$dev["Driver"].") -> ".date("Y-m-d H:i:s", $this->_devInfo[$key]["PollTime"])." <-> ".date("Y-m-d H:i:s");
                    $this->devGateway($key);
                    // print  " [".$dev["GatewayName"]."] ->";                    
                    $sensorRead = $this->endpoint->readSensors($dev);
                    $gotReply = false;
                    if (is_array($sensorRead) && (count($sensorRead) > 0)) {
                        foreach ($sensorRead as $sensors) {
                            if ($sensors['Reply'] == true) {
                                $gotReply = true;
                                if (is_array($sensors) && (count($sensors) > 0) && isset($sensors['RawData'])) {
                                    $sensors['DeviceKey'] = $dev['DeviceKey'];
                                    $this->_devInfo[$key]["failures"] = 0;
                                    if (!isset($sensors['DataIndex']) || ($this->_devInfo[$key]['DataIndex'] != $sensors["DataIndex"])) {

                                        $sensors = plog::packetLogSetup($sensors, $dev, "POLL");
                                        $ret = $this->plog->add($sensors);

                                        if ($ret) {
                                            print " Success (".number_format($sensors["ReplyTime"], 2).")";
                                            $this->_devInfo[$key]['DataIndex'] = $sensors["DataIndex"];
                                            $this->ep[$key]['LastPoll'] = $sensors['Date'];
                                            $this->_devInfo[$key]['LastPoll'] = $sensors['Date'];
                                            $this->GetNextPoll($key);
                                            $this->lastContactTime = time(); // Reset the last contact time
                                        } else {
                                            $DevCount++;
                                            print " Failed to store data \r\n"; //(".$history->Errno."): ".$history->Error;
                                            $this->stats->incStat("Poll Store Failed");
                                            //print strip_tags(get_stuff($history));
                                        }
                                    } else {
                                        $this->stats->incStat("Poll Data Index Ident");
                                        print " Data Index (".$sensors['DataIndex'].") Identical (".number_format($sensors["ReplyTime"], 2).")";
                                    }
                                }
                            }
                        }
                    }
                    if ($gotReply === false) {
                           $this->_devInfo[$key]["failures"]++;
                           if ($this->_devInfo[$key]["failures"] > $this->failureLimit) {
                             $this->GetNextPoll($key, time());
                           }
//                           $this->GetNextPoll($key, time());
                         // This will make the controller board find it if it doesn't see it yet
                        $this->stats->incStat("Find Device");
                        $ping = $this->endpoint->packet->ping($dev, true);
                        $DevCount++;
                        print " No data returned (".$this->_devInfo[$key]["failures"].")";
                        $this->_devInfo[$key]['gwIndex']++;
                        $this->ep[] = array_shift($this->ep);
                    } else {
                       $this->stats->incStat("Poll Success");                    
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
                foreach ($packet as $pkt) {
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
            $this->stats->incStat("Sent User Packet");
            if (is_array($packet)) {
                foreach ($packet as $pkt) {
                    $lpkt = plog::packetLogSetup($pkt, $p, "REPLY");
                    $lpkt['Checked'] = 2;
                    $lpkt['id'] = $p['id'];
                    $lpkt['PacketTo'] = $p['PacketTo'];
                    $lpkt['DeviceKey'] = $p['DeviceKey'];
                    $lpkt['GatewayKey'] = $p['GatewayKey'];
                    $this->plog->add($lpkt);
                }
                print "Success";
                $this->stats->incStat("Sent User Success");
            } else {
                print "Failed";
            }
            $this->psend->remove($p);
            print "\n";
            return $packet;
        } else {
            return false;
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
        $this->stats->incStat("Device Checked");
        $pkt = $this->endpoint->readConfig($dev);
        $gotConfig = false;
        if ($pkt !== false) {
            $newConfig = $this->interpConfig($pkt);
            foreach ($pkt as $p) {
                if ($p !== false) {
                    if ($p["Reply"]) {
                        if (!isset($p['DeviceKey'])) $p['DeviceKey'] = $dev['DeviceKey'];
                        if (empty($dev['GatewayKey'])) $dev['GatewayKey'] = $this->GatewayKey;
                                   $logpkt = plog::packetLogSetup($p, $dev, "CONFIG");
                        if ($this->plog->add($logpkt)) {
                            print " Done (".number_format($p["ReplyTime"], 2).")";
                            $gotConfig = true;
                            $this->ep[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["LastConfig"] = date("Y-m-d H:i:s");
                            $this->_devInfo[$key]["GetConfig"] = false;
                            $this->_devInfo[$key]['LastCheck'] = time();
                            unset($this->_devInfo[$key]['failedCheck']);
                            $this->lastContactTime = time();
                        } else {
//                            print "Error: ".$this->plog->_sqlite->lastError();
                        }
                    }
                }
            }
            if ($gotConfig) {
                // If it is a controller board check the program
                $this->stats->incStat("Device Checked Success");
                if (method_exists($this->endpoint->drivers[$newConfig['Driver']], "checkProgram")) {
                    $this->stats->incStat("Check Program");
                    print " Checking Program ";
                    $ret = $this->endpoint->drivers[$dev['Driver']]->checkProgram($newConfig, $pkt, true);
                    if ($ret) {
                        $this->stats->incStat("Check Program Success");
                        print " Done ";
                    } else {
                        print " Failed ";
                    }
                }
               } else {
                   print " Failed ";
                   $this->_devInfo[$key]['nextCheck'] = time()+120;
                print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);
//                               $this->_devInfo[$key]["gwIndex"]++;
               }

        } else {
            print " Nothing Returned ";
             $ping = $this->endpoint->packet->ping($dev, true);
            $this->_devInfo[$key]['nextCheck'] = time()+300;
            print " - Next Check ".date("Y-m-d H:i:s", $this->_devInfo[$key]['nextCheck']);

        }
        print "\r\n";
    
    }

    function getConfig() {
        if ($this->myInfo['doConfig'] !== true) return;
        
        foreach ($this->ep as $key => $dev) {
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

        $doPoll = true;
        $doConfig = true;
        $doCCheck = true;
        $doUnsolicited = true;
        $maxPriority = 0;
        foreach ($this->otherGW as $key => $gw) {
            if ($this->otherGW[$key]['failedCheckGW'] != date("i")) {
                $expired = false;
                if ($gw['RemoveTime'] < time()) {
                    unset($this->otherGW[$key]);
                    print "Removed ".$gw['DeviceID'];
                } else if ($key == "Wait") {
                    print "Waiting before Polling... ".date("Y-m-d H:i:s", $gw['RemoveTime']);
                    $doPoll = false;
                    $doConfig = false;
                    $doCCheck = false;
                    $doUnsolicited = false;
                    $maxPriority = 0xFFFF;  // Real priorities should never be this high.
                } else {
                    print "Checking in with Gateway ".$gw['DeviceID']; 
                    $pkt = $this->packet->buildPacket($gw['DeviceID'], PACKET_COMMAND_GETSETUP);
                    $ret = $this->packet->sendPacket($gw, array($pkt), true, 2);
                    if (is_array($ret)) {
                        foreach ($ret as $p) {
                            $this->CheckPacket($p);
                        }
                        print " Done ";
                           unset($this->otherGW[$key]['failedCheckGW']);
        
                    } else {
                        print " Failed ";
                           $this->otherGW[$key]['failedCheckGW'] = date("i");
                        if ($gw['ConfigExpire'] < time()) {
                            $this->otherGW[$key]['doPoll'] = false;
                            $this->otherGW[$key]['doConfig'] = false;
                            $this->otherGW[$key]['doCCheck'] = false;
                            $this->otherGW[$key]['doUnsolicited'] = false;
                            $expired = true;
                            print " Expired ";
                        } else {
                            print date("Y-m-d H:i:s", $gw['ConfigExpire']);
                        }
        
                    }
                    print " P:".$this->otherGW[$key]['Priority'];
                    print " IP:".$this->otherGW[$key]['NodeIP'];
                    print " Name:".$this->otherGW[$key]['NodeName'];
                    if ($this->otherGW[$key]['Priority'] == $this->myInfo['Priority']) {
                        $this->setPriority();
                    }
                    if (($maxPriority < $this->otherGW[$key]['Priority']) && !$expired)  {
                        $maxPriority = $this->otherGW[$key]['Priority'];
                    }
                    print " Jobs: ";

                    if ($this->otherGW[$key]['doPoll']) {
                        $doPoll = false;
                        print " Polling";
                    }            
                    if ($this->otherGW[$key]['doConfig']) {
                        $doConfig = false;
                        print " Config";
                    }            
                    if ($this->otherGW[$key]['doCCheck']) {
                        $doCCheck = false;
                        print " CCheck";
                    }            
                    if ($this->otherGW[$key]['doUnsolicited']) {
                        $doUnsolicited = false;
                        print " Unsolicited";
                    }            
        
                }
                print "\r\n";
            }
        }
        if ($maxPriority < $this->myInfo['Priority']) {
            $doPoll = true;
            $doConfig = true;
            $doCCheck = true;
            $doUnsolicited = true;
        } else {
            $doPoll = false;
            $doConfig = false;
            $doCCheck = false;
            $doUnsolicited = false;        
        }
        $this->myInfo['doPoll'] = $doPoll;
        $this->myInfo['doConfig'] = $doConfig;
        $this->myInfo['doCCheck'] = $doCCheck;
        $this->myInfo['doUnsolicited'] = $doUnsolicited;
    }

    function randomizegwTimeout() {
        return (time() + mt_rand(120, 420));
    }

    function setupMyInfo() {
        $this->myInfo['DeviceID'] = $this->packet->SN;
        $this->DeviceID = $this->myInfo['DeviceID'];
        $this->myInfo['SerialNum'] = $this->packet->SN;

        $this->myInfo['HWPartNum'] = POLL_PARTNUMBER;
        $this->myInfo['FWPartNum'] = POLL_PARTNUMBER;
        $this->myInfo['FWVersion'] = POLL_VERSION;    

        // I know this works on Linux
        $Info =`/sbin/ifconfig|grep Bcast`;
        $Info = explode("  ", $Info);
        foreach ($Info as $key => $val) {
            if (!empty($val)) {
                $t = explode(":", $val);
                $netInfo[trim($t[0])] = trim($t[1]);
            }
        }
        $this->myInfo['IP'] = $netInfo["inet addr"];

        $this->myInfo['Name'] = trim($this->uproc->me['Host']);
        if (!empty($this->uproc->me['Domain'])) $this->myInfo['Name'] .= ".".trim($this->uproc->me['Domain']);


    }

    function interpConfig($pkt) 
    {
        if (!is_array($pkt)) return;

        $newConfig = $this->endpoint->InterpConfig($pkt);
        if ($newConfig['isGateway']) {

            $newConfig['RemoveTime'] = time() + $this->gwRemove;
            $newConfig['ConfigExpire'] = $this->randomizegwTimeout();
            $newConfig['RemoveTimeDate'] = date("Y-m-d H:i:s", $newConfig['RemoveTime']);
            $newConfig['ConfigExpireDate'] = date("Y-m-d H:i:s", $newConfig['ConfigExpire']);
            
            $send = array("FWVersion", "Priority", "myGatewayKey", "NodeName", "NodeIP",
                          "doPoll", "doConfig", "doCCheck", "doUnsolicited", 'ConfigExpire');

            if (!is_array($this->otherGW[$newConfig['DeviceID']])) {
                $this->otherGW[$newConfig['DeviceID']] = $newConfig;
            } else {
                $this->otherGW[$newConfig['DeviceID']] = array_merge($this->otherGW[$newConfig['DeviceID']], $newConfig);
            }

        } else if ($newConfig['sendCommand'] == PACKET_COMMAND_GETSETUP) {
            if (!is_null($newConfig['DeviceKey'])) {
                $devKey = $newConfig['DeviceKey'];
            } else {
                $devKey = $this->findDev($pkt['From']);
            }
            if ($devkey === false) {
                $devKey = $newConfig['DeviceID'];
            }
            if (is_array($this->ep[$devKey])) $this->ep[$devKey] = array_merge($this->ep[$devKey], $newConfig);
            $this->GetNextPoll($devKey);
            $this->_devInfo[$devKey]['GetConfig'] = false;
        }
        
        return $newConfig;
    }
    
    function criticalFailure($reason) 
    {
        $last = (int) $this->stats->getStat("LastCriticalError", $this->uproc->me['Program']);
        
        if (is_null($last)) {
            $last = $this->last;
        }
        if ((time() - $last) > ($this->critTime * 60)) { 
            $to = "hugnet@hugllc.com";
            $from = "".$this->uproc->me['Host']."<noreply@hugllc.com>";
            $subject = "HUGnet Critical Failure on ".`hostname`."!";
            $message = $reason;
            mail ($to, $subject, $message);
            $this->last = time();

        }
        $this->stats->setStat("LastCriticalError", time());

    }

}

?>
